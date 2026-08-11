<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Admin_Orders_Page {

	const PAGE_SLUG = 'technopay-orders';
	const PER_PAGE  = 10;

	private $hook_suffix = '';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		$this->hook_suffix = add_submenu_page(
			'woocommerce',
			__( 'استرداد سفارشات آنلاین تکنوپی', 'technopay-payment-gateway-for-woocommerce' ),
			__( 'تکنوپی', 'technopay-payment-gateway-for-woocommerce' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$style_path  = TPFW_PLUGIN_PATH . 'assets/css/admin-orders.css';
		$script_path = TPFW_PLUGIN_PATH . 'assets/js/admin-orders.js';

		wp_enqueue_style(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/css/admin-orders.css',
			array( 'dashicons' ),
			(string) filemtime( $style_path )
		);

		wp_enqueue_script(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/js/admin-orders.js',
			array(),
			(string) filemtime( $script_path ),
			true
		);

		wp_localize_script(
			'tpfw-admin-orders',
			'tpfwAdminOrders',
			array(
				'copied' => __( 'کپی شد', 'technopay-payment-gateway-for-woocommerce' ),
				'copy'   => __( 'کپی', 'technopay-payment-gateway-for-woocommerce' ),
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$filters  = $this->get_filters();
		$cursor   = $this->sanitize_cursor( $this->get_request_value( 'cursor' ) );
		$response = ( new TPFW_Technopay_Api_Client() )->get_refunds( $this->get_api_query( $filters, $cursor ) );
		$results  = array();
		$metas    = array();
		$error    = '';

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
		} else {
			$results = $response['results'];
			$metas   = $response['metas'];
		}

		$rows = $this->get_rows( $results );

		$view = array(
			'error'           => $error,
			'filters'         => $filters,
			'pagination'      => $this->get_pagination( $metas, $filters ),
			'reset_url'       => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'rows'            => $rows,
			'status_options'  => $this->get_status_options(),
			'visible_results' => count( $rows ),
		);

		include TPFW_PLUGIN_PATH . 'templates/admin-orders-page.php';
	}

	private function get_filters() {
		$status_options = $this->get_status_options();
		$status         = sanitize_key( $this->get_request_value( 'order_status' ) );
		$period         = sanitize_key( $this->get_request_value( 'order_period' ) );
		$periods        = array( 'today', 'yesterday', 'last-7-days', 'last-30-days', 'current-month' );

		return array(
			'amount'          => $this->sanitize_amount( $this->get_request_value( 'amount' ) ),
			'customer_mobile' => $this->normalize_digits( sanitize_text_field( $this->get_request_value( 'customer_mobile' ) ) ),
			'period'          => in_array( $period, $periods, true ) ? $period : '',
			'status'          => isset( $status_options[ $status ] ) ? $status : '',
		);
	}

	private function get_api_query( $filters, $cursor ) {
		$api_filters = array();

		if ( '' !== $filters['customer_mobile'] ) {
			$api_filters['customer_mobile'] = $filters['customer_mobile'];
		}

		if ( '' !== $filters['amount'] ) {
			$api_filters['ticket_amount'] = (int) $filters['amount'];
		}

		if ( '' !== $filters['status'] ) {
			$api_filters['status'] = $filters['status'];
		}

		if ( '' !== $filters['period'] ) {
			$api_filters['paid_at'] = $this->get_paid_at_filter( $filters['period'] );
		}

		$query = array( 'per_page' => self::PER_PAGE );

		if ( ! empty( $api_filters ) ) {
			$query['filters'] = $api_filters;
		}

		if ( '' !== $cursor ) {
			$query['cursor'] = $cursor;
		}

		return $query;
	}

	private function get_paid_at_filter( $period ) {
		$today = new DateTimeImmutable( 'today', wp_timezone() );

		switch ( $period ) {
			case 'today':
				return $today->format( 'Y-m-d' );
			case 'yesterday':
				return $today->modify( '-1 day' )->format( 'Y-m-d' );
			case 'last-7-days':
				return array( $today->modify( '-6 days' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
			case 'last-30-days':
				return array( $today->modify( '-29 days' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
			case 'current-month':
				return array( $today->modify( 'first day of this month' )->format( 'Y-m-d' ), $today->format( 'Y-m-d' ) );
		}

		return '';
	}

	private function get_rows( $results ) {
		$rows = array();

		foreach ( $results as $index => $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			$ticket_amount    = $this->parse_amount( isset( $result['ticket_amount'] ) ? $result['ticket_amount'] : null );
			$requested_amount = $this->parse_amount( isset( $result['requested_amount'] ) ? $result['requested_amount'] : null );
			$refund_status    = $this->get_scalar_value( $result, 'refund_status' );
			$ticket_status    = $this->get_scalar_value( $result, 'ticket_status' );
			$status           = $this->get_display_status( $refund_status, $ticket_status, $ticket_amount, $requested_amount );
			$track_number     = $this->normalize_digits( $this->get_scalar_value( $result, 'track_number' ) );

			$rows[] = array(
				'action'              => $status['action'],
				'amount'              => $this->format_amount( $ticket_amount ),
				'customer_mobile'     => $this->normalize_digits( $this->get_scalar_value( $result, 'customer_mobile' ) ),
				'customer_name'       => $this->get_display_value( $this->get_scalar_value( $result, 'customer_full_name' ) ),
				'number'              => (string) ( $index + 1 ),
				'paid_at'             => $this->format_date( $this->get_scalar_value( $result, 'paid_at' ) ),
				'refund_amount'       => null !== $requested_amount && $requested_amount > 0 ? $this->format_amount( $requested_amount ) : '—',
				'requested_amount_raw' => null === $requested_amount ? '' : (string) $requested_amount,
				'status_label'        => $status['label'],
				'status_tone'         => $status['tone'],
				'ticket_amount_raw'   => null === $ticket_amount ? '' : (string) $ticket_amount,
				'track_number'        => $track_number,
			);
		}

		return $rows;
	}

	private function get_display_status( $refund_status, $ticket_status, $ticket_amount, $requested_amount ) {
		$refund_key = $this->normalize_status( $refund_status );
		$ticket_key = $this->normalize_status( $ticket_status );
		$pending    = array( 'pending', 'requested', 'waiting', 'in_progress', 'processing' );
		$completed  = array( 'approved', 'completed', 'done', 'refunded', 'success', 'successful' );

		if ( in_array( $refund_key, $pending, true ) ) {
			return array(
				'action' => 'cancel',
				'label'  => __( 'در انتظار استرداد پرداخت', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'warning',
			);
		}

		if ( in_array( $refund_key, $completed, true ) ) {
			$is_full_refund = null !== $ticket_amount && null !== $requested_amount && $requested_amount >= $ticket_amount;

			return array(
				'action' => 'details',
				'label'  => $is_full_refund
					? __( 'استرداد کل مبلغ', 'technopay-payment-gateway-for-woocommerce' )
					: __( 'استرداد بخشی از مبلغ', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'danger',
			);
		}

		if ( in_array( $refund_key, array( 'canceled', 'cancelled' ), true ) ) {
			return array(
				'action' => 'refund',
				'label'  => __( 'درخواست استرداد لغو شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'info',
			);
		}

		if ( in_array( $refund_key, array( 'failed', 'rejected' ), true ) ) {
			return array(
				'action' => 'refund',
				'label'  => __( 'درخواست استرداد رد شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'danger',
			);
		}

		if ( in_array( $ticket_key, array( 'completed', 'finalized' ), true ) ) {
			return array(
				'action' => 'none',
				'label'  => __( 'نهایی شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'info',
			);
		}

		if ( in_array( $ticket_key, array( 'approved', 'paid', 'processing', 'verified' ), true ) ) {
			return array(
				'action' => 'refund',
				'label'  => __( 'تایید شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'success',
			);
		}

		return array(
			'action' => 'none',
			'label'  => $this->get_display_value( '' !== $refund_status ? $refund_status : $ticket_status ),
			'tone'   => 'info',
		);
	}

	private function get_pagination( $metas, $filters ) {
		$previous_cursor = isset( $metas['prev_cursor'] ) && is_scalar( $metas['prev_cursor'] ) ? (string) $metas['prev_cursor'] : '';
		$next_cursor     = isset( $metas['next_cursor'] ) && is_scalar( $metas['next_cursor'] ) ? (string) $metas['next_cursor'] : '';

		return array(
			'next_url'     => '' !== $next_cursor ? $this->get_cursor_url( $next_cursor, $filters ) : '',
			'previous_url' => '' !== $previous_cursor ? $this->get_cursor_url( $previous_cursor, $filters ) : '',
		);
	}

	private function get_cursor_url( $cursor, $filters ) {
		$query_args = array(
			'page'   => self::PAGE_SLUG,
			'cursor' => $cursor,
		);

		if ( '' !== $filters['customer_mobile'] ) {
			$query_args['customer_mobile'] = $filters['customer_mobile'];
		}

		if ( '' !== $filters['amount'] ) {
			$query_args['amount'] = $filters['amount'];
		}

		if ( '' !== $filters['status'] ) {
			$query_args['order_status'] = $filters['status'];
		}

		if ( '' !== $filters['period'] ) {
			$query_args['order_period'] = $filters['period'];
		}

		return add_query_arg( $query_args, admin_url( 'admin.php' ) );
	}

	private function get_status_options() {
		return array(
			'pending'  => __( 'در انتظار استرداد پرداخت', 'technopay-payment-gateway-for-woocommerce' ),
			'approved' => __( 'استرداد شده', 'technopay-payment-gateway-for-woocommerce' ),
			'rejected' => __( 'رد شده', 'technopay-payment-gateway-for-woocommerce' ),
			'canceled' => __( 'لغو شده', 'technopay-payment-gateway-for-woocommerce' ),
		);
	}

	private function get_request_value( $key ) {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_GET[ $key ] );
	}

	private function sanitize_amount( $amount ) {
		$amount = $this->normalize_digits( sanitize_text_field( $amount ) );
		$amount = str_replace( array( ',', '٬', ' ' ), '', $amount );

		return ctype_digit( $amount ) ? $amount : '';
	}

	private function sanitize_cursor( $cursor ) {
		return sanitize_text_field( $cursor );
	}

	private function get_scalar_value( $result, $key ) {
		return isset( $result[ $key ] ) && is_scalar( $result[ $key ] ) ? sanitize_text_field( (string) $result[ $key ] ) : '';
	}

	private function get_display_value( $value ) {
		return '' !== trim( $value ) ? $value : '—';
	}

	private function parse_amount( $amount ) {
		if ( null === $amount || ! is_scalar( $amount ) ) {
			return null;
		}

		$amount = str_replace( array( ',', '٬', ' ' ), '', $this->normalize_digits( (string) $amount ) );

		return is_numeric( $amount ) ? (float) $amount : null;
	}

	private function format_amount( $amount ) {
		return null === $amount ? '—' : number_format( $amount, 0, '.', ',' ) . ' ' . __( 'تومان', 'technopay-payment-gateway-for-woocommerce' );
	}

	private function format_date( $value ) {
		if ( '' === $value ) {
			return '—';
		}

		try {
			$date = new DateTimeImmutable( $value );
		} catch ( Throwable $exception ) {
			return $this->normalize_digits( $value );
		}

		if ( class_exists( 'IntlDateFormatter' ) ) {
			try {
				$timezone = wp_timezone_string();
				$timezone = preg_match( '/^[+-]/', $timezone ) ? 'GMT' . $timezone : $timezone;
				$formatter = new IntlDateFormatter(
					'fa_IR@calendar=persian',
					IntlDateFormatter::NONE,
					IntlDateFormatter::NONE,
					$timezone,
					IntlDateFormatter::TRADITIONAL,
					'yyyy/MM/dd'
				);
				$formatted = $formatter->format( $date->getTimestamp() );

				if ( false !== $formatted ) {
					return $this->normalize_digits( $formatted );
				}
			} catch ( Throwable $exception ) {
				return $this->normalize_digits( wp_date( 'Y/m/d', $date->getTimestamp(), wp_timezone() ) );
			}
		}

		return $this->normalize_digits( wp_date( 'Y/m/d', $date->getTimestamp(), wp_timezone() ) );
	}

	private function normalize_status( $status ) {
		return sanitize_key( strtolower( str_replace( array( ' ', '-' ), '_', $status ) ) );
	}

	private function normalize_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'۰' => '0',
				'۱' => '1',
				'۲' => '2',
				'۳' => '3',
				'۴' => '4',
				'۵' => '5',
				'۶' => '6',
				'۷' => '7',
				'۸' => '8',
				'۹' => '9',
				'٠' => '0',
				'١' => '1',
				'٢' => '2',
				'٣' => '3',
				'٤' => '4',
				'٥' => '5',
				'٦' => '6',
				'٧' => '7',
				'٨' => '8',
				'٩' => '9',
			)
		);
	}
}
