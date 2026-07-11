<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_DB
{
    private const DB_VERSION = '0.1.0';
    private const DB_VERSION_OPTION = 'jat_reservation_db_version';

    public static function table_orders(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'jat_orders';
    }

    public static function activate(): void
    {
        self::migrate();
    }

    public static function maybe_upgrade(): void
    {
        if (get_option(self::DB_VERSION_OPTION) !== self::DB_VERSION) {
            self::migrate();
        }
    }

    private static function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table_orders();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id varchar(32) NOT NULL,
            idempotency_key char(36) NOT NULL,
            request_fingerprint char(64) NOT NULL,
            service_type varchar(40) NOT NULL,
            location_id varchar(40) NOT NULL,
            service_date date NOT NULL,
            scheduled_time time NOT NULL,
            applicant_name varchar(120) NOT NULL,
            applicant_email varchar(190) NOT NULL,
            company_name varchar(190) NOT NULL DEFAULT '',
            status varchar(40) NOT NULL DEFAULT 'received',
            payload longtext NOT NULL,
            consent_version varchar(40) NOT NULL,
            consented_at datetime NOT NULL,
            submitted_ip_hash char(64) NOT NULL,
            user_agent_hash char(64) NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY request_fingerprint (request_fingerprint),
            KEY service_date (service_date),
            KEY status_created (status, created_at),
            KEY applicant_email (applicant_email)
        ) {$charset_collate};";

        dbDelta($sql);
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, false);
    }

    /**
     * @return array{id:int,public_id:string,duplicate:bool}|WP_Error
     */
    public static function create_order(array $data, string $idempotency_key, string $fingerprint): array|WP_Error
    {
        global $wpdb;

        $table = self::table_orders();
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, public_id FROM {$table} WHERE idempotency_key = %s LIMIT 1",
                $idempotency_key
            ),
            ARRAY_A
        );

        if (is_array($existing)) {
            return array(
                'id' => (int) $existing['id'],
                'public_id' => (string) $existing['public_id'],
                'duplicate' => true,
            );
        }

        $recent_duplicate = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, public_id FROM {$table} WHERE request_fingerprint = %s AND created_at >= %s ORDER BY id DESC LIMIT 1",
                $fingerprint,
                gmdate('Y-m-d H:i:s', time() - (15 * MINUTE_IN_SECONDS))
            ),
            ARRAY_A
        );

        if (is_array($recent_duplicate)) {
            return array(
                'id' => (int) $recent_duplicate['id'],
                'public_id' => (string) $recent_duplicate['public_id'],
                'duplicate' => true,
            );
        }

        $public_id = self::generate_public_id();
        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $table,
            array(
                'public_id' => $public_id,
                'idempotency_key' => $idempotency_key,
                'request_fingerprint' => $fingerprint,
                'service_type' => $data['service_type'],
                'location_id' => $data['location_id'],
                'service_date' => $data['service_date'],
                'scheduled_time' => $data['scheduled_time'] . ':00',
                'applicant_name' => $data['applicant_name'],
                'applicant_email' => $data['applicant_email'],
                'company_name' => $data['company_name'],
                'status' => 'received',
                'payload' => wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'consent_version' => '2026-07-draft',
                'consented_at' => $now,
                'submitted_ip_hash' => self::request_hash(self::request_ip()),
                'user_agent_hash' => self::request_hash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );

        if ($inserted !== 1) {
            return new WP_Error(
                'jat_database_error',
                'お申し込みを保存できませんでした。時間をおいて再度お試しください。',
                array('status' => 500)
            );
        }

        return array(
            'id' => (int) $wpdb->insert_id,
            'public_id' => $public_id,
            'duplicate' => false,
        );
    }

    private static function generate_public_id(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $suffix = '';

        for ($index = 0; $index < 6; $index++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'JAT-' . wp_date('Ym') . '-' . $suffix;
    }

    private static function request_ip(): string
    {
        return sanitize_text_field(wp_unslash((string) ($_SERVER['REMOTE_ADDR'] ?? '')));
    }

    private static function request_hash(string $value): string
    {
        return hash_hmac('sha256', $value, wp_salt('auth'));
    }
}
