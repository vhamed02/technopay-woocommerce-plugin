<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once TPFW_PLUGIN_PATH . 'includes/orders/class-tpfw-orders-formatter.php';
require_once TPFW_PLUGIN_PATH . 'includes/orders/class-tpfw-refund-status.php';
require_once TPFW_PLUGIN_PATH . 'includes/orders/interface-tpfw-orders-tab.php';
require_once TPFW_PLUGIN_PATH . 'includes/orders/class-tpfw-refundable-tickets-tab.php';
require_once TPFW_PLUGIN_PATH . 'includes/orders/class-tpfw-refunds-tab.php';

final class TPFW_Admin_Orders_Page {

	const PAGE_SLUG         = 'technopay-orders';
	const PER_PAGE          = 10;
	const REASONS_TRANSIENT = 'tpfw_refund_reasons_v2';

	private $hook_suffix = '';
	private $formatter;
	private $tabs = array();

	public function __construct() {
		$this->formatter = new TPFW_Orders_Formatter();

		foreach ( array( new TPFW_Refundable_Tickets_Tab( $this->formatter ), new TPFW_Refunds_Tab( $this->formatter ) ) as $tab ) {
			$this->tabs[ $tab->get_slug() ] = $tab;
		}

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

		$style_path     = TPFW_PLUGIN_PATH . 'assets/css/admin-orders.css';
		$script_path    = TPFW_PLUGIN_PATH . 'assets/js/admin-orders.js';
		$slimselect_js  = TPFW_PLUGIN_PATH . 'assets/js/slimselect.min.js';
		$slimselect_css = TPFW_PLUGIN_PATH . 'assets/css/slimselect.css';

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
		$tab        = $this->get_active_tab();
		$filters    = $this->get_filters();
		$cursor     = $this->sanitize_cursor( $this->get_request_value( 'cursor' ) );
		$row_offset = '' !== $cursor ? absint( $this->get_request_value( 'row_offset' ) ) : 0;
		$response   = $tab->fetch( $api_client, $this->get_api_query( $tab, $filters, $cursor ) );
		$results    = array();
		$metas      = array();
		$error      = '';

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
		} else {
			$results = $response['results'];
			$metas   = $response['metas'];
		}

		$rows = $tab->get_rows( $results, $row_offset );

		$view = array(
			'active_tab'      => $tab->get_slug(),
			'date_label'      => $tab->get_date_column_label(),
			'empty_message'   => $tab->get_empty_message(),
			'error'           => $error,
			'filters'         => $filters,
			'notice'          => $this->get_notice(),
			'pagination'      => $this->get_pagination( $tab, $metas, $filters, $row_offset, count( $rows ) ),
			'reasons'         => $this->get_reason_options( $api_client ),
			'refund_label'    => $tab->get_refund_column_label(),
			'reset_url'       => $this->get_page_url( $tab->get_slug() ),
			'rows'            => $rows,
			'status_options'  => $this->get_status_options(),
			'supports_status' => '' !== $tab->get_status_filter_key(),
			'tabs'            => $this->get_tab_links( $tab, $filters ),
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

		$track_number = $this->sanitize_track_number( $this->get_post_value( 'track_number' ) );
		$amount       = $this->sanitize_amount( $this->get_post_value( 'requested_amount' ) );
		$reason_code  = sanitize_text_field( $this->get_post_value( 'refund_reason' ) );
		$description  = trim( sanitize_text_field( $this->get_post_value( 'refund_description' ) ) );

		if ( '' === $track_number || '' === $amount || 0 >= (int) $amount || '' === $reason_code ) {
			$this->redirect_with_notice( 'error', __( 'اطلاعات درخواست استرداد کامل یا معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$api_client = new TPFW_Technopay_Api_Client();
		$reasons    = $this->get_cached_reasons( $api_client );

		if ( ! in_array( $reason_code, $this->get_valid_reason_codes( $reasons ), true ) ) {
			$this->redirect_with_notice( 'error', __( 'دلیل استرداد انتخاب‌شده معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$requires_description = $this->requires_description( $reasons, $reason_code );

		if ( $requires_description && '' === $description ) {
			$this->redirect_with_notice( 'error', __( 'وارد کردن توضیحات برای این دلیل الزامی است.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$available_amount = $this->get_tab( TPFW_Refundable_Tickets_Tab::SLUG )->get_refundable_amount( $api_client, $track_number );

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

		$this->redirect_with_notice(
			'success',
			__( 'درخواست استرداد با موفقیت ثبت شد.', 'technopay-payment-gateway-for-woocommerce' ),
			TPFW_Refunds_Tab::SLUG
		);
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

		$track_number = $this->sanitize_track_number( $this->get_post_value( 'track_number' ) );

		if ( '' === $track_number ) {
			$this->redirect_with_notice( 'error', __( 'اطلاعات لغو درخواست استرداد کامل یا معتبر نیست.', 'technopay-payment-gateway-for-woocommerce' ) );
		}

		$api_client = new TPFW_Technopay_Api_Client();
		$cancelable = $this->get_tab( TPFW_Refunds_Tab::SLUG )->ensure_cancelable( $api_client, $track_number );

		if ( is_wp_error( $cancelable ) ) {
			$this->redirect_with_notice( 'error', $cancelable->get_error_message() );
		}

		$response = $api_client->cancel_refund( $track_number );

		if ( is_wp_error( $response ) ) {
			$this->redirect_with_notice( 'error', $response->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'درخواست استرداد با موفقیت لغو شد.', 'technopay-payment-gateway-for-woocommerce' ) );
	}

	private function get_active_tab() {
		$slug = sanitize_key( $this->get_request_value( 'tab' ) );

		return $this->get_tab( $slug );
	}

	private function get_tab( $slug ) {
		return isset( $this->tabs[ $slug ] ) ? $this->tabs[ $slug ] : $this->tabs[ TPFW_Refundable_Tickets_Tab::SLUG ];
	}

	private function get_tab_links( TPFW_Orders_Tab $active_tab, $filters ) {
		$links = array();

		foreach ( $this->tabs as $slug => $tab ) {
			$links[] = array(
				'is_active' => $slug === $active_tab->get_slug(),
				'label'     => $tab->get_label(),
				'url'       => $this->get_page_url( $slug, $filters ),
			);
		}

		return $links;
	}

	private function get_filters() {
		$status_options = $this->get_status_options();
		$status         = sanitize_key( $this->get_request_value( 'order_status' ) );
		$period         = sanitize_key( $this->get_request_value( 'order_period' ) );
		$periods        = array( 'today', 'yesterday', 'last-7-days', 'last-30-days', 'current-month' );

		return array(
			'amount'          => $this->sanitize_amount( $this->get_request_value( 'amount' ) ),
			'customer_mobile' => $this->formatter->normalize_digits( sanitize_text_field( $this->get_request_value( 'customer_mobile' ) ) ),
			'period'          => in_array( $period, $periods, true ) ? $period : '',
			'status'          => isset( $status_options[ $status ] ) ? $status : '',
			'track_number'    => $this->sanitize_track_number( $this->get_request_value( 'track_number' ) ),
		);
	}

	private function get_api_query( TPFW_Orders_Tab $tab, $filters, $cursor ) {
		$api_filters = array();

		if ( '' !== $filters['customer_mobile'] ) {
			$api_filters['customer_mobile'] = $filters['customer_mobile'];
		}

		if ( '' !== $filters['track_number'] ) {
			$api_filters['track_number'] = $filters['track_number'];
		}

		if ( '' !== $filters['amount'] ) {
			$api_filters['ticket_amount'] = (int) $filters['amount'];
		}

		$status_filter_key = $tab->get_status_filter_key();

		if ( '' !== $filters['status'] && '' !== $status_filter_key ) {
			$api_filters[ $status_filter_key ] = $filters['status'];
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
		$today = new DateTimeImmutable( 'today', $this->formatter->get_timezone() );

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

	private function get_pagination( TPFW_Orders_Tab $tab, $metas, $filters, $row_offset, $visible_results ) {
		$previous_cursor = isset( $metas['prev_cursor'] ) && is_scalar( $metas['prev_cursor'] ) ? (string) $metas['prev_cursor'] : '';
		$next_cursor     = isset( $metas['next_cursor'] ) && is_scalar( $metas['next_cursor'] ) ? (string) $metas['next_cursor'] : '';

		return array(
			'next_url'     => '' !== $next_cursor
				? $this->get_page_url( $tab->get_slug(), $filters, $next_cursor, $row_offset + $visible_results )
				: '',
			'previous_url' => '' !== $previous_cursor
				? $this->get_page_url( $tab->get_slug(), $filters, $previous_cursor, max( 0, $row_offset - self::PER_PAGE ) )
				: '',
		);
	}

	private function get_page_url( $tab_slug, $filters = array(), $cursor = '', $row_offset = 0 ) {
		$query_args = array(
			'page' => self::PAGE_SLUG,
			'tab'  => $tab_slug,
		);

		if ( '' !== $cursor ) {
			$query_args['cursor']     = $cursor;
			$query_args['row_offset'] = $row_offset;
		}

		$filter_args = array(
			'customer_mobile' => 'customer_mobile',
			'track_number'    => 'track_number',
			'amount'          => 'amount',
			'status'          => 'order_status',
			'period'          => 'order_period',
		);

		foreach ( $filter_args as $filter_key => $query_key ) {
			if ( isset( $filters[ $filter_key ] ) && '' !== $filters[ $filter_key ] ) {
				$query_args[ $query_key ] = $filters[ $filter_key ];
			}
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
		$cached = get_transient( self::REASONS_TRANSIENT );

		if ( is_array( $cached ) && ! empty( $cached ) ) {
			return $cached;
		}

		$reasons = $api_client->get_reasons();

		if ( is_wp_error( $reasons ) ) {
			return array();
		}

		$reasons = array_values( $reasons );

		if ( ! empty( $reasons ) ) {
			set_transient( self::REASONS_TRANSIENT, $reasons, HOUR_IN_SECONDS );
		}

		return $reasons;
	}

	private function get_reason_options( $api_client ) {
		$options = array();

		foreach ( $this->get_cached_reasons( $api_client ) as $reason ) {
			$options[] = array(
				'code'                 => $this->formatter->get_scalar_value( $reason, 'code' ),
				'reason'               => $this->formatter->get_scalar_value( $reason, 'reason' ),
				'requires_description' => $this->reason_requires_description( $reason ),
				'text'                 => $this->formatter->get_scalar_value( $reason, 'text' ),
			);
		}

		return $options;
	}

	private function reason_requires_description( $reason ) {
		if ( isset( $reason['group'] ) && 'other_issues' === $reason['group'] ) {
			return true;
		}

		return '' === $this->formatter->get_scalar_value( $reason, 'text' );
	}

	private function get_valid_reason_codes( $reasons ) {
		return array_map(
			function ( $reason ) {
				return (string) $reason['code'];
			},
			$reasons
		);
	}

	private function requires_description( $reasons, $reason_code ) {
		foreach ( $reasons as $reason ) {
			if ( isset( $reason['code'] ) && (string) $reason['code'] === $reason_code ) {
				return $this->reason_requires_description( $reason );
			}
		}

		return false;
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

	private function redirect_with_notice( $type, $message, $tab_slug = '' ) {
		set_transient(
			'tpfw_refund_notice_' . get_current_user_id(),
			array(
				'message' => sanitize_text_field( $message ),
				'type'    => 'success' === $type ? 'success' : 'error',
			),
			MINUTE_IN_SECONDS
		);

		$redirect_url = '' !== $tab_slug ? $this->get_page_url( $tab_slug ) : wp_get_referer();

		if ( ! $redirect_url ) {
			$redirect_url = $this->get_page_url( TPFW_Refundable_Tickets_Tab::SLUG );
		}

		$redirect_url = remove_query_arg( 'tpfw_refund_notice', $redirect_url );
		$redirect_url = add_query_arg( 'tpfw_refund_notice', '1', $redirect_url );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function sanitize_amount( $amount ) {
		$amount = $this->formatter->normalize_digits( sanitize_text_field( $amount ) );
		$amount = str_replace( array( ',', '٬', ' ' ), '', $amount );

		return ctype_digit( $amount ) ? $amount : '';
	}

	private function sanitize_track_number( $track_number ) {
		return trim( $this->formatter->normalize_digits( sanitize_text_field( $track_number ) ) );
	}

	private function sanitize_cursor( $cursor ) {
		return sanitize_text_field( $cursor );
	}
}
