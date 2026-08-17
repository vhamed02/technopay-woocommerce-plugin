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
		add_action( 'admin_post_tpfw_cancel_refund', array( $this, 'handle_cancel_refund' ) );
		add_action( 'admin_post_tpfw_create_refund', array( $this, 'handle_create_refund' ) );
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

		$style_path       = TPFW_PLUGIN_PATH . 'assets/css/admin-orders.css';
		$script_path      = TPFW_PLUGIN_PATH . 'assets/js/admin-orders.js';
		$slimselect_js    = TPFW_PLUGIN_PATH . 'assets/js/slimselect.min.js';
		$slimselect_css   = TPFW_PLUGIN_PATH . 'assets/css/slimselect.css';

		wp_enqueue_style(
			'tpfw-slimselect',
			TPFW_PLUGIN_URL . 'assets/css/slimselect.css',
			array(),
			(string) filemtime( $slimselect_css )
		);

		wp_enqueue_style(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/css/admin-orders.css',
			array( 'dashicons', 'tpfw-slimselect' ),
			(string) filemtime( $style_path )
		);

		wp_enqueue_script(
			'tpfw-slimselect',
			TPFW_PLUGIN_URL . 'assets/js/slimselect.min.js',
			array(),
			(string) filemtime( $slimselect_js ),
			true
		);

		wp_enqueue_script(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/js/admin-orders.js',
			array( 'tpfw-slimselect' ),
			(string) filemtime( $script_path ),
			true
		);

		wp_localize_script(
			'tpfw-admin-orders',
			'tpfwAdminOrders',
			array(
				'amountRequired' => __( 'مبلغ استرداد را وارد کنید.', 'technopay-payment-gateway-for-woocommerce' ),
				'amountTooHigh'  => __( 'مبلغ استرداد نمی‌تواند بیشتر از مبلغ قابل استرداد باشد.', 'technopay-payment-gateway-for-woocommerce' ),
				'canceling'      => __( 'در حال لغو...', 'technopay-payment-gateway-for-woocommerce' ),
				'copied'         => __( 'کپی شد', 'technopay-payment-gateway-for-woocommerce' ),
				'copy'           => __( 'کپی', 'technopay-payment-gateway-for-woocommerce' ),
				'submitting'     => __( 'در حال ثبت...', 'technopay-payment-gateway-for-woocommerce' ),
			)
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این صفحه را ندارید.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$api_client = new TPFW_Technopay_Api_Client();
		$filters    = $this->get_filters();
		$cursor     = $this->sanitize_cursor( $this->get_request_value( 'cursor' ) );
		$row_offset = '' !== $cursor ? absint( $this->get_request_value( 'row_offset' ) ) : 0;
		$response   = $api_client->get_refunds( $this->get_api_query( $filters, $cursor ) );
		$results    = array();
		$metas      = array();
		$error      = '';

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
		} else {
			$results = $response['results'];
			$metas   = $response['metas'];
		}

		$rows    = $this->get_rows( $results, $row_offset );
		$reasons = $this->get_cached_reasons( $api_client );

		$view = array(
			'error'           => $error,
			'filters'         => $filters,
			'notice'          => $this->get_notice(),
			'pagination'      => $this->get_pagination( $metas, $filters, $row_offset, count( $rows ) ),
			'reasons'         => $reasons,
			'reset_url'       => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'rows'            => $rows,
			'status_options'  => $this->get_status_options(),
			'visible_results' => count( $rows ),
		);

		include TPFW_PLUGIN_PATH . 'templates/admin-orders-page.php';
	}

	public function handle_create_refund() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'شما اجازه ثبت درخواست استرداد را ندارید.', 'technopay-payment-gateway-for-woocommerce' ),
				esc_html__( 'خطای دسترسی', 'technopay-payment-gateway-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}

		$nonce = $this->get_post_value( 'tpfw_refund_nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'tpfw_create_refund' ) ) {
			$this->redirect_with_notice( 'error', __( 'اعتبار درخواست به پایان رسیده است. لطفا دوباره تلاش کنید.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$track_number = trim( $this->normalize_digits( sanitize_text_field( $this->get_post_value( 'track_number' ) ) ) );
		$amount       = $this->sanitize_amount( $this->get_post_value( 'requested_amount' ) );
		$reason_code  = sanitize_text_field( $this->get_post_value( 'refund_reason' ) );
		$description  = trim( sanitize_text_field( $this->get_post_value( 'refund_description' ) ) );

		if ( '' === $track_number || '' === $amount || 0 >= (int) $amount || '' === $reason_code ) {
			$this->redirect_with_notice( 'error', __( 'اطلاعات درخواست استرداد کامل یا معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$api_client  = new TPFW_Technopay_Api_Client();
		$reasons     = $this->get_cached_reasons( $api_client );
		$valid_codes = $this->get_valid_reason_codes( $api_client );

		if ( ! in_array( $reason_code, $valid_codes, true ) ) {
			$this->redirect_with_notice( 'error', __( 'دلیل استرداد انتخاب‌شده معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$selected_reason = null;
		foreach ( $reasons as $reason ) {
			if ( isset( $reason['code'] ) && (string) $reason['code'] === $reason_code ) {
				$selected_reason = $reason;
				break;
			}
		}

		$requires_description = $selected_reason && isset( $selected_reason['group'] ) && 'other_issues' === $selected_reason['group'];

		if ( $requires_description && '' === $description ) {
			$this->redirect_with_notice( 'error', __( 'وارد کردن توضیحات برای این دلیل الزامی است.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$available_amount = $this->get_refundable_amount( $api_client, $track_number );

		if ( is_wp_error( $available_amount ) ) {
			$this->redirect_with_notice( 'error', $available_amount->get_error_message() );
		}

		if ( (int) $amount > $available_amount ) {
			$this->redirect_with_notice( 'error', __( 'مبلغ استرداد نمی‌تواند بیشتر از مبلغ قابل استرداد باشد.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$response = $api_client->create_refund(
			$track_number,
			(int) $amount,
			array( $reason_code ),
			$requires_description ? $description : null
		);

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_notice( 'error', $response->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'درخواست استرداد با موفقیت ثبت شد.', 'technopay-payment-gateway-for-woocommerce' ) );
	}

	public function handle_cancel_refund() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'شما اجازه لغو درخواست استرداد را ندارید.', 'technopay-payment-gateway-for-woocommerce' ),
				esc_html__( 'خطای دسترسی', 'technopay-payment-gateway-for-woocommerce' ),
				array( 'response' => 403 )
			);
		}

		$nonce = $this->get_post_value( 'tpfw_cancel_refund_nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'tpfw_cancel_refund' ) ) {
			$this->redirect_with_notice( 'error', __( 'اعتبار درخواست به پایان رسیده است. لطفا دوباره تلاش کنید.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$track_number = trim( $this->normalize_digits( sanitize_text_field( $this->get_post_value( 'track_number' ) ) ) );

		if ( '' === $track_number ) {
			$this->redirect_with_notice( 'error', __( 'اطلاعات لغو درخواست استرداد کامل یا معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$api_client = new TPFW_Technopay_Api_Client();
		$state      = $this->get_refund_state( $api_client, $track_number );

		if ( is_wp_error( $state ) ) {
			$this->redirect_with_notice( 'error', $state->get_error_message() );
		}

		if ( 'cancel' !== $state['action'] ) {
			$this->redirect_with_notice( 'error', __( 'لغو درخواست استرداد برای این پرداخت امکان‌پذیر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$response = $api_client->cancel_refund( $track_number );

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_notice( 'error', $response->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'درخواست استرداد با موفقیت لغو شد.', 'technopay-payment-gateway-for-woocommerce' ) );
	}

	private function get_refundable_amount( $api_client, $track_number ) {
		$state = $this->get_refund_state( $api_client, $track_number );

		if ( is_wp_error( $state ) ) {
			return $state;
		}

		if ( 'refund' === $state['action'] && null !== $state['ticket_amount'] && $state['ticket_amount'] >= 1 ) {
			return (int) $state['ticket_amount'];
		}

		return new WP_Error(
			'tpfw_refund_not_allowed',
			__( 'ثبت درخواست استرداد برای این پرداخت امکان‌پذیر نیست.', 'technopay-payment-gateway-for-woocommerce' )
		);
	}

	private function get_refund_state( $api_client, $track_number ) {
		$response = $api_client->get_refunds(
			array(
				'filters'  => array( 'track_number' => $track_number ),
				'per_page' => 1,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		foreach ( $response['results'] as $result ) {
			if ( ! is_array( $result ) || $track_number !== $this->normalize_digits( $this->get_scalar_value( $result, 'track_number' ) ) ) {
				continue;
			}

			$ticket_amount = $this->parse_amount( isset( $result['ticket_amount'] ) ? $result['ticket_amount'] : null );
			$status        = $this->get_display_status(
				$this->get_scalar_value( $result, 'refund_status' ),
				$this->get_scalar_value( $result, 'ticket_status' ),
				$ticket_amount,
				$this->parse_amount( isset( $result['requested_amount'] ) ? $result['requested_amount'] : null )
			);

			return array(
				'action'        => $status['action'],
				'ticket_amount' => $ticket_amount,
			);
		}

		return new WP_Error(
			'tpfw_refund_not_found',
			__( 'اطلاعات درخواست استرداد این پرداخت یافت نشد.', 'technopay-payment-gateway-for-woocommerce' )
		);
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
		$today = new DateTimeImmutable( 'today', $this->get_timezone() );

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

	private function get_rows( $results, $row_offset ) {
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
			$refund_reasons   = $this->parse_reasons( isset( $result['refund_reasons'] ) ? $result['refund_reasons'] : array() );
			$reject_reasons   = $this->parse_reasons( isset( $result['reject_reasons'] ) ? $result['reject_reasons'] : array() );
			$display_reasons  = ! empty( $refund_reasons ) ? $refund_reasons : $reject_reasons;			$rows[] = array(
				'action'               => $status['action'],
				'amount'               => $this->format_amount( $ticket_amount ),
				'customer_mobile'      => $this->normalize_digits( $this->get_scalar_value( $result, 'customer_mobile' ) ),
				'customer_name'        => $this->get_display_value( $this->get_scalar_value( $result, 'customer_full_name' ) ),
				'has_reasons'          => ( ! empty( $refund_reasons ) || ! empty( $reject_reasons ) ) && ! in_array( $this->normalize_status( $refund_status ), array( 'canceled', 'cancelled' ), true ),
				'number'               => (string) ( $row_offset + $index + 1 ),
				'paid_at'              => $this->format_date( $this->get_scalar_value( $result, 'paid_at' ) ),
				'refund_amount'        => null !== $requested_amount && $requested_amount > 0 ? $this->format_amount( $requested_amount ) : '—',
				'refund_reasons'       => $refund_reasons,
				'reject_reasons'       => $reject_reasons,
				'requested_amount_raw' => null === $requested_amount ? '' : (string) $requested_amount,
				'status_label'         => $status['label'],
				'status_tone'          => $status['tone'],
				'ticket_amount_raw'    => null === $ticket_amount ? '' : (string) $ticket_amount,
				'track_number'         => $track_number,
			);
		}

		return $rows;
	}

	private function get_display_status( $refund_status, $ticket_status, $ticket_amount, $requested_amount ) {
		$refund_key = $this->normalize_status( $refund_status );
		$ticket_key = $this->normalize_status( $ticket_status );
		$pending    = array( 'pending', 'requested', 'waiting', 'in_progress', 'processing' );
		$completed  = array( 'approved', 'completed', 'done', 'refunded', 'success', 'successful', 'partial_refunded' );
		$is_settled = in_array( $ticket_key, array( 'settled', 'settle', 'completed', 'finalized' ), true );

		if ( in_array( $refund_key, $pending, true ) ) {
			return array(
				'action' => 'cancel',
				'label'  => __( 'در انتظار استرداد', 'technopay-payment-gateway-for-woocommerce' ),
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
				'action' => $is_settled ? 'none' : 'refund',
				'label'  => __( 'درخواست استرداد لغو شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'info',
			);
		}

		if ( in_array( $refund_key, array( 'failed', 'rejected' ), true ) ) {
			return array(
				'action' => $is_settled ? 'none' : 'refund',
				'label'  => __( 'درخواست استرداد رد شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'   => 'danger',
			);
		}

		if ( $is_settled ) {
			return array(
				'action' => 'none',
				'label'  => in_array( $ticket_key, array( 'settled', 'settle' ), true )
					? __( 'تسویه شده', 'technopay-payment-gateway-for-woocommerce' )
					: __( 'نهایی شده', 'technopay-payment-gateway-for-woocommerce' ),
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

	private function get_pagination( $metas, $filters, $row_offset, $visible_results ) {
		$previous_cursor = isset( $metas['prev_cursor'] ) && is_scalar( $metas['prev_cursor'] ) ? (string) $metas['prev_cursor'] : '';
		$next_cursor     = isset( $metas['next_cursor'] ) && is_scalar( $metas['next_cursor'] ) ? (string) $metas['next_cursor'] : '';
		$previous_offset = max( 0, $row_offset - self::PER_PAGE );
		$next_offset     = $row_offset + $visible_results;

		return array(
			'next_url'     => '' !== $next_cursor ? $this->get_cursor_url( $next_cursor, $filters, $next_offset ) : '',
			'previous_url' => '' !== $previous_cursor ? $this->get_cursor_url( $previous_cursor, $filters, $previous_offset ) : '',
		);
	}

	private function get_cursor_url( $cursor, $filters, $row_offset ) {
		$query_args = array(
			'page'       => self::PAGE_SLUG,
			'cursor'     => $cursor,
			'row_offset' => $row_offset,
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
			'pending'  => __( 'در انتظار استرداد', 'technopay-payment-gateway-for-woocommerce' ),
			'approved' => __( 'استرداد شده', 'technopay-payment-gateway-for-woocommerce' ),
			'rejected' => __( 'رد شده', 'technopay-payment-gateway-for-woocommerce' ),
			'canceled' => __( 'لغو شده', 'technopay-payment-gateway-for-woocommerce' ),
		);
	}

	private function get_cached_reasons( $api_client ) {
		$cached = get_transient( 'tpfw_refund_reasons' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$reasons = $api_client->get_reasons();

		if ( is_wp_error( $reasons ) ) {
			return array();
		}

		$reasons = array_values( $reasons );

		set_transient( 'tpfw_refund_reasons', $reasons, HOUR_IN_SECONDS );

		return $reasons;
	}

	private function get_valid_reason_codes( $api_client ) {
		$reasons = $this->get_cached_reasons( $api_client );

		return array_map(
			function ( $reason ) {
				return (string) $reason['code'];
			},
			$reasons
		);
	}

	private function parse_reasons( $reasons ) {
		if ( ! is_array( $reasons ) ) {
			return array();
		}

		$parsed = array();

		foreach ( $reasons as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$parsed[] = array(
				'code'        => isset( $item['code'] ) && is_scalar( $item['code'] ) ? sanitize_text_field( (string) $item['code'] ) : '',
				'reason'      => isset( $item['reason'] ) && is_scalar( $item['reason'] ) ? sanitize_text_field( (string) $item['reason'] ) : '',
				'description' => isset( $item['description'] ) && is_scalar( $item['description'] ) ? sanitize_text_field( (string) $item['description'] ) : '',
			);
		}

		return $parsed;
	}

	private function get_refund_reason_label( $reason ) {
		$cached = get_transient( 'tpfw_refund_reasons' );

		if ( is_array( $cached ) ) {
			foreach ( $cached as $item ) {
				if ( isset( $item['code'] ) && (string) $item['code'] === (string) $reason ) {
					return isset( $item['reason'] ) ? (string) $item['reason'] : $reason;
				}
			}
		}

		return $reason;
	}

	private function get_request_value( $key ) {
		$value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR );

		if ( ! is_string( $value ) ) {
			return '';
		}

		return sanitize_text_field( $value );
	}

	private function get_post_value( $key ) {
		$value = filter_input( INPUT_POST, $key, FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR );

		if ( ! is_string( $value ) ) {
			return '';
		}

		return sanitize_text_field( $value );
	}

	private function get_notice() {
		if ( '1' !== $this->get_request_value( 'tpfw_refund_notice' ) ) {
			return array();
		}

		$key    = 'tpfw_refund_notice_' . get_current_user_id();
		$notice = get_transient( $key );

		delete_transient( $key );

		if ( ! is_array( $notice ) || ! isset( $notice['type'], $notice['message'] ) ) {
			return array();
		}

		return array(
			'message' => sanitize_text_field( $notice['message'] ),
			'type'    => 'success' === $notice['type'] ? 'success' : 'error',
		);
	}

	private function redirect_with_notice( $type, $message ) {
		set_transient(
			'tpfw_refund_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => 'success' === $type ? 'success' : 'error',
			),
			MINUTE_IN_SECONDS
		);

		$redirect_url = wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		}

		$redirect_url = remove_query_arg( 'tpfw_refund_notice', $redirect_url );
		$redirect_url = add_query_arg( 'tpfw_refund_notice', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
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
				$timezone = $this->get_timezone()->getName();
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
				return $this->format_gregorian_date( $date );
			}
		}

		return $this->format_gregorian_date( $date );
	}

	private function format_gregorian_date( $date ) {
		return $this->normalize_digits( $date->setTimezone( $this->get_timezone() )->format( 'Y/m/d' ) );
	}

	private function get_timezone() {
		$timezone_string = get_option( 'timezone_string' );

		if ( is_string( $timezone_string ) && '' !== $timezone_string ) {
			try {
				return new DateTimeZone( $timezone_string );
			} catch ( Throwable $exception ) {
			}
		}

		$offset  = (float) get_option( 'gmt_offset', 0 );
		$hours   = (int) $offset;
		$minutes = (int) round( abs( $offset - $hours ) * 60 );
		$sign    = $offset < 0 ? '-' : '+';

		return new DateTimeZone( sprintf( '%s%02d:%02d', $sign, abs( $hours ), $minutes ) );
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
