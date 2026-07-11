<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Admin
{
    private const PAGE_SLUG = 'jat-reservations';

    public static function init(): void
    {
        add_action('admin_menu', array(self::class, 'register_menu'));
        add_action('admin_post_jat_update_order_status', array(self::class, 'handle_status_update'));
        add_action('admin_post_jat_add_order_note', array(self::class, 'handle_add_note'));
        add_action('admin_post_jat_export_orders', array(self::class, 'handle_export'));
    }

    public static function register_menu(): void
    {
        add_menu_page(
            'お申し込み管理',
            'お申し込み管理',
            'jat_view_orders',
            self::PAGE_SLUG,
            array(self::class, 'render_page'),
            'dashicons-clipboard',
            26
        );
    }

    public static function render_page(): void
    {
        if (! current_user_can('jat_view_orders')) {
            wp_die(esc_html__('この画面を表示する権限がありません。', 'jat-reservation'));
        }
        $order_id = absint($_GET['order_id'] ?? 0);
        echo '<div class="wrap jat-admin">';
        if ($order_id > 0) {
            self::render_detail($order_id);
        } else {
            self::render_list();
        }
        echo '</div>';
    }

    private static function render_list(): void
    {
        $filters = self::request_filters();
        $page_number = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 20;
        $total = JAT_Reservation_DB::count_orders($filters);
        $orders = JAT_Reservation_DB::list_orders($filters, $per_page, ($page_number - 1) * $per_page);

        echo '<h1 class="wp-heading-inline">お申し込み管理</h1>';
        echo '<p>一覧では個人情報を必要最小限に表示します。詳細情報は受付データを開いて確認してください。</p>';
        self::render_notice();
        echo '<form method="get" class="jat-order-filters">';
        echo '<input type="hidden" name="page" value="' . esc_attr(self::PAGE_SLUG) . '">';
        echo '<label>検索 <input type="search" name="s" value="' . esc_attr((string) ($filters['search'] ?? '')) . '" placeholder="受付番号・氏名・会社名"></label> ';
        echo '<label>状態 <select name="status"><option value="">すべて</option>' . self::status_options((string) ($filters['status'] ?? '')) . '</select></label> ';
        echo '<label>サービス <select name="service_type"><option value="">すべて</option>' . self::service_options((string) ($filters['service_type'] ?? '')) . '</select></label> ';
        echo '<label>場所 <select name="location_id"><option value="">すべて</option>' . self::location_options((string) ($filters['location_id'] ?? '')) . '</select></label> ';
        echo '<label>ご利用日 <input type="date" name="service_date" value="' . esc_attr((string) ($filters['service_date'] ?? '')) . '"></label> ';
        submit_button('絞り込む', 'secondary', '', false);
        echo ' <a class="button" href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">条件を解除</a>';
        echo '</form>';

        if (current_user_can('jat_export_orders')) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0">';
            echo '<input type="hidden" name="action" value="jat_export_orders">';
            wp_nonce_field('jat_export_orders');
            foreach ($filters as $key => $value) {
                echo '<input type="hidden" name="filters[' . esc_attr((string) $key) . ']" value="' . esc_attr((string) $value) . '">';
            }
            submit_button('現在の条件でCSVを書き出す', 'secondary', '', false);
            echo '</form>';
        }

        echo '<table class="widefat fixed striped"><thead><tr>';
        foreach (array('受付番号', 'サービス', '場所', 'ご利用日時', 'お申込者', '会社名', '状態', '受付日時') as $heading) {
            echo '<th>' . esc_html($heading) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if ($orders === array()) {
            echo '<tr><td colspan="8">条件に一致するお申し込みはありません。</td></tr>';
        }
        foreach ($orders as $order) {
            $detail_url = add_query_arg(array('page' => self::PAGE_SLUG, 'order_id' => (int) $order['id']), admin_url('admin.php'));
            echo '<tr>';
            echo '<td><a href="' . esc_url($detail_url) . '"><strong>' . esc_html((string) $order['public_id']) . '</strong></a></td>';
            echo '<td>' . esc_html(self::service_label((string) $order['service_type'])) . '</td>';
            echo '<td>' . esc_html(self::location_label((string) $order['location_id'])) . '</td>';
            echo '<td>' . esc_html((string) $order['service_date'] . ' ' . substr((string) $order['scheduled_time'], 0, 5)) . '</td>';
            echo '<td>' . esc_html(self::mask_name((string) $order['applicant_name'])) . '</td>';
            echo '<td>' . esc_html(self::mask_company((string) $order['company_name'])) . '</td>';
            echo '<td>' . esc_html(JAT_Reservation_State_Machine::label((string) $order['status'])) . '</td>';
            echo '<td>' . esc_html(get_date_from_gmt((string) $order['created_at'], 'Y-m-d H:i')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        $pages = (int) ceil($total / $per_page);
        if ($pages > 1) {
            echo '<div class="tablenav"><div class="tablenav-pages">';
            echo wp_kses_post(paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $page_number, 'total' => $pages)));
            echo '</div></div>';
        }
    }

    private static function render_detail(int $order_id): void
    {
        $order = JAT_Reservation_DB::get_order($order_id);
        if ($order === null) {
            echo '<h1>受付データが見つかりません</h1>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">一覧へ戻る</a></p>';
            return;
        }
        $payload = json_decode((string) $order['current_payload'], true);
        if (! is_array($payload)) {
            $payload = array();
        }
        echo '<p><a href="' . esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)) . '">← お申し込み一覧へ戻る</a></p>';
        echo '<h1>' . esc_html((string) $order['public_id']) . '</h1>';
        self::render_notice();
        echo '<p><strong>現在の状態：</strong> ' . esc_html(JAT_Reservation_State_Machine::label((string) $order['status'])) . '</p>';

        self::render_payload_section('行程・サービス', $payload, array('service_type', 'location_id', 'terminal_area', 'service_date', 'scheduled_time', 'flight_number', 'train_number', 'origin_destination', 'quote_only'));
        self::render_payload_section('ご利用者情報', $payload, array('lead_passenger_name', 'passenger_count_adult', 'passenger_count_child', 'group_name', 'luggage_count', 'checked_baggage', 'emergency_phone', 'mobility_support', 'special_notes'));
        self::render_payload_section('お出迎え・お見送り内容', $payload, array('signboard_text', 'signboard_company', 'service_language', 'completion_report', 'wifi_help', 'sim_help', 'ic_card_help', 'currency_exchange_help', 'porter_help', 'checkin_help', 'ticket_help', 'boarding_confirmation'));
        self::render_payload_section('お申込者・車両情報', $payload, array('customer_type', 'company_name', 'department', 'applicant_name', 'applicant_email', 'applicant_phone', 'contract_customer', 'contract_code', 'destination', 'transport_type', 'driver_name', 'driver_phone', 'vehicle_info'));
        self::render_consent($order, $payload);
        self::render_status_form($order);
        self::render_note_form($order_id);
        self::render_history('状態履歴', JAT_Reservation_DB::get_related('status', $order_id), 'status');
        self::render_history('内部メモ', JAT_Reservation_DB::get_related('notes', $order_id), 'notes');
        self::render_history('メール送信履歴', JAT_Reservation_DB::get_related('mail', $order_id), 'mail');
        self::render_history('監査履歴', JAT_Reservation_DB::get_related('audit', $order_id), 'audit');
    }

    /** @param list<string> $keys */
    private static function render_payload_section(string $title, array $payload, array $keys): void
    {
        echo '<h2>' . esc_html($title) . '</h2><table class="widefat striped"><tbody>';
        foreach ($keys as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === '' || $payload[$key] === array()) {
                continue;
            }
            $raw_values = is_array($payload[$key]) ? array_map('strval', $payload[$key]) : array((string) $payload[$key]);
            $display_values = array();
            foreach ($raw_values as $raw_value) {
                $display_values[] = self::field_value_label($key, $raw_value);
            }
            echo '<tr><th style="width:220px">' . esc_html(self::field_label($key)) . '</th><td><span style="white-space:pre-wrap">' . esc_html(implode('、', $display_values)) . '</span></td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_consent(array $order, array $payload): void
    {
        echo '<h2>同意記録</h2><table class="widefat striped"><tbody>';
        echo '<tr><th style="width:220px">同意文書の版</th><td>' . esc_html((string) $order['consent_version']) . '</td></tr>';
        echo '<tr><th>同意日時</th><td>' . esc_html(get_date_from_gmt((string) $order['consented_at'], 'Y-m-d H:i:s')) . '</td></tr>';
        foreach (array('privacy_consent', 'terms_consent', 'marketing_consent', 'final_confirm') as $key) {
            echo '<tr><th>' . esc_html(self::field_label($key)) . '</th><td>' . (! empty($payload[$key]) ? 'はい' : 'いいえ') . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_status_form(array $order): void
    {
        if (! current_user_can('jat_manage_orders')) {
            return;
        }
        $can_restore = current_user_can('jat_restore_cancelled_orders');
        $targets = JAT_Reservation_State_Machine::allowed_targets((string) $order['status'], $can_restore);
        if ($targets === array()) {
            return;
        }
        echo '<h2>状態を変更する</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="jat_update_order_status"><input type="hidden" name="order_id" value="' . esc_attr((string) $order['id']) . '">';
        wp_nonce_field('jat_update_order_status_' . (int) $order['id']);
        echo '<p><label>変更後の状態 <select name="next_status" required><option value="">選択してください</option>';
        foreach ($targets as $status) {
            echo '<option value="' . esc_attr($status) . '">' . esc_html(JAT_Reservation_State_Machine::label($status)) . '</option>';
        }
        echo '</select></label></p>';
        echo '<p><label>変更理由・連絡事項<br><textarea name="reason" rows="3" class="large-text"></textarea></label></p>';
        submit_button('状態を更新する', 'primary', 'submit', false);
        echo '</form>';
    }

    private static function render_note_form(int $order_id): void
    {
        if (! current_user_can('jat_manage_orders')) {
            return;
        }
        echo '<h2>内部メモを追加する</h2><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="jat_add_order_note"><input type="hidden" name="order_id" value="' . esc_attr((string) $order_id) . '">';
        wp_nonce_field('jat_add_order_note_' . $order_id);
        echo '<textarea name="note" rows="3" class="large-text" required></textarea><p>内部メモはお客様向けメールに表示されません。</p>';
        submit_button('内部メモを保存する', 'secondary', 'submit', false);
        echo '</form>';
    }

    /** @param list<array<string,mixed>> $rows */
    private static function render_history(string $title, array $rows, string $type): void
    {
        echo '<h2>' . esc_html($title) . '</h2><table class="widefat striped"><thead><tr><th>日時</th><th>担当</th><th>内容</th></tr></thead><tbody>';
        if ($rows === array()) {
            echo '<tr><td colspan="3">記録はありません。</td></tr>';
        }
        foreach ($rows as $row) {
            $actor_id = (int) ($row['actor_user_id'] ?? 0);
            $actor = $actor_id > 0 ? get_userdata($actor_id) : null;
            $actor_name = $actor instanceof WP_User ? $actor->display_name : 'システム';
            $content = '';
            if ($type === 'status') {
                $content = JAT_Reservation_State_Machine::label((string) $row['from_status']) . ' → ' . JAT_Reservation_State_Machine::label((string) $row['to_status']) . ' ' . (string) $row['reason'];
            } elseif ($type === 'notes') {
                $content = (string) $row['note'];
            } elseif ($type === 'mail') {
                $content = (string) $row['subject'] . '／' . ((string) $row['delivery_status'] === 'sent' ? '送信済み' : '送信失敗');
            } else {
                $content = (string) $row['action'];
            }
            echo '<tr><td>' . esc_html(get_date_from_gmt((string) $row['created_at'], 'Y-m-d H:i:s')) . '</td><td>' . esc_html($actor_name) . '</td><td><span style="white-space:pre-wrap">' . esc_html(trim($content)) . '</span></td></tr>';
        }
        echo '</tbody></table>';
    }

    public static function handle_status_update(): void
    {
        if (! current_user_can('jat_manage_orders')) {
            wp_die('この操作を行う権限がありません。', '', array('response' => 403));
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer('jat_update_order_status_' . $order_id);
        $next_status = sanitize_key((string) ($_POST['next_status'] ?? ''));
        $reason = sanitize_textarea_field(wp_unslash((string) ($_POST['reason'] ?? '')));
        $result = JAT_Reservation_DB::update_status($order_id, $next_status, get_current_user_id(), $reason, current_user_can('jat_restore_cancelled_orders'));
        $notice = is_wp_error($result) ? 'error:' . $result->get_error_message() : 'status-updated';
        if ($result === true) {
            JAT_Reservation_Mailer::send_for_status($order_id, $next_status, get_current_user_id());
        }
        self::redirect_to_order($order_id, $notice);
    }

    public static function handle_add_note(): void
    {
        if (! current_user_can('jat_manage_orders')) {
            wp_die('この操作を行う権限がありません。', '', array('response' => 403));
        }
        $order_id = absint($_POST['order_id'] ?? 0);
        check_admin_referer('jat_add_order_note_' . $order_id);
        $note = sanitize_textarea_field(wp_unslash((string) ($_POST['note'] ?? '')));
        $result = JAT_Reservation_DB::add_note($order_id, $note, get_current_user_id());
        self::redirect_to_order($order_id, is_wp_error($result) ? 'error:' . $result->get_error_message() : 'note-added');
    }

    public static function handle_export(): void
    {
        if (! current_user_can('jat_export_orders')) {
            wp_die('CSVを書き出す権限がありません。', '', array('response' => 403));
        }
        check_admin_referer('jat_export_orders');
        $raw_filters = isset($_POST['filters']) && is_array($_POST['filters']) ? wp_unslash($_POST['filters']) : array();
        $filters = self::sanitize_filters($raw_filters);
        $orders = JAT_Reservation_DB::list_orders($filters, 10000, 0);
        JAT_Reservation_DB::record_audit(0, 'csv_exported', get_current_user_id(), array('filters' => $filters, 'count' => count($orders)));

        nocache_headers();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="jat-orders-' . gmdate('Ymd-His') . '.csv"');
        $output = fopen('php://output', 'wb');
        if ($output === false) {
            wp_die('CSVを作成できませんでした。');
        }
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, array('受付番号', 'サービス', '場所', 'ご利用日', '予定時刻', 'お申込者', 'メールアドレス', '会社名', '状態', '受付日時'));
        foreach ($orders as $order) {
            fputcsv($output, array(
                (string) $order['public_id'],
                self::service_label((string) $order['service_type']),
                self::location_label((string) $order['location_id']),
                (string) $order['service_date'],
                substr((string) $order['scheduled_time'], 0, 5),
                self::csv_safe((string) $order['applicant_name']),
                self::csv_safe((string) $order['applicant_email']),
                self::csv_safe((string) $order['company_name']),
                JAT_Reservation_State_Machine::label((string) $order['status']),
                (string) $order['created_at'],
            ));
        }
        fclose($output);
        exit;
    }

    private static function redirect_to_order(int $order_id, string $notice): never
    {
        wp_safe_redirect(add_query_arg(array('page' => self::PAGE_SLUG, 'order_id' => $order_id, 'jat_notice' => rawurlencode($notice)), admin_url('admin.php')));
        exit;
    }

    private static function render_notice(): void
    {
        $notice = sanitize_text_field(wp_unslash((string) ($_GET['jat_notice'] ?? '')));
        if ($notice === '') {
            return;
        }
        if (str_starts_with($notice, 'error:')) {
            echo '<div class="notice notice-error"><p>' . esc_html(substr($notice, 6)) . '</p></div>';
            return;
        }
        $messages = array('status-updated' => '状態を更新しました。', 'note-added' => '内部メモを保存しました。');
        echo '<div class="notice notice-success"><p>' . esc_html($messages[$notice] ?? '更新しました。') . '</p></div>';
    }

    /** @return array<string,string> */
    private static function request_filters(): array
    {
        return self::sanitize_filters(array(
            'search' => $_GET['s'] ?? '',
            'status' => $_GET['status'] ?? '',
            'service_type' => $_GET['service_type'] ?? '',
            'location_id' => $_GET['location_id'] ?? '',
            'service_date' => $_GET['service_date'] ?? '',
        ));
    }

    /** @return array<string,string> */
    private static function sanitize_filters(array $filters): array
    {
        return array(
            'search' => sanitize_text_field((string) ($filters['search'] ?? '')),
            'status' => sanitize_key((string) ($filters['status'] ?? '')),
            'service_type' => sanitize_key((string) ($filters['service_type'] ?? '')),
            'location_id' => sanitize_key((string) ($filters['location_id'] ?? '')),
            'service_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters['service_date'] ?? '')) === 1 ? (string) $filters['service_date'] : '',
        );
    }

    private static function status_options(string $selected): string
    {
        $html = '';
        foreach (JAT_Reservation_State_Machine::labels() as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    private static function service_options(string $selected): string
    {
        $html = '';
        foreach (self::services() as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    private static function location_options(string $selected): string
    {
        $html = '';
        foreach (self::locations() as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }

    /** @return array<string,string> */
    private static function services(): array
    {
        return array('airport_meet' => '空港お迎え（ミート）', 'airport_send' => '空港お見送り（センディング）', 'station_meet' => '駅お迎え', 'station_send' => '駅お見送り', 'transfer_support' => '乗継サポート', 'attend' => 'アテンドサービス');
    }

    /** @return array<string,string> */
    private static function locations(): array
    {
        return array('haneda' => '羽田空港', 'narita' => '成田空港', 'tokyo' => '東京駅', 'shinagawa' => '品川駅', 'other_airport' => 'その他の空港', 'other_station' => 'その他の駅');
    }

    private static function service_label(string $value): string
    {
        return self::services()[$value] ?? $value;
    }

    private static function location_label(string $value): string
    {
        return self::locations()[$value] ?? $value;
    }

    private static function field_value_label(string $key, string $value): string
    {
        if ($key === 'service_type') {
            return self::service_label($value);
        }
        if ($key === 'location_id') {
            return self::location_label($value);
        }
        if ($key === 'customer_type') {
            return array('individual' => '個人', 'corporate' => '法人・団体')[$value] ?? $value;
        }
        $boolean_fields = array('quote_only', 'completion_report', 'wifi_help', 'sim_help', 'ic_card_help', 'currency_exchange_help', 'porter_help', 'checkin_help', 'ticket_help', 'boarding_confirmation', 'contract_customer');
        if (in_array($key, $boolean_fields, true)) {
            $labels = array('1' => '希望する', 'yes' => '希望する', 'true' => '希望する', 'on' => '希望する', '0' => '希望しない', 'no' => '希望しない', 'false' => '希望しない', 'off' => '希望しない');
            return $labels[strtolower($value)] ?? $value;
        }
        return $value;
    }

    private static function mask_name(string $value): string
    {
        if ($value === '') {
            return '—';
        }
        return mb_substr($value, 0, 1) . str_repeat('＊', max(1, min(4, mb_strlen($value) - 1)));
    }

    private static function mask_company(string $value): string
    {
        return $value === '' ? '—' : mb_substr($value, 0, 3) . '…';
    }

    private static function csv_safe(string $value): string
    {
        return preg_match('/^[=+\-@]/u', $value) === 1 ? "'" . $value : $value;
    }

    private static function field_label(string $key): string
    {
        $labels = array(
            'service_type' => 'サービス', 'location_id' => '空港・駅', 'terminal_area' => 'ターミナル・改札等', 'service_date' => 'ご利用日', 'scheduled_time' => '予定時刻', 'flight_number' => '便名・便番号', 'train_number' => '列車名・列車番号', 'origin_destination' => '出発地・到着地', 'quote_only' => 'お見積りのみ希望', 'lead_passenger_name' => '代表者名', 'passenger_count_adult' => '大人の人数', 'passenger_count_child' => 'お子様の人数', 'group_name' => '団体名', 'luggage_count' => 'お荷物の個数', 'checked_baggage' => '受託手荷物', 'emergency_phone' => '当日の緊急連絡先', 'mobility_support' => '移動に関するサポート', 'special_notes' => 'ご利用者に関する連絡事項', 'signboard_text' => 'サインボード表示名', 'signboard_company' => '会社名・団体名', 'service_language' => 'ご希望の対応言語', 'completion_report' => 'サービス完了のご報告', 'wifi_help' => 'Wi-Fi受取サポート', 'sim_help' => 'SIMカード購入サポート', 'ic_card_help' => '交通系ICカード購入サポート', 'currency_exchange_help' => '外貨両替のご案内', 'porter_help' => 'ポーターサービス', 'checkin_help' => 'チェックインのお手伝い', 'ticket_help' => '乗車券の購入・変更サポート', 'boarding_confirmation' => 'ご乗車確認まで希望', 'customer_type' => 'お申込者区分', 'company_name' => '会社名・団体名', 'department' => '部署名', 'applicant_name' => 'ご担当者名', 'applicant_email' => 'メールアドレス', 'applicant_phone' => '電話番号', 'contract_customer' => '法人契約', 'contract_code' => '契約コード', 'destination' => 'ご案内先・目的地', 'transport_type' => 'ご利用予定の交通手段', 'driver_name' => 'ドライバー名', 'driver_phone' => 'ドライバー連絡先', 'vehicle_info' => '車種・色・ナンバー等', 'privacy_consent' => 'プライバシーポリシーへの同意', 'terms_consent' => '利用規約への同意', 'marketing_consent' => 'お知らせの受取', 'final_confirm' => '最終確認',
        );
        return $labels[$key] ?? $key;
    }
}
