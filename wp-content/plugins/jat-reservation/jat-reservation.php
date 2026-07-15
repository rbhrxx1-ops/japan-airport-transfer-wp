<?php
/**
 * Plugin Name: JAT Reservation
 * Description: Meet & Link の五段階お申し込みフォーム、受付 API、注文管理、通知およびプライバシー機能。
 * Version: 0.2.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Meet & Link
 * Text Domain: jat-reservation
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('JAT_RESERVATION_VERSION', '0.2.0');
define('JAT_RESERVATION_FILE', __FILE__);
define('JAT_RESERVATION_DIR', plugin_dir_path(__FILE__));
define('JAT_RESERVATION_URL', plugin_dir_url(__FILE__));

require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-state-machine.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-db.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-validator.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-mailer.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-api.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-form.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-roles.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-admin.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-privacy.php';

register_activation_hook(JAT_RESERVATION_FILE, array('JAT_Reservation_DB', 'activate'));
register_activation_hook(JAT_RESERVATION_FILE, array('JAT_Reservation_Roles', 'install'));

add_action(
    'plugins_loaded',
    static function (): void {
        JAT_Reservation_DB::maybe_upgrade();
        JAT_Reservation_Roles::maybe_upgrade();
        JAT_Reservation_API::init();
        JAT_Reservation_Form::init();
        JAT_Reservation_Admin::init();
        JAT_Reservation_Privacy::init();
    }
);
