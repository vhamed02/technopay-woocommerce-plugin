<?php
/**
 * TechnoPay Gateway Class
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TechnoPay_Gateway extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'technopay';
		$this->icon               = TECHNOPAY_PLUGIN_URL . 'assets/images/technopay-logo.svg';
		$this->has_fields         = false;
		$this->method_title       = __( 'تکنوپی', 'technopay-gateway' );
		$this->method_description = __( 'پرداخت اقساطی از طریق تکنوپی', 'technopay-gateway' );

		// Load the settings
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->merchant_name   = $this->get_option( 'merchant_name' );
		$this->merchant_id     = $this->get_option( 'merchant_id' );
		$this->merchant_secret = $this->get_option( 'merchant_secret' );
		$this->testmode        = $this->get_option( 'testmode' );
		$this->currency_mode   = $this->get_option( 'currency_mode' );

		// API URLs
		$this->api_url = 'https://api.technopay.ir/payment';

		// Save settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		// Add callback endpoints
		add_action( 'woocommerce_api_technopay_callback', array( $this, 'process_callback' ) );
		add_action( 'woocommerce_api_technopay_fallback', array( $this, 'process_fallback' ) );

		// Make billing phone required when TechnoPay is selected
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_billing_phone' ) );

		// Declare HPOS compatibility
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Initialize Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'technopay-gateway' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable TechnoPay Gateway', 'technopay-gateway' ),
				'default' => 'yes'
			),
			'title'           => array(
				'title'       => __( 'Title', 'technopay-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'technopay-gateway' ),
				'default'     => __( 'تکنوپی', 'technopay-gateway' ),
				'desc_tip'    => true,
			),
			'description'     => array(
				'title'       => __( 'Description', 'technopay-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'technopay-gateway' ),
				'default'     => __( 'پرداخت اقساطی از طریق تکنوپی', 'technopay-gateway' ),
			),
			'merchant_name'   => array(
				'title'       => __( 'Merchant Name', 'technopay-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your merchant name', 'technopay-gateway' ),
				'default'     => '',
				'required'    => true,
			),
			'merchant_id'     => array(
				'title'       => __( 'Merchant ID', 'technopay-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your TechnoPay merchant ID', 'technopay-gateway' ),
				'default'     => '',
				'required'    => true,
			),
			'merchant_secret' => array(
				'title'       => __( 'Merchant Secret', 'technopay-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your TechnoPay merchant secret', 'technopay-gateway' ),
				'default'     => '',
				'required'    => true,
			),
			'testmode'        => array(
				'title'       => __( 'Test Mode', 'technopay-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable Test Mode', 'technopay-gateway' ),
				'default'     => 'no',
				'description' => __( 'Place the payment gateway in test mode.', 'technopay-gateway' ),
			),
			'currency_mode'   => array(
				'title'       => __( 'Currency Mode', 'technopay-gateway' ),
				'type'        => 'select',
				'description' => __( 'Select how amounts should be processed based on your store currency.', 'technopay-gateway' ),
				'default'     => 'auto',
				'options'     => array(
					'auto' => __( 'Auto-detect from WooCommerce currency', 'technopay-gateway' ),
					'irr'  => __( 'IRR (Iranian Rial) - Amounts will be divided by 10', 'technopay-gateway' ),
					'irt'  => __( 'IRT (Iranian Toman) - No conversion needed', 'technopay-gateway' ),
				),
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Process the payment and return the result
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wc_add_notice( __( 'Order not found.', 'technopay-gateway' ), 'error' );

			return array( 'result' => 'fail' );
		}

		// Validate required settings
		if ( empty( $this->merchant_id ) || empty( $this->merchant_secret ) ) {
			wc_add_notice( __( 'TechnoPay gateway is not properly configured.', 'technopay-gateway' ), 'error' );

			return array( 'result' => 'fail' );
		}

		try {
			// Create payment ticket
			$ticket_data = $this->create_payment_ticket( $order );

			if ( $ticket_data && isset( $ticket_data['payment_uri'] ) ) {
				// Store track number for verification (HPOS compatible)
				$order->update_meta_data( '_technopay_track_number', $ticket_data['track_number'] );
				$order->save();

				// Redirect to payment page
				return array(
					'result'   => 'success',
					'redirect' => $ticket_data['payment_uri']
				);
			} else {
				throw new Exception( __( 'Failed to create payment ticket.', 'technopay-gateway' ) );
			}

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			$order->add_order_note( __( 'TechnoPay payment failed: ', 'technopay-gateway' ) . $e->getMessage() );

			return array( 'result' => 'fail' );
		}
	}

	/**
	 * Create payment ticket
	 */
	private function create_payment_ticket( $order ) {
		$timestamp    = time();
		$payment_type = 'cpg';

		// Generate signature
		$signature = $this->generate_signature( $this->merchant_id, $this->merchant_secret, $timestamp, $payment_type );

		// Prepare request data - handle IRR and IRT currencies
		$amount        = $this->calculate_amount_for_api( $order );
		$ticket_number = 'WC' . $order->get_id() . '_' . time();

		// Get customer mobile number from billing info or user profile
		$mobile_number = $this->get_customer_mobile_number( $order );
		if ( empty( $mobile_number ) ) {
			throw new Exception( __( 'Customer mobile number is required for TechnoPay payment. Please add your mobile number in billing information.', 'technopay-gateway' ) );
		}

		// Normalize and validate Iranian mobile number format
		$mobile_number = $this->normalize_iranian_mobile( $mobile_number );
		if ( ! $this->is_valid_iranian_mobile( $mobile_number ) ) {
			throw new Exception( __( 'Please enter a valid Iranian mobile number (e.g., 09123456789).', 'technopay-gateway' ) );
		}

		// Prepare items
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'name'   => $item->get_name(),
				'amount' => $this->calculate_item_amount_for_api( $item ),
				'qty'    => $item->get_quantity(),
				'url'    => $product ? get_permalink( $product->get_id() ) : ''
			);
		}

		$request_data = array(
			'amount'        => $amount,
			'ticket_number' => $ticket_number,
			'redirect_uri'  => $this->get_callback_url(),
			'fallback_uri'  => $this->get_fallback_url(),
			'mobile_number' => $mobile_number,
			'items'         => $items
		);

		// Make API request
		$response = $this->make_api_request( '/purchase', $request_data, $signature, $timestamp );

		if ( $response && isset( $response['succeed'] ) && $response['succeed'] ) {
			return $response['results'];
		} else {
			$error_message = isset( $response['message'] ) ? $response['message'] : __( 'Unknown error occurred.', 'technopay-gateway' );
			throw new Exception( $error_message );
		}
	}

	/**
	 * Generate signature for API requests
	 */
	private function generate_signature( $merchant_id, $merchant_secret, $timestamp, $payment_type ) {
		$plain_signature = $merchant_id . ';' . $timestamp . ';' . $payment_type . ';' . $merchant_secret;

		// Decode merchant secret from base64
		$key = base64_decode( $merchant_secret );

		// Ensure key is exactly 16 bytes
		if ( strlen( $key ) < 16 ) {
			$key = str_pad( $key, 16, "\0" );
		} else {
			$key = substr( $key, 0, 16 );
		}

		// Generate random IV
		$iv = openssl_random_pseudo_bytes( 16 );

		// Encrypt the plain signature
		$encrypted = openssl_encrypt( $plain_signature, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( $encrypted === false ) {
			throw new Exception( __( 'Failed to generate signature.', 'technopay-gateway' ) );
		}

		// Create JSON payload
		$json_data = json_encode( array(
			'iv'    => base64_encode( $iv ),
			'value' => base64_encode( $encrypted )
		) );

		// Return base64 encoded JSON
		return base64_encode( $json_data );
	}

	/**
	 * Make API request
	 */
	private function make_api_request( $endpoint, $data, $signature, $timestamp ) {
		$url = $this->api_url . $endpoint;

		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'signature'    => $signature,
			'merchantId'   => $this->merchant_id
		);

		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => json_encode( $data ),
			'timeout' => 30
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$body      = wp_remote_retrieve_body( $response );
		$http_code = wp_remote_retrieve_response_code( $response );

		$decoded_response = json_decode( $body, true );

		if ( $http_code >= 400 ) {
			$error_message = isset( $decoded_response['message'] ) ? $decoded_response['message'] : __( 'HTTP Error: ', 'technopay-gateway' ) . $http_code;
			throw new Exception( $error_message );
		}

		return $decoded_response;
	}

	/**
	 * Get callback URL
	 */
	private function get_callback_url() {
		return WC()->api_request_url( 'technopay_callback' );
	}

	/**
	 * Get fallback URL
	 */
	private function get_fallback_url() {
		return WC()->api_request_url( 'technopay_fallback' );
	}

	/**
	 * Process successful payment callback
	 */
	public function process_callback() {
		// Ensure settings are loaded
		$this->init_settings();
		$this->merchant_id     = $this->get_option( 'merchant_id' );
		$this->merchant_secret = $this->get_option( 'merchant_secret' );

		// Zarinpal-style status gate: if provided and not OK, treat as cancelled
		$status = isset( $_REQUEST['Status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['Status'] ) ) : '';
		if ( ! empty( $status ) && strtoupper( $status ) !== 'OK' ) {
			wc_add_notice( __( 'Payment was cancelled by user.', 'technopay-gateway' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// Try to read track number from request in a robust way
		$track_number      = $this->get_request_track_number();
		$order_from_ticket = null;
		$order_from_wc     = null;

		// Support resolving via wc_order similar to Zarinpal plugins
		$wc_order_id = isset( $_REQUEST['wc_order'] ) ? absint( $_REQUEST['wc_order'] ) : 0;
		if ( $wc_order_id ) {
			$order_from_wc = wc_get_order( $wc_order_id );
			if ( $order_from_wc && empty( $track_number ) ) {
				$meta_track = $order_from_wc->get_meta( '_technopay_track_number' );
				if ( ! empty( $meta_track ) ) {
					$track_number = $meta_track;
				}
			}
		}

		if ( empty( $track_number ) ) {
			list( $order_from_ticket, $track_from_ticket ) = $this->get_track_from_ticket_or_meta();
			if ( ! empty( $track_from_ticket ) ) {
				$track_number = $track_from_ticket;
			}
		}

		if ( empty( $track_number ) ) {
			wc_add_notice( __( 'Invalid callback request.', 'technopay-gateway' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		try {
			// Find order by track number first
			$order = $this->get_order_by_track_number( $track_number );
			if ( ! $order && $order_from_ticket ) {
				// Fallback if we could resolve the order via ticket number
				$order = $order_from_ticket;
			}
			if ( ! $order && $order_from_wc ) {
				$order = $order_from_wc;
			}

			if ( ! $order ) {
				wc_add_notice( __( 'Order not found.', 'technopay-gateway' ), 'error' );
				wp_redirect( wc_get_checkout_url() );
				exit;
			}

			// Check if order is already completed
			if ( $order->is_paid() ) {
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			}

			// Verify the payment
			$verification_result = $this->verify_payment( $track_number );

			if ( $verification_result && isset( $verification_result['succeed'] ) && $verification_result['succeed'] ) {
				// Complete the order
				$order->payment_complete();
				$order->add_order_note( __( 'Payment completed via TechnoPay. Track Number: ', 'technopay-gateway' ) . $track_number );

				// Clear cart
				WC()->cart->empty_cart();

				// Redirect to thank you page
				wp_redirect( $order->get_checkout_order_received_url() );
				exit;
			} else {
				$error_message = isset( $verification_result['message'] ) ? $verification_result['message'] : __( 'Payment verification failed.', 'technopay-gateway' );
				$order->update_status( 'failed', $error_message );
				$order->add_order_note( __( 'TechnoPay verification failed: ', 'technopay-gateway' ) . $error_message );
				wc_add_notice( $error_message, 'error' );
				wp_redirect( wc_get_checkout_url() );
				exit;
			}

		} catch ( Exception $e ) {
			if ( isset( $order ) && $order ) {
				$order->update_status( 'failed', $e->getMessage() );
				$order->add_order_note( __( 'TechnoPay callback error: ', 'technopay-gateway' ) . $e->getMessage() );
			}
			wc_add_notice( $e->getMessage(), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}
	}

	/**
	 * Process failed payment callback
	 */
	public function process_fallback() {
		// Ensure settings are loaded
		$this->init_settings();
		$this->merchant_id     = $this->get_option( 'merchant_id' );
		$this->merchant_secret = $this->get_option( 'merchant_secret' );

		$track_number      = $this->get_request_track_number();
		$order_from_ticket = null;
		if ( empty( $track_number ) ) {
			list( $order_from_ticket, $track_from_ticket ) = $this->get_track_from_ticket_or_meta();
			if ( ! empty( $track_from_ticket ) ) {
				$track_number = $track_from_ticket;
			}
		}

		if ( ! empty( $track_number ) ) {
			$order = $this->get_order_by_track_number( $track_number );
			if ( ! $order && $order_from_ticket ) {
				$order = $order_from_ticket;
			}

			if ( $order && ! $order->is_paid() ) {
				$order->update_status( 'failed', __( 'Payment failed via TechnoPay.', 'technopay-gateway' ) );
				$order->add_order_note( __( 'Payment failed via TechnoPay. Track Number: ', 'technopay-gateway' ) . $track_number );
			}
		}

		// Add error notice
		wc_add_notice( __( 'Payment was cancelled or failed. Please try again.', 'technopay-gateway' ), 'error' );

		// Redirect to checkout page
		wp_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Verify payment
	 */
	private function verify_payment( $track_number ) {
		$timestamp    = time();
		$payment_type = 'cpg';

		// Generate signature
		$signature = $this->generate_signature( $this->merchant_id, $this->merchant_secret, $timestamp, $payment_type );

		$request_data = array(
			'track_number' => $track_number
		);

		return $this->make_api_request( '/verify', $request_data, $signature, $timestamp );
	}

	/**
	 * Get order by track number (HPOS compatible)
	 */
	private function get_order_by_track_number( $track_number ) {
		// Try HPOS first (WooCommerce 5.0+)
		if ( class_exists( 'WC_Order_Data_Store_CPT' ) ) {
			$data_store = WC_Data_Store::load( 'order' );
			if ( method_exists( $data_store, 'get_order_id_by_meta' ) ) {
				$order_id = $data_store->get_order_id_by_meta( '_technopay_track_number', $track_number );
				if ( $order_id ) {
					return wc_get_order( $order_id );
				}
			}
		}

		// Fallback to legacy method
		global $wpdb;
		$order_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_technopay_track_number' AND meta_value = %s",
			$track_number
		) );

		if ( $order_id ) {
			return wc_get_order( $order_id );
		}

		return false;
	}

	/**
	 * Get customer mobile number from order or user profile
	 */
	private function get_customer_mobile_number( $order ) {
		// First try to get from billing phone
		$mobile_number = $order->get_billing_phone();

		// If empty, try to get from user profile
		if ( empty( $mobile_number ) && $order->get_user_id() ) {
			$user_id       = $order->get_user_id();
			$mobile_number = get_user_meta( $user_id, 'billing_phone', true );

			// If still empty, try other common mobile fields
			if ( empty( $mobile_number ) ) {
				$mobile_number = get_user_meta( $user_id, 'mobile', true );
			}
			if ( empty( $mobile_number ) ) {
				$mobile_number = get_user_meta( $user_id, 'phone', true );
			}
		}

		// Clean the mobile number (remove spaces, dashes, etc.)
		if ( ! empty( $mobile_number ) ) {
			$mobile_number = preg_replace( '/[^0-9]/', '', $mobile_number );
		}

		return $mobile_number;
	}

	/**
	 * Normalize Iranian mobile number to local 11-digit format starting with 09
	 */
	private function normalize_iranian_mobile( $mobile_number ) {
		// Keep digits only
		$digits = preg_replace( '/[^0-9]/', '', $mobile_number );

		// Handle 0098XXXXXXXXXX -> 09XXXXXXXXX
		if ( strlen( $digits ) === 14 && substr( $digits, 0, 4 ) === '0098' && substr( $digits, 4, 1 ) === '9' ) {
			return '0' . substr( $digits, 4 );
		}

		// Handle 98XXXXXXXXXX -> 09XXXXXXXXX
		if ( strlen( $digits ) === 12 && substr( $digits, 0, 2 ) === '98' && substr( $digits, 2, 1 ) === '9' ) {
			return '0' . substr( $digits, 2 );
		}

		// Handle 9XXXXXXXXX -> 09XXXXXXXXX
		if ( strlen( $digits ) === 10 && substr( $digits, 0, 1 ) === '9' ) {
			return '0' . $digits;
		}

		// Already in local format 09XXXXXXXXX
		if ( strlen( $digits ) === 11 && substr( $digits, 0, 2 ) === '09' ) {
			return $digits;
		}

		// Return digits as-is (will fail validation later)
		return $digits;
	}

	/**
	 * Validate Iranian mobile number format
	 */
	private function is_valid_iranian_mobile( $mobile_number ) {
		$normalized = $this->normalize_iranian_mobile( $mobile_number );

		return ( strlen( $normalized ) === 11 && substr( $normalized, 0, 2 ) === '09' );
	}

	/**
	 * Validate billing phone when TechnoPay is selected
	 */
	public function validate_billing_phone() {
		if ( $_POST['payment_method'] === $this->id ) {
			$billing_phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : '';

			if ( empty( $billing_phone ) ) {
				wc_add_notice( __( 'Mobile number is required for TechnoPay payment.', 'technopay-gateway' ), 'error' );
			} else {
				$normalized = $this->normalize_iranian_mobile( $billing_phone );
				if ( $this->is_valid_iranian_mobile( $normalized ) ) {
					// Save normalized value so it persists to order/billing
					$_POST['billing_phone'] = $normalized;
				} else {
					wc_add_notice( __( 'Please enter a valid Iranian mobile number (e.g., 09123456789).', 'technopay-gateway' ), 'error' );
				}
			}
		}
	}

	/**
	 * Declare HPOS compatibility
	 */
	public function declare_hpos_compatibility() {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}

	/**
	 * Retrieve track number from request supporting multiple providers/shapes
	 */
	private function get_request_track_number() {
		$candidates = array(
			'track_number',    // default
			'trackNumber',     // camelCase
			'track',           // short
			'tn',              // alias
			'token',           // generic token
			'reference_id',    // generic reference
			'ref_id',          // generic ref
			'Authority'        // Zarinpal style
		);

		foreach ( $candidates as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && $_REQUEST[ $key ] !== '' ) {
				return sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) );
			}
		}

		return '';
	}

	/**
	 * Attempt to derive order and track number from ticket_number when present
	 * Expected format we sent: WC{orderId}_{timestamp}
	 */
	private function get_track_from_ticket_or_meta() {
		$ticket_number = isset( $_REQUEST['ticket_number'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['ticket_number'] ) ) : '';
		if ( empty( $ticket_number ) ) {
			return array( null, '' );
		}

		if ( strpos( $ticket_number, 'WC' ) === 0 ) {
			$rest          = substr( $ticket_number, 2 );
			$parts         = explode( '_', $rest );
			$order_id_part = isset( $parts[0] ) ? $parts[0] : '';
			if ( ctype_digit( $order_id_part ) ) {
				$order = wc_get_order( intval( $order_id_part ) );
				if ( $order ) {
					$track = $order->get_meta( '_technopay_track_number' );

					return array( $order, $track ? (string) $track : '' );
				}
			}
		}

		return array( null, '' );
	}

	/**
	 * Calculate amount for API based on currency
	 */
	private function calculate_amount_for_api( $order ) {
		$currency = $order->get_currency();
		$total    = $order->get_total();

		return $this->convert_amount_to_api_format( $total, $currency );
	}

	/**
	 * Calculate item amount for API based on currency
	 */
	private function calculate_item_amount_for_api( $item ) {
		$order    = $item->get_order();
		$currency = $order->get_currency();
		$total    = $item->get_total();

		return $this->convert_amount_to_api_format( $total, $currency );
	}

	/**
	 * Convert amount to API format based on currency
	 */
	private function convert_amount_to_api_format( $amount, $currency ) {
		// Convert to integer (remove decimals)
		$amount = intval( $amount );

		// Use manual currency mode if set, otherwise auto-detect
		if ( $this->currency_mode !== 'auto' ) {
			switch ( $this->currency_mode ) {
				case 'irr':
					return intval( $amount / 10 );
				case 'irt':
					return $amount;
			}
		}

		// Auto-detect based on WooCommerce currency
		switch ( strtoupper( $currency ) ) {
			case 'IRR':
				// IRR: Divide by 10 (convert to Toman equivalent)
				return intval( $amount / 10 );

			case 'IRT':
				// IRT: No conversion needed (already in Toman)
				return $amount;

			default:
				// For unsupported currencies, throw an error
				throw new Exception( __( 'Only IRR and IRT currencies are supported by TechnoPay.', 'technopay-gateway' ) );
		}
	}
}
