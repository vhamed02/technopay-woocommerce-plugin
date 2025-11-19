<?php
if (!defined('ABSPATH')) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_TechnoPay_Blocks_Support extends AbstractPaymentMethodType {

    private $gateway;
    protected $name = 'technopay';

    public function initialize() {
        $this->settings = get_option("woocommerce_{$this->name}_settings", array());
        $gateways = WC()->payment_gateways->payment_gateways();
        $this->gateway = isset($gateways[$this->name]) ? $gateways[$this->name] : null;
    }

    public function is_active() {
        return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
    }

    public function get_payment_method_script_handles() {
        $script_path = 'assets/js/index.js';
        $script_asset_path = TECHNOPAY_WC_PLUGIN_PATH . 'assets/js/index.asset.php';
        $script_asset = file_exists($script_asset_path)
            ? require($script_asset_path)
            : array(
                'dependencies' => array(
                    'wc-blocks-registry',
                    'wc-settings',
                    'wp-element',
                    'wp-html-entities',
                    'wp-i18n',
                ),
                'version' => TECHNOPAY_WC_VERSION
            );

        wp_register_script(
            'wc-technopay-blocks-integration',
            TECHNOPAY_WC_PLUGIN_URL . $script_path,
            $script_asset['dependencies'],
            $script_asset['version'],
            true
        );

        if (function_exists('wp_set_script_translations')) {
            wp_set_script_translations('wc-technopay-blocks-integration', 'technopay-wc');
        }

        return array('wc-technopay-blocks-integration');
    }

    public function get_payment_method_data() {
        $icon_url = TECHNOPAY_WC_PLUGIN_URL . 'assets/images/technopay-logo.svg';
        
        return array(
            'title' => $this->get_setting('title', __('تکنوپی', 'technopay-wc')),
            'description' => $this->get_setting('description', __('پرداخت اقساطی از طریق تکنوپی', 'technopay-wc')),
            'icon' => file_exists(TECHNOPAY_WC_PLUGIN_PATH . 'assets/images/technopay-logo.svg') ? $icon_url : '',
            'supports' => $this->gateway ? array_filter($this->gateway->supports, array($this->gateway, 'supports')) : array(),
        );
    }
}
