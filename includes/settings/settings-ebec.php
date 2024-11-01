<?php

/* Esta clase tiene las propiedades de cada elemento de la ventana de settings 
   Aqui se cambia la ventana de visualizacion de settings
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$token         = $this->get_option( 'token' );

$needs_creds         = empty( $token );

$eligebtcurl = 'http://eligebtc.betechnology.es';

$protocol = strtolower(substr($_SERVER["SERVER_PROTOCOL"],0,strpos( $_SERVER["SERVER_PROTOCOL"],'/'))).'://';

$shop_name = $_SERVER['SERVER_NAME'];

if ( $needs_creds ) {
	//http://eligebtc.betechnology.es/#/register?hostname=defloresyfloreros.betechnology.es&shop_type=woocommerce&shopname=test
	$api_creds_text = '<a href="' .$eligebtcurl .'/#/register?hostname=' .$protocol .$_SERVER['HTTP_HOST'] .'&shop_type=woocommerce&shopname=' .$shop_name .'" class="button button-primary">' . __( 'Registrate y crea una cuenta en EligeBTC', 'woocommerce-gateway-eligebtc' ) . '</a>';
} else {
	$reset_link = add_query_arg(
		array(
			'reset_EBEC_api_credentials' => 'true',
			'environment'                => 'live',
			'reset_nonce'                => wp_create_nonce( 'reset_EBEC_api_credentials' ),
		),
		wc_gateway_ebec()->get_admin_setting_link()
	);

	$api_creds_text = sprintf( __( 'Para borrar las credenciales y entrar con otra cuenta hacer <a href="%1$s" title="%2$s">click aqui</a>.', 'woocommerce-gateway-eligebtc' ), $reset_link, __( 'Borrar credenciales actuales', 'woocommerce-gateway-eligebtc' ) );
	$dash_text = '<a target="_blank" href="http://dashboard.betechnology.es" class="button button-primary">' . __( 'Entra en el Dashboard', 'woocommerce-gateway-eligebtc' ) . '</a>';
}

wc_enqueue_js( "
	jQuery( function( $ ) {
		var ebec_mark_fields      = '#woocommerce_EBEC_eligebtc_title, #woocommerce_EBEC_eligebtc_description';
		var ebec_live_fields      = '#woocommerce_EBEC_eligebtc_token, #woocommerce_EBEC_eligebtc_client_id, #woocommerce_EBEC_eligebtc_email, #woocommerce_EBEC_eligebtc_color';

		var enable_toggle         = $( 'a.ebec-toggle-settings' ).length > 0;

		$( '#woocommerce_EBEC_eligebtc_environment' ).change(function(){
			$( ebec_sandbox_fields + ',' + ebec_live_fields ).closest( 'tr' ).hide();

			
				$( '#woocommerce_EBEC_eligebtc_api_credentials, #woocommerce_EBEC_eligebtc_api_credentials + p' ).show();

				if ( ! enable_toggle ) {
					$( ebec_live_fields ).closest( 'tr' ).show();
				}
	
		}).change();


		if ( enable_toggle ) {
			$( document ).on( 'click', '.ebec-toggle-settings', function( e ) {
				$( ebec_live_fields ).closest( 'tr' ).toggle( 'fast' );
				e.preventDefault();
			} );
		}

	});
" );

/**
 * Settings for EligeBTC Gateway.
 */
return array(
	'enabled' => array(
		'title'   => __( 'Enable/Disable', 'woocommerce-gateway-eligebtc' ),
		'type'    => 'checkbox',
		'label'   => __( 'Enable EligeBTC', 'woocommerce-gateway-eligebtc' ),
		'description' => __( 'Habilitar la pasarela de pago EligeBTC.', 'woocommerce-gateway-eligebtc' ),
		'desc_tip'    => true,
		'default'     => 'yes',
	),
	'description' => array(
		'title'       => __( 'Descripcion', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'text',
		'desc_tip'    => true,
		'description' => __( 'Texto que saldra al confirmar el carrito.', 'woocommerce-gateway-eligebtc' ),
		'default'     => __( 'Paga con Bitcoins. Puedes pagar a traves de tu wallet BitCoin.', 'woocommerce-gateway-eligebtc' ),
	),

	'account_settings' => array(
		'title'       => __( 'Account Settings', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'title',
		'description' => '',
	),
	'environment' => array(
		'title'       => __( 'Entorno', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'select',
		'class'       => 'wc-enhanced-select',
		'description' => __( 'Especifica el tipo de entorno a usar. En modo real o emulado.', 'woocommerce-gateway-eligebtc' ),
		'default'     => 'live',
		'desc_tip'    => true,
		'options'     => array(
			'live'    => __( 'Live', 'woocommerce-gateway-eligebtc' ),
			'sandbox' => __( 'Sandbox', 'woocommerce-gateway-eligebtc' ),
		),
	),

	'api_credentials' => array(
		'title'       => __( 'Credenciales de la plataforma', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'title',
		'description' => $api_creds_text,
	),
	'dashboard' => array(	
	    'title'       => __( '', 'woocommerce-gateway-eligebtc' ),	
		'type'        => 'title',
		'description' => $dash_text,
	),
	'token' => array( 
		'title'       => __( 'Token', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'password',
		'description' => __( 'Token obtenido tras el registro.', 'woocommerce-gateway-eligebtc' ),
		'default'     => '',
		'desc_tip'    => true,
	),
	'client_id' => array(
		'title'       => __( 'Client ID', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'text',
		'description' => __( 'Client ID de la plataforma.', 'woocommerce-gateway-eligebtc' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( 'ClientID', 'woocommerce-gateway-eligebtc' ),
	),
	'email' => array(
		'title'       => __( 'Email', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'text',
		'description' => __( 'Email proporcionado a la plataforma.', 'woocommerce-gateway-eligebtc' ),
		'default'     => '',
	),
	'color' => array(
		'title'       => __( 'Color', 'woocommerce-gateway-eligebtc' ),
		'type'        => 'text',
		'description' => __( 'Color que saldra en la ventana de pago' ),
		'default'     => '',
		'desc_tip'    => true,
		'placeholder' => __( '#', 'woocommerce-gateway-eligebtc' ),
	)
);
