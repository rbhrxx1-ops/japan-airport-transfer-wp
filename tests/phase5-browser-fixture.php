<?php

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run with wp eval-file.\n");
    exit(1);
}

global $wpdb;
$existing_id = (int) get_option('jat_phase5_browser_fixture_order_id', 0);

if (getenv('JAT_PHASE5_FIXTURE_MODE') === 'cleanup') {
    if ($existing_id > 0) {
        foreach (array(
            JAT_Reservation_DB::table_status_history(),
            JAT_Reservation_DB::table_notes(),
            JAT_Reservation_DB::table_mail_log(),
            JAT_Reservation_DB::table_audit_log(),
        ) as $table) {
            $wpdb->delete($table, array('order_id' => $existing_id), array('%d'));
        }
        $wpdb->delete(JAT_Reservation_DB::table_orders(), array('id' => $existing_id), array('%d'));
    }
    delete_option('jat_phase5_browser_fixture_order_id');
    echo 'CLEANED_ORDER_ID=' . $existing_id . PHP_EOL;
    exit(0);
}
if ($existing_id > 0 && JAT_Reservation_DB::get_order($existing_id) !== null) {
    $existing = JAT_Reservation_DB::get_order($existing_id);
    echo 'ORDER_ID=' . $existing_id . PHP_EOL;
    echo 'PUBLIC_ID=' . (string) $existing['public_id'] . PHP_EOL;
    exit(0);
}

$seed = wp_generate_uuid4();
$data = array(
    'service_type' => 'airport_meet',
    'location_id' => 'haneda',
    'terminal_area' => '第3ターミナル 到着ロビー',
    'service_date' => wp_date('Y-m-d', time() + DAY_IN_SECONDS * 45),
    'scheduled_time' => '14:30',
    'flight_number' => 'JL000',
    'origin_destination' => 'シンガポール',
    'lead_passenger_name' => '閲覧テスト旅客',
    'passenger_count_adult' => '2',
    'passenger_count_child' => '1',
    'luggage_count' => '3',
    'emergency_phone' => '090-0000-0000',
    'signboard_text' => 'WELCOME TEST GROUP',
    'service_language' => '日本語',
    'customer_type' => 'corporate',
    'company_name' => 'ブラウザー検証株式会社',
    'department' => '出張管理部',
    'applicant_name' => '段階五 管理画面テスト',
    'applicant_email' => 'phase5-browser@example.com',
    'applicant_phone' => '03-0000-0000',
    'destination' => '都内ホテル',
    'transport_type' => 'ハイヤー',
    'privacy_consent' => '1',
    'terms_consent' => '1',
    'marketing_consent' => '',
    'final_confirm' => '1',
);

$created = JAT_Reservation_DB::create_order($data, $seed, hash('sha256', 'phase5-browser-' . $seed));
if (is_wp_error($created)) {
    fwrite(STDERR, $created->get_error_message() . PHP_EOL);
    exit(1);
}

$order_id = (int) $created['id'];
update_option('jat_phase5_browser_fixture_order_id', $order_id, false);
echo 'ORDER_ID=' . $order_id . PHP_EOL;
echo 'PUBLIC_ID=' . (string) $created['public_id'] . PHP_EOL;
