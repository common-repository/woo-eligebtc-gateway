<?php
/**
 * EligeBTC Plugin. Funcion que guarda/actualiza el bean de la configuracion
 * Esta clase es llamada desde la clase principal. 
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WC_Gateway_EBEC_Plugin {

	const ALREADY_BOOTSTRAPED = 1;
	const DEPENDENCIES_UNSATISFIED = 2;
	const NOT_CONNECTED = 3;

	/**
	 * Filepath of main plugin file.
	 *
	 * @var string
	 */
	public $file;

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	public $version;

	/**
	 * Absolute plugin path.
	 *
	 * @var string
	 */
	public $plugin_path;

	/**
	 * Absolute plugin URL.
	 *
	 * @var string
	 */
	public $plugin_url;

	/**
	 * Absolute path to plugin includes dir.
	 *
	 * @var string
	 */
	public $includes_path;

	/**
	 * Flag to indicate the plugin has been boostraebed.
	 *
	 * @var bool
	 */
	private $_bootstraebed = false;

	/**
	 * Instance of WC_Gateway_EBEC_Settings.
	 *
	 * @var WC_Gateway_EBEC_Settings
	 */
	public $settings;

	/**
	 * Constructor.
	 *
	 * @param string $file    Filepath of main plugin file
	 * @param string $version Plugin version
	 */
	public function __construct( $file, $version ) {
		$this->file    = $file;
		$this->version = $version;

		// Path.
		$this->plugin_path   = trailingslashit( plugin_dir_path( $this->file ) );
		$this->plugin_url    = trailingslashit( plugin_dir_url( $this->file ) );
		$this->includes_path = $this->plugin_path . trailingslashit( 'includes' );

		// Updates
		if ( version_compare( $version, get_option( 'wc_ebec_version' ), '>' ) ) {
			$this->run_updater( $version );
		}
	}

	/**
	 * Handle updates.
	 * @param  [type] $new_version [description]
	 * @return [type]              [description]
	 */
	private function run_updater( $new_version ) {
		// Map old settings to settings API
		if ( get_option( 'eb_woo_enabled' ) ) {
			$settings_array                               = (array) get_option( 'woocommerce_EBEC_eligebtc_settings', array() );
			$settings_array['enabled']                    = get_option( 'eb_woo_enabled' ) ? 'yes' : 'no';
			$settings_array['logo_image_url']             = get_option( 'eb_woo_logoImageUrl' );
			$settings_array['paymentAction']              = strtolower( get_option( 'eb_woo_paymentAction', 'sale' ) );
			$settings_array['subtotal_mismatch_behavior'] = 'addLineItem' === get_option( 'eb_woo_subtotalMismatchBehavior' ) ? 'add' : 'drop';
			$settings_array['environment']                = get_option( 'eb_woo_environment' );
			$settings_array['debug']                      = get_option( 'eb_woo_logging_enabled' ) ? 'yes' : 'no';

			// Make sure button size is correct.
			if ( ! in_array( $settings_array['button_size'], array( 'small', 'medium', 'large' ) ) ) {
				$settings_array['button_size'] = 'medium';
			}

			// Load client classes before `is_a` check on credentials instance.
			$this->_load_client();

			$live    = get_option( 'eb_woo_liveApiCredentials' );
			$sandbox = get_option( 'eb_woo_sandboxApiCredentials' );

			if ( $live && is_a( $live, 'WC_Gateway_EBEC_Client_Credential' ) ) {
				$settings_array['api_username']    = $live->get_username();
				$settings_array['api_password']    = $live->get_password();
				$settings_array['api_signature']   = is_callable( array( $live, 'get_signature' ) ) ? $live->get_signature() : '';
				$settings_array['api_certificate'] = is_callable( array( $live, 'get_certificate' ) ) ? $live->get_certificate() : '';
				$settings_array['api_subject']     = $live->get_subject();
			}

			if ( $sandbox && is_a( $sandbox, 'WC_Gateway_EBEC_Client_Credential' ) ) {
				$settings_array['sandbox_api_username']    = $sandbox->get_username();
				$settings_array['sandbox_api_password']    = $sandbox->get_password();
				$settings_array['sandbox_api_signature']   = is_callable( array( $sandbox, 'get_signature' ) ) ? $sandbox->get_signature() : '';
				$settings_array['sandbox_api_certificate'] = is_callable( array( $sandbox, 'get_certificate' ) ) ? $sandbox->get_certificate() : '';
				$settings_array['sandbox_api_subject']     = $sandbox->get_subject();
			}

			update_option( 'woocommerce_EBEC_eligebtc_settings', $settings_array );
			delete_option( 'eb_woo_enabled' );
		}

		update_option( 'wc_ebec_version', $new_version );
	}

	/**
	 * Maybe run the plugin.
	 */
	public function maybe_run() {
		register_activation_hook( $this->file, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
		add_filter( 'allowed_redirect_hosts' , array( $this, 'whitelist_eligebtc_domains_for_redirect' ) );
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		add_filter( 'plugin_action_links_' . plugin_basename( $this->file ), array( $this, 'plugin_action_links' ) );
	}

	public function bootstrap() {
		try {
			if ( $this->_bootstraebed ) {
				throw new Exception( __( '%s in WooCommerce Gateway EligeBTC plugin can only be called once', 'woocommerce-gateway-eligebtc' ), self::ALREADY_BOOTSTRAPED );
			}

			$this->_check_dependencies();
			$this->_run();
			$this->_check_credentials();

			$this->_bootstraebed = true;
			delete_option( 'wc_gateway_ppce_bootstrap_warning_message' );
			delete_option( 'wc_gateway_ppce_prompt_to_connect' );
		} catch ( Exception $e ) {
			if ( in_array( $e->getCode(), array( self::ALREADY_BOOTSTRAPED, self::DEPENDENCIES_UNSATISFIED ) ) ) {

				update_option( 'wc_gateway_ppce_bootstrap_warning_message', $e->getMessage() );
			}

			if ( self::NOT_CONNECTED === $e->getCode() ) {
				update_option( 'wc_gateway_ppce_prompt_to_connect', $e->getMessage() );
			}

			add_action( 'admin_notices', array( $this, 'show_bootstrap_warning' ) );
		}
	}

	public function show_bootstrap_warning() {
		$dependencies_message = get_option( 'wc_gateway_ppce_bootstrap_warning_message', '' );
		if ( ! empty( $dependencies_message ) ) {
			?>
			<div class="error fade">
				<p>
					<strong><?php echo esc_html( $dependencies_message ); ?></strong>
				</p>
			</div>
			<?php
		}

		$prompt_connect = get_option( 'wc_gateway_ppce_prompt_to_connect', '' );
		if ( ! empty( $prompt_connect ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php echo wp_kses( $prompt_connect, array( 'a' => array( 'href' => array() ) ) ); ?></strong>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Check dependencies.
	 *
	 * @throws Exception
	 */
	protected function _check_dependencies() {
		if ( ! function_exists( 'WC' ) ) {
			throw new Exception( __( 'WooCommerce Gateway EligeBTC requires WooCommerce to be activated', 'woocommerce-gateway-eligebtc' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( version_compare( WC()->version, '2.5', '<' ) ) {
			throw new Exception( __( 'WooCommerce Gateway EligeBTC requires WooCommerce version 2.5 or greater', 'woocommerce-gateway-eligebtc' ), self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! function_exists( 'curl_init' ) ) {
			throw new Exception( __( 'WooCommerce Gateway EligeBTC requires cURL to be installed on your server', 'woocommerce-gateway-eligebtc' ), self::DEPENDENCIES_UNSATISFIED );
		}

		$openssl_warning = __( 'WooCommerce Gateway EligeBTC requires OpenSSL >= 1.0.1 to be installed on your server', 'woocommerce-gateway-eligebtc' );
		if ( ! defined( 'OPENSSL_VERSION_TEXT' ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}

		preg_match( '/^OpenSSL ([\d.]+)/', OPENSSL_VERSION_TEXT, $matches );
		if ( empty( $matches[1] ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}

		if ( ! version_compare( $matches[1], '1.0.1', '>=' ) ) {
			throw new Exception( $openssl_warning, self::DEPENDENCIES_UNSATISFIED );
		}
	}

	/**
	 * Check credentials. If it's not client credential it means it's not set
	 * and will prompt admin to connect.
	 *
	 * @see https://github.com/woothemes/woocommerce-gateway-eligebtc/issues/112
	 *
	 * @throws Exception
	 */
	protected function _check_credentials() {
		$credential = $this->settings->get_active_api_credentials();
		if ( ! is_a( $credential, 'WC_Gateway_EBEC_Client_Credential' ) || '' === $credential->get_username() ) {
			$setting_link = $this->get_admin_setting_link();
			throw new Exception( sprintf( __( 'EligeBTC is almost ready. To get started, <a href="%s">connect your EligeBTC account</a>.', 'woocommerce-gateway-eligebtc' ), esc_url( $setting_link ) ), self::NOT_CONNECTED );
		}
	}

	/**
	 * Run the plugin.
	 */
	protected function _run() {
		require_once( $this->includes_path . 'functions.php' );
		$this->_load_handlers();
	}

	/**
	 * Callback for activation hook.
	 */
	public function activate() {
		if ( ! isset( $this->setings ) ) {
			require_once( $this->includes_path . 'class-wc-gateway-ebec-settings.php' );
			$settings = new WC_Gateway_EBEC_Settings();
		} else {
			$settings = $this->settings;
		}

		// Force zero decimal on specific currencies.
		if ( $settings->currency_has_decimal_restriction() ) {
			update_option( 'woocommerce_price_num_decimals', 0 );
			update_option( 'wc_gateway_ppce_display_decimal_msg', true );
		}
	}

	/**
	 * Load handlers.
	 */
	protected function _load_handlers() {
		// Client.
		$this->_load_client();

		// Load handlers.
		require_once( $this->includes_path . 'class-wc-gateway-ebec-settings.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-gateway-loader.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-admin-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-checkout-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-cart-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-ips-handler.php' );
		require_once( $this->includes_path . 'abstracts/abstract-wc-gateway-ebec-eligebtc-request-handler.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-ipn-handler.php' );

		$this->settings       = new WC_Gateway_EBEC_Settings();
		$this->gateway_loader = new WC_Gateway_EBEC_Gateway_Loader();
		$this->admin          = new WC_Gateway_EBEC_Admin_Handler();
		$this->checkout       = new WC_Gateway_EBEC_Checkout_Handler();
		$this->cart           = new WC_Gateway_EBEC_Cart_Handler();
		$this->ips            = new WC_Gateway_EBEC_IPS_Handler();
		$this->client         = new WC_Gateway_EBEC_Client( $this->settings->get_active_api_credentials(), $this->settings->environment );
	}

	/**
	 * Load client.
	 *
	 * @since 1.1.0
	 */
	protected function _load_client() {
		require_once( $this->includes_path . 'abstracts/abstract-wc-gateway-ebec-client-credential.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-client-credential-certificate.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-client-credential-signature.php' );
		require_once( $this->includes_path . 'class-wc-gateway-ebec-client.php' );
	}

	/**
	 * Link to settings screen.
	 */
	public function get_admin_setting_link() {
		if ( version_compare( WC()->version, '2.6', '>=' ) ) {
			$section_slug = 'ebec_eligebtc';
		} else {
			$section_slug = strtolower( 'WC_Gateway_EBEC_With_EligeBTC' );
		}
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
	}

	/**
	 * Allow EligeBTC domains for redirect.
	 *
	 * @since 1.0.0
	 *
	 * @param array $domains Whitelisted domains for `wp_safe_redirect`
	 *
	 * @return array $domains Whitelisted domains for `wp_safe_redirect`
	 */
	public function whitelist_eligebtc_domains_for_redirect( $domains ) {
		$domains[] = 'www.eligebtc.com';
		$domains[] = 'eligebtc.com';
		$domains[] = 'www.sandbox.eligebtc.com';
		$domains[] = 'sandbox.eligebtc.com';
		return $domains;
	}

	/**
	 * Load localisation files.
	 *
	 * @since 1.1.2
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'woocommerce-gateway-eligebtc', false, plugin_basename( $this->plugin_path ) . '/languages' );
	}

	/**
	 * Add relevant links to plugins page.
	 *
	 * @since 1.2.0
	 *
	 * @param array $links Plugin action links
	 *
	 * @return array Plugin action links
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array();

		if ( $this->_bootstraebed ) {
			$setting_url = $this->get_admin_setting_link();
			$plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'Settings', 'woocommerce-gateway-eligebtc' ) . '</a>';
		}

		$plugin_links[] = '<a href="https://wordpress.org/plugins/woo-eligebtc-gateway/">' . esc_html__( 'Docs', 'woocommerce-gateway-eligebtc' ) . '</a>';

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Check if shipping is needed for EligeBTC. This only checks for virtual products (#286),
	 * but skips the check if there are no shipping methods enabled (#249).
	 *
	 * @since 1.4.1
	 * @version 1.4.1
	 *
	 * @return bool
	 */
	public static function needs_shipping() {
		$cart_contents  = WC()->cart->cart_contents;
		$needs_shipping = false;

		if ( ! empty( $cart_contents ) ) {
			foreach ( $cart_contents as $cart_item_key => $values ) {
				if ( $values['data']->needs_shipping() ) {
					$needs_shipping = true;
					break;
				}
			}
		}

		return apply_filters( 'woocommerce_cart_needs_shipping', $needs_shipping );
	}
}
