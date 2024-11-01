<?php
/**
 * Cart handler.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_EBEC_Cart_Handler handles button display in the cart.
 */
class WC_Gateway_EBEC_Cart_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! wc_gateway_ebec()->settings->is_enabled() ) {
			return;
		}

		add_action( 'woocommerce_before_cart_totals', array( $this, 'before_cart_totals' ) );
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'display_mini_eligebtc_button' ), 20 );
		add_action( 'woocommerce_proceed_to_checkout', array( $this, 'display_eligebtc_button' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		if ( 'yes' === wc_gateway_ebec()->settings->checkout_on_single_product_enabled ) {
			add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'display_eligebtc_button_product' ), 1 );
			add_action( 'wc_ajax_wc_ebec_generate_cart', array( $this, 'wc_ajax_generate_cart' ) );
		}

		add_action( 'wc_ajax_wc_ebec_update_shipping_costs', array( $this, 'wc_ajax_update_shipping_costs' ) );
	}

	/**
	 * Start checkout handler when cart is loaded.
	 */
	public function before_cart_totals() {
		// If there then call start_checkout() else do nothing so page loads as normal.
		if ( ! empty( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			// Trying to prevent auto running checkout when back button is pressed from EligeBTC page.
			$_GET['startcheckout'] = 'false';
			woo_eb_start_checkout();
		}
	}

	/**
	 * Generates the cart for express checkout on a product level.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_generate_cart() {
		global $post;

		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ebec_generate_cart_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-eligebtc' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		/**
		 * If this page is single product page, we need to simulate
		 * adding the product to the cart taken account if it is a
		 * simple or variable product.
		 */
		if ( is_product() ) {
			$product = wc_get_product( $post->ID );
			$qty     = ! isset( $_POST['qty'] ) ? 1 : absint( $_POST['qty'] );

			if ( $product->is_type( 'variable' ) ) {
				$attributes = array_map( 'wc_clean', $_POST['attributes'] );

				if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
					$variation_id = $product->get_matching_variation( $attributes );
				} else {
					$data_store = WC_Data_Store::load( 'product' );
					$variation_id = $data_store->find_matching_product_variation( $product, $attributes );
				}

				WC()->cart->add_to_cart( $product->get_id(), $qty, $variation_id, $attributes );
			} elseif ( $product->is_type( 'simple' ) ) {
				WC()->cart->add_to_cart( $product->get_id(), $qty );
			}

			WC()->cart->calculate_totals();
		}

		wp_send_json( new stdClass() );
	}

	/**
	 * Update shipping costs. Trigger this update before checking out to have total costs up to date.
	 *
	 * @since 1.4.0
	 */
	public function wc_ajax_update_shipping_costs() {
		if ( ! wp_verify_nonce( $_POST['nonce'], '_wc_ebec_update_shipping_costs_nonce' ) ) {
			wp_die( __( 'Cheatin&#8217; huh?', 'woocommerce-gateway-eligebtc' ) );
		}

		if ( ! defined( 'WOOCOMMERCE_CART' ) ) {
			define( 'WOOCOMMERCE_CART', true );
		}

		WC()->shipping->reset_shipping();

		WC()->cart->calculate_totals();

		wp_send_json( new stdClass() );
	}

	/**
	 * Display eligebtc button on the product page.
	 *
	 * @since 1.4.0
	 */
	public function display_eligebtc_button_product() {
		$gateways = WC()->payment_gateways->get_available_payment_gateways();

		if ( ! is_product() || ! isset( $gateways['ebec_eligebtc'] ) ) {
			return;
		}

		$settings = wc_gateway_ebec()->settings;

		$express_checkout_img_url = apply_filters( 'woocommerce_eligebtc_express_checkout_button_img_url', sprintf( 'https://www.eligebtcobjects.com/webstatic/en_US/i/buttons/checkout-logo-%s.png', $settings->button_size ) );

		?>
		<div class="wcebec-checkout-buttons woo_eb_cart_buttons_div">

			<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_eb_ec_button_product" class="wcebec-checkout-buttons__button">
				<img src="<?php echo esc_url( $express_checkout_img_url ); ?>" alt="<?php _e( 'Check out with EligeBTC', 'woocommerce-gateway-eligebtc' ); ?>" style="width: auto; height: auto;">
			</a>
		</div>
		<?php
	}

	/**
	 * Display eligebtc button on the cart page.
	 */
	public function display_eligebtc_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ebec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ebec_eligebtc'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}

		$express_checkout_img_url = apply_filters( 'woocommerce_eligebtc_express_checkout_button_img_url', sprintf( 'https://www.eligebtcobjects.com/webstatic/en_US/i/buttons/checkout-logo-%s.png', $settings->button_size ) );
		$eligebtc_credit_img_url    = apply_filters( 'woocommerce_eligebtc_express_checkout_credit_button_img_url', sprintf( 'https://www.eligebtcobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-%s.png', $settings->button_size ) );
		?>
		<div class="wcebec-checkout-buttons woo_eb_cart_buttons_div">

			<?php if ( has_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout' ) ) : ?>
				<div class="wcebec-checkout-buttons__separator">
					<?php _e( '&mdash; or &mdash;', 'woocommerce-gateway-eligebtc' ); ?>
				</div>
			<?php endif; ?>

			<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_eb_ec_button" class="wcebec-checkout-buttons__button">
				<img src="<?php echo esc_url( $express_checkout_img_url ); ?>" alt="<?php _e( 'Check out with EligeBTC', 'woocommerce-gateway-eligebtc' ); ?>" style="width: auto; height: auto;">
			</a>

			<?php if ( $settings->is_credit_enabled() ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true', 'use-ppc' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_eb_ppc_button" class="wcebec-checkout-buttons__button">
				<img src="<?php echo esc_url( $eligebtc_credit_img_url ); ?>" alt="<?php _e( 'Pay with EligeBTC Credit', 'woocommerce-gateway-eligebtc' ); ?>" style="width: auto; height: auto;">
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Display eligebtc button on the cart widget
	 */
	public function display_mini_eligebtc_button() {

		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$settings = wc_gateway_ebec()->settings;

		// billing details on checkout page to calculate shipping costs
		if ( ! isset( $gateways['ebec_eligebtc'] ) || 'no' === $settings->cart_checkout_enabled ) {
			return;
		}
		?>
		<a href="<?php echo esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ); ?>" id="woo_eb_ec_button" class="wcebec-cart-widget-button">
			<img src="<?php echo esc_url( 'https://www.eligebtcobjects.com/webstatic/en_US/i/btn/png/gold-rect-eligebtccheckout-26px.png' ); ?>" alt="<?php _e( 'Check out with EligeBTC', 'woocommerce-gateway-eligebtc' ); ?>" style="width: auto; height: auto;">
		</a>
		<?php
	}

	/**
	 * Frontend scripts
	 */
	public function enqueue_scripts() {
		$settings = wc_gateway_ebec()->settings;
		$client   = wc_gateway_ebec()->client;

		if ( ! $client->get_payer_id() ) {
			return;
		}

		wp_enqueue_style( 'wc-gateway-ebec-frontend-cart', wc_gateway_ebec()->plugin_url . 'assets/css/wc-gateway-ebec-frontend-cart.css' );

		if ( is_cart() ) {
			wp_enqueue_script( 'eligebtc-checkout-js', 'https://www.eligebtcobjects.com/api/checkout.js', array(), null, true );
			wp_enqueue_script( 'wc-gateway-ebec-frontend-in-context-checkout', wc_gateway_ebec()->plugin_url . 'assets/js/wc-gateway-ebec-frontend-in-context-checkout.js', array( 'jquery' ), wc_gateway_ebec()->version, true );
			wp_localize_script( 'wc-gateway-ebec-frontend-in-context-checkout', 'wc_ebec_context',
				array(
					'payer_id'    => $client->get_payer_id(),
					'environment' => $settings->get_environment(),
					'locale'      => $settings->get_eligebtc_locale(),
					'start_flow'  => esc_url( add_query_arg( array( 'startcheckout' => 'true' ), wc_get_page_permalink( 'cart' ) ) ),
					'show_modal'  => apply_filters( 'woocommerce_eligebtc_express_checkout_show_cart_modal', true ),
					'update_shipping_costs_nonce' => wp_create_nonce( '_wc_ebec_update_shipping_costs_nonce' ),
					'ajaxurl'     => WC_AJAX::get_endpoint( 'wc_ebec_update_shipping_costs' ),
				)
			);
		}

		if ( is_product() ) {
			wp_enqueue_script( 'wc-gateway-ebec-generate-cart', wc_gateway_ebec()->plugin_url . 'assets/js/wc-gateway-ebec-generate-cart.js', array( 'jquery' ), wc_gateway_ebec()->version, true );
			wp_localize_script( 'wc-gateway-ebec-generate-cart', 'wc_ebec_context',
				array(
					'generate_cart_nonce' => wp_create_nonce( '_wc_ebec_generate_cart_nonce' ),
					'ajaxurl'             => WC_AJAX::get_endpoint( 'wc_ebec_generate_cart' ),
				)
			);
		}
	}

	/**
	 * @deprecated
	 */
	public function loadCartDetails() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function loadOrderDetails( $order_id ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function setECParams() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}
}
