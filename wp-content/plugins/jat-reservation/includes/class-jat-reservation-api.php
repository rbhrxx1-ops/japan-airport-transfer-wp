<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_API
{
    private const NAMESPACE = 'jat-reservation/v1';
    private const RATE_LIMIT = 8;
    private const RATE_WINDOW = 10 * MINUTE_IN_SECONDS;

    public static function init(): void
    {
        add_action('rest_api_init', array(self::class, 'register_routes'));
    }

    public static function register_routes(): void
    {
        register_rest_route(
            self::NAMESPACE,
            '/token',
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array(self::class, 'token'),
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            self::NAMESPACE,
            '/orders',
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array(self::class, 'create'),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function token(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $origin_error = self::validate_origin($request);
        if (is_wp_error($origin_error)) {
            return $origin_error;
        }

        return new WP_REST_Response(
            array(
                'nonce' => wp_create_nonce('wp_rest'),
                'expires_message' => '画面の有効期限が切れました。入力内容を保持したまま画面を更新します。',
            ),
            200
        );
    }

    public static function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $origin_error = self::validate_origin($request);
        if (is_wp_error($origin_error)) {
            return $origin_error;
        }

        $nonce = sanitize_text_field((string) $request->get_header('X-WP-Nonce'));
        if ($nonce === '' || ! wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_Error(
                'jat_expired_nonce',
                '画面の有効期限が切れました。入力内容を保持したまま画面を更新します。',
                array('status' => 403, 'refresh_nonce' => true)
            );
        }

        $input = $request->get_json_params();
        if (! is_array($input)) {
            return new WP_Error(
                'jat_invalid_request',
                '送信内容を確認できませんでした。入力内容をご確認ください。',
                array('status' => 400)
            );
        }

        if (trim((string) ($input['website'] ?? '')) !== '') {
            return new WP_Error(
                'jat_spam_detected',
                '送信できませんでした。時間をおいて再度お試しください。',
                array('status' => 400)
            );
        }

        $started_at = absint($input['form_started_at'] ?? 0);
        $elapsed = time() - $started_at;
        if ($started_at === 0 || $elapsed < 2 || $elapsed > DAY_IN_SECONDS) {
            return new WP_Error(
                'jat_invalid_form_time',
                '画面の有効期限が切れました。入力内容を保持したまま画面を更新します。',
                array('status' => 400, 'refresh_nonce' => true)
            );
        }

        $rate_error = self::enforce_rate_limit();
        if (is_wp_error($rate_error)) {
            return $rate_error;
        }

        $idempotency_key = sanitize_text_field((string) $request->get_header('Idempotency-Key'));
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $idempotency_key) !== 1) {
            return new WP_Error(
                'jat_invalid_idempotency_key',
                '送信準備を完了できませんでした。画面を更新して再度お試しください。',
                array('status' => 400)
            );
        }

        $validated = JAT_Reservation_Validator::validate($input);
        if ($validated['errors'] !== array()) {
            return new WP_Error(
                'jat_validation_failed',
                '入力内容をご確認ください。',
                array('status' => 422, 'fields' => $validated['errors'])
            );
        }

        $fingerprint = hash_hmac(
            'sha256',
            wp_json_encode(
                array(
                    $validated['data']['service_type'],
                    $validated['data']['service_date'],
                    $validated['data']['scheduled_time'],
                    strtolower($validated['data']['applicant_email']),
                    $validated['data']['lead_passenger_name'],
                ),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            wp_salt('nonce')
        );

        $result = JAT_Reservation_DB::create_order($validated['data'], $idempotency_key, $fingerprint);
        if (is_wp_error($result)) {
            return $result;
        }

        if (! $result['duplicate']) {
            JAT_Reservation_Mailer::send_received((int) $result['id']);
        }

        $message = $result['duplicate']
            ? '同じ内容のお申し込みを受け付けています。受付番号をご確認ください。'
            : 'お申し込みを受け付けました。担当者が内容を確認のうえご連絡します。';

        return new WP_REST_Response(
            array(
                'success' => true,
                'duplicate' => $result['duplicate'],
                'reference' => $result['public_id'],
                'status' => '受付済み',
                'message' => $message,
            ),
            $result['duplicate'] ? 200 : 201
        );
    }

    private static function validate_origin(WP_REST_Request $request): true|WP_Error
    {
        $origin = trim((string) $request->get_header('Origin'));
        if ($origin === '') {
            return true;
        }

        $origin_host = wp_parse_url($origin, PHP_URL_HOST);
        $home_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
        if (! is_string($origin_host) || ! is_string($home_host) || ! hash_equals(strtolower($home_host), strtolower($origin_host))) {
            return new WP_Error(
                'jat_invalid_origin',
                'この画面からは送信できません。公式サイトからお申し込みください。',
                array('status' => 403)
            );
        }

        return true;
    }

    private static function enforce_rate_limit(): true|WP_Error
    {
        $ip = sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
        $key = 'jat_rate_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $count = (int) get_transient($key);
        if ($count >= self::RATE_LIMIT) {
            return new WP_Error(
                'jat_rate_limited',
                '短時間に多くの送信が行われました。しばらくしてから再度お試しください。',
                array('status' => 429)
            );
        }

        set_transient($key, $count + 1, self::RATE_WINDOW);
        return true;
    }
}
