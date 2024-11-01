<?php
/**
 * Plugin bootstraeber.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_EBEC_Gateway_Loader {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$includes_path = wc_gateway_ebec()->includes_path;

		require_once( $includes_path . 'class-wc-gateway-ebec-refund.php' );
		require_once( $includes_path . 'abstracts/abstract-wc-gateway-ebec.php' );

		require_once( $includes_path . 'class-wc-gateway-ebec-with-eligebtc.php' );
		require_once( $includes_path . 'class-wc-gateway-ebec-with-eligebtc-addons.php' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'payment_gateways' ) );
	}

	/**
	 * Register the PPEC payment methods.
	 *
	 * @param array $methods Payment methods.
	 *
	 * @return array Payment methods
	 */
	public function payment_gateways( $methods ) {
		if ( $this->can_use_addons() ) {
			$methods[] = 'WC_Gateway_EBEC_With_EligeBTC_Addons';
		} else {
			$methods[] = 'WC_Gateway_EBEC_With_EligeBTC';
		}

		return $methods;
	}

	/**
	 * Checks whether gateway addons can be used.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if gateway addons can be used
	 */
	public function can_use_addons() {
		return ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) );
	}
}
