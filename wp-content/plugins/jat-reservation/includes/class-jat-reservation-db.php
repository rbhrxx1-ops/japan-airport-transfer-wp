<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_DB
{
    private const DB_VERSION = '0.2.0';
    private const DB_VERSION_OPTION = 'jat_reservation_db_version';

    public static function table_orders(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jat_orders';
    }

    public static function table_status_history(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jat_order_status_history';
    }

    public static function table_notes(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jat_order_notes';
    }

    public static function table_mail_log(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jat_order_mail_log';
    }

    public static function table_audit_log(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'jat_order_audit_log';
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
        $charset_collate = $wpdb->get_charset_collate();
        $orders = self::table_orders();
        $status_history = self::table_status_history();
        $notes = self::table_notes();
        $mail_log = self::table_mail_log();
        $audit_log = self::table_audit_log();

        dbDelta("CREATE TABLE {$orders} (
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
            current_payload longtext NOT NULL,
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
            KEY applicant_email (applicant_email),
            KEY company_name (company_name)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$status_history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            from_status varchar(40) NOT NULL DEFAULT '',
            to_status varchar(40) NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            reason text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_created (order_id, created_at),
            KEY actor_user_id (actor_user_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$notes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            note text NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_created (order_id, created_at),
            KEY actor_user_id (actor_user_id)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$mail_log} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL,
            template_key varchar(40) NOT NULL,
            recipient_hash char(64) NOT NULL,
            subject varchar(255) NOT NULL,
            delivery_status varchar(30) NOT NULL,
            error_code varchar(80) NOT NULL DEFAULT '',
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_created (order_id, created_at),
            KEY delivery_status (delivery_status)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$audit_log} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(60) NOT NULL,
            actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
            context longtext NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY order_created (order_id, created_at),
            KEY action_created (action, created_at),
            KEY actor_user_id (actor_user_id)
        ) {$charset_collate};");

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
            $wpdb->prepare("SELECT id, public_id FROM {$table} WHERE idempotency_key = %s LIMIT 1", $idempotency_key),
            ARRAY_A
        );
        if (is_array($existing)) {
            return array('id' => (int) $existing['id'], 'public_id' => (string) $existing['public_id'], 'duplicate' => true);
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
            return array('id' => (int) $recent_duplicate['id'], 'public_id' => (string) $recent_duplicate['public_id'], 'duplicate' => true);
        }

        $public_id = self::generate_public_id();
        $now = current_time('mysql', true);
        $payload = (string) wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
                'payload' => $payload,
                'current_payload' => $payload,
                'consent_version' => '2026-07-draft',
                'consented_at' => $now,
                'submitted_ip_hash' => self::request_hash(self::request_ip()),
                'user_agent_hash' => self::request_hash((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')),
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        if ($inserted !== 1) {
            return new WP_Error('jat_database_error', 'お申し込みを保存できませんでした。時間をおいて再度お試しください。', array('status' => 500));
        }

        $order_id = (int) $wpdb->insert_id;
        self::record_status($order_id, '', 'received', 0, '公開フォームから受け付けました。');
        self::record_audit($order_id, 'order_created', 0, array('source' => 'public_form'));

        return array('id' => $order_id, 'public_id' => $public_id, 'duplicate' => false);
    }

    /** @return array<string,mixed>|null */
    public static function get_order(int $order_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_orders() . ' WHERE id = %d', $order_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public static function get_order_by_public_id(string $public_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table_orders() . ' WHERE public_id = %s', $public_id), ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string,string|int> $filters
     * @return list<array<string,mixed>>
     */
    public static function list_orders(array $filters, int $limit = 20, int $offset = 0): array
    {
        global $wpdb;
        [$where, $params] = self::build_filter_query($filters);
        $params[] = max(1, min(100, $limit));
        $params[] = max(0, $offset);
        $sql = 'SELECT id, public_id, service_type, location_id, service_date, scheduled_time, applicant_name, applicant_email, company_name, status, created_at FROM ' . self::table_orders() . " {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    /** @param array<string,string|int> $filters */
    public static function count_orders(array $filters): int
    {
        global $wpdb;
        [$where, $params] = self::build_filter_query($filters);
        $sql = 'SELECT COUNT(*) FROM ' . self::table_orders() . " {$where}";
        return (int) ($params === array() ? $wpdb->get_var($sql) : $wpdb->get_var($wpdb->prepare($sql, ...$params)));
    }

    /** @param array<string,string|int> $filters @return array{0:string,1:list<mixed>} */
    private static function build_filter_query(array $filters): array
    {
        global $wpdb;
        $clauses = array('1=1');
        $params = array();
        $allowed_exact = array('status', 'service_type', 'location_id', 'service_date');
        foreach ($allowed_exact as $key) {
            $value = sanitize_text_field((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $clauses[] = "{$key} = %s";
                $params[] = $value;
            }
        }
        $search = sanitize_text_field((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(public_id LIKE %s OR applicant_name LIKE %s OR company_name LIKE %s)';
            array_push($params, $like, $like, $like);
        }
        return array('WHERE ' . implode(' AND ', $clauses), $params);
    }

    public static function update_status(int $order_id, string $next_status, int $actor_user_id, string $reason, bool $can_restore): true|WP_Error
    {
        global $wpdb;
        $order = self::get_order($order_id);
        if ($order === null) {
            return new WP_Error('jat_order_not_found', '受付データが見つかりません。');
        }
        $current = (string) $order['status'];
        if (! JAT_Reservation_State_Machine::can_transition($current, $next_status, $can_restore)) {
            return new WP_Error('jat_invalid_transition', 'この状態への変更は許可されていません。');
        }
        if ($current === 'cancelled' && $reason === '') {
            return new WP_Error('jat_restore_reason_required', 'キャンセル状態から戻す場合は理由を入力してください。');
        }
        $now = current_time('mysql', true);
        $updated = $wpdb->update(self::table_orders(), array('status' => $next_status, 'updated_at' => $now), array('id' => $order_id), array('%s', '%s'), array('%d'));
        if ($updated !== 1) {
            return new WP_Error('jat_status_update_failed', '状態を更新できませんでした。');
        }
        self::record_status($order_id, $current, $next_status, $actor_user_id, $reason);
        self::record_audit($order_id, 'status_changed', $actor_user_id, array('from' => $current, 'to' => $next_status, 'reason' => $reason));
        return true;
    }

    public static function add_note(int $order_id, string $note, int $actor_user_id): true|WP_Error
    {
        global $wpdb;
        $clean_note = sanitize_textarea_field($note);
        if ($clean_note === '') {
            return new WP_Error('jat_note_required', '内部メモを入力してください。');
        }
        $inserted = $wpdb->insert(self::table_notes(), array('order_id' => $order_id, 'note' => $clean_note, 'actor_user_id' => $actor_user_id, 'created_at' => current_time('mysql', true)), array('%d', '%s', '%d', '%s'));
        if ($inserted !== 1) {
            return new WP_Error('jat_note_failed', '内部メモを保存できませんでした。');
        }
        self::record_audit($order_id, 'note_added', $actor_user_id, array('note_id' => (int) $wpdb->insert_id));
        return true;
    }

    public static function record_mail(int $order_id, string $template_key, string $recipient, string $subject, string $status, string $error_code = '', int $actor_user_id = 0): void
    {
        global $wpdb;
        $wpdb->insert(
            self::table_mail_log(),
            array(
                'order_id' => $order_id,
                'template_key' => sanitize_key($template_key),
                'recipient_hash' => self::request_hash(strtolower(trim($recipient))),
                'subject' => sanitize_text_field($subject),
                'delivery_status' => sanitize_key($status),
                'error_code' => sanitize_key($error_code),
                'actor_user_id' => $actor_user_id,
                'created_at' => current_time('mysql', true),
            )
        );
    }

    /** @param array<string,mixed> $context */
    public static function record_audit(int $order_id, string $action, int $actor_user_id, array $context): void
    {
        global $wpdb;
        $wpdb->insert(
            self::table_audit_log(),
            array(
                'order_id' => $order_id,
                'action' => sanitize_key($action),
                'actor_user_id' => $actor_user_id,
                'context' => (string) wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => current_time('mysql', true),
            )
        );
    }

    /** @return list<array<string,mixed>> */
    public static function get_related(string $type, int $order_id): array
    {
        global $wpdb;
        $tables = array(
            'status' => self::table_status_history(),
            'notes' => self::table_notes(),
            'mail' => self::table_mail_log(),
            'audit' => self::table_audit_log(),
        );
        if (! isset($tables[$type])) {
            return array();
        }
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables[$type]} WHERE order_id = %d ORDER BY created_at DESC, id DESC", $order_id), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    /** @return list<array<string,mixed>> */
    public static function find_by_email(string $email): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table_orders() . ' WHERE applicant_email = %s ORDER BY created_at DESC', sanitize_email($email)), ARRAY_A);
        return is_array($rows) ? $rows : array();
    }

    public static function anonymize_order(int $order_id, int $actor_user_id = 0): bool
    {
        global $wpdb;
        $order = self::get_order($order_id);
        if ($order === null) {
            return false;
        }
        $anonymous = array('privacy_erased' => true, 'erased_at' => current_time('mysql', true));
        $updated = $wpdb->update(
            self::table_orders(),
            array(
                'applicant_name' => '削除済み',
                'applicant_email' => 'erased-' . $order_id . '@invalid.local',
                'company_name' => '',
                'payload' => (string) wp_json_encode($anonymous),
                'current_payload' => (string) wp_json_encode($anonymous),
                'submitted_ip_hash' => str_repeat('0', 64),
                'user_agent_hash' => str_repeat('0', 64),
                'updated_at' => current_time('mysql', true),
            ),
            array('id' => $order_id)
        );
        if ($updated === false) {
            return false;
        }
        self::record_audit($order_id, 'personal_data_erased', $actor_user_id, array());
        return true;
    }

    private static function record_status(int $order_id, string $from, string $to, int $actor_user_id, string $reason): void
    {
        global $wpdb;
        $wpdb->insert(
            self::table_status_history(),
            array('order_id' => $order_id, 'from_status' => $from, 'to_status' => $to, 'actor_user_id' => $actor_user_id, 'reason' => sanitize_textarea_field($reason), 'created_at' => current_time('mysql', true))
        );
    }

    private static function generate_public_id(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $suffix = '';
            for ($index = 0; $index < 6; $index++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $public_id = 'JAT-' . wp_date('Ym') . '-' . $suffix;
            if (self::get_order_by_public_id($public_id) === null) {
                return $public_id;
            }
        }
        return 'JAT-' . wp_date('Ym') . '-' . strtoupper(wp_generate_password(8, false, false));
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
