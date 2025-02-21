<?php

/* Aqui se compone la URL de redireccion en la funcion get_eligebtc_redirect_url */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles settings retrieval from the settings API.
 */
class WC_Gateway_EBEC_Settings {

	/**
	 * Setting values from get_option.
	 *
	 * @var array
	 */
	protected $_settings = array();

	/**
	 * List of locales supported by EligeBTC.
	 *
	 * @var array
	 */
	protected $_supported_locales = array(
		'da_DK',
		'de_DE',
		'en_AU',
		'en_GB',
		'en_US',
		'es_ES',
		'fr_CA',
		'fr_FR',
		'he_IL',
		'id_ID',
		'it_IT',
		'ja_JP',
		'nl_NL',
		'no_NO',
		'pl_PL',
		'pt_BR',
		'pt_PT',
		'ru_RU',
		'sv_SE',
		'th_TH',
		'tr_TR',
		'zh_CN',
		'zh_HK',
		'zh_TW',
	);

	/**
	 * Flag to indicate setting has been loaded from DB.
	 *
	 * @var bool
	 */
	private $_is_setting_loaded = false;

	public function __set( $key, $value ) {
		if ( array_key_exists( $key, $this->_settings ) ) {
			$this->_settings[ $key ] = $value;
		}
	}

	public function __get( $key ) {
		if ( array_key_exists( $key, $this->_settings ) ) {
			return $this->_settings[ $key ];
		}
		return null;
	}

	public function __isset( $name ) {
		return array_key_exists( $key, $this->_settings );
	}

	public function __construct() {
		$this->load();
	}

	/**
	 * Load settings from DB.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $force_reload Force reload settings
	 *
	 * @return WC_Gateway_EBEC_Settings Instance of WC_Gateway_EBEC_Settings
	 */
	public function load( $force_reload = false ) {
		if ( $this->_is_setting_loaded && ! $force_reload ) {
			return $this;
		}
		$this->_settings          = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
		$this->_is_setting_loaded = true;
		return $this;
	}

	/**
	 * Load settings from DB.
	 *
	 * @deprecated
	 */
	public function load_settings( $force_reload = false ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_EBEC_Settings::load' );
		return $this->load( $force_reload );
	}

	/**
	 * Save current settings.
	 *
	 * @since 1.2.0
	 */
	public function save() {
		update_option( 'woocommerce_EBEC_eligebtc_settings', $this->_settings );
	}

	/**
	 * Get API credentials for live envionment.
	 *
	 * @return WC_Gateway_EBEC_Client_Credential_Signature|WC_Gateway_EBEC_Client_Credential_Certificate
	 */
	public function get_live_api_credentials() {
		if ( $this->api_signature ) {
			return new WC_Gateway_EBEC_Client_Credential_Signature( $this->token, $this->api_signature, $this->api_subject );
		}

		return new WC_Gateway_EBEC_Client_Credential_Certificate( $this->token, $this->api_certificate, $this->api_subject );

	}



	/**
	 * Get API credentials for the current envionment.
	 *
	 * @return object
	 */
	public function get_active_api_credentials() {
		return 'live' === $this->get_environment() ? $this->get_live_api_credentials() : $this->get_sandbox_api_credentials();
	}

	/**
	 * Get EligeBTC redirect URL.
	 *
	 * @param string $token  Token
	 * @param bool   $commit If set to true, 'useraction' parameter will be set
	 *                       to 'commit' which makes EligeBTC sets the button text
	 *                       to **Pay Now** ont the EligeBTC _Review your information_
	 *                       page.
	 *
	 * @return string EligeBTC redirect URL
	 */
	public function get_eligebtc_redirect_url( $cartid, $amount ) {
		
		$settings_array = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );		
		$token=$settings_array['token'];
		$client_id=$settings_array['client_id'];
		$environment=$settings_array['environment'];
		if ($environment == 'live') $environment = '0';
		$color=str_replace('#','', $settings_array['color']);
		//$amount = L_PAYMENTREQUEST_0_AMT0

		$params_url= '&clientid=' .$client_id .'&amounteur=' .$amount .'&hostname=' .$_SERVER['SERVER_NAME'] .':' .$_SERVER['SERVER_PORT'] .'&shop_type=woocommerce&emul=' .$environment .'&color=' .$color;


		$url = 'http://';

		if ( 'live' !== $this->environment ) {
			$url .= 'sandbox.';
		}

		$url .= 'eligebtc.betechnology.es/#/payment_full?cartid=' . urlencode( $cartid ) .$params_url .urlencode($params);



		return $url;
	}

	public function get_set_express_checkout_shortcut_params( $buckets = 1 ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_EBEC_Client::get_set_express_checkout_params' );

		return wc_gateway_ebec()->client->get_set_express_checkout_params( array( 'start_from' => 'cart' ) );
	}

	public function get_set_express_checkout_mark_params( $buckets = 1 ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_EBEC_Client::get_set_express_checkout_params' );

		// Still missing order_id in args.
		return wc_gateway_ebec()->client->get_set_express_checkout_params( array(
			'start_from' => 'checkout',
		) );
	}

	/**
	 * Get base parameters, based on settings instance, for DoExpressCheckoutCheckout NVP call.
	 *
	 * @see https://developer.eligebtc.com/docs/classic/api/merchant/DoExpressCheckoutPayment_API_Operation_NVP/
	 *
	 * @param WC_Order  $order   Order object
	 * @param int|array $buckets Number of buckets or list of bucket
	 *
	 * @return array DoExpressCheckoutPayment parameters
	 */
	public function get_do_express_checkout_params( WC_Order $order, $buckets = 1 ) {
		$params = array();
		if ( ! is_array( $buckets ) ) {
			$num_buckets = $buckets;
			$buckets = array();
			for ( $i = 0; $i < $num_buckets; $i++ ) {
				$buckets[] = $i;
			}
		}

		foreach ( $buckets as $bucket_num ) {
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_NOTIFYURL' ]     = WC()->api_request_url( 'WC_Gateway_EBEC' );
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_PAYMENTACTION' ] = $this->get_paymentaction();
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_INVNUM' ]        = $this->invoice_prefix . $order->get_order_number();
			$params[ 'PAYMENTREQUEST_' . $bucket_num . '_CUSTOM' ]        = json_encode( array( 'order_id' => $order->id, 'order_key' => $order->order_key ) );
		}

		return $params;
	}

	/**
	 * Is PPEC enabled.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled;
	}

	/**
	 * Is logging enabled.
	 *
	 * @return bool
	 */
	public function is_logging_enabled() {
		return 'yes' === $this->debug;
	}

	/**
	 * Get payment action from setting.
	 *
	 * @return string
	 */
	public function get_paymentaction() {
		return 'authorization' === $this->paymentaction ? 'authorization' : 'sale';
	}

	/**
	 * Get active environment from setting.
	 *
	 * @return string
	 */
	public function get_environment() {
		return 'sandbox' === $this->environment ? 'sandbox' : 'live';
	}

	/**
	 * Subtotal mismatches.
	 *
	 * @return string
	 */
	public function get_subtotal_mismatch_behavior() {
		return 'drop' === $this->subtotal_mismatch_behavior ? 'drop' : 'add';
	}

	/**
	 * Get session length.
	 *
	 * @todo Map this to a merchant-configurable setting
	 *
	 * @return int
	 */
	public function get_token_session_length() {
		return 10800; // 3h
	}

	/**
	 * Whether currency has decimal restriction for PPCE to functions?
	 *
	 * @return bool True if it has restriction otherwise false
	 */
	public function currency_has_decimal_restriction() {
		return (
			'yes' === $this->enabled
			&&
			in_array( get_woocommerce_currency(), array( 'HUF', 'TWD', 'JPY' ) )
			&&
			0 !== absint( get_option( 'woocommerce_price_num_decimals', 2 ) )
		);
	}

	/**
	 * Get locale for EligeBTC.
	 *
	 * @return string
	 */
	public function get_eligebtc_locale() {
		$locale = get_locale();
		if ( ! in_array( $locale, $this->_supported_locales ) ) {
			$locale = 'en_US';
		}
		return $locale;
	}




	/**
	 * Checks if currency in setting supports 0 decimal places.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if currency supports 0 decimal places
	 */
	public function is_currency_supports_zero_decimal() {
		return in_array( get_woocommerce_currency(), array( 'HUF', 'JPY', 'TWD' ) );
	}

	/**
	 * Get number of digits after the decimal point.
	 *
	 * @since 1.2.0
	 *
	 * @return int Number of digits after the decimal point. Either 2 or 0
	 */
	public function get_number_of_decimal_digits() {
		return $this->is_currency_supports_zero_decimal() ? 0 : 2;
	}
}
