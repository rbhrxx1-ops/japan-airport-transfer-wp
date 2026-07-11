<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Privacy
{
    public static function init(): void
    {
        add_filter('wp_privacy_personal_data_exporters', array(self::class, 'register_exporter'));
        add_filter('wp_privacy_personal_data_erasers', array(self::class, 'register_eraser'));
        add_action('admin_init', array(self::class, 'register_policy_text'));
    }

    public static function register_exporter(array $exporters): array
    {
        $exporters['jat-reservation'] = array(
            'exporter_friendly_name' => 'Japan Airport Transfer お申し込みデータ',
            'callback' => array(self::class, 'export_personal_data'),
        );
        return $exporters;
    }

    public static function register_eraser(array $erasers): array
    {
        $erasers['jat-reservation'] = array(
            'eraser_friendly_name' => 'Japan Airport Transfer お申し込みデータ',
            'callback' => array(self::class, 'erase_personal_data'),
        );
        return $erasers;
    }

    public static function register_policy_text(): void
    {
        if (! function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        wp_add_privacy_policy_content(
            'Japan Airport Transfer 予約受付',
            wp_kses_post(
                '<p>オンライン申込フォームでは、サービス提供、連絡、本人確認および法令上必要な記録のため、氏名、連絡先、行程、ご利用者情報、同意記録等を保存します。保存期間、共同利用、第三者提供、国外移転および削除条件は、公開前に承認済みのプライバシーポリシーへ反映してください。</p>'
            )
        );
    }

    /** @return array{data:list<array<string,mixed>>,done:bool} */
    public static function export_personal_data(string $email_address, int $page = 1): array
    {
        $data = array();
        if ($page > 1 || ! is_email($email_address)) {
            return array('data' => $data, 'done' => true);
        }
        foreach (JAT_Reservation_DB::find_by_email($email_address) as $order) {
            $payload = json_decode((string) $order['current_payload'], true);
            if (! is_array($payload)) {
                $payload = array();
            }
            $fields = array(
                array('name' => '受付番号', 'value' => (string) $order['public_id']),
                array('name' => '現在の状態', 'value' => JAT_Reservation_State_Machine::label((string) $order['status'])),
                array('name' => 'ご利用日', 'value' => (string) $order['service_date']),
                array('name' => '予定時刻', 'value' => substr((string) $order['scheduled_time'], 0, 5)),
                array('name' => 'お申込者名', 'value' => (string) $order['applicant_name']),
                array('name' => 'メールアドレス', 'value' => (string) $order['applicant_email']),
                array('name' => '会社名・団体名', 'value' => (string) $order['company_name']),
                array('name' => '受付日時', 'value' => (string) $order['created_at']),
                array('name' => '同意文書の版', 'value' => (string) $order['consent_version']),
                array('name' => '申込内容', 'value' => (string) wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)),
            );
            $data[] = array(
                'group_id' => 'jat-reservation-orders',
                'group_label' => 'Japan Airport Transfer お申し込み',
                'item_id' => 'jat-order-' . (int) $order['id'],
                'data' => $fields,
            );
        }
        return array('data' => $data, 'done' => true);
    }

    /** @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool} */
    public static function erase_personal_data(string $email_address, int $page = 1): array
    {
        $removed = false;
        $retained = false;
        $messages = array();
        if ($page > 1 || ! is_email($email_address)) {
            return array('items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true);
        }
        foreach (JAT_Reservation_DB::find_by_email($email_address) as $order) {
            $can_erase = (bool) apply_filters('jat_reservation_can_erase_order', true, $order);
            if (! $can_erase) {
                $retained = true;
                $messages[] = '法令または契約上必要な記録のため、受付番号 ' . $order['public_id'] . ' は削除対象外です。';
                continue;
            }
            if (JAT_Reservation_DB::anonymize_order((int) $order['id'], get_current_user_id())) {
                $removed = true;
            } else {
                $retained = true;
                $messages[] = '受付番号 ' . $order['public_id'] . ' の匿名化に失敗しました。';
            }
        }
        return array('items_removed' => $removed, 'items_retained' => $retained, 'messages' => $messages, 'done' => true);
    }
}
