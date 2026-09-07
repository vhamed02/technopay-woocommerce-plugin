<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Refunds_Tab implements TPFW_Orders_Tab {

	const SLUG = 'refunds';

	private $formatter;

	public function __construct( TPFW_Orders_Formatter $formatter ) {
		$this->formatter = $formatter;
	}

	public function get_slug() {
		return self::SLUG;
	}

	public function get_label() {
		return __( 'درخواست‌های استرداد', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_date_column_label() {
		return __( 'تاریخ ثبت درخواست', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_refund_column_label() {
		return __( 'مبلغ استرداد', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_empty_message() {
		return __( 'درخواست استردادی مطابق با این فیلترها پیدا نشد.', 'technopay-payment-gateway-for-woocommerce' );
	}

	public function get_status_filter_key() {
		return 'status';
	}

	public function fetch( TPFW_Technopay_Api_Client $api_client, array $query ) {
		return $api_client->get_refunds( $query );
	}

	public function get_rows( array $results, $row_offset ) {
		$rows = array();

		foreach ( $results as $index => $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$ticket_amount     = $this->formatter->parse_amount( $this->formatter->get_scalar_value( $result, 'ticket_amount' ) );
			$requested_amount  = $this->formatter->parse_amount( $this->formatter->get_scalar_value( $result, 'requested_amount' ) );
			$refund_status     = $this->formatter->get_first_scalar_value( $result, array( 'refund_status', 'status' ) );
			$refund_status_key = $this->formatter->normalize_status( $refund_status );
			$refund_reasons    = $this->formatter->parse_reasons( isset( $result['refund_reasons'] ) ? $result['refund_reasons'] : array() );
			$reject_reasons    = $this->formatter->parse_reasons( isset( $result['reject_reasons'] ) ? $result['reject_reasons'] : array() );

			$status = $this->get_display_status( $refund_status, $ticket_amount, $requested_amount );

			$rows[] = array(
				'action'               => $status['action'],
				'amount'               => $this->formatter->format_amount( $ticket_amount ),
				'available_amount_raw' => '',
				'customer_mobile'      => $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'customer_mobile' ) ),
				'customer_name'        => $this->formatter->get_display_value( $this->formatter->get_scalar_value( $result, 'customer_full_name' ) ),
				'date'                 => $this->formatter->format_date( $this->formatter->get_first_scalar_value( $result, array( 'created_at', 'requested_at', 'paid_at' ) ) ),
				'has_details'          => $this->has_details( $refund_status_key, $refund_reasons, $reject_reasons ),
				'number'               => (string) ( $row_offset + $index + 1 ),
				'refund_amount'        => $this->formatter->format_optional_amount( $requested_amount ),
				'refund_reasons'       => $refund_reasons,
				'reject_reasons'       => $reject_reasons,
				'status_label'         => $status['label'],
				'status_tone'          => $status['tone'],
				'track_number'         => $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'track_number' ) ),
			);
		}

		return $rows;
	}

	public function ensure_cancelable( TPFW_Technopay_Api_Client $api_client, $track_number ) {
		$response = $api_client->get_refunds(
			array(
				'filters'  => array( 'track_number' => $track_number ),
				'per_page' => TPFW_Admin_Orders_Page::PER_PAGE,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response['results'] as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			if ( $track_number !== $this->formatter->normalize_digits( $this->formatter->get_scalar_value( $result, 'track_number' ) ) ) {
				continue;
			}

			$status_key = $this->formatter->normalize_status( $this->formatter->get_first_scalar_value( $result, array( 'refund_status', 'status' ) ) );

			if ( TPFW_Refund_Status::is_pending( $status_key ) ) {
				return true;
			}
		}

		return new WP_Error(
			'tpfw_cancel_not_allowed',
			__( 'لغو درخواست استرداد برای این پرداخت امکان‌پذیر نیست.', 'technopay-payment-gateway-for-woocommerce' )
		);
	}

	private function has_details( $refund_status_key, $refund_reasons, $reject_reasons ) {
		if ( TPFW_Refund_Status::is_completed( $refund_status_key ) ) {
			return true;
		}

		return ( ! empty( $refund_reasons ) || ! empty( $reject_reasons ) ) && ! TPFW_Refund_Status::is_canceled( $refund_status_key );
	}

	private function get_display_status( $refund_status, $ticket_amount, $requested_amount ) {
		$refund_status_key = $this->formatter->normalize_status( $refund_status );

		if ( TPFW_Refund_Status::is_pending( $refund_status_key ) ) {
			return array(
				'action' => 'cancel',
				'label'  => __( 'در انتظار استرداد', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'warning',
			);
		}

		if ( TPFW_Refund_Status::is_completed( $refund_status_key ) ) {
			$is_full_refund = null !== $ticket_amount && null !== $requested_amount && $requested_amount >= $ticket_amount;

			return array(
				'action' => 'none',
				'label'  => $is_full_refund
					? __( 'استرداد کل مبلغ', 'technopay-payment-gateway-for-woocommerce' )
					: __( 'استرداد بخشی از مبلغ', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'danger',
			);
		}

		if ( TPFW_Refund_Status::is_canceled( $refund_status_key ) ) {
			return array(
				'action' => 'none',
				'label'  => __( 'درخواست استرداد لغو شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'info',
			);
		}

		if ( TPFW_Refund_Status::is_rejected( $refund_status_key ) ) {
			return array(
				'action' => 'none',
				'label'  => __( 'درخواست استرداد رد شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'danger',
			);
		}

		return array(
			'action' => 'none',
			'label'  => $this->formatter->get_display_value( $refund_status ),
			'tone'   => 'info',
		);
	}
}
