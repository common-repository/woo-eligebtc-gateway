<?php
/**
 * Plugin Name: WooCommerce EligeBTC Gateway
 * Plugin URI: https://wordpress.org/plugins/woo-eligebtc-gateway/
 * Description: A payment gateway for EligeBTC (<a href="http://www.eligebtc.com">http://www.eligebtc.com</a>).
 * Version: 1.6.4
 * Author: WooCommerce
 * Author URI: https://woocommerce.com
 * Copyright: © 2017 WooCommerce / EligeBTC.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: woocommerce-gateway-eligebtc
 * Domain Path: /languages
 */
/**
 * Copyright (c) 2017 BeTechnology
 *
 * The name of the EligeBTC may not be used to endorse or promote products derived from this
 * software without specific prior written permission. THIS SOFTWARE IS PROVIDED ``AS IS'' AND
 * WITHOUT ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, WITHOUT LIMITATION, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

define( 'WC_GATEWAY_EBEC_VERSION', '1.6.4' );

/**
 * Return instance of WC_Gateway_EBEC_Plugin.
 *
 * @return WC_Gateway_EBEC_Plugin
 */
function wc_gateway_ebec() {
	static $plugin;

	if ( ! isset( $plugin ) ) {
		require_once( 'includes/class-wc-gateway-ebec-plugin.php' );

		$plugin = new WC_Gateway_EBEC_Plugin( __FILE__, WC_GATEWAY_EBEC_VERSION );
	}

	return $plugin;
}

wc_gateway_ebec()->maybe_run();
