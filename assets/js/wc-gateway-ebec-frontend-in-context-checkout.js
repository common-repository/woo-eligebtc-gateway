;(function ( $, window, document ) {
	'use strict';

	var $wc_ebec = {
		init: function() {
			window.eligebtcCheckoutReady = function() {				
				eligebtc.checkout.setup(
					wc_ebec_context.payer_id,
					{
						environment: wc_ebec_context.environment,
						button: ['woo_eb_ec_button', 'woo_eb_ebc_button'],
						locale: wc_ebec_context.locale,
						container: ['woo_eb_ec_button', 'woo_eb_ebc_button']
					}
				);
			}
		}
	}

	var costs_updated = false;

	$( '#woo_eb_ec_button' ).click( function( event ) {
		if ( costs_updated ) {
			costs_updated = false;

			return;
		}

		event.stopPropagation();

		var data = {
			'nonce':      wc_ebec_context.update_shipping_costs_nonce,
		};

		var href = $(this).attr( 'href' );

		$.ajax( {
			type:    'POST',
			data:    data,
			url:     wc_ebec_context.ajaxurl,
			success: function( response ) {
				costs_updated = true;
				$( '#woo_eb_ec_button' ).click();
			}
		} );
	} );

	if ( wc_ebec_context.show_modal ) {
		$wc_ebec.init();
	}
})( jQuery, window, document );
