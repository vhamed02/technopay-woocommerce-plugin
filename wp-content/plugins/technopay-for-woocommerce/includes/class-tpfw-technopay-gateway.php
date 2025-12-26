<?php
if (!defined('ABSPATH')) exit;

class TPFW_TechnoPay_Gateway extends WC_Payment_Gateway
{

    private $api_url;

    public function __construct()
    {
        $this->id = 'technopay';
        $this->icon = TPFW_PLUGIN_URL . 'assets/images/technopay-logo.svg';
        $this->method_title = __('TechnoPay', 'technopay-for-woocommerce');
        $this->method_description = __('Credit payment via TechnoPay', 'technopay-for-woocommerce');
        $this->has_fields = false;
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled', 'yes');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');
        $this->testmode = $this->get_option('testmode');
        $this->currency_mode = $this->get_option('currency_mode');

        $this->api_url = 'yes' === $this->testmode
            ? 'https://credit-api.dev.tgms.ir/payment'
            : 'https://api.technopay.ir/payment';

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_technopay_callback', array($this, 'process_callback'));
        add_action('woocommerce_api_technopay_fallback', array($this, 'process_fallback'));
        add_action('woocommerce_checkout_process', array($this, 'validate_billing_phone'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable', 'technopay-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable TechnoPay payment', 'technopay-for-woocommerce'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('Title', 'technopay-for-woocommerce'),
                'type' => 'text',
                'default' => __('TechnoPay', 'technopay-for-woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'technopay-for-woocommerce'),
                'type' => 'text',
                'default' => __('Credit payment via TechnoPay', 'technopay-for-woocommerce'),
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'technopay-for-woocommerce'),
                'type' => 'text',
                'default' => '',
            ),
            'merchant_secret' => array(
                'title' => __('Secret Key', 'technopay-for-woocommerce'),
                'type' => 'password',
                'default' => '',
            ),
            'testmode' => array(
                'title' => __('Test Mode', 'technopay-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable test mode', 'technopay-for-woocommerce'),
                'default' => 'no',
                'description' => __('In test mode, payments are sent to test server', 'technopay-for-woocommerce'),
            ),
            'currency_mode' => array(
                'title' => __('Currency Type', 'technopay-for-woocommerce'),
                'type' => 'select',
                'default' => 'auto',
                'options' => array(
                    'auto' => __('Auto Detect', 'technopay-for-woocommerce'),
                    'irr' => __('Rial (IRR)', 'technopay-for-woocommerce'),
                    'irt' => __('Toman (IRT)', 'technopay-for-woocommerce'),
                ),
            ),
        );
    }

    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (empty($this->merchant_id) || empty($this->merchant_secret)) {
            return false;
        }

        if (!WC()->cart || !WC()->cart->needs_payment()) {
            return false;
        }

        $currency = get_woocommerce_currency();
        if (!in_array($currency, array('IRR', 'IRT'), true)) {
            return false;
        }

        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            wc_add_notice(esc_html__('Order not found.', 'technopay-for-woocommerce'), 'error');
            return array('result' => 'fail');
        }

        if (!$this->validate_gateway_config()) {
            wc_add_notice(esc_html__('Payment gateway is not configured properly.', 'technopay-for-woocommerce'), 'error');
            return array('result' => 'fail');
        }
        
        $mobile_number = $order->get_billing_phone();
        if (!empty($mobile_number)) {
            $mobile_number = preg_replace('/[^0-9]/', '', $mobile_number);
            $mobile_number = $this->normalize_iranian_mobile($mobile_number);
            
            $balance_check = $this->check_customer_balance($mobile_number);
            if (is_string($balance_check)) {
                throw new \Exception(esc_html($balance_check));
            }
        }

        try {
            $ticket_data = $this->create_payment_ticket($order);

            if ($ticket_data && isset($ticket_data['payment_uri']) && isset($ticket_data['track_number'])) {
                $order->update_meta_data('_technopay_track_number', sanitize_text_field($ticket_data['track_number']));
                $order->save();

                $this->log('Payment ticket created successfully. Track number: ' . $ticket_data['track_number']);

                return array(
                    'result' => 'success',
                    'redirect' => esc_url_raw($ticket_data['payment_uri'])
                );
            } else {
                throw new Exception(esc_html__('Payment ticket creation failed.', 'technopay-for-woocommerce'));
            }

        } catch (Exception $e) {
            $this->log('Payment failed: ' . $e->getMessage());
            
            $error_message = $e->getMessage();
            if (empty($error_message)) {
                $error_message = esc_html__('Unknown error occurred.', 'technopay-for-woocommerce');
            }
            
            wc_add_notice(esc_html($error_message), 'error');
            
            if (strpos($error_message, 'wallet balance') === false) {
                $order->add_order_note(esc_html__('TechnoPay payment failed: ', 'technopay-for-woocommerce') . esc_html($error_message));
            }
            
            return array(
                'result' => 'fail',
                'messages' => $error_message
            );
        }
    }

    private function validate_gateway_config()
    {
        return !empty($this->merchant_id) && !empty($this->merchant_secret);
    }
    
    private function check_customer_balance($mobile_number)
    {
        $timestamp = time();
        $payment_type = 'cpg';
        
        try {
            $signature = $this->generate_signature($this->merchant_id, $this->merchant_secret, $timestamp, $payment_type);
            
            $balance_response = wp_remote_get($this->api_url . '/wallets/balance?mobile=' . $mobile_number, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'signature' => $signature,
                    'merchantId' => $this->merchant_id,
                    'User-Agent' => 'technopay-for-woocommerce/' . TPFW_VERSION
                ],
                'timeout' => 30,
                'sslverify' => true
            ]);
            
            if (!is_wp_error($balance_response)) {
                $balance_body = wp_remote_retrieve_body($balance_response);
                $balance_data = json_decode($balance_body, true);
                
                if (is_array($balance_data) && $balance_data['resCode'] == 0 && $balance_data['results']['balance'] == 0) {
                    return 'موجودی کیف پول شما در تکنوپی صفر می‌باشد.';
                }
            }
        } catch (Exception $e) {
            // Silent fail - continue to purchase
        }
        
        return true;
    }



    private function create_payment_ticket($order)
    {
        $timestamp = time();
        $payment_type = 'cpg';

        $signature = $this->generate_signature($this->merchant_id, $this->merchant_secret, $timestamp, $payment_type);

        $amount = $this->calculate_amount_for_api($order);
        $ticket_number = 'WC' . $order->get_id() . '_' . $timestamp;

        $mobile_number = $this->get_customer_mobile_number($order);
        if (empty($mobile_number)) {
            throw new Exception(esc_html__('Customer mobile number is required.', 'technopay-for-woocommerce'));
        }

        $mobile_number = $this->normalize_iranian_mobile($mobile_number);
        if (!$this->is_valid_iranian_mobile($mobile_number)) {
            throw new Exception(esc_html__('Please enter a valid Iranian mobile number.', 'technopay-for-woocommerce'));
        }

        $items = $this->prepare_order_items($order);
        $callback_url = add_query_arg('wc_order', $order->get_id(), $this->get_callback_url());
        $fallback_url = add_query_arg('wc_order', $order->get_id(), $this->get_fallback_url());

        $request_data = array(
            'amount' => $amount,
            'ticket_number' => $ticket_number,
            'redirect_uri' => $callback_url,
            'fallback_uri' => $fallback_url,
            'mobile_number' => $mobile_number,
            'items' => $items
        );

        $response = $this->make_api_request('/purchase', $request_data, $signature, $timestamp);

        if ($response && isset($response['succeed']) && $response['succeed']) {
            return $response['results'];
        } else {
            $error_message = isset($response['message']) ? $response['message'] : esc_html__('Unknown error occurred.', 'technopay-for-woocommerce');
            throw new Exception(esc_html($error_message));
        }
    }

    private function prepare_order_items($order)
    {
        $items = array();
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $items[] = array(
                'name' => sanitize_text_field($item->get_name()),
                'amount' => $this->calculate_item_amount_for_api($item),
                'qty' => absint($item->get_quantity()),
                'url' => $product ? esc_url_raw(get_permalink($product->get_id())) : ''
            );
        }
        return $items;
    }

    private function generate_signature($merchant_id, $merchant_secret, $timestamp, $payment_type)
    {
        $plain_signature = $merchant_id . ';' . $timestamp . ';' . $payment_type . ';' . $merchant_secret;

        $key = base64_decode($merchant_secret);

        if (strlen($key) < 16) {
            $key = str_pad($key, 16, "\0");
        } else {
            $key = substr($key, 0, 16);
        }

        $iv = openssl_random_pseudo_bytes(16);

        $encrypted = openssl_encrypt($plain_signature, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv);

        if ($encrypted === false) {
            throw new Exception(esc_html__('Digital signature creation failed.', 'technopay-for-woocommerce'));
        }

        $json_data = json_encode(array(
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted)
        ));

        return base64_encode($json_data);
    }

    private function make_api_request($endpoint, $data, $signature, $timestamp)
    {
        $url = $this->api_url . $endpoint;

        $args = array(
            'method' => 'POST',
            'headers' => array(
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'signature' => $signature,
                'merchantId' => $this->merchant_id,
                'User-Agent' => 'technopay-for-woocommerce/' . TPFW_VERSION
            ),
            'body' => wp_json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        );

        $this->log('API Request: ' . $url);
        $this->log('Request Data: ' . wp_json_encode($data));

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message());
            throw new Exception(esc_html($response->get_error_message()));
        }

        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);

        $this->log('API Response Code: ' . $http_code);
        $this->log('API Response Body: ' . $body);

        $decoded_response = json_decode($body, true);

        if ($http_code >= 400) {
            $error_message = isset($decoded_response['message']) ? $decoded_response['message'] : esc_html__('HTTP Error: ', 'technopay-for-woocommerce') . $http_code;
            $this->log('API Error Message: ' . $error_message);
            throw new Exception(esc_html($error_message));
        }

        return $decoded_response;
    }

    private function get_callback_url()
    {
        return WC()->api_request_url('technopay_callback');
    }

    private function get_fallback_url()
    {
        return WC()->api_request_url('technopay_fallback');
    }

    public function process_callback()
    {
        $this->init_settings();
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a payment gateway callback
        $status = isset($_REQUEST['Status']) ? sanitize_text_field(wp_unslash($_REQUEST['Status'])) : '';
        if (!empty($status) && strtoupper($status) !== 'OK') {
            wc_add_notice(esc_html__('Payment was cancelled by user.', 'technopay-for-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        $order = $this->get_order_from_request();

        if (!$order) {
            wc_add_notice(esc_html__('Order not found.', 'technopay-for-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        if ($order->is_paid()) {
            wp_safe_redirect($order->get_checkout_order_received_url());
            exit;
        }

        $track_number = $this->get_request_track_number();
        if (empty($track_number)) {
            $track_number = $order->get_meta('_technopay_track_number');
        }

        if (empty($track_number)) {
            $this->log('Track number not found in request or order meta');
            wc_add_notice(esc_html__('Track number not found.', 'technopay-for-woocommerce'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }

        try {
            $verification_result = $this->verify_payment($track_number);
            
            if ($verification_result && isset($verification_result['succeed']) && $verification_result['succeed']) {
                $order->payment_complete();
                $order->add_order_note(esc_html__('Payment completed successfully via TechnoPay. Track number: ', 'technopay-for-woocommerce') . esc_html($track_number));
                
                WC()->cart->empty_cart();
                
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            } else {
                $error_message = isset($verification_result['message']) ? $verification_result['message'] : esc_html__('Payment verification failed.', 'technopay-for-woocommerce');
                $order->update_status('failed', $error_message);
                $order->add_order_note(esc_html__('TechnoPay payment verification failed: ', 'technopay-for-woocommerce') . esc_html($error_message));
                wc_add_notice(esc_html($error_message), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
            
        } catch (Exception $e) {
            $this->log('Callback error: ' . $e->getMessage());
            $order->update_status('failed', $e->getMessage());
            $order->add_order_note(esc_html__('Error in TechnoPay callback: ', 'technopay-for-woocommerce') . esc_html($e->getMessage()));
            wc_add_notice(esc_html($e->getMessage()), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }

    private function get_order_from_request()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a payment gateway callback
        $sanitized_request = map_deep($_REQUEST, 'sanitize_text_field');
        $this->log('Getting order from request. REQUEST params: ' . wp_json_encode($sanitized_request));

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a payment gateway callback
        $wc_order_id = isset($_REQUEST['wc_order']) ? absint($_REQUEST['wc_order']) : 0;
        if ($wc_order_id) {
            $this->log('Found wc_order parameter: ' . $wc_order_id);
            $order = wc_get_order($wc_order_id);
            if ($order) {
                $this->log('Order found by wc_order ID: ' . $order->get_id());
                return $order;
            }
        }

        $track_number = $this->get_request_track_number();
        if (!empty($track_number)) {
            $this->log('Trying to find order by track_number: ' . $track_number);
            $order = $this->get_order_by_track_number($track_number);
            if ($order) {
                $this->log('Order found by track_number: ' . $order->get_id());
                return $order;
            }
        }

        $this->log('No order found from request');
        return false;
    }

    public function process_fallback()
    {
        $this->init_settings();
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');

        $order = $this->get_order_from_request();
        $track_number = $this->get_request_track_number();

        if ($order && !$order->is_paid()) {
            $order->update_status('failed', esc_html__('Payment via TechnoPay failed.', 'technopay-for-woocommerce'));
            if (!empty($track_number)) {
                $order->add_order_note(esc_html__('Payment via TechnoPay failed. Track number: ', 'technopay-for-woocommerce') . esc_html($track_number));
            }
        }

        wc_add_notice(esc_html__('Payment was cancelled or failed. Please try again.', 'technopay-for-woocommerce'), 'error');
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }

    private function verify_payment($track_number)
    {
        if (empty($track_number)) {
            throw new Exception(esc_html__('Track number not found.', 'technopay-for-woocommerce'));
        }

        $timestamp = time();
        $payment_type = 'cpg';

        $signature = $this->generate_signature($this->merchant_id, $this->merchant_secret, $timestamp, $payment_type);

        $request_data = array(
            'track_number' => sanitize_text_field($track_number)
        );

        return $this->make_api_request('/verify', $request_data, $signature, $timestamp);
    }

    private function get_order_by_track_number($track_number)
    {
        if (empty($track_number)) {
            return false;
        }

        $track_number = sanitize_text_field($track_number);
        
        // Try to get from cache first
        $cache_key = 'technopay_track_' . md5($track_number);
        $order_id = wp_cache_get($cache_key, 'technopay');
        
        if (false === $order_id) {
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Necessary for order lookup with caching
            $order_id = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_technopay_track_number' AND meta_value = %s LIMIT 1",
                $track_number
            ));
            
            // Cache the result for 1 hour
            wp_cache_set($cache_key, $order_id ? $order_id : 0, 'technopay', HOUR_IN_SECONDS);
        }

        if ($order_id) {
            return wc_get_order($order_id);
        }

        return false;
    }

    private function get_customer_mobile_number($order)
    {
        $mobile_number = $order->get_billing_phone();

        if (empty($mobile_number) && $order->get_user_id()) {
            $user_id = $order->get_user_id();
            $mobile_number = get_user_meta($user_id, 'billing_phone', true);
        }

        return preg_replace('/[^0-9]/', '', $mobile_number);
    }

    private function normalize_iranian_mobile($mobile_number)
    {
        $digits = preg_replace('/[^0-9]/', '', $mobile_number);

        if (strlen($digits) === 14 && substr($digits, 0, 4) === '0098' && substr($digits, 4, 1) === '9') {
            return '0' . substr($digits, 4);
        }

        if (strlen($digits) === 12 && substr($digits, 0, 2) === '98' && substr($digits, 2, 1) === '9') {
            return '0' . substr($digits, 2);
        }

        if (strlen($digits) === 10 && substr($digits, 0, 1) === '9') {
            return '0' . $digits;
        }

        if (strlen($digits) === 11 && substr($digits, 0, 2) === '09') {
            return $digits;
        }

        return $digits;
    }

    private function is_valid_iranian_mobile($mobile_number)
    {
        $normalized = $this->normalize_iranian_mobile($mobile_number);
        return (strlen($normalized) === 11 && substr($normalized, 0, 2) === '09');
    }

    public function validate_billing_phone()
    {
        // Verify nonce for security
        if (!isset($_POST['woocommerce-process-checkout-nonce']) || 
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce-process-checkout-nonce'])), 'woocommerce-process_checkout')) {
            return;
        }
        
        if (!isset($_POST['payment_method']) || sanitize_text_field(wp_unslash($_POST['payment_method'])) !== $this->id) {
            return;
        }

        $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';

        if (empty($billing_phone)) {
            wc_add_notice(esc_html__('Mobile number is required for TechnoPay payment.', 'technopay-for-woocommerce'), 'error');
        } else {
            $normalized = $this->normalize_iranian_mobile($billing_phone);
            if ($this->is_valid_iranian_mobile($normalized)) {
                $_POST['billing_phone'] = $normalized;
            } else {
                wc_add_notice(esc_html__('Please enter a valid Iranian mobile number (example: 09123456789).', 'technopay-for-woocommerce'), 'error');
            }
        }
    }

    private function get_request_track_number()
    {
        $candidates = array(
            'track_number',
            'trackNumber',
            'track',
            'tn',
            'token',
            'reference_id',
            'ref_id',
            'Authority'
        );

        foreach ($candidates as $key) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a payment gateway callback
            if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a payment gateway callback
                $track = sanitize_text_field(wp_unslash($_REQUEST[$key]));
                $this->log('Found track_number in request key "' . $key . '": ' . $track);
                return $track;
            }
        }

        $this->log('No track_number found in request');
        return '';
    }

    private function calculate_amount_for_api($order)
    {
        $currency = $order->get_currency();
        $total = $order->get_total();

        return $this->convert_amount_to_api_format($total, $currency);
    }

    private function calculate_item_amount_for_api($item)
    {
        $order = $item->get_order();
        $currency = $order->get_currency();
        $total = $item->get_total();

        return $this->convert_amount_to_api_format($total, $currency);
    }

    private function convert_amount_to_api_format($amount, $currency)
    {
        $amount = absint($amount);

        if ($this->currency_mode !== 'auto') {
            switch ($this->currency_mode) {
                case 'irr':
                    return absint($amount / 10);
                case 'irt':
                    return $amount;
            }
        }

        switch (strtoupper($currency)) {
            case 'IRR':
                return absint($amount / 10);
            case 'IRT':
                return $amount;
            default:
                throw new Exception(esc_html__('Only IRR and IRT currencies are supported by TechnoPay.', 'technopay-for-woocommerce'));
        }
    }

    private function log($message)
    {
        if ('yes' !== $this->testmode) {
            return;
        }

        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'technopay'));
        }

        // Debug logging removed for production use
    }
}