<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Validator
{
    private const STANDARD_SERVICES = array('airport_meet', 'airport_send', 'station_meet', 'station_send');
    private const CONSULT_SERVICES = array('transfer_support', 'attend');
    private const AIRPORT_SERVICES = array('airport_meet', 'airport_send');
    private const STATION_SERVICES = array('station_meet', 'station_send');
    private const MEET_SERVICES = array('airport_meet', 'station_meet');
    private const LOCATIONS = array('haneda', 'narita', 'tokyo', 'shinagawa', 'other');

    /**
     * @return array{data:array<string,mixed>,errors:array<string,string>}
     */
    public static function validate(array $input): array
    {
        $data = array();
        $errors = array();

        $service_type = self::enum($input, 'service_type', array_merge(self::STANDARD_SERVICES, self::CONSULT_SERVICES));
        if ($service_type === '') {
            $errors['service_type'] = 'ご希望のサービスを選択してください。';
        }
        $data['service_type'] = $service_type;

        $is_standard = in_array($service_type, self::STANDARD_SERVICES, true);
        $is_airport = in_array($service_type, self::AIRPORT_SERVICES, true);
        $is_station = in_array($service_type, self::STATION_SERVICES, true);
        $is_meet = in_array($service_type, self::MEET_SERVICES, true);

        $data['location_type'] = $is_airport ? 'airport' : ($is_station ? 'station' : 'consultation');
        $data['location_id'] = self::enum($input, 'location_id', self::LOCATIONS);
        if ($data['location_id'] === '') {
            $errors['location_id'] = '空港・駅を選択してください。';
        }

        if ($is_airport && in_array($data['location_id'], array('tokyo', 'shinagawa'), true)) {
            $errors['location_id'] = '空港サービスでは空港を選択してください。';
        }
        if ($is_station && in_array($data['location_id'], array('haneda', 'narita'), true)) {
            $errors['location_id'] = '駅サービスでは駅を選択してください。';
        }

        $data['terminal_area'] = self::text($input, 'terminal_area', 100);
        $data['service_date'] = self::date($input, 'service_date');
        if ($data['service_date'] === '') {
            $errors['service_date'] = 'ご利用日を正しく選択してください。';
        }
        $data['scheduled_time'] = self::time($input, 'scheduled_time');
        if ($data['scheduled_time'] === '') {
            $errors['scheduled_time'] = '到着・出発予定時刻を選択してください。';
        }

        $data['flight_number'] = $is_airport ? self::text($input, 'flight_number', 30) : '';
        $data['train_number'] = $is_station ? self::text($input, 'train_number', 60) : '';
        if ($is_airport && $data['flight_number'] === '') {
            $errors['flight_number'] = '便名・便番号を入力してください。';
        }
        if ($is_station && $data['train_number'] === '') {
            $errors['train_number'] = '列車名・列車番号を入力してください。';
        }

        $data['origin_destination'] = self::text($input, 'origin_destination', 190);
        if ($data['origin_destination'] === '') {
            $errors['origin_destination'] = '出発地・到着地を入力してください。';
        }
        $data['quote_only'] = self::boolean($input, 'quote_only');

        $data['lead_passenger_name'] = self::text($input, 'lead_passenger_name', 120);
        if ($data['lead_passenger_name'] === '') {
            $errors['lead_passenger_name'] = '代表者名を入力してください。';
        }

        $data['passenger_count_adult'] = self::integer($input, 'passenger_count_adult', 1, 99);
        if ($data['passenger_count_adult'] === null) {
            $errors['passenger_count_adult'] = '大人の人数を1名以上で入力してください。';
        }
        $data['passenger_count_child'] = self::integer($input, 'passenger_count_child', 0, 99) ?? 0;
        $data['group_name'] = self::text($input, 'group_name', 190);
        $data['luggage_count'] = self::integer($input, 'luggage_count', 0, 99);
        if ($is_standard && $data['luggage_count'] === null) {
            $errors['luggage_count'] = 'お荷物の個数を入力してください。';
        }
        if (! $is_standard && $data['luggage_count'] === null) {
            $data['luggage_count'] = 0;
        }
        $data['checked_baggage'] = $is_airport ? self::enum($input, 'checked_baggage', array('yes', 'no', 'undecided')) : '';
        if ($is_airport && $data['checked_baggage'] === '') {
            $errors['checked_baggage'] = '受託手荷物の有無を選択してください。';
        }

        $data['emergency_phone'] = self::phone($input, 'emergency_phone');
        if ($data['emergency_phone'] === '') {
            $errors['emergency_phone'] = '当日の緊急連絡先を入力してください。';
        }
        $data['mobility_support'] = self::multi_enum(
            $input,
            'mobility_support',
            array('wheelchair', 'walking', 'infant', 'support_other')
        );
        $data['special_notes'] = self::textarea($input, 'special_notes', 1000);

        $data['signboard_text'] = $is_meet ? self::text($input, 'signboard_text', 80) : '';
        if ($is_meet && $data['signboard_text'] === '') {
            $errors['signboard_text'] = 'サインボードに表示するお名前を入力してください。';
        }
        $data['signboard_company'] = $is_meet ? self::text($input, 'signboard_company', 120) : '';
        $data['service_language'] = self::enum($input, 'service_language', array('japanese', 'consultation'));
        if ($data['service_language'] === '') {
            $errors['service_language'] = 'ご希望の対応言語を選択してください。';
        }

        $data['customer_type'] = self::enum($input, 'customer_type', array('individual', 'corporate'));
        if ($data['customer_type'] === '') {
            $errors['customer_type'] = 'お申込者区分を選択してください。';
        }
        $is_corporate = $data['customer_type'] === 'corporate';
        $data['company_name'] = $is_corporate ? self::text($input, 'company_name', 190) : '';
        if ($is_corporate && $data['company_name'] === '') {
            $errors['company_name'] = '会社名・団体名を入力してください。';
        }
        $data['department'] = $is_corporate ? self::text($input, 'department', 120) : '';
        $data['applicant_name'] = self::text($input, 'applicant_name', 120);
        if ($data['applicant_name'] === '') {
            $errors['applicant_name'] = 'ご担当者名を入力してください。';
        }
        $data['applicant_email'] = self::email($input, 'applicant_email');
        if ($data['applicant_email'] === '') {
            $errors['applicant_email'] = 'メールアドレスの形式をご確認ください。';
        }
        $email_confirmation = self::email($input, 'applicant_email_confirmation');
        if ($email_confirmation === '' || ! hash_equals($data['applicant_email'], $email_confirmation)) {
            $errors['applicant_email_confirmation'] = '確認用メールアドレスが一致しません。';
        }
        $data['applicant_phone'] = self::phone($input, 'applicant_phone');
        if ($data['applicant_phone'] === '') {
            $errors['applicant_phone'] = '電話番号を入力してください。';
        }
        $data['contract_customer'] = $is_corporate ? self::enum($input, 'contract_customer', array('yes', 'no', 'unknown')) : '';
        $data['contract_code'] = ($is_corporate && $data['contract_customer'] === 'yes') ? self::text($input, 'contract_code', 80) : '';
        $data['destination'] = self::text($input, 'destination', 190);
        if ($data['destination'] === '') {
            $errors['destination'] = 'ご案内先・目的地を入力してください。';
        }

        $data['transport_type'] = $is_meet ? self::enum(
            $input,
            'transport_type',
            array('arranged_vehicle', 'customer_vehicle', 'public_transport', 'undecided')
        ) : '';
        if ($is_meet && $data['transport_type'] === '') {
            $errors['transport_type'] = 'ご利用予定の交通手段を選択してください。';
        }
        $needs_vehicle = $is_meet && $data['transport_type'] === 'customer_vehicle';
        $data['driver_name'] = $needs_vehicle ? self::text($input, 'driver_name', 120) : '';
        $data['driver_phone'] = $needs_vehicle ? self::phone($input, 'driver_phone') : '';
        $data['vehicle_info'] = $needs_vehicle ? self::text($input, 'vehicle_info', 190) : '';

        $data['privacy_consent'] = self::boolean($input, 'privacy_consent');
        $data['terms_consent'] = self::boolean($input, 'terms_consent');
        $data['marketing_consent'] = self::boolean($input, 'marketing_consent');
        $data['final_confirm'] = self::boolean($input, 'final_confirm');
        if (! $data['privacy_consent']) {
            $errors['privacy_consent'] = 'プライバシーポリシーへの同意が必要です。';
        }
        if (! $data['terms_consent']) {
            $errors['terms_consent'] = '利用規約・キャンセルポリシーへの同意が必要です。';
        }
        if (! $data['final_confirm']) {
            $errors['final_confirm'] = '入力内容をご確認ください。';
        }

        return array('data' => $data, 'errors' => $errors);
    }

    private static function raw(array $input, string $key): mixed
    {
        return $input[$key] ?? null;
    }

    private static function text(array $input, string $key, int $max_length): string
    {
        $value = sanitize_text_field((string) self::raw($input, $key));
        return mb_substr(trim($value), 0, $max_length);
    }

    private static function textarea(array $input, string $key, int $max_length): string
    {
        $value = sanitize_textarea_field((string) self::raw($input, $key));
        return mb_substr(trim($value), 0, $max_length);
    }

    private static function email(array $input, string $key): string
    {
        return sanitize_email((string) self::raw($input, $key));
    }

    private static function phone(array $input, string $key): string
    {
        $value = preg_replace('/[^0-9+().\-\s]/u', '', (string) self::raw($input, $key));
        $value = mb_substr(trim((string) $value), 0, 40);
        return preg_match('/[0-9]{6,}/', preg_replace('/\D/', '', $value) ?? '') === 1 ? $value : '';
    }

    private static function enum(array $input, string $key, array $allowed): string
    {
        $value = sanitize_key((string) self::raw($input, $key));
        return in_array($value, $allowed, true) ? $value : '';
    }

    private static function multi_enum(array $input, string $key, array $allowed): array
    {
        $value = self::raw($input, $key);
        if (! is_array($value)) {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $value), static fn (string $item): bool => in_array($item, $allowed, true))));
    }

    private static function integer(array $input, string $key, int $minimum, int $maximum): ?int
    {
        $value = filter_var(self::raw($input, $key), FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            return null;
        }
        return (int) $value;
    }

    private static function boolean(array $input, string $key): bool
    {
        return filter_var(self::raw($input, $key), FILTER_VALIDATE_BOOLEAN);
    }

    private static function date(array $input, string $key): string
    {
        $value = (string) self::raw($input, $key);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        $errors = DateTimeImmutable::getLastErrors();
        if (! $date || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return '';
        }
        $today = new DateTimeImmutable('today', wp_timezone());
        return $date < $today ? '' : $date->format('Y-m-d');
    }

    private static function time(array $input, string $key): string
    {
        $value = (string) self::raw($input, $key);
        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1 ? $value : '';
    }
}
