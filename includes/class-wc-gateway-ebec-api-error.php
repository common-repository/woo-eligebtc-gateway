<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class EligeBTC_API_Error {
	public $error_code;
	public $short_message;
	public $long_message;
	public $severity_code;

	public function __construct( $error_code, $short_message, $long_message, $severity_code ) {
		$this->error_code    = $error_code;
		$this->short_message = $short_message;
		$this->long_message  = $long_message;
		$this->severity_code = $severity_code;
	}

	public function mapToBuyerFriendlyError() {
		switch ( $this->error_code ) {
			case '-1':    return __( 'Unable to communicate with EligeBTC.  Please try your payment again.', 'woocommerce-gateway-eligebtc' );
			case '10407': return __( 'EligeBTC rejected your email address because it is not valid.  Please double-check your email address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10409':
			case '10421':
			case '10410': return __( 'Your EligeBTC checkout session is invalid.  Please check out again.', 'woocommerce-gateway-eligebtc' );
			case '10411': return __( 'Your EligeBTC checkout session has expired.  Please check out again.', 'woocommerce-gateway-eligebtc' );
			case '11607':
			case '10415': return __( 'Your EligeBTC payment has already been completed.  Please contact the store owner for more information.', 'woocommerce-gateway-eligebtc' );
			case '10416': return __( 'Your EligeBTC payment could not be processed.  Please check out again or contact EligeBTC for assistance.', 'woocommerce-gateway-eligebtc' );
			case '10417': return __( 'Your EligeBTC payment could not be processed.  Please select an alternative method of payment or contact EligeBTC for assistance.', 'woocommerce-gateway-eligebtc' );
			case '10486':
			case '10422': return __( 'Your EligeBTC payment could not be processed.  Please return to EligeBTC and select a new method of payment.', 'woocommerce-gateway-eligebtc' );
			case '10485':
			case '10435': return __( 'You have not approved this transaction on the EligeBTC website.  Please check out again and be sure to complete all steps of the EligeBTC checkout process.', 'woocommerce-gateway-eligebtc' );
			case '10474': return __( 'Your shipping address may not be in a different country than your country of residence.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10537': return __( 'This store does not accept transactions from buyers in your country.  Please contact the store owner for assistance.', 'woocommerce-gateway-eligebtc' );
			case '10538': return __( 'The transaction is over the threshold allowed by this store.  Please contact the store owner for assistance.', 'woocommerce-gateway-eligebtc' );
			case '11611':
			case '10539': return __( 'Your transaction was declined.  Please contact the store owner for assistance.', 'woocommerce-gateway-eligebtc' );
			case '10725': return __( 'The country in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10727': return __( 'The street address in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10728': return __( 'The city in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10729': return __( 'The state in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10730': return __( 'The ZIP code or postal code in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10731': return __( 'The country in your shipping address is not valid.  Please double-check your shipping address and try again.', 'woocommerce-gateway-eligebtc' );
			case '10736': return __( 'EligeBTC rejected your shipping address because the city, state, and/or ZIP code are incorrect.  Please double-check that they are all spelled correctly and try again.', 'woocommerce-gateway-eligebtc' );
			case '13113':
			case '11084': return __( 'Your EligeBTC payment could not be processed.  Please contact EligeBTC for assistance.', 'woocommerce-gateway-eligebtc' );
			case '12126':
			case '12125': return __( 'The redemption code(s) you entered on EligeBTC cannot be used at this time.  Please return to EligeBTC and remove them.', 'woocommerce-gateway-eligebtc' );
			case '17203':
			case '17204':
			case '17200': return __( 'Your funding instrument is invalid.  Please check out again and select a new funding source.', 'woocommerce-gateway-eligebtc' );
			default:      return sprintf( __( 'An error (%s) occurred while processing your EligeBTC payment.  Please contact the store owner for assistance.', 'woocommerce-gateway-eligebtc' ), $this->error_code );
		}
	}
}
