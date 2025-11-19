<?php
// Add this temporarily to debug
add_action('init', function() {
    if (current_user_can('manage_options')) {
        error_log('=== TechnoPay Debug ===');
        error_log('WC_Payment_Gateway exists: ' . (class_exists('WC_Payment_Gateway') ? 'YES' : 'NO'));
        error_log('TechnoPay_Gateway exists: ' . (class_exists('TechnoPay_Gateway') ? 'YES' : 'NO'));
        
        if (function_exists('WC')) {
            $gateways = WC()->payment_gateways->payment_gateways();
            error_log('Total gateways: ' . count($gateways));
            foreach ($gateways as $gateway) {
                error_log('Gateway: ' . $gateway->id . ' - ' . get_class($gateway));
            }
        }
    }
}, 999);
