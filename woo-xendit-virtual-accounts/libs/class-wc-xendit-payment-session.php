<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Xendit_Payment_Session_Gateway extends WC_Payment_Gateway
{
    use Xendit_Gateway_Trait;

    const DEFAULT_CHECKOUT_FLOW = 'CHECKOUT_PAGE';
    const DEFAULT_EXTERNAL_ID_VALUE = 'woocommerce-xendit';

    const API_KEY_FIELDS = array('dummy_api_key', 'dummy_secret_key', 'dummy_api_key_dev', 'dummy_secret_key_dev');
    /**
     * @var WC_Xendit_Payment_Session_Gateway
     */
    private static $_instance;

    /** @var string $method_code */
    public $method_code;

    /** @var string $developmentmode */
    public $developmentmode = '';

    /** @var string $success_payment_xendit */
    public $success_payment_xendit;

    /** @var string $checkout_msg */
    public $checkout_msg = 'Thank you for your order, please follow the account numbers provided to pay with secured Xendit.';

    /** @var string $xendit_callback_url */
    public $xendit_callback_url;

    /** @var string $generic_error_message */
    public $generic_error_message = 'We encountered an issue while processing the checkout. Please contact us. ';

    /** @var string $xendit_status */
    public $xendit_status;

    /** @var string $reference_id_format */
    public $reference_id_format;

    /** @var string $external_id_format */
    public $external_id_format; //TODO: Mark for removal after WC_Xendit_Invoice is deleted

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

    /** @var string $notification_url */
    private $notification_url = '';

    /**
     * @var boolean $use_transaction_id_for_refund_order_id
     */
    private $use_transaction_id_for_refund_order_id = false;

    /**
     * Constructor
     */
    public function __construct()
    {
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
            'products',
            'refunds'
        );

        $this->xenditClass = new WC_Xendit_PG_API();

        $this->init_form_fields();
        $this->init_settings();

        // user setting variables
        $this->title = $this->get_xendit_title();
        $this->description = $this->get_xendit_description();

        $this->developmentmode = $this->get_option('developmentmode');

        $this->success_payment_xendit = $this->get_option('success_payment_xendit');
        $this->notification_url = $this->get_option('notification_url');

        $this->xendit_status = $this->developmentmode == 'yes' ? "[Development]" : "[Production]";

        $this->reference_id_format = $this->get_option('external_id_format'); // make the same as extenal_id first
        $this->external_id_format = !empty($this->get_option('external_id_format')) ? $this->get_option('external_id_format') : 'woocommerce-xendit'; //TODO: Mark for removal after WC_Xendit_Invoice is deleted
        $this->redirect_after = !empty($this->get_option('redirect_after')) ? $this->get_option('redirect_after') : self::DEFAULT_CHECKOUT_FLOW;
        $this->for_user_id = $this->get_option('on_behalf_of');
        $this->enable_xenplatform = $this->for_user_id ? 'yes' : $this->get_option('enable_xenplatform');

        // API Key
        $this->publishable_key = $this->developmentmode == 'yes' ? $this->get_option('api_key_dev') : $this->get_option('api_key');
        $this->secret_key = $this->developmentmode == 'yes' ? $this->get_option('secret_key_dev') : $this->get_option('secret_key');

        $this->oauth_data = WC_Xendit_Oauth::getXenditOAuth();

        // Generate OAuth link
        $this->oauth_link = $this->get_oauth_link();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));

        add_filter('woocommerce_available_payment_gateways', array(&$this, 'check_gateway_status'));
        wp_register_script('sweetalert', plugins_url('assets/js/frontend/sweetalert.min.js', WC_XENDIT_PG_MAIN_FILE), null, WC_XENDIT_PG_VERSION, true);
        wp_enqueue_script('sweetalert');
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
     * @return WC_Xendit_Payment_Session_Gateway
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
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
            $cached = get_transient('xendit_merchant_info');

            if (!empty($cached)) {
                // 1. Transient exists — use it directly
                $this->merchant_info = $cached;
                $this->is_connected  = !empty($this->merchant_info['business_id']);
                return;
            }

            // 2. Check xendit_gateway_settings for a stored business_id before hitting the API
            $gateway_settings = get_option('woocommerce_xendit_gateway_settings', []);
            if (!empty($gateway_settings['business_id'])) {
                $merchant_info = ['business_id' => $gateway_settings['business_id'], 'name' => $gateway_settings['name']];
                set_transient('xendit_merchant_info', $merchant_info, 3600);
                $this->merchant_info = $merchant_info;
                $this->is_connected  = true;
                return;
            }

            // 3. Fall back to API call
            if (empty($this->xenditClass)) {
                return;
            }

            $response = $this->xenditClass->getMerchant();
            if (!empty($response['error_code'])) {
                throw new Exception($response['message']);
            }

            if (!empty($response['business_id'])) {
                $this->merchant_info = $response;
                set_transient('xendit_merchant_info', $response, 3600);

                // Persist business_id into gateway settings to avoid future API calls
                $gateway_settings['business_id'] = $response['business_id'];
                $gateway_settings['name'] = $response['name'];
                update_option('woocommerce_xendit_gateway_settings', $gateway_settings);

                $this->is_connected = true;
            }
        } catch (\Exception $e) {
            WC_Xendit_PG_Logger::log('[XenditGateway] get_xendit_connection error: ' . $e->getMessage());

            if (is_admin()) {
                WC_Admin_Settings::add_error(esc_html($e->getMessage()));
            }
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
   
        $is_new_merchant = $this->get_option('is_new_merchant_when_payment_session_introduced');

        // Show the toggle only if they are current merchant integrated with WooCommerce
        // Otherwise delete the form fields of 'enable_payment_session'
        if ($is_new_merchant == 'yes') {
            unset($this->form_fields['payment_session_option'], $this->form_fields['enable_payment_session']);
        }
    }

    /**
     * Render a toggle switch for WooCommerce settings fields with type 'toggle'.
     */
    public function generate_toggle_html($key, $data): string
    {
        $field_key = $this->get_field_key($key);
        $checked   = ($this->get_option($key) === 'yes') ? 'checked' : '';
        $title     = $data['title'] ?? '';
        $desc      = $data['description'] ?? '';

        ob_start();
        include plugin_dir_path(__FILE__) . 'views/admin/toggle-field.php';
        return ob_get_clean();
    }

    /**
     * Validate toggle field — maps checkbox value to 'yes'/'no'.
     */
    public function validate_toggle_field($key, $value): string
    {
        return ($value === 'yes') ? 'yes' : 'no';
    }

    public function payment_fields()
    {
        include plugin_dir_path(__FILE__) . 'views/admin/payment-fields.php';
    }

    public function receipt_page($order_id)
    {
        include plugin_dir_path(__FILE__) . 'views/admin/receipt-page.php';
    }

    /**
     * Process payment via Xendit Payment Session API
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id)
    {
        try {
            $return_url = '';
            $order = wc_get_order($order_id);
            $store_url = !empty($this->notification_url) ? $this->notification_url : home_url();

            $body = array(
                'woocommerce_order_id'       => $order->get_id(),
                'cart_hash'                  => $order->get_cart_hash(),
                'plugin_version'             => WC_XENDIT_PG_VERSION,
                'reference_id'               => $this->generate_reference_id($order, $this->reference_id_format),
                'amount'                     => strval($order->get_total()),
                'currency'                   => $order->get_currency(),
                'country'                    => WC()->countries->get_base_country(),
                'description'                => WC_Xendit_PG_Helper::generate_description($order),
                'success_return_url'         => $this->format_return_url($this->get_return_url($order), $store_url),
                'cancel_return_url'          => $this->format_return_url(wc_get_checkout_url(), $store_url),
                'store_url'                  => $store_url,
                'items'                      => $this->map_items_to_session_payload($order),
                'customer'                   => $this->map_customer_to_session_payload($order),
                'billing_address'            => $this->map_address_to_session_payload($order)
            );

            $response = $this->xenditClass->createCheckoutSession($body);

            if (!empty($response['error_code'])) {
                $message = isset($response['message']) ? $response['message'] : 'Unknown error occurred.';
                wc_add_notice($message, 'error');

                return array(
                    'result' => 'failure',
                    'message' => $message
                );
            }

            $payment_link_url = esc_url($response['payment_link_url']);
            $order->update_meta_data('payment_session_id', $response['payment_session_id']);
            $order->update_meta_data('payment_session_payment_link_url', $payment_link_url);
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
                    $return_url = $payment_link_url;
            }

            // Clear cart session
            if (WC()->cart) {
                WC()->cart->empty_cart();
            }

            return array(
                'result'   => 'success',
                'redirect' => $return_url,
            );
        } catch (Throwable $e) {
            WC_Xendit_PG_Logger::log("[Xendit Payment Session] Error on process_payment : ".$e->getMessage());

            if ($e instanceof Exception) {
                wc_add_notice($e->getMessage(), 'error');
            }

            return array(
                'result' => 'failure',
                'message' => $e->getMessage()
            );
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
                            $this->invalidate_merchant_cache();
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

    /**
     * Copy and modified from libs/class-wc-xendit-helper.php
     * @param $transaction_id
     * @param $status
     * @param $payment_method
     * @param $currency
     * @param $amount
     * @param $installment
     * @return string
     */
    public function build_order_notes($transaction_id, $status, $currency, $amount)
    {
        $notes  = "Transaction ID: " . $transaction_id . "<br>";
        $notes .= "Status: " . $status . "<br>";
        $notes .= "Amount: " . $currency . " " . number_format($amount);

        return $notes;
    }


    /*******************************************************************************
        Private function
     *******************************************************************************/

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

    private function format_return_url($return_url, $store_url) {
        $store_url = rtrim($store_url, '/');
        return preg_replace('/^https?:\/\/[^\/?]+/', $store_url, $return_url);
    }

    private function map_customer_to_session_payload(WC_Order $order) {
        $email = $order->get_billing_email();
        $customer = array(
            'individual_detail' => array(
                'given_names' => $order->get_billing_first_name(),
                'surname' => $order->get_billing_last_name()
            )
        );

        if (!empty($email)) {
            $customer['email'] = $email;
        }

        return $customer;
    }

    private function map_address_to_session_payload(WC_Order $order) {
        $has_shipping = !empty($order->get_shipping_country()) && !empty($order->get_shipping_address_1());

        if ($has_shipping) {
            return array(
                'country'       => $order->get_shipping_country(),
                'street_line1'  => $order->get_shipping_address_1(),
                'street_line2'  => $order->get_shipping_address_2(),
                'city'          => $order->get_shipping_city(),
                'province_state' => $order->get_shipping_state(),
                'postal_code'   => $order->get_shipping_postcode()
            );
        }

        return array(
            'country'       => $order->get_billing_country(),
            'street_line1'  => $order->get_billing_address_1(),
            'street_line2'  => $order->get_billing_address_2(),
            'city'          => $order->get_billing_city(),
            'province_state' => $order->get_billing_state(),
            'postal_code'   => $order->get_billing_postcode()
        );
    }

    private function map_items_to_session_payload(WC_Order $order) {
        $items = array();
        foreach ($order->get_items() as $item_data) {
            if (!is_object($item_data)) {
                continue;
            }

            // Get an instance of WC_Product object
            /** @var WC_Product $product */
            $product = $item_data->get_product();
            if (!is_object($product)) {
                continue;
            }

            // Get all category names of item
            $category_names = wp_get_post_terms($item_data->get_product_id(), 'product_cat', ['fields' => 'names']);

            $item = array();
            $item['id']         = $product->get_id();
            $item['name']       = $product->get_name();
            $item['price']      = strval($order->get_item_subtotal($item_data));
            $item['quantity']   = $item_data->get_quantity();

            if (!empty(get_permalink($item['id']))) {
                $item['url']    = get_permalink($item['id']);
            }

            if (!empty($category_names)) {
                $item['category']   = implode(', ', $category_names);
            }

            array_push($items, $item);
        }

        return $items;
    }

    /**
     * Generate reference id
     * copied from libs/class-wc-xendit-helper.php from generate_extenal_id
     *
     * @param WC_Order $order
     * @param $reference_id_format
     * @return string
     */
    private function generate_reference_id(WC_Order $order, $reference_id_format): string
    {
        $identifier = $order->get_id();
        if (WC_Xendit_PG_Helper::is_advanced_order_number_active()) {
            if (method_exists($order, 'get_order_number')) {
                $identifier = $order->get_order_number();
            }
        }
        return sprintf('%s-%s', $reference_id_format, $identifier);
    }

    /**
     * Bust the merchant info cache when credentials change so get_xendit_connection()
     * re-fetches from the API with the new keys on the next request.
     */
    private function invalidate_merchant_cache(): void
    {
        WC_Xendit_Oauth::clearMerchantCache();
        unset($this->settings['business_id'], $this->settings['name']);
    }
}
