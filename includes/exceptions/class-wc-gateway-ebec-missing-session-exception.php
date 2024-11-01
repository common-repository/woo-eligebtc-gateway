<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Missing session exception.
 */
class EligeBTC_Missing_Session_Exception extends Exception {

	/**
	 * Constructor.
	 *
	 * @param string $message Exception message
	 */
	public function __construct( $message = '' ) {
		if ( empty( $message ) ) {
			$message = __( 'The buyer\'s session information could not be found.', 'woocommerce-gateway-eligebtc' );
		}

		parent::__construct( $message );
	}
}
