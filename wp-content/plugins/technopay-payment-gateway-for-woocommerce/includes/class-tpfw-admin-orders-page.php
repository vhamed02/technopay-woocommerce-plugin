<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TPFW_Admin_Orders_Page {

	const PAGE_SLUG = 'technopay-orders';

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

		include TPFW_PLUGIN_PATH . 'templates/admin-orders-page.php';
	}
}
