<?php
/**
 * Aqui se lanza la URL de redireccion en la funcion start_checkout_from_checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$includes_path = wc_gateway_ebec()->includes_path;

// TODO: Use spl autoload to require on-demand maybe?

require_once( $includes_path . 'class-wc-gateway-ebec-settings.php' );
require_once( $includes_path . 'class-wc-gateway-ebec-session-data.php' );
require_once( $includes_path . 'class-wc-gateway-ebec-checkout-details.php' );

require_once( $includes_path . 'class-wc-gateway-ebec-api-error.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ebec-api-exception.php' );
require_once( $includes_path . 'exceptions/class-wc-gateway-ebec-missing-session-exception.php' );

require_once( $includes_path . 'class-wc-gateway-ebec-payment-details.php' );
require_once( $includes_path . 'class-wc-gateway-ebec-address.php' );

class WC_Gateway_EBEC_Checkout_Handler {

	/**
	 * Cached result from self::get_checkout_defails.
	 *
	 * @since 1.2.0
	 *
	 * @var EligeBTC_Checkout_Details
	 */
	protected $_checkout_details;

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		add_action( 'woocommerce_checkout_init', array( $this, 'checkout_init' ) );
		add_filter( 'woocommerce_default_address_fields', array( $this, 'filter_default_address_fields' ) );
		add_filter( 'woocommerce_billing_fields', array( $this, 'filter_billing_fields' ) );
		add_action( 'woocommerce_checkout_process', array( $this, 'copy_checkout_details_to_post' ) );

		add_action( 'wp', array( $this, 'maybe_return_from_eligebtc' ) );
		add_action( 'wp', array( $this, 'maybe_cancel_checkout_with_eligebtc' ) );
		add_action( 'woocommerce_cart_emptied', array( $this, 'maybe_clear_session_data' ) );

		add_action( 'woocommerce_available_payment_gateways', array( $this, 'maybe_disable_other_gateways' ) );
		add_action( 'woocommerce_review_order_after_submit', array( $this, 'maybe_render_cancel_link' ) );

		add_action( 'woocommerce_cart_shipping_packages', array( $this, 'maybe_add_shipping_information' ) );
	}

	/**
	 * If the buyer clicked on the "Check Out with EligeBTC" button, we need to wait for the cart
	 * totals to be available.  Unfortunately that doesn't haeben until
	 * woocommerce_before_cart_totals executes, and there is already output sent to the browser by
	 * this point.  So, to get around this issue, we'll enable output buffering to prevent WP from
	 * sending anything back to the browser.
	 */
	public function init() {
		if ( isset( $_GET['startcheckout'] ) && 'true' === $_GET['startcheckout'] ) {
			ob_start();
		}
	}

	/**
	 * Handle endpoint page title
	 * @param  string $title
	 * @return string
	 */
	public function endpoint_page_titles( $title ) {
		if ( ! is_admin() && is_main_query() && in_the_loop() && is_page() && is_checkout() && $this->has_active_session() ) {
			$title = __( 'Confirm your EligeBTC order', 'woocommerce-gateway-eligebtc' );
			remove_filter( 'the_title', array( $this, 'endpoint_page_titles' ) );
		}
		return $title;
	}

	/**
	 * If there's an active EligeBTC session during checkout (e.g. if the customer started checkout
	 * with EligeBTC from the cart), import billing and shipping details from EligeBTC using the
	 * token we have for the customer.
	 *
	 * Hooked to the woocommerce_checkout_init action
	 *
	 * @param WC_Checkout $checkout
	 */
	function checkout_init( $checkout ) {
		if ( ! $this->has_active_session() ) {
			return;
		}

		// Since we've removed the billing and shipping checkout fields, we should also remove the
		// billing and shipping portion of the checkout form
		remove_action( 'woocommerce_checkout_billing', array( $checkout, 'checkout_form_billing' ) );
		remove_action( 'woocommerce_checkout_shipping', array( $checkout, 'checkout_form_shipping' ) );

		// Lastly, let's add back in 1) displaying customer details from EligeBTC, 2) allow for
		// account registration and 3) shipping details from EligeBTC
		add_action( 'woocommerce_checkout_billing', array( $this, 'eligebtc_billing_details' ) );
		add_action( 'woocommerce_checkout_billing', array( $this, 'account_registration' ) );
		add_action( 'woocommerce_checkout_shipping', array( $this, 'eligebtc_shipping_details' ) );
	}

	/**
	 * If the cart doesn't need shipping at all, don't require the address fields
	 * (this is unique to PPEC). This is one of two places we need to filter fields.
	 * See also filter_billing_fields below.
	 *
	 * @since 1.2.1
	 * @param $fields array
	 *
	 * @return array
	 */
	public function filter_default_address_fields( $fields ) {
		if ( method_exists( WC()->cart, 'needs_shipping' ) && ! WC()->cart->needs_shipping() ) {
			$not_required_fields = array( 'address_1', 'city', 'state', 'postcode', 'country' );
			foreach ( $not_required_fields as $not_required_field ) {
				if ( array_key_exists( $not_required_field, $fields ) ) {
					$fields[ $not_required_field ]['required'] = false;
				}
			}
		}

		return $fields;

	}

	/**
	 * Since EligeBTC doesn't always give us the phone number for the buyer, we need to make
	 * that field not required. Note that core WooCommerce adds the phone field after calling
	 * get_default_address_fields, so the woocommerce_default_address_fields cannot
	 * be used to make the phone field not required.
	 *
	 * This is one of two places we need to filter fields. See also filter_default_address_fields above.
	 *
	 * @since 1.2.0
	 * @version 1.2.1
	 * @param $billing_fields array
	 *
	 * @return array
	 */
	public function filter_billing_fields( $billing_fields ) {
		$require_phone_number = wc_gateway_ebec()->settings->require_phone_number;

		if ( array_key_exists( 'billing_phone', $billing_fields ) ) {
			$billing_fields['billing_phone']['required'] = 'yes' === $require_phone_number;
		};

		return $billing_fields;
	}

	/**
	 * When an active session is present, gets (from EligeBTC) the buyer details
	 * and replaces the appropriate checkout fields in $_POST
	 *
	 * Hooked to woocommerce_checkout_process
	 *
	 * @since 1.2.0
	 */
	public function copy_checkout_details_to_post() {
		if ( ! $this->has_active_session() ) {
			return;
		}

		// Make sure the selected payment method is ebec_eligebtc
		if ( ! isset( $_POST['payment_method'] ) || ( 'ebec_eligebtc' !== $_POST['payment_method'] ) ) {
			return;
		}

		// Get the buyer details from EligeBTC
		try {
			$session          = WC()->session->get( 'eligebtc' );
			$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( EligeBTC_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}

		$shipping_details = $this->get_maebed_shipping_address( $checkout_details );
		foreach( $shipping_details as $key => $value ) {
			$_POST['shipping_' . $key] = $value;
		}

		$billing_details = $this->get_maebed_billing_address( $checkout_details );
		// If the billing address is empty, copy address from shipping
		if ( empty( $billing_details['address_1'] ) ) {
			$copyable_keys = array( 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' );
			foreach ( $copyable_keys as $copyable_key ) {
				if ( array_key_exists( $copyable_key, $shipping_details ) ) {
					$billing_details[ $copyable_key ] = $shipping_details[ $copyable_key ];
				}
			}
		}
		foreach( $billing_details as $key => $value ) {
			$_POST['billing_' . $key] = $value;
		}
	}

	/**
	 * Show billing information obtained from EligeBTC. This replaces the billing fields
	 * that the customer would ordinarily fill in. Should only haeben if we have an active
	 * session (e.g. if the customer started checkout with EligeBTC from their cart.)
	 *
	 * Is hooked to woocommerce_checkout_billing action by checkout_init
	 */
	public function eligebtc_billing_details() {
		$session          = WC()->session->get( 'eligebtc' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;
		try {
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( EligeBTC_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}
		?>
		<h3><?php _e( 'Billing details', 'woocommerce-gateway-eligebtc' ); ?></h3>
		<ul>
			<?php if ( $checkout_details->payer_details->billing_address ) : ?>
				<li><strong><?php _e( 'Address:', 'woocommerce-gateway-eligebtc' ) ?></strong></br><?php echo WC()->countries->get_formatted_address( $this->get_maebed_billing_address( $checkout_details ) ); ?></li>
			<?php else : ?>
				<li><strong><?php _e( 'Name:', 'woocommerce-gateway-eligebtc' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->first_name . ' ' . $checkout_details->payer_details->last_name ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->email ) ) : ?>
				<li><strong><?php _e( 'Email:', 'woocommerce-gateway-eligebtc' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->email ); ?></li>
			<?php endif; ?>

			<?php if ( ! empty( $checkout_details->payer_details->phone_number ) ) : ?>
				<li><strong><?php _e( 'Phone:', 'woocommerce-gateway-eligebtc' ) ?></strong> <?php echo esc_html( $checkout_details->payer_details->phone_number ); ?></li>
			<?php elseif ( 'yes' === wc_gateway_ebec()->settings->require_phone_number ) : ?>
				<li><?php $fields = WC()->checkout->get_checkout_fields( 'billing' ); woocommerce_form_field( 'billing_phone', $fields['billing_phone'], WC()->checkout->get_value( 'billing_phone' ) ); ?></li>
			<?php endif; ?>
		</ul>
		<?php
	}

	/**
	 * If there is an active session (e.g. the customer initiated checkout from the cart), since we
	 * removed the checkout_form_billing action, we need to put a registration form back in to
	 * allow the customer to create an account.
	 *
	 *  Is hooked to woocommerce_checkout_billing action by checkout_init
	 * @since 1.2.0
	 */
	public function account_registration() {
		$checkout = WC()->checkout();

		if ( ! is_user_logged_in() && $checkout->enable_signup ) {

			if ( $checkout->enable_guest_checkout ) {
				?>
				<p class="form-row form-row-wide create-account">
					<input class="input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true) ?> type="checkbox" name="createaccount" value="1" /> <label for="createaccount" class="checkbox"><?php _e( 'Create an account?', '' ); ?></label>
				</p>
				<?php
			}

			if ( ! empty( $checkout->checkout_fields['account'] ) ) {
				?>
				<div class="create-account">

					<p><?php _e( 'Create an account by entering the information below. If you are a returning customer please login at the top of the page.', 'woocommerce' ); ?></p>

					<?php foreach ( $checkout->checkout_fields['account'] as $key => $field ) : ?>

						<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>

					<?php endforeach; ?>

					<div class="clear"></div>

				</div>
				<?php
			}

		}
	}

	/**
	 * Show shipping information obtained from EligeBTC. This replaces the shipping fields
	 * that the customer would ordinarily fill in. Should only haeben if we have an active
	 * session (e.g. if the customer started checkout with EligeBTC from their cart.)
	 *
	 * Is hooked to woocommerce_checkout_shipping action by checkout_init
	 */
	public function eligebtc_shipping_details() {
		$session          = WC()->session->get( 'eligebtc' );
		$token            = isset( $_GET['token'] ) ? $_GET['token'] : $session->token;

		try {
			$checkout_details = $this->get_checkout_details( $token );
		} catch ( EligeBTC_API_Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			return;
		}

		if ( ! WC_Gateway_EBEC_Plugin::needs_shipping() ) {
			return;
		}

		?>
		<h3><?php _e( 'Shipping details', 'woocommerce-gateway-eligebtc' ); ?></h3>
		<?php
		echo WC()->countries->get_formatted_address( $this->get_maebed_shipping_address( $checkout_details ) );
	}

	/**
	 * @deprecated 1.2.0
	 */
	public function after_checkout_validation( $posted_checkout ) {
		_deprecated_function( 'after_checkout_validation', '1.2.0', '' );
	}

	/**
	 * Map EligeBTC billing address to WC shipping address
	 * NOTE: Not all EligeBTC_Checkout_Payer_Details objects include a billing address
	 * @param  object $checkout_details
	 * @return array
	 */
	public function get_maebed_billing_address( $checkout_details ) {
		if ( empty( $checkout_details->payer_details ) ) {
			return array();
		}

		$phone = '';

		if ( ! empty( $checkout_details->payer_details->phone_number ) ) {
			$phone = $checkout_details->payer_details->phone_number;
		} elseif ( 'yes' === wc_gateway_ebec()->settings->require_phone_number && ! empty( $_POST['billing_phone'] ) ) {
			$phone = wc_clean( $_POST['billing_phone'] );
		}

		return array(
			'first_name' => $checkout_details->payer_details->first_name,
			'last_name'  => $checkout_details->payer_details->last_name,
			'company'    => $checkout_details->payer_details->business_name,
			'address_1'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet1() : '',
			'address_2'  => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getStreet2() : '',
			'city'       => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCity() : '',
			'state'      => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getState() : '',
			'postcode'   => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getZip() : '',
			'country'    => $checkout_details->payer_details->billing_address ? $checkout_details->payer_details->billing_address->getCountry() : $checkout_details->payer_details->country,
			'phone'      => $phone,
			'email'      => $checkout_details->payer_details->email,
		);
	}

	/**
	 * Map EligeBTC shipping address to WC shipping address.
	 *
	 * @param  object $checkout_details Checkout details
	 * @return array
	 */
	public function get_maebed_shipping_address( $checkout_details ) {
		if ( empty( $checkout_details->payments[0] ) || empty( $checkout_details->payments[0]->shipping_address ) ) {
			return array();
		}

		$name       = explode( ' ', $checkout_details->payments[0]->shipping_address->getName() );
		$first_name = array_shift( $name );
		$last_name  = implode( ' ', $name );
		return array(
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'company'       => $checkout_details->payer_details->business_name,
			'address_1'     => $checkout_details->payments[0]->shipping_address->getStreet1(),
			'address_2'     => $checkout_details->payments[0]->shipping_address->getStreet2(),
			'city'          => $checkout_details->payments[0]->shipping_address->getCity(),
			'state'         => $checkout_details->payments[0]->shipping_address->getState(),
			'postcode'      => $checkout_details->payments[0]->shipping_address->getZip(),
			'country'       => $checkout_details->payments[0]->shipping_address->getCountry(),
		);
	}

	/**
	 * Checks data is correctly set when returning from EligeBTC
	 */
	public function maybe_return_from_eligebtc() {
		if ( empty( $_GET['woo-eligebtc-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return;
		}

		$token                    = $_GET['token'];
		$payer_id                 = $_GET['PayerID'];
		$create_billing_agreement = ! empty( $_GET['create-billing-agreement'] );
		$session                  = WC()->session->get( 'eligebtc' );

		if ( empty( $session ) || $this->session_has_expired( $token ) ) {
			wc_add_notice( __( 'Your EligeBTC checkout session has expired. Please check out again.', 'woocommerce-gateway-eligebtc' ), 'error' );
			return;
		}

		// Store values in session.
		$session->checkout_completed = true;
		$session->payer_id           = $payer_id;
		WC()->session->set( 'eligebtc', $session );

		try {
			// If commit was true, take payment right now
			if ( 'order' === $session->source && $session->order_id ) {
				$checkout_details = $this->get_checkout_details( $token );

				// Get order
				$order = wc_get_order( $session->order_id );

				// Maybe create billing agreement.
				if ( $create_billing_agreement ) {
					$this->create_billing_agreement( $order, $checkout_details );
				}

				// Complete the payment now.
				$this->do_payment( $order, $session->token, $session->payer_id );

				// Clear Cart
				WC()->cart->empty_cart();

				// Redirect
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}
		} catch ( EligeBTC_API_Exception $e ) {
			wc_add_notice( __( 'Sorry, an error occurred while trying to retrieve your information from EligeBTC. Please try again.', 'woocommerce-gateway-eligebtc' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		} catch ( EligeBTC_Missing_Session_Exception $e ) {
			wc_add_notice( __( 'Your EligeBTC checkout session has expired. Please check out again.', 'woocommerce-gateway-eligebtc' ), 'error' );
			$this->maybe_clear_session_data();
			wp_safe_redirect( wc_get_page_permalink( 'cart' ) );
			exit;
		}
	}

	/**
	 * Maybe disable this or other gateways.
	 *
	 * @since 1.0.0
	 * @version 1.2.1
	 *
	 * @param array $gateways Available gateways
	 *
	 * @return array Available gateways
	 */
	public function maybe_disable_other_gateways( $gateways ) {
		// Unset all other gateways after checking out from cart.
		if ( $this->has_active_session() ) {
			foreach ( $gateways as $id => $gateway ) {
				if ( 'ebec_eligebtc' !== $id ) {
					unset( $gateways[ $id ] );
				}
			}

		// If using EligeBTC standard (this is admin choice) we don't need to also show EligeBTC EC on checkout.
		} elseif ( is_checkout() && ( isset( $gateways['eligebtc'] ) || 'no' === wc_gateway_ebec()->settings->mark_enabled ) ) {
			unset( $gateways['ebec_eligebtc'] );
		}

		// If the cart total is zero (e.g. because of a coupon), don't allow this gateway.
		// We do this only if we're on the checkout page (is_checkout), but not on the order-pay page (is_checkout_pay_page)
		if ( is_cart() || ( is_checkout() && ! is_checkout_pay_page() ) ) {
			if ( isset( $gateways['ebec_eligebtc'] ) && ( 0 >= WC()->cart->total ) ) {
				unset( $gateways['ebec_eligebtc'] );
			}
		}

		return $gateways;
	}

	/**
	 * When cart based Checkout with PPEC is in effect, we need to include
	 * a Cancel button on the checkout form to give the user a means to throw
	 * away the session provided and possibly select a different payment
	 * gateway.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_render_cancel_link() {
		if ( $this->has_active_session() ) {
			printf(
				'<a href="%s" class="wc-gateway-ebec-cancel">%s</a>',
				esc_url( add_query_arg( 'wc-gateway-ebec-clear-session', true, wc_get_cart_url() ) ),
				esc_html__( 'Cancel', 'woocommerce-gateway-eligebtc' )
			);
		}
	}

	/**
	 * Buyer cancels checkout with EligeBTC.
	 *
	 * Clears the session data and display notice.
	 */
	public function maybe_cancel_checkout_with_eligebtc() {
		if ( is_cart() && ! empty( $_GET['wc-gateway-ebec-clear-session'] ) ) {
			$this->maybe_clear_session_data();

			$notice =  __( 'You have cancelled Checkout with EligeBTC. Please try to process your order again.', 'woocommerce-gateway-eligebtc' );
			if ( ! wc_has_notice( $notice, 'notice' ) ) {
				wc_add_notice( $notice, 'notice' );
			}
		}
	}

	/**
	 * Used when cart based Checkout with EligeBTC is in effect. Hooked to woocommerce_cart_emptied
	 * Also called by WC_EligeBTC_Braintree_Loader::possibly_cancel_checkout_with_eligebtc
	 *
	 * @since 1.0.0
	 */
	public function maybe_clear_session_data() {
		if ( $this->has_active_session() ) {
			unset( WC()->session->eligebtc );
		}
	}

	/**
	 * Checks whether session with passed token has expired.
	 *
	 * @since 1.0.0
	 *
	 * @param string $token Token
	 *
	 * @return bool
	 */
	public function session_has_expired( $token ) {
		$session = WC()->session->eligebtc;
		return ( ! $session || ! is_a( $session, 'WC_Gateway_EBEC_Session_Data' ) || $session->expiry_time < time() || $token !== $session->token );
	}

	/**
	 * Checks whether there's active session from cart-based checkout with PPEC.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Returns true if PPEC session exists and still valid
	 */
	public function has_active_session() {
		if ( ! WC()->session ) {
			return false;
		}

		$session = WC()->session->eligebtc;
		return ( is_a( $session, 'WC_Gateway_EBEC_Session_Data' ) && $session->payer_id && $session->expiry_time > time() );
	}

	/**
	 * @deprecated
	 */
	public function get_token_from_session() {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function setShippingAddress( $address ) {
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getSetExpressCheckoutParameters() {
		// No replacement because WC_Gateway_EBEC_Client::get_set_express_checkout_params
		// needs context from where the buyer starts checking out.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	public function getDoExpressCheckoutParameters( $token, $payer_id ) {
		// No replacement because WC_Gateway_EBEC_Client::get_do_express_checkout_params
		// needs order_id to return properly.
		_deprecated_function( __METHOD__, '1.2.0', '' );
	}

	/**
	 * @deprecated
	 */
	protected function is_success( $response ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_EBEC_Client::response_has_success_status' );

		$client = wc_gateway_ebec()->client;
		return $client->response_has_success_status( $response );
	}

	/**
	 * Handler when buyer is checking out from cart page.
	 *
	 * @todo This methods looks similar to start_checkout_from_checkout. Please
	 *       refactor by merging them.
	 *
	 * @throws EligeBTC_API_Exception
	 */
	public function start_checkout_from_cart() {
		$settings     = wc_gateway_ebec()->settings;
		$client       = wc_gateway_ebec()->client;
		$context_args = array(
			'start_from' => 'cart',
		);
		$context_args['create_billing_agreement'] = $this->needs_billing_agreement_creation( $context_args );

		$params   = $client->get_set_express_checkout_params( $context_args );
		$response = $client->set_express_checkout( $params );
		if ( $client->response_has_success_status( $response ) ) {
			WC()->session->eligebtc = new WC_Gateway_EBEC_Session_Data(
				array(
					'token'             => $response['TOKEN'],
					'source'            => 'cart',
					'expires_in'        => $settings->get_token_session_length(),
					'use_eligebtc_credit' => wc_gateway_EBEC_is_using_credit(),
				)
			);

			return $settings->get_eligebtc_redirect_url( $response['TOKEN'], false );
		} else {
			throw new EligeBTC_API_Exception( $response );
		}
	}

	/**
	 * Handler cuando un comprador clicka sobre el metodo de pago.
	 *
	 * @todo This methods looks similar to start_checkout_from_cart. Please
	 *       refactor by merging them.
	 *
	 * @throws EligeBTC_API_Exception
	 *
	 * @param int $order_id Order ID
	 */
	public function start_checkout_from_checkout( $order_id ) {
		$settings     = wc_gateway_ebec()->settings;
		$client       = wc_gateway_ebec()->client;
		$context_args = array(
			'start_from' => 'checkout',
			'order_id'   => $order_id,
		);

		/* nuevo hasta la comentado */
		$order      = wc_get_order( $order_id );
 		$amount = $order->get_total();
		//$params   = $client->get_set_express_checkout_params( $context_args );
		//$amount = $params['L_PAYMENTREQUEST_0_AMT0'];
		return $settings->get_eligebtc_redirect_url( $order_id, $amount ); //Con esta llamada se obtiene la URL del pago. Aqui esta todo

		/*
		$params   = $client->get_set_express_checkout_params( $context_args );
		$response = $client->set_express_checkout( $params );//Esta funcion hace un curl
		if ( $client->response_has_success_status( $response ) ) {
			WC()->session->eligebtc = new WC_Gateway_EBEC_Session_Data(
				array(
					'token'      => $response['TOKEN'],
					'source'     => 'order',
					'order_id'   => $order_id,
					'expires_in' => $settings->get_token_session_length()
				)
			);

			return $settings->get_eligebtc_redirect_url( $response['TOKEN'], true );
		} else {
			throw new EligeBTC_API_Exception( $response );
		}
		*/
	}

	/**
	 * Checks whether buyer checkout from checkout page.
	 *
	 * @since 1.2.0
	 *
	 * @return bool Returns true if buyer checkout from checkout page
	 */
	public function is_started_from_checkout_page() {
		$session = WC()->session->get( 'eligebtc' );

		return (
			! $this->has_active_session()
			||
			! $session->checkout_completed
		);
	}

	/**
	 * @deprecated
	 */
	public function getCheckoutDetails( $token = false ) {
		_deprecated_function( __METHOD__, '1.2.0', 'WC_Gateway_EBEC_Checkout_Handler::get_checkout_details' );
		return $this->get_checkout_details( $token );
	}

	/**
	 * Get checkout details from token.
	 *
	 * @since 1.2.0
	 *
	 * @throws \Exception
	 *
	 * @param bool|string $token Express Checkout token
	 */
	public function get_checkout_details( $token = false ) {
		if ( is_a( $this->_checkout_details, 'EligeBTC_Checkout_Details' ) ) {
			return $this->_checkout_details;
		}

		if ( false === $token && ! empty( $_GET['token'] ) ) {
			$token = $_GET['token'];
		}

		$client   = wc_gateway_ebec()->client;
		$response = $client->get_express_checkout_details( $token );

		if ( $client->response_has_success_status( $response ) ) {
			$checkout_details = new EligeBTC_Checkout_Details();
			$checkout_details->loadFromGetECResponse( $response );

			$this->_checkout_details = $checkout_details;
			return $checkout_details;
		} else {
			throw new EligeBTC_API_Exception( $response );
		}
	}

	/**
	 * Creates billing agreement and stores the billing agreement ID in order's
	 * meta and subscriptions meta.
	 *
	 * @since 1.2.0
	 *
	 * @throws \Exception
	 *
	 * @param WC_Order                $order            Order object
	 * @param EligeBTC_Checkout_Details $checkout_details Checkout details
	 */
	public function create_billing_agreement( $order, $checkout_details ) {
		if ( 1 !== intval( $checkout_details->billing_agreement_accepted ) ) {
			throw new EligeBTC_API_Exception( $checkout_details->raw_response );
		}

		$client = wc_gateway_ebec()->client;
		$resp   = $client->create_billing_agreement( $checkout_details->token );

		if ( ! $client->response_has_success_status( $resp ) || empty( $resp['BILLINGAGREEMENTID'] ) ) {
			throw new EligeBTC_API_Exception( $resp );
		}

		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$order_id = $old_wc ? $order->id : $order->get_id();
		if ( $old_wc ) {
			update_post_meta( $order_id, '_EBEC_billing_agreement_id', $resp['BILLINGAGREEMENTID'] );
		} else {
			$order->update_meta_data( '_EBEC_billing_agreement_id', $resp['BILLINGAGREEMENTID'] );
		}

		$subscriptions = array();
		if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id );
		} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
		}

		$billing_agreement_id = $old_wc ? get_post_meta( $order_id, '_EBEC_billing_agreement_id', true ) : $order->get_meta( '_EBEC_billing_agreement_id', true );

		foreach ( $subscriptions as $subscription ) {
			update_post_meta( $subscription->id, '_EBEC_billing_agreement_id', $billing_agreement_id );
		}
	}

	/**
	 * Complete a payment that has been authorized via PPEC.
	 */
	public function do_payment( $order, $token, $payer_id ) {
		$settings     = wc_gateway_ebec()->settings;
		$session_data = WC()->session->get( 'eligebtc', null );

		if ( ! $order || null === $session_data || $this->session_has_expired( $token ) || empty( $payer_id ) ) {
			throw new EligeBTC_Missing_Session_Exception();
		}

		$client = wc_gateway_ebec()->client;
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );
		$order_id = $old_wc ? $order->id : $order->get_id();
		$params = $client->get_do_express_checkout_params( array(
			'order_id' => $order_id,
			'token'    => $token,
			'payer_id' => $payer_id,
		) );

		$response = $client->do_express_checkout_payment( $params );

		if ( $client->response_has_success_status( $response ) ) {
			$payment_details = new EligeBTC_Payment_Details();
			$payment_details->loadFromDoECResponse( $response );

			$meta = $old_wc ? get_post_meta( $order_id, '_woo_eb_txnData', true ) : $order->get_meta( '_woo_eb_txnData', true );
			if ( ! empty( $meta ) ) {
				$txnData = $meta;
			} else {
				$txnData = array( 'refundable_txns' => array() );
			}

			$paymentAction = $settings->get_paymentaction();

			$txn = array(
				'txnID'           => $payment_details->payments[0]->transaction_id,
				'amount'          => $order->get_total(),
				'refunded_amount' => 0
			);
			if ( 'Completed' == $payment_details->payments[0]->payment_status ) {
				$txn['status'] = 'Completed';
			} else {
				$txn['status'] = $payment_details->payments[0]->payment_status . '_' . $payment_details->payments[0]->pending_reason;
			}
			$txnData['refundable_txns'][] = $txn;

			if ( 'authorization' == $paymentAction ) {
				$txnData['auth_status'] = 'NotCompleted';
			}

			$txnData['txn_type'] = $paymentAction;

			if ( $old_wc ) {
				update_post_meta( $order_id, '_woo_eb_txnData', $txnData );
			} else {
				$order->update_meta_data( '_woo_eb_txnData', $txnData );
			}

			// Payment was taken so clear session
			$this->maybe_clear_session_data();

			// Handle order
			$this->handle_payment_response( $order, $payment_details->payments[0] );
		} else {
			throw new EligeBTC_API_Exception( $response );
		}
	}

	/**
	 * Handle result of do_payment
	 */
	public function handle_payment_response( $order, $payment ) {
		// Store meta data to order
		$old_wc = version_compare( WC_VERSION, '3.0', '<' );

		update_post_meta( $old_wc ? $order->id : $order->get_id(), '_eligebtc_status', strtolower( $payment->payment_status ) );
		update_post_meta( $old_wc ? $order->id : $order->get_id(), '_transaction_id', $payment->transaction_id );

		// Handle $payment response
		if ( 'completed' === strtolower( $payment->payment_status ) ) {
			$order->payment_complete( $payment->transaction_id );
		} else {
			if ( 'authorization' === $payment->pending_reason ) {
				$order->update_status( 'on-hold', __( 'Payment authorized. Change payment status to processing or complete to capture funds.', 'woocommerce-gateway-eligebtc' ) );
			} else {
				$order->update_status( 'on-hold', sprintf( __( 'Payment pending (%s).', 'woocommerce-gateway-eligebtc' ), $payment->pending_reason ) );
			}
			if ( $old_wc ) {
				if ( ! get_post_meta( $order->id, '_order_stock_reduced', true ) ) {
					$order->reduce_order_stock();
				}
			} else {
				wc_maybe_reduce_stock_levels( $order->get_id() );
			}
		}
	}

	/**
	 * This function filter the packages adding shipping information from EligeBTC on the checkout page
	 * after the user is authenticated by EligeBTC.
	 *
	 * @since 1.9.13 Introduced
	 * @param array $packages
	 *
	 * @return mixed
	 */
	public function maybe_add_shipping_information( $packages ) {
		if ( empty( $_GET['woo-eligebtc-return'] ) || empty( $_GET['token'] ) || empty( $_GET['PayerID'] ) ) {
			return $packages;
		}
		// Shipping details from EligeBTC

		try {
			$checkout_details = $this->get_checkout_details( wc_clean( $_GET['token'] ) );
		} catch ( EligeBTC_API_Exception $e ) {
			return $packages;
		}


		$destination = $this->get_maebed_shipping_address( $checkout_details );

		$packages[0]['destination']['country']   = $destination['country'];
		$packages[0]['destination']['state']     = $destination['state'];
		$packages[0]['destination']['postcode']  = $destination['postcode'];
		$packages[0]['destination']['city']      = $destination['city'];
		$packages[0]['destination']['address']   = $destination['address_1'];
		$packages[0]['destination']['address_2'] = $destination['address_2'];

		return $packages;
	}

	/**
	 * Checks whether checkout needs billing agreement creation.
	 *
	 * @since 1.2.0
	 *
	 * @param array $args {
	 *     Context args to retrieve SetExpressCheckout parameters.
	 *
	 *     @type string $start_from Start from 'cart' or 'checkout'.
	 *     @type int    $order_id   Order ID if $start_from is 'checkout'.
	 * }
	 *
	 * @return bool Returns true if billing agreement is needed in the purchase
	 */
	public function needs_billing_agreement_creation( $args ) {
		$needs_billing_agreement = false;
		switch ( $args['start_from'] ) {
			case 'cart':
				if ( class_exists( 'WC_Subscriptions_Cart' ) ) {
					$needs_billing_agreement = WC_Subscriptions_Cart::cart_contains_subscription();
				}
				break;
			case 'checkout':
				if ( function_exists( 'wcs_order_contains_subscription' ) ) {
					$needs_billing_agreement = wcs_order_contains_subscription( $args['order_id'] );
				}
				if ( function_exists( 'wcs_order_contains_renewal' ) ) {
					$needs_billing_agreement = ( $needs_billing_agreement || wcs_order_contains_renewal( $args['order_id'] ) );
				}
				break;
		}

		return $needs_billing_agreement;
	}
}
