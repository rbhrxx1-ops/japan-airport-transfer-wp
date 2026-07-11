<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

final class JAT_Reservation_Roles
{
    private const VERSION = '0.1.0';
    private const VERSION_OPTION = 'jat_reservation_roles_version';

    /** @return list<string> */
    public static function all_capabilities(): array
    {
        return array(
            'jat_view_orders',
            'jat_manage_orders',
            'jat_export_orders',
            'jat_manage_reservation_settings',
            'jat_restore_cancelled_orders',
        );
    }

    public static function maybe_upgrade(): void
    {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }
        self::install();
    }

    public static function install(): void
    {
        add_role(
            'jat_reservation_staff',
            '予約客服',
            array(
                'read' => true,
                'jat_view_orders' => true,
                'jat_manage_orders' => true,
            )
        );

        add_role(
            'jat_reservation_supervisor',
            '予約主管',
            array(
                'read' => true,
                'jat_view_orders' => true,
                'jat_manage_orders' => true,
                'jat_export_orders' => true,
                'jat_restore_cancelled_orders' => true,
            )
        );

        $administrator = get_role('administrator');
        if ($administrator instanceof WP_Role) {
            foreach (self::all_capabilities() as $capability) {
                $administrator->add_cap($capability);
            }
        }

        update_option(self::VERSION_OPTION, self::VERSION, false);
    }
}
