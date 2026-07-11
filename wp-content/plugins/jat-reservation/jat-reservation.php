<?php
/**
 * Plugin Name: JAT Reservation
 * Description: Japan Airport Transfer の五段階お申し込みフォームと受付 API。
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.3
 * Author: Japan Airport Transfer
 * Text Domain: jat-reservation
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('JAT_RESERVATION_VERSION', '0.1.0');
define('JAT_RESERVATION_FILE', __FILE__);
define('JAT_RESERVATION_DIR', plugin_dir_path(__FILE__));
define('JAT_RESERVATION_URL', plugin_dir_url(__FILE__));

require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-db.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-validator.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-api.php';
require_once JAT_RESERVATION_DIR . 'includes/class-jat-reservation-form.php';

register_activation_hook(JAT_RESERVATION_FILE, array('JAT_Reservation_DB', 'activate'));

add_action(
    'plugins_loaded',
    static function (): void {
        JAT_Reservation_DB::maybe_upgrade();
        JAT_Reservation_API::init();
        JAT_Reservation_Form::init();
    }
);
