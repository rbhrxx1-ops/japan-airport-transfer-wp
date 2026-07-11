<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_State_Machine
{
    /**
     * @return array<string,string>
     */
    public static function labels(): array
    {
        return array(
            'received' => '受付済み',
            'reviewing' => '確認中',
            'quote_sent' => 'お見積り送付済み',
            'confirmed' => '予約確定',
            'change_pending' => '変更確認中',
            'completed' => 'サービス完了',
            'cancelled' => 'キャンセル',
        );
    }

    /**
     * @return array<string,list<string>>
     */
    private static function transitions(): array
    {
        return array(
            'received' => array('reviewing', 'cancelled'),
            'reviewing' => array('quote_sent', 'change_pending', 'cancelled'),
            'quote_sent' => array('confirmed', 'change_pending', 'cancelled'),
            'confirmed' => array('change_pending', 'completed', 'cancelled'),
            'change_pending' => array('reviewing', 'quote_sent', 'confirmed', 'cancelled'),
            'completed' => array(),
            'cancelled' => array(),
        );
    }

    public static function label(string $status): string
    {
        return self::labels()[$status] ?? '不明';
    }

    /**
     * @return list<string>
     */
    public static function allowed_targets(string $current, bool $can_restore = false): array
    {
        $targets = self::transitions()[$current] ?? array();
        if ($current === 'cancelled' && $can_restore) {
            $targets[] = 'reviewing';
        }

        return $targets;
    }

    public static function can_transition(string $current, string $next, bool $can_restore = false): bool
    {
        if (! isset(self::labels()[$current], self::labels()[$next]) || $current === $next) {
            return false;
        }

        return in_array($next, self::allowed_targets($current, $can_restore), true);
    }
}
