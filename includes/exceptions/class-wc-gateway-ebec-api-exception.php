<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * EligeBTC API Exception.
 */
class EligeBTC_API_Exception extends Exception {

	/**
	 * List of errors from EligeBTC API.
	 *
	 * @var array
	 */
	public $errors;

	/**
	 * Unique identifier of EligeBTC transaction.
	 *
	 * This identifies the EligeBTC application that processed the request and
	 * must be provided to Merchant Technical Support if you need their assistance
	 * with a specific transaction.
	 *
	 * @var string
	 */
	public $correlation_id;

	/**
	 * Constructor.
	 *
	 * This constructor takes the API response received from EligeBTC, parses out the
	 * errors in the response, then places those errors into the $errors property.
	 * It also captures correlation ID and places that in the $correlation_id property.
	 *
	 * @param array $response Response from EligeBTC API
	 */
	public function __construct( $response ) {
		parent::__construct( __( 'An error occurred while calling the EligeBTC API.', 'woocommerce-gateway-eligebtc' ) );

		$errors = array();
		foreach ( $response as $index => $value ) {
			if ( preg_match( '/^L_ERRORCODE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['code'] = $value;
			} elseif ( preg_match( '/^L_SHORTMESSAGE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['message'] = $value;
			} elseif ( preg_match( '/^L_LONGMESSAGE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['long'] = $value;
			} elseif ( preg_match( '/^L_SEVERITYCODE(\d+)$/', $index, $matches ) ) {
				$errors[ $matches[1] ]['severity'] = $value;
			} elseif ( 'CORRELATIONID' == $index ) {
				$this->correlation_id = $value;
			}
		}

		$this->errors   = array();
		$error_messages = array();
		foreach ( $errors as $value ) {
			$error = new EligeBTC_API_Error( $value['code'], $value['message'], $value['long'], $value['severity'] );
			$this->errors[] = $error;

			/* translators: placeholders are error code and message from EligeBTC */
			$error_messages[] = sprintf( __( 'EligeBTC error (%1$s): %2$s', 'woocommerce-gateway-eligebtc' ), $error->error_code, $error->maptoBuyerFriendlyError() );
		}

		if ( empty( $error_messages ) ) {
			$error_messages[] = __( 'An error occurred while calling the EligeBTC API.', 'woocommerce-gateway-eligebtc' );
		}

		$this->message = implode( PHP_EOL, $error_messages );
	}
}
