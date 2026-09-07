<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Refundable_Tickets_Tab implements TPFW_Orders_Tab {

	const SLUG = 'refundable';

	private $formatter;

	public function __construct( TPFW_Orders_Formatter $formatter ) {
		$this->formatter = $formatter;
	}

	public function get_slug() {
		return self::SLUG;
	}

	public function get_label() {
		return __( 'پرداخت‌های قابل استرداد', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_date_column_label() {
		return __( 'تاریخ ثبت پرداخت', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_refund_column_label() {
		return __( 'مبلغ قابل استرداد', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_empty_message() {
		return __( 'پرداخت قابل استردادی مطابق با این فیلترها پیدا نشد.', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_status_filter_key() {
		return '';
	}

	public function fetch( TPFW_Technopay_Api_Client $api_client, array $query ) {
		return $api_client->get_refundable_tickets( $query );
	}

	public function get_rows( array $results, $row_offset ) {
		$rows = array();

		foreach ( $results as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$refundable_amount = $this->formatter->parse_amount( $this->formatter->get_scalar_value( $result, 'refundable_amount' ) );
			$latest_request    = $this->get_latest_refund_request( $result );
			$refund_status_key = $this->formatter->normalize_status( $this->formatter->get_scalar_value( $latest_request, 'status' ) );

			if ( ! $this->is_refundable( $result, $refundable_amount, $refund_status_key ) ) {
				continue;
			}

			$refund_reasons = $this->formatter->parse_reasons( isset( $latest_request['refund_reasons'] ) ? $latest_request['refund_reasons'] : array() );
			$reject_reasons = $this->formatter->parse_reasons( isset( $latest_request['reject_reasons'] ) ? $latest_request['reject_reasons'] : array() );

			$rows[] = array(
				'action'               => 'refund',
				'amount'               => $this->formatter->format_amount( $this->formatter->parse_amount( $this->formatter->get_scalar_value( $result, 'ticket_amount' ) ) ),
				'available_amount_raw' => $this->formatter->format_raw_amount( $refundable_amount ),
				'customer_mobile'      => $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'customer_mobile' ) ),
				'customer_name'        => $this->formatter->get_display_value( $this->formatter->get_scalar_value( $result, 'customer_full_name' ) ),
				'date'                 => $this->formatter->format_date( $this->formatter->get_scalar_value( $result, 'paid_at' ) ),
				'has_details'          => $this->has_details( $refund_status_key, $refund_reasons, $reject_reasons ),
				'number'               => (string) ( $row_offset + count( $rows ) + 1 ),
				'refund_amount'        => $this->formatter->format_amount( $refundable_amount ),
				'refund_reasons'       => $refund_reasons,
				'reject_reasons'       => $reject_reasons,
				'status_label'         => __( 'قابل استرداد', 'technopay-payment-gateway-for-woocommerce' ),
				'status_tone'          => 'success',
				'track_number'         => $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'track_number' ) ),
			);
		}

		return $rows;
	}

	public function get_refundable_amount( TPFW_Technopay_Api_Client $api_client, $track_number ) {
		$ticket = $this->find_ticket( $api_client, $track_number );

		if ( is_wp_error( $ticket ) ) {
			return $ticket;
		}

		$refundable_amount = $this->formatter->parse_amount( $this->formatter->get_scalar_value( $ticket, 'refundable_amount' ) );
		$latest_request    = $this->get_latest_refund_request( $ticket );
		$refund_status_key = $this->formatter->normalize_status( $this->formatter->get_scalar_value( $latest_request, 'status' ) );

		if ( ! $this->is_refundable( $ticket, $refundable_amount, $refund_status_key ) ) {
			return new WP_Error(
				'tpfw_refund_not_allowed',
				__( 'ثبت درخواست استرداد برای این پرداخت امکان‌پذیر نیست.', 'technopay-payment-gateway-for-woocommerce' )
			);
		}

		return (int) $refundable_amount;
	}

	private function find_ticket( TPFW_Technopay_Api_Client $api_client, $track_number ) {
		$response = $api_client->get_refundable_tickets(
			array(
				'filters'  => array( 'track_number' => $track_number ),
				'per_page' => 1,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response['results'] as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			if ( $track_number === $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'track_number' ) ) ) {
				return $result;
			}
		}

		return new WP_Error(
			'tpfw_refund_not_found',
			__( 'اطلاعات این پرداخت یافت نشد.', 'technopay-payment-gateway-for-woocommerce' )
		);
	}

	private function is_refundable( $result, $refundable_amount, $refund_status_key ) {
		return ! empty( $result['is_refundable'] )
			&& null !== $refundable_amount
			&& $refundable_amount >= 1
			&& ! TPFW_Refund_Status::is_pending( $refund_status_key );
	}

	private function get_latest_refund_request( $result ) {
		if ( ! isset( $result['refund_requests'] ) || ! is_array( $result['refund_requests'] ) ) {
			return array();
		}

		foreach ( $result['refund_requests'] as $request ) {
			if ( is_array( $request ) ) {
				return $request;
			}
		}

		return array();
	}

	private function has_details( $refund_status_key, $refund_reasons, $reject_reasons ) {
		if ( TPFW_Refund_Status::is_completed( $refund_status_key ) ) {
			return true;
		}

		return ( ! empty( $refund_reasons ) || ! empty( $reject_reasons ) ) && ! TPFW_Refund_Status::is_canceled( $refund_status_key );
	}
}
