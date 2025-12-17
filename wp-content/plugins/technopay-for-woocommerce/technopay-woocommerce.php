<?php
/**
 * Plugin Name: TechnoPay for WooCommerce
 * Plugin URI: https://technopay.ir
 * Description: Secure credit payment gateway plugin for WooCommerce by TechnoPay
 * Version: 1.0.1
 * Author: TechnoPay
 * Author URI: https://technopay.ir
 * Text Domain: technopay-for-woocommerce
 * Requires at least: 5.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

use Automattic\WooCommerce\Utilities\FeaturesUtil;

if (!defined('ABSPATH')) exit;

define('TECHNOPAY_WC_VERSION', '1.0.1');
define('TECHNOPAY_WC_PLUGIN_FILE', __FILE__);
define('TECHNOPAY_WC_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TECHNOPAY_WC_PLUGIN_URL', plugin_dir_url(__FILE__));

class TechnoPay_WC_Main {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 11);
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->load_textdomain();
        $this->includes();
        $this->init_hooks();
    }
    
    private function load_textdomain() {
        // WordPress automatically loads translations for plugins hosted on WordPress.org
        // This function is kept for backward compatibility but is no longer needed
    }
    
    private function includes() {
        require_once TECHNOPAY_WC_PLUGIN_PATH . 'includes/class-wc-technopay-gateway.php';
        require_once TECHNOPAY_WC_PLUGIN_PATH . 'includes/class-wc-technopay-blocks-support.php';
    }
    
    private function init_hooks() {
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
        add_action('woocommerce_blocks_payment_method_type_registration', array($this, 'register_blocks_support'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    public function add_gateway($methods) {
        $methods[] = 'WC_TechnoPay_Gateway';
        return $methods;
    }
    
    public function register_blocks_support($payment_method_registry) {
        if (class_exists('WC_TechnoPay_Blocks_Support')) {
            $payment_method_registry->register(new WC_TechnoPay_Blocks_Support());
        }
    }
    
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('admin.php?page=wc-settings&tab=checkout&section=technopay'),
            __('Settings', 'technopay-for-woocommerce')
        );
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }
    
    public function woocommerce_missing_notice() {
        echo '<div class="error"><p><strong>' . esc_html__('TechnoPay', 'technopay-for-woocommerce') . '</strong> ' . esc_html__('requires WooCommerce to be installed and activated.', 'technopay-for-woocommerce') . '</p></div>';
    }
    
    public function activate() {
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('This plugin requires WooCommerce.', 'technopay-for-woocommerce'));
        }
    }
    
    public function deactivate() {
        wp_cache_flush();
    }
}

TechnoPay_WC_Main::get_instance();
