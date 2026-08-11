<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Technopay_Api_Client {

	const PAYMENT_TYPE = 'cpg';

	private $base_url;
	private $merchant_id;
	private $merchant_secret;

	public function __construct() {
		$settings              = get_option( 'woocommerce_technopay_settings', array() );
		$this->merchant_id     = isset( $settings['merchant_id'] ) ? trim( (string) $settings['merchant_id'] ) : '';
		$this->merchant_secret = isset( $settings['merchant_secret'] ) ? trim( (string) $settings['merchant_secret'] ) : '';
		$this->base_url        = isset( $settings['testmode'] ) && 'yes' === $settings['testmode']
			? 'https://credit-api.dev.tgms.ir/payment'
			: 'https://api.technopay.ir/payment';
	}

	public function is_configured() {
		return '' !== $this->merchant_id && '' !== $this->merchant_secret;
	}

	public function get_refunds( $query_args ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'tpfw_api_not_configured',
				__( 'اطلاعات اتصال تکنوپی تکمیل نشده است.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		try {
			$signature = self::generate_signature(
				$this->merchant_id,
				$this->merchant_secret,
				time(),
				self::PAYMENT_TYPE
			);
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'tpfw_signature_failed',
				__( 'امضای دیجیتال درخواست ایجاد نشد.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		$url      = add_query_arg( $query_args, $this->base_url . '/refunds' );
		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array(
					'Accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'signature'    => $signature,
					'merchantId'   => $this->merchant_id,
					'User-Agent'   => 'technopay-payment-gateway-for-woocommerce/' . TPFW_VERSION,
				),
				'sslverify' => true,
				'timeout'   => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'tpfw_api_unavailable',
				__( 'ارتباط با سرویس تکنوپی برقرار نشد.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new WP_Error(
				'tpfw_api_invalid_response',
				__( 'پاسخ سرویس تکنوپی معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 || empty( $body['succeed'] ) ) {
			$message = isset( $body['message'] ) && is_scalar( $body['message'] )
				? sanitize_text_field( (string) $body['message'] )
				: __( 'دریافت لیست استردادها ناموفق بود.', 'technopay-payment-gateway-for-woocommerce' );

			return new WP_Error( 'tpfw_api_request_failed', $message );
		}

		return array(
			'results' => isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : array(),
			'metas'   => isset( $body['metas'] ) && is_array( $body['metas'] ) ? $body['metas'] : array(),
		);
	}

	public static function generate_signature( $merchant_id, $merchant_secret, $timestamp, $payment_type ) {
		$plain_signature = $merchant_id . ';' . $timestamp . ';' . $payment_type . ';' . $merchant_secret;
		$key             = base64_decode( $merchant_secret, true );

		if ( false === $key ) {
			throw new InvalidArgumentException( 'Invalid merchant secret.' );
		}

		$key             = strlen( $key ) < 16 ? str_pad( $key, 16, "\0" ) : substr( $key, 0, 16 );
		$iv              = random_bytes( 16 );
		$encrypted       = openssl_encrypt( $plain_signature, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			throw new RuntimeException( 'Signature encryption failed.' );
		}

		return base64_encode(
			wp_json_encode(
				array(
					'iv'    => base64_encode( $iv ),
					'value' => base64_encode( $encrypted ),
				)
			)
		);
	}
}
