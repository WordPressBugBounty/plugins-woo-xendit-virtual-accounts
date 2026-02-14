<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Xendit_Invoice extends WC_Payment_Gateway
{
    const DEFAULT_MAXIMUM_AMOUNT = 1000000000;
    const DEFAULT_MINIMUM_AMOUNT = 1;
    const DEFAULT_EXTERNAL_ID_VALUE = 'woocommerce-xendit';
    const DEFAULT_CHECKOUT_FLOW = 'CHECKOUT_PAGE';

    const API_KEY_FIELDS = array('dummy_api_key', 'dummy_secret_key', 'dummy_api_key_dev', 'dummy_secret_key_dev');

    /**
     * @var WC_Xendit_Invoice
     */
    private static $_instance;

    /** @var bool $isActionCalled */
    public $isActionCalled = false;

    /** @var string $method_code */
    public $method_code;

    /** @var string $developmentmode */
    public $developmentmode = '';

    /** @var string $showlogo */
    public $showlogo = 'yes';

    /** @var string $success_response_xendit */
    public $success_response_xendit = 'COMPLETED';

    /** @var string $success_payment_xendit */
    public $success_payment_xendit;

    /** @var string $responce_url_sucess */
    public $responce_url_sucess;

    /** @var string $checkout_msg */
    public $checkout_msg = 'Thank you for your order, please follow the account numbers provided to pay with secured Xendit.';

    /** @var string $xendit_callback_url */
    public $xendit_callback_url;

    /** @var string $generic_error_message */
    public $generic_error_message = 'We encountered an issue while processing the checkout. Please contact us. ';

    /** @var string $xendit_status */
    public $xendit_status;

    /** @var array $msg */
    public $msg = ['message' => '', 'class' => ''];

    /** @var string $external_id_format */
    public $external_id_format;

    /** @var string $redirect_after */
    public $redirect_after;

    /** @var string $for_user_id */
    public $for_user_id;

    /** @var string $enable_xenplatform */
    public $enable_xenplatform;

    /** @var string $publishable_key */
    public $publishable_key;

    /** @var string $secret_key */
    public $secret_key;

    /** @var WC_Xendit_PG_API $xenditClass */
    public $xenditClass;

    /** @var false|mixed|null $oauth_data */
    public $oauth_data;

    /** @var string $oauth_link */
    public $oauth_link;

    /** @var bool $is_connected */
    public $is_connected = false;

    /** @var array|mixed $merchant_info */
    public $merchant_info;

    /** @var int $setting_processed */
    public static $setting_processed = 0;

    /**
     * @var string $method_type
     */
    public $method_type = '';

    /** @var string $default_title */
    public $default_title = '';

    /**
     * @var int $DEFAULT_MAXIMUM_AMOUNT
     */
    public $DEFAULT_MAXIMUM_AMOUNT = 0;

    /**
     * @var int $DEFAULT_MINIMUM_AMOUNT
     */
    public $DEFAULT_MINIMUM_AMOUNT = 0;

    /**
     * Constructor
     */
    public function __construct()
    {
        // check constants of XENDIT_ENV
        $env = !empty(XENDIT_ENV) ? XENDIT_ENV : 'production';

        $this->id = 'xendit_gateway';
        $this->has_fields = true;
        $this->method_title = 'Xendit';
        $this->default_title = 'Xendit Payment Gateway';
        $this->method_type = $this->method_title;
        /* translators: %1$s: Payment Method Accepted, %2%s: Xendit Login Link, %3%s: Xendit Register Link, %4%s: Developer Link. */
        $this->method_description = sprintf(wp_kses(__('Collect payment from %1$s on checkout page and get the report realtime on your Xendit Dashboard. <a href="%2$s" target="_blank">Sign In</a> or <a href="%3$s" target="_blank">sign up</a> on Xendit and integrate with your <a href="%4$s" target="_blank">Xendit keys</a>', 'woo-xendit-virtual-accounts'), ['a' => ['href' => true, 'target' => true]]), 'Bank Transfer (Virtual Account), Credit Card, Direct Debit, EWallet, QR Code & PayLater', 'https://dashboard.xendit.co/auth/login', 'https://dashboard.xendit.co/register', 'https://dashboard.xendit.co/settings/developers#api-keys');
        $this->method_code = strtoupper($this->method_title);
        $this->enabled = $this->get_option('enabled');

        $this->supports = array(
            'products'
        );

        $this->init_form_fields();
        $this->init_settings();

        // user setting variables
        $this->title = $this->get_xendit_title();
        $this->description = $this->get_xendit_description();

        $this->DEFAULT_MAXIMUM_AMOUNT = self::DEFAULT_MAXIMUM_AMOUNT;
        $this->DEFAULT_MINIMUM_AMOUNT = self::DEFAULT_MINIMUM_AMOUNT;

        $this->developmentmode = $this->get_option('developmentmode');

        $this->success_payment_xendit = $this->get_option('success_payment_xendit');
        $this->responce_url_sucess = $this->get_option('responce_url_calback');
        $this->xendit_callback_url = home_url() . '/?wc-api=wc_xendit_callback&xendit_mode=xendit_invoice_callback';

        $this->xendit_status = $this->developmentmode == 'yes' ? "[Development]" : "[Production]";

        $this->external_id_format = !empty($this->get_option('external_id_format')) ? $this->get_option('external_id_format') : self::DEFAULT_EXTERNAL_ID_VALUE;
        $this->redirect_after = !empty($this->get_option('redirect_after')) ? $this->get_option('redirect_after') : self::DEFAULT_CHECKOUT_FLOW;
        $this->for_user_id = $this->get_option('on_behalf_of');
        $this->enable_xenplatform = $this->for_user_id ? 'yes' : $this->get_option('enable_xenplatform');

        // API Key
        $this->publishable_key = $this->developmentmode == 'yes' ? $this->get_option('api_key_dev') : $this->get_option('api_key');
        $this->secret_key = $this->developmentmode == 'yes' ? $this->get_option('secret_key_dev') : $this->get_option('secret_key');

        $this->xenditClass = new WC_Xendit_PG_API($env);
        $this->oauth_data = WC_Xendit_Oauth::getXenditOAuth();

        // Generate OAuth link
        $this->oauth_link = $this->get_oauth_link();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));

        add_filter('woocommerce_available_payment_gateways', array(&$this, 'check_gateway_status'));
        add_action('woocommerce_order_status_changed', array(&$this, 'expire_invoice_when_order_cancelled'), 10, 3);
        wp_register_script('sweetalert', plugins_url('assets/js/frontend/sweetalert.min.js', WC_XENDIT_PG_MAIN_FILE), null, WC_XENDIT_PG_VERSION, true);
        wp_enqueue_script('sweetalert');

        // Init payment channels
        $this->init_activate_payment_channel();
    }

    /**
     * Get Xendit Oauth Link URL
     * @return string
     */
    public function get_oauth_link() {
        $tpi_gateway_url = $this->xenditClass->get_tpi_gateway_domain_url();

        $dashboard_url = XENDIT_ENV == 'staging' 
            ? XENDIT_DASHBOARD_URL_STAGING 
            : XENDIT_DASHBOARD_URL_PRODUCTION;
        

        $app_client_id = XENDIT_ENV == 'staging' 
            ? XENDIT_OAUTH_CLIENT_ID_STAGING
            : XENDIT_OAUTH_CLIENT_ID_PRODUCTION;

        // Generate Validation Key
        if (empty(WC_Xendit_Oauth::getValidationKey())) {
            $key = md5(wp_rand());
            WC_Xendit_Oauth::updateValidationKey($key);
        }

        $validation_key = WC_Xendit_Oauth::getValidationKey();

        $redirect_uri =  sprintf("%1s%2s", $tpi_gateway_url, XENDIT_OAUTH_REDIRECTION_URL_PATH);

        return esc_url_raw(
            sprintf(
                '%1s/oauth/authorize?client_id=%2s&response_type=code&state=WOOCOMMERCE|%3s|%4s?wc-api=wc_xendit_oauth|%5s&redirect_uri=%6s',
                $dashboard_url,
                $app_client_id,
                $validation_key,
                home_url(),
                WC_XENDIT_PG_VERSION,
                $redirect_uri
            ));
    }

    /**
     * @return WC_Xendit_Invoice
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @return bool
     */
    protected function onboarded_payment_channel(): bool
    {
        if (get_option('xendit_onboarding_payment_channel') == 1) {
            return true;
        }
        $this->update_option('enabled', 'yes'); // Enable Xendit_Gateway

        return update_option('xendit_onboarding_payment_channel', 1);
    }
    

    /**
     * Used to enable the default activate channel when onboarding
     *
     * @return void
     */
    protected function init_activate_payment_channel()
    {
        if (!is_admin()) {
            return;
        }

        $this->onboarded_payment_channel();
    }

    /**
     * @return string
     */
    protected function generate_api_key_settings_html(): string
    {
        $form_fields = $this->form_fields;

        foreach ($form_fields as $index => $field) {
            if (!in_array($index, array_merge(self::API_KEY_FIELDS, ['developmentmode', 'api_keys_help_text']))) {
                unset($form_fields[ $index ]);
            }
        }

        return $this->generate_settings_html($form_fields, false);
    }

    /**
     * @return void
     */
    protected function get_xendit_connection()
    {
        try {
            if (empty(get_transient('xendit_merchant_info'))) {
                $response = $this->xenditClass->getMerchant();
                if (!empty($response['error_code'])) {
                    throw new Exception($response['message']);
                }

                if (!empty($response['business_id'])) {
                    $this->merchant_info = $response;
                    set_transient('xendit_merchant_info', $response, 3600);
                    $this->is_connected = true;
                }
            } else {
                $this->merchant_info = get_transient('xendit_merchant_info');
                $this->is_connected = !empty($this->merchant_info['business_id']);
            }
        } catch (\Exception $e) {
            WC_Admin_Settings::add_error(esc_html($e->getMessage()));
        }
    }

    /**
     * @return void
     */
    protected function initialize_xendit_onboarding_info()
    {
        include plugin_dir_path(__FILE__) . 'views/admin/onboarding-info.php';
    }

    /**
     * @return void
     */
    protected function show_merchant_info()
    {
        include plugin_dir_path(__FILE__) . 'views/admin/merchant-info.php';
    }

    /**
     * @return void
     * @throws Exception
     */
    public function admin_options()
    {
        $this->get_xendit_connection(); // Always check the Xendit connection on the top of admin_options
        $this->initialize_xendit_onboarding_info();
        
        include plugin_dir_path(__FILE__) . 'views/admin/admin-options.php';
    }

    /**
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = require(WC_XENDIT_PG_PLUGIN_PATH . '/libs/settings/wc-xendit-gateway-settings.php');
    }

    public function payment_fields()
    {
        include plugin_dir_path(__FILE__) . 'views/admin/payment-fields.php';
    }

    public function receipt_page($order_id)
    {
        include plugin_dir_path(__FILE__) . 'views/admin/receipt-page.php';
    }

    public function get_success_redirect_url($order): string
    {
        $returnUrl = $this->get_return_url($order);

        if (strpos($returnUrl, home_url()) === false) {
            $returnUrl = rtrim(wc_get_checkout_url(), '/') . $returnUrl;
        }

        return $returnUrl;
    }

    /**
     * @param $order_id
     * @return array|void
     * @throws Exception
     */
    public function process_payment($order_id)
    {
        try {
            $order = wc_get_order($order_id);
            $amount = $order->get_total();
            $currency = $order->get_currency();

            if ($amount < $this->DEFAULT_MINIMUM_AMOUNT) {
                WC_Xendit_PG_Helper::cancel_order($order, 'Cancelled because amount is below minimum amount');
                /* translators: %1%s: Currency, 2: Min Amount. */
                $err_msg = sprintf(__(
                    'The minimum amount for using this payment is %1$s %2$s. Please put more item(s) to reach the minimum amount. Code: 100001',
                    'woo-xendit-virtual-accounts'
                ), $currency, wc_price($this->DEFAULT_MINIMUM_AMOUNT));

                wc_add_notice($this->get_localized_error_message('INVALID_AMOUNT_ERROR', $err_msg), 'error');

                return array(
                    'result' => 'failure',
                    'message' => $err_msg,
                );
            }

            if ($amount > $this->DEFAULT_MAXIMUM_AMOUNT) {
                WC_Xendit_PG_Helper::cancel_order($order, 'Cancelled because amount is above maximum amount');

                /* translators: %1%s: Currency, 2: Max Amount. */
                $err_msg = sprintf(__(
                    'The maximum amount for using this payment is %1$s %2$s. Please remove one or more item(s) from your cart. Code: 100002',
                    'woo-xendit-virtual-accounts'
                ), $currency, wc_price($this->DEFAULT_MAXIMUM_AMOUNT));

                wc_add_notice($this->get_localized_error_message('INVALID_AMOUNT_ERROR', $err_msg), 'error');

                return array(
                    'result' => 'failure',
                    'message' => $err_msg,
                );;
            }

            $blog_name = html_entity_decode(get_option('blogname'), ENT_QUOTES | ENT_HTML5);
            $description = WC_Xendit_PG_Helper::generate_invoice_description($order);

            $payer_email = !empty($order->get_billing_email()) ? $order->get_billing_email() : 'noreply@mail.com';
            $payment_gateway = wc_get_payment_gateway_by_order($order_id);

            // How likely this condition below will happened?
            if ($payment_gateway->id != $this->id) {
                return array(
                    'result' => 'failure',
                    'message' => 'Can\'t proceed the order with '.$payment_gateway->id,
                );
            }

            $invoice = $order->get_meta('Xendit_invoice');
            $invoice_exp = $order->get_meta('Xendit_expiry');

            $additional_data = WC_Xendit_PG_Helper::generate_items_and_customer($order);
            $invoice_data = array(
                'external_id' => WC_Xendit_PG_Helper::generate_external_id($order, $this->external_id_format),
                'amount' => $amount,
                'currency' => $currency,
                'payer_email' => $payer_email,
                'description' => $description,
                'client_type' => 'INTEGRATION',
                'success_redirect_url' => $this->get_success_redirect_url($order),
                'failure_redirect_url' => wc_get_checkout_url(),
                'platform_callback_url' => $this->xendit_callback_url,
                'checkout_redirect_flow' => $this->redirect_after,
                'customer' => !empty($additional_data['customer']) ? $additional_data['customer'] : '',
                'items' => !empty($additional_data['items']) ? $additional_data['items'] : ''
            );

            // Generate Xendit payment fees
            $fees = WC_Xendit_Payment_Fees::generatePaymentFees($order);
            if (!empty($fees)) {
                $invoice_data['fees'] = $fees;
            }

            $header = array(
                'x-plugin-method' => strtoupper($this->method_code),
                'x-plugin-store-name' => $blog_name
            );

            if ($invoice && $invoice_exp > time()) {
                $response = $this->xenditClass->getInvoice($invoice);
            } else {
                $response = $this->xenditClass->createInvoice($invoice_data, $header);
            }

            if (!empty($response['error_code'])) {
                $response['message'] = !empty($response['code']) ? $response['message'] . ' Code: ' . $response['code'] : $response['message'];
                $message = $this->get_localized_error_message($response['error_code'], $response['message']);
                $order->add_order_note('Checkout with invoice unsuccessful. Reason: ' . $message);

                throw new Exception($message);
            }

            if ($response['status'] == 'PAID' || $response['status'] == 'COMPLETED') {
                // Return thankyou redirect
                return array(
                    'result'    => 'success',
                    'redirect'  => $this->get_return_url($order)
                );
            }

            $xendit_invoice_url = esc_attr($response['invoice_url']);
            $order->update_meta_data('Xendit_invoice', esc_attr($response['id']));
            $order->update_meta_data('Xendit_invoice_url', $xendit_invoice_url);
            $order->update_meta_data('Xendit_expiry', esc_attr(strtotime($response['expiry_date'])));
            $order->save();

            switch ($this->redirect_after) {
                case 'ORDER_RECEIVED_PAGE':
                    $args = array(
                        'utm_nooverride' => '1',
                        'order_id' => $order_id,
                    );
                    $return_url = esc_url_raw(add_query_arg($args, $this->get_return_url($order)));
                    break;
                case 'CHECKOUT_PAGE':
                default:
                    $return_url = $xendit_invoice_url;
            }

            // clear cart session
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }

            // Return thankyou redirect
            return array(
                'result' => 'success',
                'redirect' => $return_url,
            );
        } catch (Throwable $e) {
            if ($e instanceof Exception) {
                wc_add_notice($e->getMessage(), 'error');
            }
            $metrics = $this->xenditClass->constructMetricPayload('woocommerce_checkout', array(
                'type' => 'error',
                'payment_method' => strtoupper($this->method_code),
                'error_message' => $e->getMessage()
            ));
            $this->xenditClass->trackMetricCount($metrics);

            return array(
                'result' => 'failure',
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * @param $response
     * @return void
     */
    public function validate_payment($response)
    {
        global $wpdb, $woocommerce;

        try {
            $external_id = $response->external_id;
            $exploded_ext_id = explode("-", $external_id);
            $order_num = end($exploded_ext_id);

            if (!is_numeric($order_num)) {
                $exploded_ext_id = explode("_", $external_id);
                $order_num = end($exploded_ext_id);
            }

            if (WC_Xendit_PG_Helper::is_advanced_order_number_active()) {
                // 1. Try direct meta query
                $orders = wc_get_orders(array(
                    'meta_key' => '_order_number',
                    'meta_value' => $order_num,
                    'limit' => 1
                ));
                
                if (!empty($orders) && count($orders) == 1) {
                    $order = $orders[0];
                    $order_num = $order->get_id();
                }
            }

            $order = wc_get_order($order_num);
            $order_id = $order->get_id();

            if ($this->developmentmode != 'yes') {
                $payment_gateway = wc_get_payment_gateway_by_order($order_id);
                if (false === get_post_status($order_id) || strpos($payment_gateway->id, 'xendit')) {
                    header('HTTP/1.1 400 Invalid Data Received');
                    die('Xendit is live and require a valid order id');
                }
            }

            if (in_array($response->status, array('PAID', 'SETTLED'))) {
                //update payment method in case customer change method after invoice is generated
                $order->set_payment_method($this->id);
                $order->set_payment_method_title($this->method_title);

                //save charge ID if paid by credit card
                if ($response->channel == 'CREDIT_CARD' && !empty($response->credit_card_charge_id)) {
                    $order->set_transaction_id($response->credit_card_charge_id);
                }

                $order->save();

                $notes = WC_Xendit_PG_Helper::build_order_notes(
                    $response->id,
                    $response->status,
                    $response->channel,
                    $order->get_currency(),
                    $order->get_total()
                );
                WC_Xendit_PG_Helper::complete_payment($order, $notes, $this->success_payment_xendit);

                // Empty cart in action
                $woocommerce->cart->empty_cart();

                die('Success');
            } else {
                if (empty($order->get_meta('Xendit_invoice_expired'))) {
                    $order->add_meta_data('Xendit_invoice_expired', 1);
                }
                $order->update_status('failed');

                $notes = WC_Xendit_PG_Helper::build_order_notes(
                    $response->id,
                    $response->status,
                    $response->channel,
                    $order->get_currency(),
                    $order->get_total()
                );

                $order->add_order_note( wp_kses("<b>Xendit payment failed.</b><br>", ['b' => true, 'br' => true]). $notes);
                die(esc_html('Invoice ' . $response->channel . ' status is ' . $response->status));
            }
        } catch (Exception $e) {
            header('HTTP/1.1 500 Server Error');
            echo esc_html($e->getMessage());
            exit;
        }
    }

    public function check_gateway_status($gateways)
    {
        global $woocommerce;

        if (is_null($woocommerce->cart)) {
            return $gateways;
        }

        if ($this->enabled == 'no') {
            // Disable all Xendit payments
            if ($this->id == 'xendit_gateway') {
                return array_filter($gateways, function ($gateway) {
                    return strpos($gateway->id, 'xendit') === false;
                });
            }

            unset($gateways[$this->id]);
            return $gateways;
        }

        if (!$this->xenditClass->isCredentialExist()) {
            unset($gateways[$this->id]);
            return $gateways;
        }

        /**
         * get_cart_contents_total() will give us just the final (float) amount after discounts.
         * Compatible with WC version 3.2.0 & above.
         * Source: https://woocommerce.github.io/code-reference/classes/WC-Cart.html#method_get_cart_contents_total
         */
        $amount = $woocommerce->cart->get_total('');
        if ($amount > $this->DEFAULT_MAXIMUM_AMOUNT) {
            unset($gateways[$this->id]);
            return $gateways;
        }

        return $gateways;
    }

    /**
     * Return filter of PG icon image in checkout page. Called by this class automatically.
     */
    public function get_icon()
    {
        $style = "style='margin-left: 0.3em; max-height: 28px; max-width: 65px;'";
        $icon = '<img src="' . plugins_url('assets/images/xendit.svg', WC_XENDIT_PG_MAIN_FILE) . '" alt="Xendit" ' . $style . ' />';

        return apply_filters('woocommerce_gateway_icon', $icon, $this->id);
    }

    /**
     * @return string
     */
    public function get_xendit_admin_description(): string
    {
        return $this->method_description;
    }

    public function get_xendit_title() {
        return !empty($this->get_option('channel_name')) ? $this->get_option('channel_name') : $this->default_title;
    }

    public function get_xendit_description() {
        return !empty($this->get_option('payment_description')) ? nl2br($this->get_option('payment_description')) : esc_html(__('Pay your order via Xendit Payment Gateway', 'woo-xendit-virtual-accounts'));
    }

    /**
     * @param $sub_account_id
     * @return true
     * @throws Exception
     */
    protected function validate_sub_account($sub_account_id): bool
    {
        if (empty($sub_account_id)) {
            throw new Exception(esc_html('Please enter XenPlatform User.'));
        }

        $response = $this->xenditClass->getSubAccount($sub_account_id);
        if (!empty($response['account_id'])) {
            return true;
        }

        if (!empty($response['error_code'])) {
            throw new Exception(esc_html($response['message']));
        }

        throw new Exception(esc_html('Validate XenPlatform User failed'));
    }

    /**
     * @param array $settings
     * @return bool
     */
    protected function is_test_mode(array $settings = []): bool
    {
        if (empty($settings['secret_key']) && !empty($settings['secret_key_dev'])) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function process_admin_options(): bool
    {
        // To avoid duplicated request
        if (self::$setting_processed > 0) {
            return false;
        }

        $this->init_settings();
        $post_data = $this->get_post_data();

        foreach ($this->get_form_fields() as $key => $field) {
            if ('title' !== $this->get_field_type($field)) {
                try {
                    $value = $this->get_field_value($key, $field, $post_data);

                    // map dummy api keys
                    if (in_array($key, self::API_KEY_FIELDS)) {
                        $real_key_field = str_replace('dummy_', '', $key);
                        $real_api_key_char_count = !empty($this->settings[$real_key_field]) ? strlen($this->settings[$real_key_field]) : 0;

                        if ($value === $this->generateStarChar($real_api_key_char_count)) { // skip when no changes
                            continue;
                        } else {
                            $this->settings[$real_key_field] = $value; // save real api keys in original field name
                        }
                        $this->settings[$key] = $this->generateStarChar($real_api_key_char_count); // always set dummy fields to ****
                        continue;
                    }

                    $this->settings[$key] = $value;
                } catch (Exception $e) {
                    WC_Admin_Settings::add_error(esc_html($e->getMessage()));
                }
            }
        }

        if (!isset($post_data['woocommerce_' . $this->id . '_enabled']) && $this->get_option_key() == 'woocommerce_' . $this->id . '_settings') {
            $this->settings['enabled'] = $this->id === 'xendit_gateway' ? 'no' : $this->enabled;
        }

        // default value
        if ($this->id === 'xendit_gateway') {
            $this->settings['external_id_format'] = empty($this->settings['external_id_format']) ? self::DEFAULT_EXTERNAL_ID_VALUE : $this->settings['external_id_format'];
        }

        // Update settings
        update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');
        self::$setting_processed += 1;

        // validate sub account
        try {
            if (isset($this->settings['enable_xenplatform']) && $this->settings['enable_xenplatform'] === 'yes') {
                $this->validate_sub_account($this->settings['on_behalf_of']);
            }
        } catch (Exception $e) {
            // Reset Xen Platform if validation failed
            $this->settings['enable_xenplatform'] = 'no';
            $this->settings['on_behalf_of'] = '';
            update_option($this->get_option_key(), apply_filters('woocommerce_settings_api_sanitized_fields_' . $this->id, $this->settings), 'yes');

            WC_Admin_Settings::add_error(esc_html($e->getMessage()));
            return false;
        }

        return true;
    }

    /**
     * @param $count
     * @return string
     */
    private function generateStarChar($count = 0): string
    {
        $result = '';
        for ($i = 0; $i < $count; $i++) {
            $result .= '*';
        }

        return $result;
    }

    public function get_localized_error_message($error_code, $message)
    {
        switch ($error_code) {
            case 'UNSUPPORTED_CURRENCY':
                return str_replace('{{currency}}', get_woocommerce_currency(), $message);
            default:
                return $message ? $message : $error_code;
        }
    }

    /**
     * @return string
     */
    public function get_xendit_option(string $key)
    {
        return $this->get_option($key);
    }

    /**
     * @param $order_id
     * @param $old_status
     * @param $new_status
     * @return void
     * @throws Exception
     */
    public function expire_invoice_when_order_cancelled($order_id, $old_status, $new_status)
    {
        if ($new_status !== 'cancelled') {
            return;
        }

        $order = wc_get_order($order_id);
        if ($order) {
            $payment_method = $order->get_payment_method();
            $xendit_invoice_expired = $order->get_meta('Xendit_invoice_expired');
            $xendit_invoice_id = $order->get_meta('Xendit_invoice');

            if (preg_match('/xendit/i', $payment_method)
                && empty($xendit_invoice_expired)
            ) {
                // Expire Xendit invoice
                $response = $this->xenditClass->expiredInvoice($xendit_invoice_id);
                if (!empty($response) && !isset($response['error_code'])) {
                    $order->add_meta_data('Xendit_invoice_expired', 1);
                    $order->save();
                }
            }
        }
    }

    /**
     * Cancel all unpaid orders after held duration to prevent stock lock for those products.
     */
    public function custome_cancel_unpaid_orders()
    {
        global $wpdb;

        $held_duration = get_option('woocommerce_hold_stock_minutes');

        if ($held_duration < 1 || 'yes' !== get_option('woocommerce_manage_stock')) {
            return;
        }

        $canceled_order = $wpdb->get_col(
            $wpdb->prepare(
				// @codingStandardsIgnoreStart
				"SELECT posts.ID
				FROM {$wpdb->posts} AS posts
                LEFT JOIN {$wpdb->postmeta} AS pm_expired
                    ON posts.ID = pm_expired.post_id
                        AND pm_expired.meta_key = 'Xendit_invoice_expired'
                LEFT JOIN {$wpdb->postmeta} AS pm_method
                    ON posts.ID = pm_method.post_id
                        AND pm_method.meta_key = '_payment_method'
				WHERE posts.post_type IN ('" . implode( "','", wc_get_order_types() ) . "')
                    AND posts.post_status = 'wc-cancelled'
                    AND `pm_method`.`meta_value` LIKE 'xendit_%'
                    AND pm_expired.meta_id IS NULL"
			)
		);

        if ($canceled_order) {
            foreach ($canceled_order as $cancel_order) {
                $order = wc_get_order( $cancel_order );
                $xendit_invoice_expired = $order->get_meta('Xendit_invoice_expired');
                $xendit_invoice_id = $order->get_meta('Xendit_invoice');
                if (empty($xendit_invoice_expired)) {
                    $order->add_meta_data('Xendit_invoice_expired', 1);
                    $order->save();

                    $response = $this->xenditClass->expiredInvoice($xendit_invoice_id);
                }
            }
        }
    }

    /**
     * @param $public_key
     * @param $public_key_dev
     * @return true
     */
    public function update_public_keys($public_key, $public_key_dev): bool
    {
        if (!empty($public_key)) {
            $this->update_option('api_key', $public_key);
        }

        if (!empty($public_key_dev)) {
            $this->update_option('api_key_dev', $public_key_dev);
        }

        return true;
    }
}
