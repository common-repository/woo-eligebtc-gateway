<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_EBEC_With_EligeBTC extends WC_Gateway_EBEC {
	public function __construct() {
		$this->id = 'ebec_eligebtc';

		parent::__construct();

		if ( $this->is_available() ) {
			$ipn_handler = new WC_Gateway_EBEC_IPN_Handler( $this );
			$ipn_handler->handle();
		}
	}
}
