<?php
/**
 * Uninstall file for TechnoPay Gateway
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up plugin data
delete_option('woocommerce_technopay_settings');

// Clean up any custom tables or data if needed
global $wpdb;

// Remove any custom meta data (HPOS compatible)
if (class_exists('WC_Order_Data_Store_CPT')) {
    // HPOS cleanup
    $data_store = WC_Data_Store::load('order');
    if (method_exists($data_store, 'delete_order_meta_by_key')) {
        $data_store->delete_order_meta_by_key('_technopay_track_number');
    }
} else {
    // Legacy cleanup
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_technopay_%'");
}

// Flush rewrite rules
flush_rewrite_rules();
