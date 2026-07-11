<?php

if (! defined('ABSPATH')) {
    fwrite(STDERR, "Run with wp eval-file.\n");
    exit(1);
}

$passed = 0;
$failed = 0;
$created_ids = array();

$assert = static function (bool $condition, string $message) use (&$passed, &$failed): void {
    if ($condition) {
        $passed++;
        echo "PASS: {$message}\n";
        return;
    }

    $failed++;
    echo "FAIL: {$message}\n";
};

$future_date = (new DateTimeImmutable('+14 days', wp_timezone()))->format('Y-m-d');
$unique = strtolower(wp_generate_password(8, false, false));

$base = array(
    'service_type' => 'airport_meet',
    'location_id' => 'haneda',
    'terminal_area' => '第3ターミナル',
    'service_date' => $future_date,
    'scheduled_time' => '15:30',
    'flight_number' => 'NH105',
    'train_number' => '',
    'origin_destination' => '東京',
    'quote_only' => false,
    'lead_passenger_name' => '山田 太郎',
    'passenger_count_adult' => 2,
    'passenger_count_child' => 0,
    'group_name' => '',
    'luggage_count' => 2,
    'checked_baggage' => 'yes',
    'emergency_phone' => '+81 90-1234-5678',
    'mobility_support' => array(),
    'special_notes' => '',
    'signboard_text' => 'TARO YAMADA',
    'signboard_company' => '',
    'service_language' => 'japanese',
    'customer_type' => 'individual',
    'company_name' => '',
    'department' => '',
    'applicant_name' => '山田 太郎',
    'applicant_email' => "phase4-{$unique}@example.com",
    'applicant_email_confirmation' => "phase4-{$unique}@example.com",
    'applicant_phone' => '+81 90-1234-5678',
    'contract_customer' => '',
    'contract_code' => '',
    'destination' => '東京都内ホテル',
    'transport_type' => 'undecided',
    'driver_name' => '',
    'driver_phone' => '',
    'vehicle_info' => '',
    'privacy_consent' => true,
    'terms_consent' => true,
    'marketing_consent' => false,
    'final_confirm' => true,
);

$branches = array(
    'airport_meet' => array('location_id' => 'haneda', 'flight_number' => 'NH105', 'train_number' => '', 'checked_baggage' => 'yes', 'signboard_text' => 'TARO YAMADA', 'transport_type' => 'undecided'),
    'airport_send' => array('location_id' => 'narita', 'flight_number' => 'JL12', 'train_number' => '', 'checked_baggage' => 'no', 'signboard_text' => '', 'transport_type' => ''),
    'station_meet' => array('location_id' => 'tokyo', 'flight_number' => '', 'train_number' => 'のぞみ25号', 'checked_baggage' => '', 'signboard_text' => 'TARO YAMADA', 'transport_type' => 'public_transport'),
    'station_send' => array('location_id' => 'shinagawa', 'flight_number' => '', 'train_number' => 'ひかり501号', 'checked_baggage' => '', 'signboard_text' => '', 'transport_type' => ''),
    'transfer_support' => array('location_id' => 'other', 'flight_number' => 'SHOULD-CLEAR', 'train_number' => 'SHOULD-CLEAR', 'checked_baggage' => 'yes', 'signboard_text' => 'SHOULD-CLEAR', 'transport_type' => 'customer_vehicle'),
    'attend' => array('location_id' => 'other', 'flight_number' => 'SHOULD-CLEAR', 'train_number' => 'SHOULD-CLEAR', 'checked_baggage' => 'yes', 'signboard_text' => 'SHOULD-CLEAR', 'transport_type' => 'customer_vehicle'),
);

foreach ($branches as $service => $overrides) {
    $payload = array_merge($base, $overrides, array('service_type' => $service));
    $result = JAT_Reservation_Validator::validate($payload);
    $assert($result['errors'] === array(), "{$service} 有效资料通过服务器端校验");

    if (in_array($service, array('airport_meet', 'airport_send'), true)) {
        $assert($result['data']['train_number'] === '', "{$service} 清除车站专属字段");
    }
    if (in_array($service, array('station_meet', 'station_send'), true)) {
        $assert($result['data']['flight_number'] === '' && $result['data']['checked_baggage'] === '', "{$service} 清除机场专属字段");
    }
    if (in_array($service, array('transfer_support', 'attend'), true)) {
        $assert(
            $result['data']['flight_number'] === ''
            && $result['data']['train_number'] === ''
            && $result['data']['checked_baggage'] === ''
            && $result['data']['signboard_text'] === ''
            && $result['data']['transport_type'] === '',
            "{$service} 不保存标准服务承诺字段"
        );
    }
}

$invalid_location = array_merge($base, array('service_type' => 'airport_meet', 'location_id' => 'tokyo'));
$invalid_location_result = JAT_Reservation_Validator::validate($invalid_location);
$assert(isset($invalid_location_result['errors']['location_id']), '机场服务拒绝车站地点');

$invalid_email = array_merge($base, array('applicant_email' => 'invalid', 'applicant_email_confirmation' => 'invalid'));
$invalid_email_result = JAT_Reservation_Validator::validate($invalid_email);
$assert(isset($invalid_email_result['errors']['applicant_email']), '无效邮箱返回日语字段错误');

$unsafe = array_merge(
    $base,
    array(
        'lead_passenger_name' => '<script>alert(1)</script>山田',
        'special_notes' => '<img src=x onerror=alert(1)>確認事項',
        'company_name' => str_repeat('長', 250),
        'customer_type' => 'corporate',
        'contract_customer' => 'no',
    )
);
$unsafe_result = JAT_Reservation_Validator::validate($unsafe);
$assert(strpos($unsafe_result['data']['lead_passenger_name'], '<') === false, '姓名字段移除 HTML 标签');
$assert(strpos($unsafe_result['data']['special_notes'], '<') === false, '备注字段移除 HTML 标签');
$assert(mb_strlen($unsafe_result['data']['company_name']) === 190, '长公司名按服务器端上限截断');

$hidden_vehicle = array_merge(
    $base,
    array(
        'transport_type' => 'public_transport',
        'driver_name' => 'SHOULD-CLEAR',
        'driver_phone' => '+81 90-9999-9999',
        'vehicle_info' => 'SHOULD-CLEAR',
    )
);
$hidden_vehicle_result = JAT_Reservation_Validator::validate($hidden_vehicle);
$assert(
    $hidden_vehicle_result['data']['driver_name'] === ''
    && $hidden_vehicle_result['data']['driver_phone'] === ''
    && $hidden_vehicle_result['data']['vehicle_info'] === '',
    '非自备车辆分支清除司机和车辆隐藏字段'
);

JAT_Reservation_DB::activate();
$validated = JAT_Reservation_Validator::validate($base);
$assert($validated['errors'] === array(), '幂等测试基准资料有效');

$uuid = static function (): string {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
};

$fingerprint = hash('sha256', 'phase4-' . $unique);
$first = JAT_Reservation_DB::create_order($validated['data'], $uuid(), $fingerprint);
$assert(! is_wp_error($first) && $first['duplicate'] === false, '首次持久化生成一个新订单');
if (! is_wp_error($first)) {
    $created_ids[] = (int) $first['id'];
    $assert((bool) preg_match('/^JAT-\d{6}-[A-HJ-NP-Z2-9]{6}$/', $first['public_id']), '受付编号不可顺序推测且格式正确');
}

$same_key = $uuid();
$first_same_key = JAT_Reservation_DB::create_order($validated['data'], $same_key, hash('sha256', 'phase4-key-' . $unique));
if (! is_wp_error($first_same_key)) {
    $created_ids[] = (int) $first_same_key['id'];
}
$second_same_key = JAT_Reservation_DB::create_order($validated['data'], $same_key, hash('sha256', 'phase4-key-' . $unique));
$assert(
    ! is_wp_error($first_same_key)
    && ! is_wp_error($second_same_key)
    && $second_same_key['duplicate'] === true
    && $second_same_key['public_id'] === $first_same_key['public_id'],
    '相同幂等键重试返回既有受付编号且不新建订单'
);

$fingerprint_duplicate = hash('sha256', 'phase4-fingerprint-' . $unique);
$fingerprint_first = JAT_Reservation_DB::create_order($validated['data'], $uuid(), $fingerprint_duplicate);
if (! is_wp_error($fingerprint_first)) {
    $created_ids[] = (int) $fingerprint_first['id'];
}
$fingerprint_second = JAT_Reservation_DB::create_order($validated['data'], $uuid(), $fingerprint_duplicate);
$assert(
    ! is_wp_error($fingerprint_first)
    && ! is_wp_error($fingerprint_second)
    && $fingerprint_second['duplicate'] === true
    && $fingerprint_second['public_id'] === $fingerprint_first['public_id'],
    '短时间同指纹请求返回既有订单且不重复建单'
);

global $wpdb;
$created_ids = array_values(array_unique(array_filter($created_ids)));
if ($created_ids !== array()) {
    $placeholders = implode(',', array_fill(0, count($created_ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM " . JAT_Reservation_DB::table_orders() . " WHERE id IN ({$placeholders})", ...$created_ids));
}

echo "RESULT: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
