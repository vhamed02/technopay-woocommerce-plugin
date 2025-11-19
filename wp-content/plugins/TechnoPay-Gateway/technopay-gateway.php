<?php
/**
 * Plugin Name: TechnoPay Gateway for WooCommerce
 * Plugin URI: https://technopay.ir
 * Description: TechnoPay payment gateway integration for WooCommerce
 * Version: 1.0.0
 * Author: TechnoPay
 * Author URI: https://technopay.ir
 * Developer: Ali Dalir
 * Text Domain: technopay-gateway
 * Domain Path: /languages
 * Language: fa_IR
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Woo: 12345:abcdefghijklmnopqrstuvwxyz123456
 * Requires Plugins: woocommerce
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('TECHNOPAY_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TECHNOPAY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TECHNOPAY_VERSION', '1.0.0');

/**
 * Main TechnoPay Gateway Class
 */
class TechnoPay_Gateway_Plugin {

    /**
     * Constructor
     */
    public function __construct() {
        // Declare HPOS compatibility early
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WC_Payment_Gateway class exists
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        // Include the gateway class
        require_once TECHNOPAY_PLUGIN_PATH . 'includes/class-technopay-gateway.php';

        // Add the gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway_class'));
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('technopay-gateway', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Add the gateway to WooCommerce
     */
    public function add_gateway_class($gateways) {
        $gateways[] = 'TechnoPay_Gateway';
        return $gateways;
    }

    /**
     * Declare HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        }
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=technopay') . '">' . __('Settings', 'technopay-gateway') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new TechnoPay_Gateway_Plugin();

// Debug - remove after testing
if (defined('WP_DEBUG') && WP_DEBUG) {
    require_once __DIR__ . '/debug-log.php';
}

// Activation hook
register_activation_hook(__FILE__, function() {
    // No rewrite rules needed - using WooCommerce API endpoints
});
