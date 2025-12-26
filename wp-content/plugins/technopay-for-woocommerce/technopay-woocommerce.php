<?php
/**
 * Plugin Name: TechnoPay Payment Gateway for WooCommerce
 * Description: Secure credit payment gateway plugin for WooCommerce by TechnoPay
 * Version: 1.1.1
 * Author: vhamed32
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

define('TPFW_VERSION', '1.1.1');
define('TPFW_PLUGIN_FILE', __FILE__);
define('TPFW_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TPFW_PLUGIN_URL', plugin_dir_url(__FILE__));

class TPFW_Main {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('plugins_loaded', array($this, 'init'), 11);
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        add_filter('all_plugins', array($this, 'translate_plugin_meta'));
        add_filter('plugin_row_meta', array($this, 'translate_plugin_row_meta'), 10, 2);
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function load_textdomain() {
        $locale = apply_filters('tpfw_plugin_locale', determine_locale(), 'technopay-for-woocommerce');
        $mo_file = WP_LANG_DIR . '/plugins/technopay-for-woocommerce-' . $locale . '.mo';
        
        if (file_exists($mo_file)) {
            load_textdomain('technopay-for-woocommerce', $mo_file);
        } else {
            $mo_file_local = dirname(__FILE__) . '/languages/technopay-for-woocommerce-' . $locale . '.mo';
            if (file_exists($mo_file_local)) {
                load_textdomain('technopay-for-woocommerce', $mo_file_local);
            }
        }
    }
    
    public function init() {
        if (!class_exists('WC_Payment_Gateway')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        $this->includes();
        $this->init_hooks();
    }
    
    private function includes() {
        require_once TPFW_PLUGIN_PATH . 'includes/class-tpfw-technopay-gateway.php';
        require_once TPFW_PLUGIN_PATH . 'includes/class-tpfw-technopay-blocks-support.php';
    }
    
    private function init_hooks() {
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
        add_action('woocommerce_blocks_payment_method_type_registration', array($this, 'register_blocks_support'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
    }
    
    public function add_gateway($methods) {
        $methods[] = 'TPFW_TechnoPay_Gateway';
        return $methods;
    }
    
    public function register_blocks_support($payment_method_registry) {
        if (class_exists('TPFW_TechnoPay_Blocks_Support')) {
            $payment_method_registry->register(new TPFW_TechnoPay_Blocks_Support());
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
    
    public function translate_plugin_meta($plugins) {
        $plugin_file = plugin_basename(__FILE__);
        
        if (isset($plugins[$plugin_file])) {
            if (get_locale() === 'fa_IR') {
                $plugins[$plugin_file]['Name'] = 'تکنوپی برای ووکامرس';
                $plugins[$plugin_file]['Description'] = 'افزونه درگاه پرداخت اعتباری امن برای ووکامرس توسط تکنوپی';
                $plugins[$plugin_file]['Author'] = 'تکنوپی';
            } else {
                $plugins[$plugin_file]['Name'] = __('TechnoPay Payment Gateway for WooCommerce', 'technopay-for-woocommerce');
                $plugins[$plugin_file]['Description'] = __('Secure credit payment gateway plugin for WooCommerce by TechnoPay', 'technopay-for-woocommerce');
                $plugins[$plugin_file]['Author'] = __('vhamed32', 'technopay-for-woocommerce');
            }
        }
        
        return $plugins;
    }
    
    public function translate_plugin_row_meta($links, $file) {
        if (plugin_basename(__FILE__) === $file && get_locale() === 'fa_IR') {
            foreach ($links as $key => $link) {
                if (strpos($link, 'technopay.ir') !== false) {
                    $links[$key] = str_replace('Visit plugin site', 'مشاهده سایت افزونه', $link);
                }
                if (strpos($link, 'View details') !== false) {
                    $links[$key] = str_replace('View details', 'مشاهده جزئیات', $link);
                }
            }
        }
        return $links;
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

TPFW_Main::get_instance();
