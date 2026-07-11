<?php

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run with wp eval-file.\n");
    exit(1);
}

$failures = array();
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if ($condition) {
        echo "PASS: {$message}\n";
        return;
    }
    $failures[] = $message;
    echo "FAIL: {$message}\n";
};

$tables = array(
    JAT_Reservation_DB::table_orders(),
    JAT_Reservation_DB::table_status_history(),
    JAT_Reservation_DB::table_notes(),
    JAT_Reservation_DB::table_mail_log(),
    JAT_Reservation_DB::table_audit_log(),
);

global $wpdb;
foreach ($tables as $table) {
    $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
    $assert($exists === $table, "table exists: {$table}");
}

$administrator = get_role('administrator');
$staff = get_role('jat_reservation_staff');
$supervisor = get_role('jat_reservation_supervisor');
$assert($administrator instanceof WP_Role && $administrator->has_cap('jat_export_orders'), 'administrator has export capability');
$assert($staff instanceof WP_Role && $staff->has_cap('jat_view_orders') && $staff->has_cap('jat_manage_orders'), 'staff can view and manage orders');
$assert($staff instanceof WP_Role && ! $staff->has_cap('jat_export_orders') && ! $staff->has_cap('jat_restore_cancelled_orders'), 'staff cannot export or restore cancelled orders');
$assert($supervisor instanceof WP_Role && $supervisor->has_cap('jat_export_orders') && $supervisor->has_cap('jat_restore_cancelled_orders'), 'supervisor can export and restore cancelled orders');

$assert(JAT_Reservation_State_Machine::can_transition('received', 'reviewing'), 'received can move to reviewing');
$assert(! JAT_Reservation_State_Machine::can_transition('received', 'completed'), 'received cannot skip directly to completed');
$assert(! JAT_Reservation_State_Machine::can_transition('cancelled', 'reviewing', false), 'cancelled cannot be restored without supervisor permission');
$assert(JAT_Reservation_State_Machine::can_transition('cancelled', 'reviewing', true), 'cancelled can be restored with supervisor permission');

$seed = wp_generate_uuid4();
$email = 'phase5-' . substr(hash('sha256', $seed), 0, 12) . '@example.com';
$data = array(
    'service_type' => 'airport_meet',
    'location_id' => 'haneda',
    'service_date' => wp_date('Y-m-d', time() + DAY_IN_SECONDS * 30),
    'scheduled_time' => '10:30',
    'applicant_name' => '段階五テスト',
    'applicant_email' => $email,
    'company_name' => '=CSV Formula Test',
    'customer_type' => 'corporate',
    'privacy_consent' => '1',
    'terms_consent' => '1',
    'final_confirm' => '1',
);

$created = JAT_Reservation_DB::create_order($data, $seed, hash('sha256', 'phase5-' . $seed));
$assert(is_array($created) && ! $created['duplicate'], 'creates a new order with audit records');
$order_id = is_array($created) ? (int) $created['id'] : 0;
$public_id = is_array($created) ? (string) $created['public_id'] : '';

if ($order_id > 0) {
    $order = JAT_Reservation_DB::get_order($order_id);
    $assert(is_array($order) && $order['status'] === 'received', 'new order starts in received state');
    $assert(is_array($order) && $order['payload'] === $order['current_payload'], 'original and current snapshots match at creation');

    $invalid = JAT_Reservation_DB::update_status($order_id, 'completed', 0, 'invalid skip', false);
    $assert(is_wp_error($invalid) && $invalid->get_error_code() === 'jat_invalid_transition', 'database layer rejects invalid state transition');

    $updated = JAT_Reservation_DB::update_status($order_id, 'reviewing', 0, '内容確認を開始', false);
    $assert($updated === true, 'database layer accepts valid state transition');

    $note = JAT_Reservation_DB::add_note($order_id, "内部確認メモ\n顧客メールには含めない", 0);
    $assert($note === true, 'internal note is stored');
    $notes = JAT_Reservation_DB::get_related('notes', $order_id);
    $assert(count($notes) === 1 && str_contains((string) $notes[0]['note'], '顧客メールには含めない'), 'internal note can be retrieved from isolated note table');

    add_filter('pre_wp_mail', static fn () => true, 10, 2);
    JAT_Reservation_Mailer::send_for_status($order_id, 'reviewing', 0, 'テスト環境からのご案内');
    remove_all_filters('pre_wp_mail');
    $mail_rows = JAT_Reservation_DB::get_related('mail', $order_id);
    $assert(count($mail_rows) === 1 && $mail_rows[0]['template_key'] === 'reviewing' && $mail_rows[0]['delivery_status'] === 'sent', 'status mail is logged without storing recipient address');
    $assert(isset($mail_rows[0]['recipient_hash']) && strlen((string) $mail_rows[0]['recipient_hash']) === 64, 'mail log stores only recipient hash');

    $history = JAT_Reservation_DB::get_related('status', $order_id);
    $assert(count($history) === 2 && $history[0]['to_status'] === 'reviewing', 'initial and updated states are preserved in history');
    $audits = JAT_Reservation_DB::get_related('audit', $order_id);
    $actions = array_column($audits, 'action');
    $assert(in_array('order_created', $actions, true) && in_array('status_changed', $actions, true) && in_array('note_added', $actions, true) && in_array('mail_sent', $actions, true), 'order, state, note and mail actions are audited');

    $export = JAT_Reservation_Privacy::export_personal_data($email, 1);
    $assert($export['done'] === true && count($export['data']) === 1, 'privacy exporter returns the matching order');
    $assert(($export['data'][0]['item_id'] ?? '') === 'jat-order-' . $order_id, 'privacy export item uses stable order identifier');

    $erased = JAT_Reservation_Privacy::erase_personal_data($email, 1);
    $assert($erased['done'] === true && $erased['items_removed'] === true && $erased['items_retained'] === false, 'privacy eraser anonymizes matching order');
    $anonymous = JAT_Reservation_DB::get_order($order_id);
    $assert(is_array($anonymous) && $anonymous['applicant_name'] === '削除済み' && str_ends_with((string) $anonymous['applicant_email'], '@invalid.local'), 'privacy erasure removes direct applicant identifiers');
    $assert(is_array($anonymous) && $anonymous['payload'] === $anonymous['current_payload'] && str_contains((string) $anonymous['payload'], 'privacy_erased'), 'privacy erasure replaces both payload snapshots with an erasure marker');
    $assert(JAT_Reservation_DB::find_by_email($email) === array(), 'erased order is no longer discoverable by original email');

    foreach (array(JAT_Reservation_DB::table_status_history(), JAT_Reservation_DB::table_notes(), JAT_Reservation_DB::table_mail_log(), JAT_Reservation_DB::table_audit_log()) as $related_table) {
        $wpdb->delete($related_table, array('order_id' => $order_id), array('%d'));
    }
    $wpdb->delete(JAT_Reservation_DB::table_orders(), array('id' => $order_id), array('%d'));
    $assert(JAT_Reservation_DB::get_order_by_public_id($public_id) === null, 'test order and related records are cleaned up');
}

if ($failures !== array()) {
    fwrite(STDERR, sprintf("\n%d/%d checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "\nAll {$checks} phase 5 integration checks passed.\n";
