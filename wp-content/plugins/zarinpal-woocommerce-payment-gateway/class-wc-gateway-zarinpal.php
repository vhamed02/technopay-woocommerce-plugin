<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once plugin_dir_path(__FILE__) . 'ZarinpalHelperClass.php';
define('WC_ZPAL_TEXT_DOMAIN', 'wc-zpal');

add_action('plugins_loaded', 'load_zarinpal_textdomain');
function load_zarinpal_textdomain() {
    load_plugin_textdomain(WC_ZPAL_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

function Load_ZarinPal_Gateway() {
    if (!function_exists('Woocommerce_Add_ZarinPal_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_ZPal')) {
        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_ZarinPal_Gateway');
        function Woocommerce_Add_ZarinPal_Gateway($methods) {
            $methods[] = 'WC_ZPal';
            return $methods;
        }
        add_filter('woocommerce_currencies', 'add_IR_currency');
        function add_IR_currency($currencies) {
            $currencies['IRR'] = __('ریال', WC_ZPAL_TEXT_DOMAIN);
            $currencies['IRT'] = __('تومان', WC_ZPAL_TEXT_DOMAIN);
            $currencies['IRHR'] = __('هزار ریال', WC_ZPAL_TEXT_DOMAIN);
            $currencies['IRHT'] = __('هزار تومان', WC_ZPAL_TEXT_DOMAIN);
            return $currencies;
        }
        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);
        function add_IR_currency_symbol($currency_symbol, $currency) {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }
        class WC_ZPal extends WC_Payment_Gateway {
            public $merchantCode;
            public $sandbox;
            public $successMessage;
            public $failedMessage;
            public $trustLogo;
            public $zarinpal;
            public $instructions;
            public $accessToken;
            public $feePayer;
            public function __construct() {
                $this->id = 'WC_ZPal';
                $this->method_title = __('پرداخت امن زرین‌پال', WC_ZPAL_TEXT_DOMAIN);
                $this->method_description = __('تنظیمات درگاه پرداخت زرین‌پال برای افزونه فروشگاه ساز ووکامرس', WC_ZPAL_TEXT_DOMAIN);
                $this->icon = apply_filters('WC_ZPal_logo', plugin_dir_url(__FILE__) . 'assets/images/logo.svg');
                $this->has_fields = true;
                $this->supports = array(
                    'products',
                    'tokenization',
                    'refunds',
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change',
                    'pre-orders',
                );
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->merchantCode = $this->get_option('merchantcode');
                $this->sandbox = ($this->get_option('sandbox') === 'yes') ? true : false;
                $this->successMessage = $this->get_option('success_message');
                $this->failedMessage = $this->get_option('failed_message');
                $this->instructions = $this->get_option('instructions');
                $this->trustLogo = $this->get_option('trust_logo');
                $this->accessToken = $this->sanitize_access_token($this->get_option('access_token'));
                $this->feePayer = $this->get_option('fee_payer', 'merchant');
                $this->order_button_text = __('پرداخت با زرین‌پال', WC_ZPAL_TEXT_DOMAIN);
                $this->zarinpal = new ZarinpalHelperClass($this->merchantCode, $this->sandbox, $this->accessToken);
                
                $this->auto_detect_fee_payer();
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_receipt_' . $this->id, array($this, 'Send_to_ZarinPal_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'Return_from_ZarinPal_Gateway'));
                add_action('woocommerce_email_after_order_table', array($this, 'email_instructions'), 10, 3);
                add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
                add_action('woocommerce_order_status_refunded', array($this, 'process_refund'), 10, 2);
                if (is_admin() && $this->sandbox) {
                    add_action('admin_bar_menu', array($this, 'add_sandbox_notice_to_admin_bar'), 100);
                }
                add_action('admin_notices', array($this, 'admin_notice_missing_merchantcode'));
                add_action('admin_notices', array($this, 'admin_notice_missing_accesstoken'));
                
                add_action('woocommerce_cart_calculate_fees', array($this, 'add_zarinpal_fee_to_cart'));
                add_action('woocommerce_checkout_update_order_meta', array($this, 'save_fee_to_order'));
                add_action('woocommerce_checkout_update_order_review', array($this, 'update_checkout_fees'));
                add_action('woocommerce_store_api_register_endpoint_data', array($this, 'register_store_api_data'));
                add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'blocks_add_fee'), 10, 2);
                add_action('woocommerce_checkout_create_order', array($this, 'checkout_create_order_fee'), 10, 2);
                add_action('woocommerce_blocks_checkout_order_processed', array($this, 'blocks_order_processed'), 10, 1);
                add_action('woocommerce_store_api_cart_update_customer', array($this, 'blocks_payment_method_changed'), 10, 1);
                add_filter('woocommerce_get_price_decimals', array($this, 'adjust_decimals_for_zarinpal_fee'), 10, 1);
                
                add_action('wp_head', array($this, 'add_cart_css'));

            }
            public function init_form_fields() {
                $this->form_fields = apply_filters(
                    'WC_ZPal_Config',
                    array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'description' => __('برای فعالسازی درگاه پرداخت زرین‌پال باید چک باکس را تیک بزنید', WC_ZPAL_TEXT_DOMAIN),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده می‌شود', WC_ZPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت امن زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', WC_ZPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت امن به وسیله کلیه کارت‌های عضو شتاب از طریق درگاه زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'merchantcode' => array(
                            'title' => __('مرچنت کد', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'access_token' => array(
                            'title' => __('توکن دسترسی (اختیاری ویژه سرویس استرداد وجه)', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'password',
                            'description' => __('توکن دسترسی برای استفاده از API گراف‌کیوال زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'sandbox' => array(
                            'title' => __('حالت آزمایشی (Sandbox)', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی حالت آزمایشی', WC_ZPAL_TEXT_DOMAIN),
                            'description' => __('برای تست درگاه پرداخت از حالت آزمایشی استفاده کنید.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => 'no',
                            'desc_tip' => true,
                        ),
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'fee_payer' => array(
                            'title' => __('کسر کارمزد از', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'select',
                            'description' => __('انتخاب کنید که کارمزد تراکنش از پذیرنده کسر شود یا به خریدار اضافه شود. اگر کسر کارمزد از خریدار انتخاب شود، در صفحه چک‌اوت مبلغی تحت عنوان کارمزد به صورت جداگانه محاسبه و به مبلغ کل اضافه خواهد شد.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => 'merchant',
                            'desc_tip' => true,
                            'options' => array(
                                'merchant' => __('پذیرنده (پیش‌فرض)', WC_ZPAL_TEXT_DOMAIN),
                                'customer' => __('خریدار', WC_ZPAL_TEXT_DOMAIN),
                            ),
                        ),
                        'success_message' => array(
                            'title' => __('پیام پرداخت موفق', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که می‌خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید. می‌توانید از شورت کد {transaction_id} برای نمایش کد رهگیری استفاده کنید.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => __('با تشکر از شما. سفارش شما با موفقیت پرداخت شد.', WC_ZPAL_TEXT_DOMAIN),
                        ),
                        'failed_message' => array(
                            'title' => __('پیام پرداخت ناموفق', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که می‌خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید. می‌توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده کنید.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => __('پرداخت شما ناموفق بوده است. لطفاً مجدداً تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.', WC_ZPAL_TEXT_DOMAIN),
                        ),
                        'instructions' => array(
                            'title' => __('توضیحات پس از خرید', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'textarea',
                            'description' => __('دستورالعمل‌هایی که پس از تکمیل پرداخت به مشتری نمایش داده می‌شود.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => '',
                            'desc_tip' => true,
                        ),
                        'trust_logo' => array(
                            'title' => __('کد تراست لوگوی زرین‌پال', WC_ZPAL_TEXT_DOMAIN),
                            'type' => 'trust_logo',
                            'description' => __('کد تراست لوگوی زرین‌پال را کپی نمایید و در فوتر سایت خود قرار دهید.', WC_ZPAL_TEXT_DOMAIN),
                            'default' => '<style>#zarinpal{margin:auto} #zarinpal img {width: 80px;}</style>
                            <div id="zarinpal"><script src="https://www.zarinpal.com/webservice/TrustCode" type="text/javascript"></script></div>',
                        ),
                    )
                );
            }
            public function admin_options() {
                echo '<h3>' . __('درگاه پرداخت زرین‌پال', WC_ZPAL_TEXT_DOMAIN) . '</h3>';
                echo '<p>' . __('تنظیمات درگاه پرداخت زرین‌پال برای ووکامرس', WC_ZPAL_TEXT_DOMAIN) . '</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';
            }
            public function get_icon() {
                $icon = '<img src="' . plugin_dir_url(__FILE__) . 'assets/images/logo.svg" alt="زرین‌پال" />';
                return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
            }
            public function payment_fields() {
                if ($this->description) {
                    echo wpautop(wptexturize($this->description));
                }
                echo $this->trustLogo;
            }
            public function process_payment($order_id) {
                $order = wc_get_order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true),
                );
            }
            public function tokenization_script() {
                if (!$this->supports('tokenization')) {
                    return;
                }
                wp_enqueue_script('wc-credit-card-form');
            }
            public function save_payment_method_checkbox() {
                ?>
                <p class="form-row">
                    <label for="wc-<?php echo esc_attr($this->id); ?>-new-payment-method">
                        <input id="wc-<?php echo esc_attr($this->id); ?>-new-payment-method" name="wc-<?php echo esc_attr($this->id); ?>-new-payment-method" type="checkbox" value="true" style="width:auto;" /> <?php esc_html_e('ذخیره روش پرداخت برای خریدهای بعدی', WC_ZPAL_TEXT_DOMAIN); ?>
                    </label>
                </p>
                <?php
            }
            public function Send_to_ZarinPal_Gateway($order_id) {
                $order = wc_get_order($order_id);
                $currency = $order->get_currency();
                
                $order_total = $order->get_total();
                $amount = intval($order_total);
                $currency = strtolower($currency);
                if ($currency === 'irht') {
                    $amount *= 10000;
                } elseif ($currency === 'irhr') {
                    $amount *= 1000;
                } elseif ($currency === 'irt') {
                    $amount *= 10;
                }
                
                $payment_amount = $amount;
                
                if ($this->feePayer === 'customer') {
                    try {
                        $base_amount = $order_total;
                        $fees = $order->get_fees();
                        foreach ($fees as $fee) {
                            if (strpos($fee->get_name(), 'کارمزد درگاه') !== false) {
                                $base_amount -= $fee->get_total();
                                break;
                            }
                        }
                        
                        $base_amount_rial = intval($base_amount);
                        if ($currency === 'irht') {
                            $base_amount_rial *= 10000;
                        } elseif ($currency === 'irhr') {
                            $base_amount_rial *= 1000;
                        } elseif ($currency === 'irt') {
                            $base_amount_rial *= 10;
                        }
                        
                        $fee_data = $this->zarinpal->calculateFee($base_amount_rial, 'IRR');
                        
                        if (isset($fee_data['fee_type']) && $fee_data['fee_type'] === 'Merchant' && 
                            isset($fee_data['suggested_amount']) && $fee_data['suggested_amount'] > 0) {
                            
                            $payment_amount = $fee_data['suggested_amount'];
                            
                            $order->update_meta_data('_zarinpal_fee_data', array(
                                'base_amount' => $base_amount_rial,
                                'order_total' => $amount,
                                'fee' => $fee_data['fee'],
                                'suggested_amount' => $fee_data['suggested_amount'],
                                'fee_type' => $fee_data['fee_type'],
                                'timestamp' => time()
                            ));
                            $order->save();
                        }
                    } catch (Exception $e) {
                        $payment_amount = $amount;
                    }
                }
                $callback_url = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_ZPal'));
                $description = 'خرید به شماره سفارش: ' . $order->get_order_number();
                $description .= ' | خریدار: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $mobile = $order->get_billing_phone();
                $email = $order->get_billing_email();
                $cart_items = array();
                foreach ($order->get_items() as $item_id => $item) {
                    $product_name = $item->get_name();
                    $quantity = $item->get_quantity();
                    $total = $item->get_total();
                    $subtotal = $item->get_subtotal();
                    $unit_price = $subtotal / $quantity;
                    $cart_items[] = array(
                        'product_name' => $product_name,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total' => $total,
                    );
                }
                $discount_total = $order->get_discount_total();
                $total_amount = $order->get_total();
                $cart_data = array(
                    'items' => $cart_items,
                    'discount' => $discount_total,
                    'total' => $total_amount,
                );
                $cart_json = json_encode($cart_data);
                $referrer_id = get_option('wc_zpal_referrer_id', null);
                $metadata = array(
                    'email' => $email,
                    'mobile' => $mobile,
                );
                try {
                    $authority = $this->zarinpal->requestPayment(
                        $payment_amount,
                        $callback_url,
                        $description,
                        $metadata,
                        $cart_json,
                        $referrer_id
                    );
                    $order->update_meta_data('_zarinpal_authority', $authority);
                    $order->save();
                    $note = sprintf(__('کاربر به درگاه پرداخت هدایت شد. شناسه تراکنش: %s', WC_ZPAL_TEXT_DOMAIN), $authority);
                    $order->add_order_note($note);
                    wp_redirect($this->zarinpal->getRedirectUrl($authority));
                    exit;
                } catch (Exception $e) {
                    wc_add_notice(__('خطا در اتصال به درگاه پرداخت: ', WC_ZPAL_TEXT_DOMAIN) . $e->getMessage(), 'error');
                    return;
                }
            }
            public function Return_from_ZarinPal_Gateway() {
                global $woocommerce;
                $order_id = isset($_GET['wc_order']) ? sanitize_text_field($_GET['wc_order']) : 0;
                $order = wc_get_order($order_id);
                if (!$order) {
                    wc_add_notice(__('سفارش پیدا نشد.', WC_ZPAL_TEXT_DOMAIN), 'error');
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
                if (isset($_GET['Status']) && $_GET['Status'] === 'OK') {
                    $authority = sanitize_text_field($_GET['Authority']);
                    
                    $order_total = $order->get_total();
                    $amount = intval($order_total);
                    $currency = $order->get_currency();
                    $currency = strtolower($currency);
                    if ($currency === 'irht') {
                        $amount *= 10000;
                    } elseif ($currency === 'irhr') {
                        $amount *= 1000;
                    } elseif ($currency === 'irt') {
                        $amount *= 10;
                    }
                    
                    $verify_amount = $amount;
                    if ($this->feePayer === 'customer') {
                        $fee_data = $order->get_meta('_zarinpal_fee_data');
                        if ($fee_data && is_array($fee_data)) {
                            if (isset($fee_data['order_total'], $fee_data['suggested_amount'], $fee_data['timestamp'], $fee_data['fee_type'])) {
                                if ($fee_data['order_total'] == $amount && $fee_data['fee_type'] === 'Merchant') {
                                    if ((time() - $fee_data['timestamp']) < 3600) {
                                        $verify_amount = $fee_data['suggested_amount'];
                                    }
                                }
                            }
                        }
                    }
                    
                    try {
                        $response = $this->zarinpal->verifyPayment($authority, $verify_amount);
                        if ($response['code'] == 100) {
                            $transaction_id = $response['ref_id'];
                            $order->payment_complete($transaction_id);
                            $order->add_order_note(sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_ZPAL_TEXT_DOMAIN), $transaction_id));
                            wc_add_notice(str_replace('{transaction_id}', $transaction_id, $this->successMessage), 'success');
                            $woocommerce->cart->empty_cart();
                            wp_redirect($this->get_return_url($order));
                            exit;
                        } else {
                            throw new Exception('تراکنش ناموفق بود.');
                        }
                    } catch (Exception $e) {
                        wc_add_notice(str_replace('{fault}', $e->getMessage(), $this->failedMessage), 'error');
                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }
                } else {
                    wc_add_notice(str_replace('{fault}', 'تراکنش توسط کاربر لغو شد.', $this->failedMessage), 'error');
                    wp_redirect(wc_get_checkout_url());
                    exit;
                }
            }
            public function process_refund($order_id, $amount = null, $reason = 'CUSTOMER_REQUEST') {
                $order = wc_get_order($order_id);
                if (!$amount) {
                    return new WP_Error('invalid_amount', __('مبلغ استرداد معتبر نیست.', WC_ZPAL_TEXT_DOMAIN));
                }
                $settings = get_option('woocommerce_WC_ZPal_settings');
                $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
                if (empty($accessToken)) {
                    return new WP_Error('no_access_token', __('برای استفاده از این سرویس باید توکن دسترسی خود را در تنظیمات درگاه زرین پال وارد نمایید.', WC_ZPAL_TEXT_DOMAIN));
                }
                $authority = $order->get_meta('_zarinpal_authority');
                if (!$authority) {
                    return new WP_Error('no_authority', __('شناسه تراکنش زرین‌پال برای این سفارش یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
                }
                try {
                    $transactions = $this->zarinpal->getTransactions($authority);
                    if (!empty($transactions)) {
                        $transaction_info = $transactions[0];
                        $transaction_id = $transaction_info['id'];
                        $refund = $this->zarinpal->refundPayment($transaction_id, $amount * 10, $reason);
                        $order->add_order_note(sprintf(__('استرداد مبلغ %s انجام شد.', WC_ZPAL_TEXT_DOMAIN), wc_price($amount)));
                        return true;
                    } else {
                        return new WP_Error('transaction_not_found', __('تراکنش مرتبط یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
                    }
                } catch (Exception $e) {
                    return new WP_Error('refund_error', $e->getMessage());
                }
            }
            public function get_transaction_url($order) {
                $transaction_id = $order->get_meta('_zarinpal_authority');
                if ($transaction_id) {
                    $url = add_query_arg(array(
                        'action' => 'zpal_transaction_info',
                        'transaction_id' => $transaction_id,
                        'order_id' => $order->get_id(),
                    ), admin_url('admin-ajax.php'));
                    return $url;
                }
                return '';
            }
            public function email_instructions($order, $sent_to_admin, $plain_text = false) {
                if ($order->get_payment_method() !== $this->id || $sent_to_admin) {
                    return;
                }
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
                }
            }
            public function thankyou_page() {
                if ($this->instructions) {
                    echo wpautop(wptexturize($this->instructions));
                }
            }
            public function add_sandbox_notice_to_admin_bar($wp_admin_bar) {
                if (!current_user_can('manage_options')) {
                    return;
                }
                $message = sprintf(
                    __('درگاه زرین‌پال در حالت آزمایشی (Sandbox) فعال است. پرداخت‌های واقعی انجام نخواهند شد. برای تغییر این حالت، به تنظیمات درگاه <a href="%s">اینجا</a> مراجعه کنید.', WC_ZPAL_TEXT_DOMAIN),
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=wc_zpal')
                );
                echo '<div class="notice notice-warning is-dismissible">';
                echo '<p>' . $message . '</p>';
                echo '</div>';
            }
            public function generate_trust_logo_html($key, $data) {
                $field = wp_parse_args($data, array(
                    'title' => '',
                    'description' => '',
                    'default' => '',
                ));
                ob_start();
                ?>
                <tr valign="top">
                    <th scope="row" class="titledesc"><?php echo esc_html($field['title']); ?></th>
                    <td class="forminp">
                        <textarea readonly style="direction: ltr; white-space: pre-wrap; width: 100%; height: 100px;"><?php echo esc_textarea($field['default']); ?></textarea>
                        <br/><?php echo wp_kses_post($field['description']); ?>
                    </td>
                </tr>
                <?php
                return ob_get_clean();
            }
            public function admin_notice_missing_merchantcode() {
                $merchantcode = $this->get_option('merchantcode');
                if (empty($merchantcode) && 'yes' === $this->get_option('enabled')) {
                    echo '<div class="notice notice-error is-dismissible">';
                    echo '<p>' . __('مرچنت کد درگاه زرین‌پال خالی است. لطفاً آن را در تنظیمات درگاه وارد نمایید.', WC_ZPAL_TEXT_DOMAIN) . '</p>';
                    echo '</div>';
                }
            }
            public function admin_notice_missing_accesstoken() {
                $accesstoken = $this->get_option('access_token');
                if (empty($accesstoken) && 'yes' === $this->get_option('enabled')) {
                }
            }
            private function sanitize_access_token($token) {
                if (strpos($token, 'Bearer ') === 0) {
                    return substr($token, 7);
                }
                return $token;
            }
            
            public function add_zarinpal_fee_to_cart($cart) {
                if (is_admin() && !defined('DOING_AJAX')) {
                    return;
                }
                
                if (is_cart() && !defined('DOING_AJAX')) {
                    return;
                }
                
                if ($this->feePayer === 'customer') {
                    $chosen_payment_method = WC()->session->get('chosen_payment_method');
                    
                    $is_zarinpal_selected = false;
                    
                    if ($chosen_payment_method === $this->id) {
                        $is_zarinpal_selected = true;
                    }
                    
                    if (isset($_POST['payment_method']) && $_POST['payment_method'] === $this->id) {
                        $is_zarinpal_selected = true;
                    }
                    
                    if (defined('WC_DOING_AJAX') && WC_DOING_AJAX) {
                        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
                        if (strpos($request_uri, '/wc/store/') !== false) {
                            global $wp;
                            if (isset($wp->query_vars['rest_route']) && strpos($wp->query_vars['rest_route'], '/wc/store/') !== false) {
                                $input = file_get_contents('php://input');
                                if ($input) {
                                    $data = json_decode($input, true);
                                    if (isset($data['payment_method']) && $data['payment_method'] === $this->id) {
                                        $is_zarinpal_selected = true;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (!$is_zarinpal_selected && empty($chosen_payment_method) && is_checkout()) {
                        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                        $gateway_keys = array_keys($available_gateways);
                        if (!empty($gateway_keys) && $gateway_keys[0] === $this->id) {
                            $is_zarinpal_selected = true;
                        }
                    }
                    
                    if ($is_zarinpal_selected) {
                        $fees = $cart->get_fees();
                        $fee_exists = false;
                        foreach ($fees as $fee) {
                            if (strpos($fee->name, 'کارمزد درگاه') !== false) {
                                $fee_exists = true;
                                break;
                            }
                        }
                        
                        if (!$fee_exists) {
                            $cart_total = $cart->get_subtotal() + $cart->get_subtotal_tax() + $cart->get_shipping_total() + $cart->get_shipping_tax();
                            
                            $cart_fees = $cart->get_fees();
                            foreach ($cart_fees as $fee) {
                                if (strpos($fee->name, 'کارمزد درگاه') === false) {
                                    $cart_total += $fee->total;
                                }
                            }
                            
                            $cart_total -= $cart->get_discount_total();
                            
                            $currency = get_woocommerce_currency();
                            $amount_in_rial = intval($cart_total);
                            $currency_lower = strtolower($currency);
                            
                            if ($currency_lower === 'irht') {
                                $amount_in_rial *= 10000;
                            } elseif ($currency_lower === 'irhr') {
                                $amount_in_rial *= 1000;
                            } elseif ($currency_lower === 'irt') {
                                $amount_in_rial *= 10;
                            }
                            
                            try {
                                $fee_data = $this->zarinpal->calculateFee($amount_in_rial, 'IRR');
                                
                                if (isset($fee_data['fee_type']) && $fee_data['fee_type'] === 'Merchant' && 
                                    isset($fee_data['suggested_amount']) && $fee_data['suggested_amount'] > 0) {
                                    
                                    $suggested_amount = $fee_data['suggested_amount'];
                                    
                                    if ($currency_lower === 'irht') {
                                        $suggested_amount /= 10000;
                                    } elseif ($currency_lower === 'irhr') {
                                        $suggested_amount /= 1000;
                                    } elseif ($currency_lower === 'irt') {
                                        $suggested_amount /= 10;
                                    }
                                    

                                    $cart_total_rial = $amount_in_rial;
                                    $gateway_amount_rial = $fee_data['suggested_amount'];
                                    $fee_rial = $gateway_amount_rial - $cart_total_rial;
                                    

                                    $fee_amount = $fee_rial;
                                    if ($currency_lower === 'irht') {
                                        $fee_amount /= 10000;
                                    } elseif ($currency_lower === 'irhr') {
                                        $fee_amount /= 1000;
                                    } elseif ($currency_lower === 'irt') {
                                        $fee_amount /= 10;
                                    }
                                    

                                    $decimals = wc_get_price_decimals();
                                    if ($fee_amount < 1000) {
                                        $decimals = max($decimals, 3); // At least 3 decimals for small amounts
                                    }
                                    $fee_amount = round($fee_amount, $decimals);
                                    
                                    if ($fee_amount > 0) {
                                        $cart->add_fee(__('کارمزد درگاه پرداخت', WC_ZPAL_TEXT_DOMAIN), $fee_amount);
                                    }
                                }
                            } catch (Exception $e) {
                            }
                        }
                    } else {
                        $this->remove_zarinpal_fees($cart);
                    }
                } else {
                    if ($this->feePayer === 'merchant') {
                        $this->remove_zarinpal_fees($cart);
                    }
                }
            }
            
            public function register_store_api_data() {
                if (function_exists('woocommerce_store_api_register_endpoint_data')) {
                    woocommerce_store_api_register_endpoint_data(array(
                        'endpoint' => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
                        'namespace' => 'zarinpal',
                        'data_callback' => array($this, 'store_api_data_callback'),
                        'schema_callback' => array($this, 'store_api_schema_callback'),
                    ));
                }
            }
            
            public function store_api_data_callback() {
                return array(
                    'fee_payer' => $this->feePayer,
                    'gateway_id' => $this->id,
                );
            }
            
            public function store_api_schema_callback() {
                return array(
                    'fee_payer' => array(
                        'description' => __('Who pays the fee', WC_ZPAL_TEXT_DOMAIN),
                        'type' => 'string',
                        'readonly' => true,
                    ),
                    'gateway_id' => array(
                        'description' => __('Gateway ID', WC_ZPAL_TEXT_DOMAIN),
                        'type' => 'string',
                        'readonly' => true,
                    ),
                );
            }
            
            public function blocks_add_fee($order, $request) {
                $payment_method = $request['payment_method'] ?? '';
                
                                if ($payment_method === $this->id && $this->feePayer === 'customer') {
                    $existing_fees = $order->get_fees();
                    $fee_exists = false;
                    foreach ($existing_fees as $existing_fee) {
                        if (strpos($existing_fee->get_name(), 'کارمزد درگاه') !== false) {
                            $fee_exists = true;
                            break;
                        }
                    }
                    
                    if (!$fee_exists) {
                        $order_total = $order->get_subtotal() + $order->get_total_tax() + $order->get_shipping_total() + $order->get_shipping_tax();
                        
                        $order_fees = $order->get_fees();
                        foreach ($order_fees as $fee) {
                            if (strpos($fee->get_name(), 'کارمزد درگاه') === false) {
                                $order_total += $fee->get_total();
                            }
                        }
                        
                        $order_total -= $order->get_discount_total();
                        
                        $currency = $order->get_currency();
                        $amount_in_rial = intval($order_total);
                        $currency_lower = strtolower($currency);
                        
                        if ($currency_lower === 'irht') {
                            $amount_in_rial *= 10000;
                        } elseif ($currency_lower === 'irhr') {
                            $amount_in_rial *= 1000;
                        } elseif ($currency_lower === 'irt') {
                            $amount_in_rial *= 10;
                        }
                        
                        try {
                            $fee_data = $this->zarinpal->calculateFee($amount_in_rial, 'IRR');
                            
                            if (isset($fee_data['fee_type']) && $fee_data['fee_type'] === 'Merchant' && 
                                isset($fee_data['suggested_amount']) && $fee_data['suggested_amount'] > 0) {
                                
                                $suggested_amount = $fee_data['suggested_amount'];
                                
                                if ($currency_lower === 'irht') {
                                    $suggested_amount /= 10000;
                                } elseif ($currency_lower === 'irhr') {
                                    $suggested_amount /= 1000;
                                } elseif ($currency_lower === 'irt') {
                                    $suggested_amount /= 10;
                                }
                                

                                    $order_total_rial = $amount_in_rial;
                                    $gateway_amount_rial = $fee_data['suggested_amount'];
                                    $fee_rial = $gateway_amount_rial - $order_total_rial;
                                    

                                    $fee_amount = $fee_rial;
                                    if ($currency_lower === 'irht') {
                                        $fee_amount /= 10000;
                                    } elseif ($currency_lower === 'irhr') {
                                        $fee_amount /= 1000;
                                    } elseif ($currency_lower === 'irt') {
                                        $fee_amount /= 10;
                                    }
                                    

                                    $decimals = wc_get_price_decimals();
                                    if ($fee_amount < 1000) {
                                        $decimals = max($decimals, 3); // At least 3 decimals for small amounts
                                    }
                                    $fee_amount = round($fee_amount, $decimals);
                                    
                                    if ($fee_amount > 0) {
                                        $fee = new WC_Order_Item_Fee();
                                        $fee->set_name(__('کارمزد درگاه پرداخت', WC_ZPAL_TEXT_DOMAIN));
                                        $fee->set_amount($fee_amount);
                                        $fee->set_total($fee_amount);
                                        $fee->set_tax_status('none');
                                        $order->add_item($fee);
                                        $order->calculate_totals();
                                    }
                            }
                        } catch (Exception $e) {
                        }
                    }
                }
            }
            
            public function save_fee_to_order($order_id) {
                $order = wc_get_order($order_id);
                if ($order && $order->get_payment_method() === $this->id && $this->feePayer === 'customer') {
                }
            }
            
            public function checkout_create_order_fee($order, $data) {
                if (isset($data['payment_method']) && $data['payment_method'] === $this->id && $this->feePayer === 'customer') {
                    $existing_fees = $order->get_fees();
                    $fee_exists = false;
                    foreach ($existing_fees as $existing_fee) {
                        if (strpos($existing_fee->get_name(), 'کارمزد درگاه') !== false) {
                            $fee_exists = true;
                            break;
                        }
                    }
                    
                    if (!$fee_exists) {
                        $cart = WC()->cart;
                        if ($cart) {
                            $cart_total = $cart->get_subtotal() + $cart->get_subtotal_tax() + $cart->get_shipping_total() + $cart->get_shipping_tax();
                            
                            $cart_fees = $cart->get_fees();
                            foreach ($cart_fees as $fee) {
                                if (strpos($fee->name, 'کارمزد درگاه') === false) {
                                    $cart_total += $fee->total;
                                }
                            }
                            
                            $cart_total -= $cart->get_discount_total();
                            
                            $currency = get_woocommerce_currency();
                            $amount_in_rial = intval($cart_total);
                            $currency_lower = strtolower($currency);
                            
                            if ($currency_lower === 'irht') {
                                $amount_in_rial *= 10000;
                            } elseif ($currency_lower === 'irhr') {
                                $amount_in_rial *= 1000;
                            } elseif ($currency_lower === 'irt') {
                                $amount_in_rial *= 10;
                            }
                            
                            try {
                                $fee_data = $this->zarinpal->calculateFee($amount_in_rial, 'IRR');
                                
                                if (isset($fee_data['fee_type']) && $fee_data['fee_type'] === 'Merchant' && 
                                    isset($fee_data['suggested_amount']) && $fee_data['suggested_amount'] > 0) {
                                    
                                    $suggested_amount = $fee_data['suggested_amount'];
                                    
                                    if ($currency_lower === 'irht') {
                                        $suggested_amount /= 10000;
                                    } elseif ($currency_lower === 'irhr') {
                                        $suggested_amount /= 1000;
                                    } elseif ($currency_lower === 'irt') {
                                        $suggested_amount /= 10;
                                    }
                                    

                                    $cart_total_rial = $amount_in_rial;
                                    $gateway_amount_rial = $fee_data['suggested_amount'];
                                    $fee_rial = $gateway_amount_rial - $cart_total_rial;
                                    

                                    $fee_amount = $fee_rial;
                                    if ($currency_lower === 'irht') {
                                        $fee_amount /= 10000;
                                    } elseif ($currency_lower === 'irhr') {
                                        $fee_amount /= 1000;
                                    } elseif ($currency_lower === 'irt') {
                                        $fee_amount /= 10;
                                    }
                                    

                                    $decimals = wc_get_price_decimals();
                                    if ($fee_amount < 1000) {
                                        $decimals = max($decimals, 3); // At least 3 decimals for small amounts
                                    }
                                    $fee_amount = round($fee_amount, $decimals);
                                    
                                    if ($fee_amount > 0) {
                                        $fee = new WC_Order_Item_Fee();
                                        $fee->set_name(__('کارمزد درگاه پرداخت', WC_ZPAL_TEXT_DOMAIN));
                                        $fee->set_amount($fee_amount);
                                        $fee->set_total($fee_amount);
                                        $fee->set_tax_status('none');
                                        $order->add_item($fee);
                                    }
                                }
                            } catch (Exception $e) {
                            }
                        }
                    }
                }
            }
            
            public function blocks_order_processed($order) {
                if ($order->get_payment_method() === $this->id && $this->feePayer === 'customer') {
                    $fees = $order->get_fees();
                }
            }
            
            public function blocks_payment_method_changed($customer) {
                if (WC()->cart && $this->feePayer === 'customer') {
                    WC()->cart->calculate_fees();
                    WC()->cart->calculate_totals();
                }
            }
            
            public function remove_zarinpal_fees($cart) {
                $fees = $cart->get_fees();
                $fee_removed = false;
                
                foreach ($fees as $fee_key => $fee) {
                    if (strpos($fee->name, 'کارمزد درگاه') !== false) {
                        unset($cart->fees[$fee_key]);
                        $fee_removed = true;
                    }
                }
                
                if ($fee_removed) {
                    $cart->fees = array_values($cart->fees);
                }
            }
            
            public function update_checkout_fees() {
                if ($this->feePayer === 'customer') {
                    $chosen_payment_method = WC()->session->get('chosen_payment_method');
                    
                    if ($chosen_payment_method !== $this->id && WC()->cart) {
                        $this->remove_zarinpal_fees(WC()->cart);
                    }
                    
                    WC()->cart->calculate_fees();
                }
            }
            
            public function add_fee_notice() {
                $chosen_payment_method = WC()->session->get('chosen_payment_method');
                if ($chosen_payment_method === $this->id && $this->feePayer === 'customer') {
                    echo '<div class="woocommerce-info zarinpal-fee-notice" style="margin-bottom: 15px;">';
                    echo '<p>' . __('با انتخاب درگاه زرین‌پال، کارمزد تراکنش به مبلغ سفارش اضافه می‌شود.', WC_ZPAL_TEXT_DOMAIN) . '</p>';
                    echo '</div>';
                }
            }
            
                        public function enqueue_zarinpal_scripts() {
                if (is_checkout() || is_cart()) {
                    wp_enqueue_script('jquery');
                }
            }
            
            public function add_cart_css() {
                return;
            }
            

            public function adjust_decimals_for_zarinpal_fee($decimals) {
                if ((is_checkout() || is_cart() || wp_doing_ajax()) && $this->feePayer === 'customer') {
                    $chosen_payment_method = WC()->session ? WC()->session->get('chosen_payment_method') : '';
                    
                    if ($chosen_payment_method === $this->id || 
                        (isset($_POST['payment_method']) && $_POST['payment_method'] === $this->id)) {
                        
                        return max($decimals, 3);
                    }
                }
                
                return $decimals;
            }
            

            

            
            private function auto_detect_fee_payer() {
                if (empty($this->merchantCode)) {
                    return;
                }
                
                $fee_detection_done = get_option('zarinpal_fee_detection_done_' . $this->merchantCode, false);
                if ($fee_detection_done) {
                    return;
                }
                
                try {
                    $test_amount = 100000;
                    $fee_data = $this->zarinpal->calculateFee($test_amount, 'IRR');
                    
                    if (isset($fee_data['fee_type'])) {
                        $auto_fee_payer = ($fee_data['fee_type'] === 'Merchant') ? 'merchant' : 'customer';
                        
                        $current_settings = get_option('woocommerce_WC_ZPal_settings', array());
                        
                        if (!isset($current_settings['fee_payer']) || empty($current_settings['fee_payer'])) {
                            $current_settings['fee_payer'] = $auto_fee_payer;
                            update_option('woocommerce_WC_ZPal_settings', $current_settings);
                            $this->feePayer = $auto_fee_payer;
                        }
                        
                        update_option('zarinpal_fee_detection_done_' . $this->merchantCode, true);
                    }
                    
                } catch (Exception $e) {
                    update_option('zarinpal_fee_detection_done_' . $this->merchantCode, true);
                }
            }
        }
    }
}
add_action('plugins_loaded', 'Load_ZarinPal_Gateway', 11);

add_action('wp_ajax_get_zarinpal_fee', 'zarinpal_ajax_get_fee');
add_action('wp_ajax_nopriv_get_zarinpal_fee', 'zarinpal_ajax_get_fee');

add_action('wp_ajax_zarinpal_update_payment_method', 'zarinpal_update_payment_method');
add_action('wp_ajax_nopriv_zarinpal_update_payment_method', 'zarinpal_update_payment_method');

function zarinpal_update_payment_method() {
    $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
    $nonce = sanitize_text_field($_POST['nonce'] ?? '');
    
    $nonce_valid = false;
    if (check_ajax_referer('update_order_review', 'nonce', false)) {
        $nonce_valid = true;
    } elseif ($nonce === 'zarinpal_checkout_nonce') {
        $nonce_valid = true;
    }
    
    if (!$nonce_valid) {
        wp_send_json_error(array('message' => 'Security check failed'));
        return;
    }
    
    if (WC()->session) {
        $old_method = WC()->session->get('chosen_payment_method');
        WC()->session->set('chosen_payment_method', $payment_method);
        
        if (WC()->cart) {
            $cart_fees = WC()->cart->get_fees();
            foreach ($cart_fees as $fee_key => $fee) {
                if (strpos($fee->name, 'کارمزد درگاه') !== false) {
                    unset(WC()->cart->fees[$fee_key]);
                }
            }
            
            WC()->cart->calculate_fees();
            WC()->cart->calculate_totals();
        }
    }
    
    wp_send_json_success(array(
        'payment_method' => $payment_method,
        'cart_total' => WC()->cart ? WC()->cart->get_total('') : 0
    ));
}

function zarinpal_ajax_get_fee() {
    if (!check_ajax_referer('zarinpal_fee_nonce', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Invalid nonce'));
    }
    
    $gateways = WC()->payment_gateways->payment_gateways();
    if (!isset($gateways['WC_ZPal'])) {
        wp_send_json_error(array('message' => 'Gateway not found'));
    }
    
    $gateway = $gateways['WC_ZPal'];
    if ($gateway->feePayer !== 'customer') {
        wp_send_json_error(array('message' => 'Fee not applicable'));
    }
    
    $cart_total = floatval($_POST['cart_total']);
    if ($cart_total <= 0) {
        wp_send_json_error(array('message' => 'Invalid amount'));
    }
    
    $currency = get_woocommerce_currency();
    $amount_in_rial = intval($cart_total);
    $currency_lower = strtolower($currency);
    
    if ($currency_lower === 'irht') {
        $amount_in_rial *= 10000;
    } elseif ($currency_lower === 'irhr') {
        $amount_in_rial *= 1000;
    } elseif ($currency_lower === 'irt') {
        $amount_in_rial *= 10;
    }
    
    try {
        $fee_data = $gateway->zarinpal->calculateFee($amount_in_rial, 'IRR');
        
        if (isset($fee_data['fee_type']) && $fee_data['fee_type'] === 'Merchant' && 
            isset($fee_data['fee']) && $fee_data['fee'] > 0) {
            
            $fee_amount = $fee_data['fee'];
            
            if ($currency_lower === 'irht') {
                $fee_amount /= 10000;
            } elseif ($currency_lower === 'irhr') {
                $fee_amount /= 1000;
            } elseif ($currency_lower === 'irt') {
                $fee_amount /= 10;
            }
            

            $decimals = wc_get_price_decimals();
            if ($fee_amount < 1000) {
                $decimals = max($decimals, 3);
            }
            $fee_amount = round($fee_amount, $decimals);
            
            wp_send_json_success(array(
                'fee_amount' => $fee_amount,
                'fee_formatted' => wc_price($fee_amount),
                'fee_type' => $fee_data['fee_type']
            ));
        } else {
            wp_send_json_success(array(
                'fee_amount' => 0, 
                'fee_formatted' => '',
                'fee_type' => isset($fee_data['fee_type']) ? $fee_data['fee_type'] : 'unknown'
            ));
        }
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}

add_action('upgrader_process_complete', 'zarinpal_plugin_updated', 10, 2);
function zarinpal_plugin_updated($upgrader_object, $options) {
    if ($options['action'] == 'update' && $options['type'] == 'plugin') {
        if (isset($options['plugins'])) {
            foreach ($options['plugins'] as $plugin) {
                if (strpos($plugin, 'zarinpal') !== false || strpos($plugin, 'class-wc-gateway-zarinpal') !== false) {
                    $settings = get_option('woocommerce_WC_ZPal_settings');
                    if ($settings && isset($settings['merchantcode'])) {
                        delete_option('zarinpal_fee_detection_done_' . $settings['merchantcode']);
                    }
                    break;
                }
            }
        }
    }
}

add_action('wp_ajax_zpal_transaction_info', 'zpal_display_transaction_info');
add_action('wp_ajax_nopriv_zpal_transaction_info', 'zpal_display_transaction_info');
function zpal_display_transaction_info() {
    function gregorian_to_jalali($g_y, $g_m, $g_d) {
        $g_days_in_month = [31,28,31,30,31,30,31,31,30,31,30,31];
        $j_days_in_month = [31,31,31,31,31,31,30,30,30,30,30,29];
        $gy = $g_y - 1600;
        $gm = $g_m - 1;
        $gd = $g_d - 1;
        $g_day_no = 365 * $gy + floor(($gy + 3) / 4) - floor(($gy + 99) / 100) + floor(($gy + 399) / 400);
        for ($i = 0; $i < $gm; ++$i)
            $g_day_no += $g_days_in_month[$i];
        if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
            $g_day_no++;
        $g_day_no += $gd;
        $j_day_no = $g_day_no - 79;
        $j_np = floor($j_day_no / 12053);
        $j_day_no = $j_day_no % 12053;
        $jy = 979 + 33 * $j_np + 4 * floor($j_day_no / 1461);
        $j_day_no %= 1461;
        if ($j_day_no >= 366) {
            $jy += floor(($j_day_no - 366) / 365);
            $j_day_no = ($j_day_no - 366) % 365;
        }
        for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
            $j_day_no -= $j_days_in_month[$i];
        $jm = $i + 1;
        $jd = $j_day_no + 1;
        return [$jy, $jm, $jd];
    }
    function format_jalali_date($date_str) {
        if (empty($date_str)) {
            return '-';
        }
        $date = new DateTime($date_str);
        $g_y = (int)$date->format('Y');
        $g_m = (int)$date->format('m');
        $g_d = (int)$date->format('d');
        list($j_y, $j_m, $j_d) = gregorian_to_jalali($g_y, $g_m, $g_d);
        return sprintf('%04d/%02d/%02d %02d:%02d:%02d', $j_y, $j_m, $j_d, $date->format('H'), $date->format('i'), $date->format('s'));
    }
    $transaction_id = isset($_GET['transaction_id']) ? sanitize_text_field($_GET['transaction_id']) : '';
    $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
    if (!current_user_can('manage_woocommerce') || empty($transaction_id) || empty($order_id)) {
        wp_die(__('شما دسترسی لازم برای مشاهده این اطلاعات را ندارید.', WC_ZPAL_TEXT_DOMAIN));
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('سفارش پیدا نشد.', WC_ZPAL_TEXT_DOMAIN));
    }
    $settings = get_option('woocommerce_WC_ZPal_settings');
    $merchantCode = isset($settings['merchantcode']) ? $settings['merchantcode'] : '';
    $sandbox = (isset($settings['sandbox']) && $settings['sandbox'] === 'yes');
    $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
    if (empty($accessToken)) {
        echo '<p style="color:red; text-align:center;">' . __('برای استفاده از این سرویس باید توکن دسترسی خود را در تنظیمات درگاه زرین پال وارد نمایید.', WC_ZPAL_TEXT_DOMAIN) . '</p>';
        exit;
    }
    $zarinpal = new ZarinpalHelperClass($merchantCode, $sandbox, $accessToken);
    try {
        $authority = $order->get_meta('_zarinpal_authority');
        if (empty($authority)) {
            wp_die(__('کد آتوریتی برای این سفارش یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
        }
        $transactions = $zarinpal->getTransactions($authority);
        if ($transactions) {
            $transaction_info = $transactions[0];
            echo '<style>
                .transaction-container {
                    direction: rtl;
                    font-family: Tahoma, Arial, sans-serif;
                    margin: 20px auto;
                    max-width: 800px;
                    padding: 20px;
                    background-color: #f9f9f9;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    border-radius: 8px;
                }
                .transaction-title {
                    text-align: center;
                    margin-bottom: 20px;
                    color: #333;
                    font-size: 24px;
                    border-bottom: 2px solid #ddd;
                    padding-bottom: 10px;
                }
                .transaction-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .transaction-table th, .transaction-table td {
                    padding: 12px;
                    border: 1px solid #ddd;
                    text-align: right;
                }
                .transaction-table th {
                    background-color: #f2f2f2;
                    width: 30%;
                    font-weight: bold;
                }
                .transaction-table tr:nth-child(even) td {
                    background-color: #f5f5f5;
                }
                </style>';
            echo '<div class="transaction-container">';
            echo '<h2 class="transaction-title">' . __('اطلاعات تراکنش', WC_ZPAL_TEXT_DOMAIN) . '</h2>';
            echo '<table class="transaction-table">';
            function render_row($title, $value) {
                echo '<tr>';
                echo '<th>' . $title . '</th>';
                echo '<td>' . $value . '</td>';
                echo '</tr>';
            }
            render_row(__('شناسه پیگیری', WC_ZPAL_TEXT_DOMAIN), esc_html($transaction_info['id']));
            render_row(__('کد مرجع', WC_ZPAL_TEXT_DOMAIN), esc_html($transaction_info['reference_id'] ?? '-'));
            render_row(__('کد آتوریتی', WC_ZPAL_TEXT_DOMAIN), esc_html($transaction_info['authority'] ?? '-'));
            render_row(__('وضعیت تراکنش', WC_ZPAL_TEXT_DOMAIN), esc_html($transaction_info['status']));
            render_row(__('مبلغ', WC_ZPAL_TEXT_DOMAIN), esc_html(number_format($transaction_info['amount']) . ' ریال'));
            render_row(__('کارمزد', WC_ZPAL_TEXT_DOMAIN), esc_html(number_format($transaction_info['fee']) . ' ریال'));
            render_row(__('توضیحات', WC_ZPAL_TEXT_DOMAIN), esc_html($transaction_info['description']));
            $shamsi_date_created = !empty($transaction_info['created_at']) ? format_jalali_date($transaction_info['created_at']) : '-';
            render_row(__('تاریخ ایجاد', WC_ZPAL_TEXT_DOMAIN), esc_html($shamsi_date_created));
            $shamsi_date_reconciled = !empty($transaction_info['reconciled_at']) ? format_jalali_date($transaction_info['reconciled_at']) : '-';
            render_row(__('تاریخ تسویه', WC_ZPAL_TEXT_DOMAIN), esc_html($shamsi_date_reconciled));
            $session_tries = $transaction_info['session_tries'];
            if (!empty($session_tries)) {
                $first_try = $session_tries[0];
                $card_pan = isset($first_try['card_pan']) ? $first_try['card_pan'] : '-';
                $rrn = isset($first_try['rrn']) ? $first_try['rrn'] : '-';
                $payer_ip = isset($first_try['payer_ip']) ? $first_try['payer_ip'] : '-';
            } else {
                $card_pan = '-';
                $rrn = '-';
                $payer_ip = '-';
            }
            render_row(__('شماره کارت', WC_ZPAL_TEXT_DOMAIN), esc_html($card_pan));
            render_row(__('RRN', WC_ZPAL_TEXT_DOMAIN), esc_html($rrn));
            render_row(__('آیپی پرداخت کننده', WC_ZPAL_TEXT_DOMAIN), esc_html($payer_ip));
            echo '</table>';
            echo '</div>';
        } else {
            echo '<p>' . __('اطلاعاتی برای این تراکنش یافت نشد.', WC_ZPAL_TEXT_DOMAIN) . '</p>';
        }
    } catch (Exception $e) {
        echo '<p>' . __('خطا در دریافت اطلاعات تراکنش: ', WC_ZPAL_TEXT_DOMAIN) . esc_html($e->getMessage()) . '</p>';
    }
    exit;
}

add_action('wp_ajax_zpal_manual_verify', 'zpal_manual_verify_transaction');
function zpal_manual_verify_transaction() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('شما دسترسی لازم برای انجام این عملیات را ندارید.', WC_ZPAL_TEXT_DOMAIN));
    }
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    if (!$order_id) {
        wp_die(__('سفارش یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_die(__('سفارش یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
    }
    $settings = get_option('woocommerce_WC_ZPal_settings');
    $merchantCode = isset($settings['merchantcode']) ? $settings['merchantcode'] : '';
    $sandbox = (isset($settings['sandbox']) && $settings['sandbox'] === 'yes');
    $accessToken = isset($settings['access_token']) ? $settings['access_token'] : '';
    $zarinpal = new ZarinpalHelperClass($merchantCode, $sandbox, $accessToken);
    $authority = $order->get_meta('_zarinpal_authority');
    if (empty($authority)) {
        wp_die(__('کد آتوریتی برای این سفارش یافت نشد.', WC_ZPAL_TEXT_DOMAIN));
    }
    $order_total = $order->get_total();
    $amount = intval($order_total);
    $currency = strtolower($order->get_currency());
    if ($currency === 'irht') {
        $amount *= 10000;
    } elseif ($currency === 'irhr') {
        $amount *= 1000;
    } elseif ($currency === 'irt') {
        $amount *= 10;
    }
    
    $verify_amount = $amount;
    $fee_payer = isset($settings['fee_payer']) ? $settings['fee_payer'] : 'merchant';
    if ($fee_payer === 'customer') {
        $fee_data = $order->get_meta('_zarinpal_fee_data');
        if ($fee_data && is_array($fee_data)) {
            if (isset($fee_data['order_total'], $fee_data['suggested_amount'], $fee_data['timestamp'], $fee_data['fee_type'])) {
                if ($fee_data['order_total'] == $amount && $fee_data['fee_type'] === 'Merchant') {
                    if ((time() - $fee_data['timestamp']) < 3600) {
                        $verify_amount = $fee_data['suggested_amount'];
                    }
                }
            }
        }
    }
    
    try {
        $response = $zarinpal->verifyPayment($authority, $verify_amount);
        if ($response['code'] == 100) {
            $transaction_id = $response['ref_id'];
            if (!$order->is_paid()) {
                $order->payment_complete($transaction_id);
            }
            $order->add_order_note(sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_ZPAL_TEXT_DOMAIN), $transaction_id));
            $message = sprintf(__('پرداخت با موفقیت انجام شد. کد رهگیری: %s', WC_ZPAL_TEXT_DOMAIN), $transaction_id);
            echo '<div class="notice notice-success is-dismissible"><p>' . $message . '</p></div>';
        } elseif ($response['code'] == 101) {
            $message = __('تراکنش قبلا وریفای شده است.', WC_ZPAL_TEXT_DOMAIN);
            echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
        } else {
            throw new Exception('تراکنش ناموفق بود.');
        }
    } catch (Exception $e) {
        echo '<div class="notice notice-error is-dismissible"><p>' . __('خطا: ', WC_ZPAL_TEXT_DOMAIN) . esc_html($e->getMessage()) . '</p></div>';
    }
    wp_die();
}

add_action('woocommerce_admin_order_data_after_order_details', 'zpal_manual_verify_button');
function zpal_manual_verify_button($order) {
    if ($order->get_payment_method() !== 'WC_ZPal') {
        return;
    }
    $order_id = $order->get_id();
    ?>
    <p style="margin-top:20px;">
        <a href="#" id="zpal-manual-verify-btn" class="button button-primary" style="margin-top:20px;">
            <?php _e('اعتبارسنجی مجدد تراکنش', WC_ZPAL_TEXT_DOMAIN); ?>
        </a>
    </p>
    <div id="zpal-manual-verify-result"></div>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#zpal-manual-verify-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                btn.prop('disabled', true);
                $('#zpal-manual-verify-result').html('<div class="notice notice-info is-dismissible"><p><?php echo esc_js(__('در حال بررسی تراکنش...', WC_ZPAL_TEXT_DOMAIN)); ?></p></div>');
                $.post(ajaxurl, { action: 'zpal_manual_verify', order_id: <?php echo intval($order_id); ?> }, function(response) {
                    $('#zpal-manual-verify-result').html(response);
                    btn.prop('disabled', false);
                });
            });
        });
    </script>
    <?php
}
?>
