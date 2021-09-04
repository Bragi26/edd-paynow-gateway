<?php

/*
 * Plugin Name: Payment Gateway for Paynow on Easy Digital Downloads
 * Plugin URI: https://github.com/Bragi26/edd-paynow-gateway
 * Description: A Paynow payment gateway for Easy Digital Downloads Wordpress plugin
 * Version: 1.0.1
 * Author: Piotr Włoch
 * Author URI: pwloch.eu
 * License: GPLv3
 * Text Domain: edd-paynow-gateway
 */

/*
	Easy Digital Downloads Paynow Payment Gateway Plugin is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	any later version.

	Easy Digital Downloads Paynow Payment Gateway Plugin is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with Easy Digital Downloads Paynow Payment Gateway Plugin. If not, see http://www.gnu.org/licenses/gpl-3.0.html.
 */

if (!defined('EDD_PAYNOW_PAYMENTS_VERSION')) {
    define('EDD_PAYNOW_PAYMENTS_VERSION', '1.0.1');
}

if (!defined('EDD_PAYNOW_PAYMENTS_DIR')) {
    define('EDD_PAYNOW_PAYMENTS_DIR', dirname(__FILE__));
}

if (!defined('EDD_PAYNOW_PAYMENTS_URL')) {
    define('EDD_PAYNOW_PAYMENTS_URL', plugins_url('', __FILE__));
}

function edd_paynow_payments_init()
{
    $langdir = trailingslashit(WP_LANG_DIR);
    load_textdomain('edd-paynow-gateway', $langdir . 'edd-paynow-gateway-' . get_locale() . '.mo');
    load_plugin_textdomain('edd-paynow-gateway', false, plugin_basename(EDD_PAYNOW_PAYMENTS_DIR . '/languages'));

    require_once EDD_PAYNOW_PAYMENTS_DIR . '/src/EDD/Payments/Paynow/Paynow.php';
    $edd_paynow_payments = \EDD\Payments\Paynow\Paynow::getInstance();
}

add_action('plugins_loaded', 'edd_paynow_payments_init');
