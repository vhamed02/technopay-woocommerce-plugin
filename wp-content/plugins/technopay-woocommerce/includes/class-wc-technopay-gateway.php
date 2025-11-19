<?php
if (!defined('ABSPATH')) exit;

class WC_TechnoPay_Gateway extends WC_Payment_Gateway {
    
    private $api_url;
    
    public function __construct() {
        $this->id = 'technopay';
        $this->icon = TECHNOPAY_WC_PLUGIN_URL . 'assets/images/technopay-logo.svg';
        $this->method_title = __('تکنوپی', 'technopay-wc');
        $this->method_description = __('پرداخت اعتباری از طریق تکنوپی', 'technopay-wc');
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
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('فعالسازی', 'technopay-wc'),
                'type' => 'checkbox',
                'label' => __('فعالسازی پرداخت با تکنوپی', 'technopay-wc'),
                'default' => 'yes'
            ),
            'title' => array(
                'title' => __('عنوان', 'technopay-wc'),
                'type' => 'text',
                'default' => __('تکنوپی', 'technopay-wc'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('توضیحات', 'technopay-wc'),
                'type' => 'text',
                'default' => __('پرداخت اقساطی از طریق تکنوپی', 'technopay-wc'),
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'technopay-wc'),
                'type' => 'text',
                'default' => '',
            ),
            'merchant_secret' => array(
                'title' => __('Secret Key', 'technopay-wc'),
                'type' => 'password',
                'default' => '',
            ),
            'testmode' => array(
                'title' => __('حالت تست', 'technopay-wc'),
                'type' => 'checkbox',
                'label' => __('فعالسازی حالت تست', 'technopay-wc'),
                'default' => 'no',
                'description' => __('در حالت تست، پرداخت‌ها به سرور تست ارسال می‌شود', 'technopay-wc'),
            ),
            'currency_mode' => array(
                'title' => __('نوع ارز', 'technopay-wc'),
                'type' => 'select',
                'default' => 'auto',
                'options' => array(
                    'auto' => __('تشخیص خودکار', 'technopay-wc'),
                    'irr' => __('ریال (IRR)', 'technopay-wc'),
                    'irt' => __('تومان (IRT)', 'technopay-wc'),
                ),
            ),
        );
    }
    
    public function is_available() {
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
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wc_add_notice(__('سفارش یافت نشد.', 'technopay-wc'), 'error');
            return array('result' => 'fail');
        }
        
        if (!$this->validate_gateway_config()) {
            wc_add_notice(__('درگاه پرداخت به درستی پیکربندی نشده است.', 'technopay-wc'), 'error');
            return array('result' => 'fail');
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
                throw new Exception(__('ایجاد تیکت پرداخت ناموفق بود.', 'technopay-wc'));
            }
            
        } catch (Exception $e) {
            $this->log('Payment failed: ' . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            $order->add_order_note(__('پرداخت تکنوپی ناموفق بود: ', 'technopay-wc') . $e->getMessage());
            return array('result' => 'fail');
        }
    }
    
    private function validate_gateway_config() {
        return !empty($this->merchant_id) && !empty($this->merchant_secret);
    }
    
    private function create_payment_ticket($order) {
        $timestamp = time();
        $payment_type = 'cpg';
        
        $signature = $this->generate_signature($this->merchant_id, $this->merchant_secret, $timestamp, $payment_type);
        
        $amount = $this->calculate_amount_for_api($order);
        $ticket_number = 'WC' . $order->get_id() . '_' . $timestamp;
        
        $mobile_number = $this->get_customer_mobile_number($order);
        if (empty($mobile_number)) {
            throw new Exception(__('شماره موبایل مشتری الزامی است.', 'technopay-wc'));
        }
        
        $mobile_number = $this->normalize_iranian_mobile($mobile_number);
        if (!$this->is_valid_iranian_mobile($mobile_number)) {
            throw new Exception(__('لطفاً شماره موبایل معتبر ایرانی وارد کنید.', 'technopay-wc'));
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
            $error_message = isset($response['message']) ? $response['message'] : __('خطای نامشخص رخ داد.', 'technopay-wc');
            throw new Exception($error_message);
        }
    }
    
    private function prepare_order_items($order) {
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
    
    private function generate_signature($merchant_id, $merchant_secret, $timestamp, $payment_type) {
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
            throw new Exception(__('ایجاد امضای دیجیتال ناموفق بود.', 'technopay-wc'));
        }
        
        $json_data = json_encode(array(
            'iv' => base64_encode($iv),
            'value' => base64_encode($encrypted)
        ));
        
        return base64_encode($json_data);
    }
    
    private function make_api_request($endpoint, $data, $signature, $timestamp) {
        $url = $this->api_url . $endpoint;
        
        $headers = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'signature' => $signature,
            'merchantId' => $this->merchant_id,
            'User-Agent' => 'TechnoPay-WooCommerce/' . TECHNOPAY_WC_VERSION
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        );
        
        $this->log('API Request: ' . $url);
        $this->log('Request Data: ' . wp_json_encode($data));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log('API Error: ' . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $http_code = wp_remote_retrieve_response_code($response);
        
        $this->log('API Response Code: ' . $http_code);
        $this->log('API Response Body: ' . $body);
        
        $decoded_response = json_decode($body, true);
        
        if ($http_code >= 400) {
            $error_message = isset($decoded_response['message']) ? $decoded_response['message'] : __('خطای HTTP: ', 'technopay-wc') . $http_code;
            $this->log('API Error Message: ' . $error_message);
            throw new Exception($error_message);
        }
        
        return $decoded_response;
    }
    
    private function get_callback_url() {
        return WC()->api_request_url('technopay_callback');
    }
    
    private function get_fallback_url() {
        return WC()->api_request_url('technopay_fallback');
    }
    
    public function process_callback() {
        $this->init_settings();
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');
        
        $status = isset($_REQUEST['Status']) ? sanitize_text_field(wp_unslash($_REQUEST['Status'])) : '';
        if (!empty($status) && strtoupper($status) !== 'OK') {
            wc_add_notice(__('پرداخت توسط کاربر لغو شد.', 'technopay-wc'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
        
        $order = $this->get_order_from_request();
        
        if (!$order) {
            wc_add_notice(__('سفارش یافت نشد.', 'technopay-wc'), 'error');
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
            wc_add_notice(__('شماره پیگیری یافت نشد.', 'technopay-wc'), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
        
        try {
            $verification_result = $this->verify_payment($track_number);
            
            if ($verification_result && isset($verification_result['succeed']) && $verification_result['succeed']) {
                $order->payment_complete();
                $order->add_order_note(__('پرداخت با موفقیت از طریق تکنوپی انجام شد. شماره پیگیری: ', 'technopay-wc') . $track_number);
                
                WC()->cart->empty_cart();
                
                wp_safe_redirect($order->get_checkout_order_received_url());
                exit;
            } else {
                $error_message = isset($verification_result['message']) ? $verification_result['message'] : __('تایید پرداخت ناموفق بود.', 'technopay-wc');
                $order->update_status('failed', $error_message);
                $order->add_order_note(__('تایید پرداخت تکنوپی ناموفق بود: ', 'technopay-wc') . $error_message);
                wc_add_notice($error_message, 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
            
        } catch (Exception $e) {
            $this->log('Callback error: ' . $e->getMessage());
            $order->update_status('failed', $e->getMessage());
            $order->add_order_note(__('خطا در بازگشت از تکنوپی: ', 'technopay-wc') . $e->getMessage());
            wc_add_notice($e->getMessage(), 'error');
            wp_safe_redirect(wc_get_checkout_url());
            exit;
        }
    }
    
    private function get_order_from_request() {
        $this->log('Getting order from request. REQUEST params: ' . wp_json_encode($_REQUEST));
        
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
    
    public function process_fallback() {
        $this->init_settings();
        $this->merchant_id = $this->get_option('merchant_id');
        $this->merchant_secret = $this->get_option('merchant_secret');
        
        $order = $this->get_order_from_request();
        $track_number = $this->get_request_track_number();
        
        if ($order && !$order->is_paid()) {
            $order->update_status('failed', __('پرداخت از طریق تکنوپی ناموفق بود.', 'technopay-wc'));
            if (!empty($track_number)) {
                $order->add_order_note(__('پرداخت از طریق تکنوپی ناموفق بود. شماره پیگیری: ', 'technopay-wc') . $track_number);
            }
        }
        
        wc_add_notice(__('پرداخت لغو یا ناموفق بود. لطفاً دوباره تلاش کنید.', 'technopay-wc'), 'error');
        wp_safe_redirect(wc_get_checkout_url());
        exit;
    }
    
    private function verify_payment($track_number) {
        if (empty($track_number)) {
            throw new Exception(__('شماره پیگیری یافت نشد.', 'technopay-wc'));
        }
        
        $timestamp = time();
        $payment_type = 'cpg';
        
        $signature = $this->generate_signature($this->merchant_id, $this->merchant_secret, $timestamp, $payment_type);
        
        $request_data = array(
            'track_number' => sanitize_text_field($track_number)
        );
        
        return $this->make_api_request('/verify', $request_data, $signature, $timestamp);
    }

    private function get_order_by_track_number($track_number) {
        if (empty($track_number)) {
            return false;
        }
        
        $track_number = sanitize_text_field($track_number);
        
        global $wpdb;
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_technopay_track_number' AND meta_value = %s LIMIT 1",
            $track_number
        ));
        
        if ($order_id) {
            return wc_get_order($order_id);
        }
        
        return false;
    }
    
    private function get_customer_mobile_number($order) {
        $mobile_number = $order->get_billing_phone();
        
        if (empty($mobile_number) && $order->get_user_id()) {
            $user_id = $order->get_user_id();
            $mobile_number = get_user_meta($user_id, 'billing_phone', true);
        }
        
        return preg_replace('/[^0-9]/', '', $mobile_number);
    }
    
    private function normalize_iranian_mobile($mobile_number) {
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
    
    private function is_valid_iranian_mobile($mobile_number) {
        $normalized = $this->normalize_iranian_mobile($mobile_number);
        return (strlen($normalized) === 11 && substr($normalized, 0, 2) === '09');
    }
    
    public function validate_billing_phone() {
        if (!isset($_POST['payment_method']) || $_POST['payment_method'] !== $this->id) {
            return;
        }
        
        $billing_phone = isset($_POST['billing_phone']) ? sanitize_text_field(wp_unslash($_POST['billing_phone'])) : '';
        
        if (empty($billing_phone)) {
            wc_add_notice(__('شماره موبایل برای پرداخت تکنوپی الزامی است.', 'technopay-wc'), 'error');
        } else {
            $normalized = $this->normalize_iranian_mobile($billing_phone);
            if ($this->is_valid_iranian_mobile($normalized)) {
                $_POST['billing_phone'] = $normalized;
            } else {
                wc_add_notice(__('لطفاً شماره موبایل معتبر ایرانی وارد کنید (مثال: 09123456789).', 'technopay-wc'), 'error');
            }
        }
    }
    
    private function get_request_track_number() {
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
            if (isset($_REQUEST[$key]) && $_REQUEST[$key] !== '') {
                $track = sanitize_text_field(wp_unslash($_REQUEST[$key]));
                $this->log('Found track_number in request key "' . $key . '": ' . $track);
                return $track;
            }
        }
        
        $this->log('No track_number found in request');
        return '';
    }
    
    private function calculate_amount_for_api($order) {
        $currency = $order->get_currency();
        $total = $order->get_total();
        
        return $this->convert_amount_to_api_format($total, $currency);
    }
    
    private function calculate_item_amount_for_api($item) {
        $order = $item->get_order();
        $currency = $order->get_currency();
        $total = $item->get_total();
        
        return $this->convert_amount_to_api_format($total, $currency);
    }
    
    private function convert_amount_to_api_format($amount, $currency) {
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
                throw new Exception(__('فقط ارزهای IRR و IRT توسط تکنوپی پشتیبانی می‌شود.', 'technopay-wc'));
        }
    }
    
    private function log($message) {
        if ('yes' !== $this->testmode) {
            return;
        }
        
        if (function_exists('wc_get_logger')) {
            $logger = wc_get_logger();
            $logger->info($message, array('source' => 'technopay'));
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TechnoPay: ' . $message);
        }
    }
}
