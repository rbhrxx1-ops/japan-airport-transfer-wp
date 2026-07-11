<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Mailer
{
    public static function send_received(int $order_id): void
    {
        $order = JAT_Reservation_DB::get_order($order_id);
        if ($order === null) {
            return;
        }

        $subject = '【Japan Airport Transfer】お申し込みを受け付けました（' . $order['public_id'] . '）';
        $body = self::customer_header($order)
            . "\nお申し込みを受け付けました。内容を確認後、担当者よりご案内します。\n"
            . "このメールは予約確定をお知らせするものではありません。\n\n"
            . self::order_summary($order)
            . self::customer_footer();
        self::send_and_log($order_id, 'received', (string) $order['applicant_email'], $subject, $body, 0);

        $office = sanitize_email((string) get_option('jat_reservation_notification_email', get_option('admin_email')));
        if ($office !== '') {
            $office_subject = '【新規受付】' . $order['public_id'] . ' ' . JAT_Reservation_State_Machine::label((string) $order['status']);
            $office_body = "新しいお申し込みを受け付けました。\n\n" . self::order_summary($order) . "\n管理画面で詳細を確認してください。\n";
            self::send_and_log($order_id, 'received_office', $office, $office_subject, $office_body, 0);
        }
    }

    public static function send_for_status(int $order_id, string $status, int $actor_user_id, string $customer_message = ''): void
    {
        $order = JAT_Reservation_DB::get_order($order_id);
        if ($order === null) {
            return;
        }

        $templates = array(
            'reviewing' => array('内容確認中のご案内', '現在、担当者がお申し込み内容を確認しています。'),
            'quote_sent' => array('お見積りのご案内', 'お見積りに関するご案内です。'),
            'confirmed' => array('ご予約確定のご案内', 'ご予約が確定しました。'),
            'change_pending' => array('変更内容確認中のご案内', '変更内容を確認しています。担当者からの確定連絡をお待ちください。'),
            'completed' => array('サービス完了のご案内', 'サービスのご利用ありがとうございました。'),
            'cancelled' => array('キャンセルのご案内', 'お申し込みをキャンセルとして受け付けました。'),
        );
        if (! isset($templates[$status])) {
            return;
        }

        [$title, $intro] = $templates[$status];
        $subject = '【Japan Airport Transfer】' . $title . '（' . $order['public_id'] . '）';
        $body = self::customer_header($order) . "\n{$intro}\n";
        if ($customer_message !== '') {
            $body .= "\n担当者からのご案内：\n" . sanitize_textarea_field($customer_message) . "\n";
        }
        $body .= "\n" . self::order_summary($order) . self::customer_footer();
        self::send_and_log($order_id, $status, (string) $order['applicant_email'], $subject, $body, $actor_user_id);
    }

    private static function send_and_log(int $order_id, string $template_key, string $recipient, string $subject, string $body, int $actor_user_id): void
    {
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = wp_mail($recipient, $subject, $body, $headers);
        JAT_Reservation_DB::record_mail(
            $order_id,
            $template_key,
            $recipient,
            $subject,
            $sent ? 'sent' : 'failed',
            $sent ? '' : 'wp_mail_failed',
            $actor_user_id
        );
        JAT_Reservation_DB::record_audit($order_id, $sent ? 'mail_sent' : 'mail_failed', $actor_user_id, array('template' => $template_key));
    }

    /** @param array<string,mixed> $order */
    private static function customer_header(array $order): string
    {
        return (string) $order['applicant_name'] . " 様\n\nJapan Airport Transferをご利用いただき、ありがとうございます。\n";
    }

    /** @param array<string,mixed> $order */
    private static function order_summary(array $order): string
    {
        return "受付番号：" . $order['public_id'] . "\n"
            . "ご利用日：" . $order['service_date'] . "\n"
            . "予定時刻：" . substr((string) $order['scheduled_time'], 0, 5) . "\n"
            . "現在の状態：" . JAT_Reservation_State_Machine::label((string) $order['status']) . "\n";
    }

    private static function customer_footer(): string
    {
        return "\n※このメールはシステムから自動送信されています。\n"
            . "内容にお心当たりがない場合は、本メールへの返信ではなく公式サイトのお問い合わせ窓口からご連絡ください。\n";
    }
}
