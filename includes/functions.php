<?php

function woo_eb_start_checkout() {
	$checkout = wc_gateway_ebec()->checkout;

	try {
		$redirect_url = $checkout->start_checkout_from_cart();
		wp_safe_redirect( $redirect_url );
		exit;
	} catch( EligeBTC_API_Exception $e ) {
		wc_add_notice( $e->getMessage(), 'error' );

		$redirect_url = WC()->cart->get_cart_url();
		$settings     = wc_gateway_ebec()->settings;
		$client       = wc_gateway_ebec()->client;

		if ( $settings->is_enabled() && $client->get_payer_id() ) {
			ob_end_clean();
			?>
			<script type="text/javascript">
				if( ( window.opener != null ) && ( window.opener !== window ) &&
						( typeof window.opener.eligebtc != "undefined" ) &&
						( typeof window.opener.eligebtc.checkout != "undefined" ) ) {
					window.opener.location.assign( "<?php echo $redirect_url; ?>" );
					window.close();
				} else {
					window.location.assign( "<?php echo $redirect_url; ?>" );
				}
			</script>
			<?php
			exit;
		} else {
			wp_safe_redirect( $redirect_url );
			exit;
		}

	}
}

/**
 * @deprecated
 */
function wc_gateway_EBEC_format_eligebtc_api_exception( $errors ) {
	_deprecated_function( 'wc_gateway_EBEC_format_eligebtc_api_exception', '1.2.0', '' );
}

/**
 * Log a message via WC_Logger.
 *
 * @param string $message Message to log
 */
function wc_gateway_EBEC_log( $message ) {
	static $wc_ebec_logger;

	// No need to write to log file if logging is disabled.
	if ( ! wc_gateway_ebec()->settings->is_logging_enabled() ) {
		return false;
	}

	if ( ! isset( $wc_ebec_logger ) ) {
		$wc_ebec_logger = new WC_Logger();
	}

	$wc_ebec_logger->add( 'WC_Gateway_EBEC', $message );

	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( $message );
	}
}

/**
 * Checks whether buyer is checking out with EligeBTC Credit.
 *
 * @since 1.2.0
 *
 * @return bool Returns true if buyer is checking out with EligeBTC Credit
 */
function wc_gateway_EBEC_is_using_credit() {
	return ! empty( $_GET['use-ppc'] ) && 'true' === $_GET['use-ppc'];
}
