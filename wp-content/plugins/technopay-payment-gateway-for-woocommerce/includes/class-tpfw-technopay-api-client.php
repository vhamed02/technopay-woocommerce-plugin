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
		$body = $this->request( 'GET', '/refunds', array( 'query' => $query_args ), 'refunds' );

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		return array(
			'results' => isset( $body['results'] ) && is_array( $body['results'] ) ? $body['results'] : array(),
			'metas'   => isset( $body['metas'] ) && is_array( $body['metas'] ) ? $body['metas'] : array(),
		);
	}

	public function create_refund( $track_number, $requested_amount ) {
		$body = $this->request(
			'POST',
			'/refund',
			array(
				'body' => array(
					'track_number'     => (string) $track_number,
					'requested_amount' => (int) $requested_amount,
				),
			),
			'refund'
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if (
			! isset( $body['results'] ) ||
			! is_array( $body['results'] ) ||
			! isset( $body['results']['track_number'], $body['results']['refund_request_id'], $body['results']['status'], $body['results']['requested_amount'] ) ||
			! is_scalar( $body['results']['track_number'] ) ||
			! is_numeric( $body['results']['refund_request_id'] ) ||
			! is_scalar( $body['results']['status'] ) ||
			! is_numeric( $body['results']['requested_amount'] ) ||
			(string) $track_number !== (string) $body['results']['track_number'] ||
			(int) $requested_amount !== (int) $body['results']['requested_amount']
		) {
			return new WP_Error(
				'tpfw_api_invalid_response',
				__( 'پاسخ سرویس تکنوپی معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		return array(
			'refund_request_id' => (int) $body['results']['refund_request_id'],
			'requested_amount'  => (int) $body['results']['requested_amount'],
			'status'            => sanitize_text_field( (string) $body['results']['status'] ),
			'track_number'      => sanitize_text_field( (string) $body['results']['track_number'] ),
		);
	}

	public function cancel_refund( $track_number ) {
		$body = $this->request(
			'POST',
			'/refund/cancel',
			array(
				'body' => array( 'track_number' => (string) $track_number ),
			),
			'refund_cancel'
		);

		if ( is_wp_error( $body ) ) {
			return $body;
		}

		if (
			! isset( $body['results'] ) ||
			! is_array( $body['results'] ) ||
			! isset( $body['results']['track_number'], $body['results']['status'], $body['results']['requested_amount'] ) ||
			! is_scalar( $body['results']['track_number'] ) ||
			! is_scalar( $body['results']['status'] ) ||
			! is_numeric( $body['results']['requested_amount'] ) ||
			(string) $track_number !== (string) $body['results']['track_number']
		) {
			return new WP_Error(
				'tpfw_api_invalid_response',
				__( 'پاسخ سرویس تکنوپی معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		return array(
			'requested_amount' => (int) $body['results']['requested_amount'],
			'status'           => sanitize_text_field( (string) $body['results']['status'] ),
			'track_number'     => sanitize_text_field( (string) $body['results']['track_number'] ),
		);
	}

	public static function generate_signature( $merchant_id, $merchant_secret, $timestamp, $payment_type ) {
		$plain_signature = $merchant_id . ';' . $timestamp . ';' . $payment_type . ';' . $merchant_secret;
		$key             = base64_decode( $merchant_secret, true );

		if ( false === $key ) {
			throw new InvalidArgumentException( 'کلید محرمانه تکنوپی معتبر نیست.' );
		}

		$key             = strlen( $key ) < 16 ? str_pad( $key, 16, "\0" ) : substr( $key, 0, 16 );
		$iv              = random_bytes( 16 );
		$encrypted       = openssl_encrypt( $plain_signature, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			throw new RuntimeException( 'ایجاد امضای دیجیتال ناموفق بود.' );
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

	private function request( $method, $path, $args, $operation ) {
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

		$url = $this->base_url . $path;

		if ( isset( $args['query'] ) && is_array( $args['query'] ) ) {
			$url = add_query_arg( $args['query'], $url );
		}

		$request_args = array(
			'headers'   => array(
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
				'signature'    => $signature,
				'merchantId'   => $this->merchant_id,
				'User-Agent'   => 'technopay-payment-gateway-for-woocommerce/' . TPFW_VERSION,
			),
			'method'    => $method,
			'sslverify' => true,
			'timeout'   => 10,
		);

		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$request_args['body'] = wp_json_encode( $args['body'] );
		}

		$response = wp_remote_request( $url, $request_args );

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
			return new WP_Error( 'tpfw_api_request_failed', $this->get_request_error_message( $status_code, $operation ) );
		}

		return $body;
	}

	private function get_request_error_message( $status_code, $operation ) {
		if ( 401 === $status_code || 403 === $status_code ) {
			return __( 'اطلاعات احراز هویت تکنوپی معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' );
		}

		if ( 404 === $status_code ) {
			if ( 'refund' === $operation ) {
				return __( 'سرویس ثبت درخواست استرداد تکنوپی در دسترس نیست.', 'technopay-payment-gateway-for-woocommerce' );
			}

			return 'refund_cancel' === $operation
				? __( 'سرویس لغو درخواست استرداد تکنوپی در دسترس نیست.', 'technopay-payment-gateway-for-woocommerce' )
				: __( 'سرویس لیست استردادهای تکنوپی در دسترس نیست.', 'technopay-payment-gateway-for-woocommerce' );
		}

		if ( 422 === $status_code ) {
			if ( 'refund' === $operation ) {
				return __( 'اطلاعات درخواست استرداد معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' );
			}

			return 'refund_cancel' === $operation
				? __( 'اطلاعات لغو درخواست استرداد معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' )
				: __( 'فیلترهای ارسال‌شده معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' );
		}

		if ( 429 === $status_code ) {
			return __( 'تعداد درخواست‌ها زیاد است. لطفا کمی بعد دوباره تلاش کنید.', 'technopay-payment-gateway-for-woocommerce' );
		}

		if ( $status_code >= 500 ) {
			return __( 'سرویس تکنوپی موقتا در دسترس نیست.', 'technopay-payment-gateway-for-woocommerce' );
		}

		if ( 'refund' === $operation ) {
			return __( 'ثبت درخواست استرداد ناموفق بود.', 'technopay-payment-gateway-for-woocommerce' );
		}

		return 'refund_cancel' === $operation
			? __( 'لغو درخواست استرداد ناموفق بود.', 'technopay-payment-gateway-for-woocommerce' )
			: __( 'دریافت لیست استردادها ناموفق بود.', 'technopay-payment-gateway-for-woocommerce' );
	}
}
