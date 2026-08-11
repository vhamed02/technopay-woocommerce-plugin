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

		wp_enqueue_style(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/css/admin-orders.css',
			array( 'dashicons' ),
			TPFW_VERSION
		);

		wp_enqueue_script(
			'tpfw-admin-orders',
			TPFW_PLUGIN_URL . 'assets/js/admin-orders.js',
			array(),
			TPFW_VERSION,
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

		$filters      = $this->get_filters();
		$current_page = max( 1, absint( $this->get_request_value( 'technopay_page' ) ) );
		$query        = new TPFW_TechnoPay_Order_Query( $filters );
		$results      = $query->get_results( $current_page, self::PER_PAGE );
		$view         = array(
			'filters'        => $filters,
			'rows'           => $this->get_rows( $results->orders, $current_page ),
			'status_options' => $this->get_status_options(),
			'pagination'     => $this->get_pagination( $results->max_num_pages, $current_page, $filters ),
			'reset_url'      => admin_url( 'admin.php?page=' . self::PAGE_SLUG ),
			'logo_url'       => TPFW_PLUGIN_URL . 'assets/images/technopay-logo.svg',
			'total'          => absint( $results->total ),
		);

		include TPFW_PLUGIN_PATH . 'templates/admin-orders-page.php';
	}

	private function get_filters() {
		$allowed_statuses = array_keys( $this->get_status_options() );
		$status           = sanitize_key( $this->get_request_value( 'order_status' ) );

		return array(
			'customer_mobile' => sanitize_text_field( $this->get_request_value( 'customer_mobile' ) ),
			'amount'          => $this->sanitize_amount( $this->get_request_value( 'amount' ) ),
			'status'          => in_array( $status, $allowed_statuses, true ) ? $status : '',
			'date_from'       => $this->sanitize_date( $this->get_request_value( 'date_from' ) ),
			'date_to'         => $this->sanitize_date( $this->get_request_value( 'date_to' ) ),
		);
	}

	private function get_request_value( $key ) {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_GET[ $key ] );
	}

	private function sanitize_amount( $amount ) {
		$amount = strtr(
			sanitize_text_field( $amount ),
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
		$amount = str_replace( array( ',', '٬', ' ' ), '', $amount );

		return is_numeric( $amount ) && (float) $amount >= 0 ? wc_format_decimal( $amount ) : '';
	}

	private function sanitize_date( $date ) {
		$date      = sanitize_text_field( $date );
		$date_time = DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return $date_time && $date_time->format( 'Y-m-d' ) === $date ? $date : '';
	}

	private function get_status_options() {
		return array(
			'processing' => __( 'تایید شده', 'technopay-payment-gateway-for-woocommerce' ),
			'completed'  => __( 'نهایی شده', 'technopay-payment-gateway-for-woocommerce' ),
			'refunded'   => __( 'استرداد شده', 'technopay-payment-gateway-for-woocommerce' ),
		);
	}

	private function get_rows( $orders, $current_page ) {
		$rows   = array();
		$offset = ( $current_page - 1 ) * self::PER_PAGE;

		foreach ( $orders as $index => $order ) {
			$total    = (float) $order->get_total();
			$refunded = abs( (float) $order->get_total_refunded() );
			$status   = $this->get_display_status( $order, $total, $refunded );
			$paid_at  = $order->get_date_paid();
			$rows[]   = array(
				'number'              => $this->localize_digits( (string) ( $offset + $index + 1 ) ),
				'customer_name'       => $this->get_customer_name( $order ),
				'customer_mobile'     => $this->localize_digits( $order->get_billing_phone() ),
				'customer_mobile_raw' => $order->get_billing_phone(),
				'track_number'        => $this->localize_digits( $order->get_meta( '_technopay_track_number' ) ),
				'track_number_raw'    => $order->get_meta( '_technopay_track_number' ),
				'paid_at'             => $paid_at ? $this->format_date( $paid_at ) : '—',
				'amount'              => wc_price( $total, array( 'currency' => $order->get_currency() ) ),
				'refund_amount'       => $refunded > 0 ? wc_price( $refunded, array( 'currency' => $order->get_currency() ) ) : '—',
				'status_label'        => $status['label'],
				'status_tone'         => $status['tone'],
				'order_url'           => $order->get_edit_order_url(),
				'can_refund'          => $order->is_paid() && $refunded < $total,
				'has_refund'          => $refunded > 0,
			);
		}

		return $rows;
	}

	private function get_customer_name( $order ) {
		$name = trim( $order->get_formatted_billing_full_name() );
		return $name !== '' ? $name : '—';
	}

	private function get_display_status( $order, $total, $refunded ) {
		if ( $refunded > 0 && $refunded >= $total ) {
			return array(
				'label' => __( 'استرداد کل مبلغ', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'  => 'danger',
			);
		}

		if ( $refunded > 0 ) {
			return array(
				'label' => __( 'استرداد بخشی از مبلغ', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'  => 'danger',
			);
		}

		if ( $order->has_status( 'completed' ) ) {
			return array(
				'label' => __( 'نهایی شده', 'technopay-payment-gateway-for-woocommerce' ),
				'tone'  => 'info',
			);
		}

		return array(
			'label' => __( 'تایید شده', 'technopay-payment-gateway-for-woocommerce' ),
			'tone'  => 'success',
		);
	}

	private function format_date( $date ) {
		if ( class_exists( 'IntlDateFormatter' ) ) {
			$timezone = wp_timezone_string();
			if ( preg_match( '/^[+-]/', $timezone ) ) {
				$timezone = 'GMT' . $timezone;
			}

			try {
				$formatter = new IntlDateFormatter(
					'fa_IR@calendar=persian',
					IntlDateFormatter::NONE,
					IntlDateFormatter::NONE,
					$timezone,
					IntlDateFormatter::TRADITIONAL,
					'yyyy/MM/dd'
				);
				$formatted = $formatter->format( $date->getTimestamp() );
				if ( $formatted !== false ) {
					return $formatted;
				}
			} catch ( Throwable $exception ) {
				return $this->localize_digits( wp_date( 'Y/m/d', $date->getTimestamp(), wp_timezone() ) );
			}
		}

		return $this->localize_digits( wp_date( 'Y/m/d', $date->getTimestamp(), wp_timezone() ) );
	}

	private function localize_digits( $value ) {
		return strtr(
			(string) $value,
			array(
				'0' => '۰',
				'1' => '۱',
				'2' => '۲',
				'3' => '۳',
				'4' => '۴',
				'5' => '۵',
				'6' => '۶',
				'7' => '۷',
				'8' => '۸',
				'9' => '۹',
			)
		);
	}

	private function get_pagination( $total_pages, $current_page, $filters ) {
		if ( $total_pages < 2 ) {
			return '';
		}

		$query_args = array(
			'page'           => self::PAGE_SLUG,
			'technopay_page' => 999999999,
		);
		foreach ( $filters as $key => $value ) {
			if ( $value !== '' ) {
				$query_args[ $key === 'status' ? 'order_status' : $key ] = $value;
			}
		}

		$base = add_query_arg( $query_args, admin_url( 'admin.php' ) );

		return paginate_links(
			array(
				'base'      => str_replace( '999999999', '%#%', esc_url( $base ) ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $total_pages,
				'mid_size'  => 2,
				'end_size'  => 1,
				'prev_text' => '<span class="dashicons dashicons-arrow-right-alt2"></span>',
				'next_text' => '<span class="dashicons dashicons-arrow-left-alt2"></span>',
				'type'      => 'list',
			)
		);
	}
}
