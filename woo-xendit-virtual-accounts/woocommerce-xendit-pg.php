<?php
if (!defined('ABSPATH')) {
    exit;
}

/*
Plugin Name: Xendit Payment
Plugin URI: https://wordpress.org/plugins/woo-xendit-virtual-accounts
Description: Accept payments in Indonesia with Xendit. Seamlessly integrated into WooCommerce.
Version: 6.0.2
Requires Plugins: woocommerce
Text Domain: woo-xendit-virtual-accounts
Domain Path: /languages
Author: Xendit
Author URI: https://www.xendit.co/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

define('WC_XENDIT_PG_VERSION', '6.0.2');
define('WC_XENDIT_PG_MAIN_FILE', __FILE__);
define('WC_XENDIT_PG_PLUGIN_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

add_action('plugins_loaded', 'xendit_payment_init');

function xendit_payment_init()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    if (!class_exists('WC_Xendit_PG')) {
        class WC_Xendit_PG
        {
            private static $instance;

            public static function get_instance()
            {
                if (self::$instance === null) {
                    self::$instance = new self();
                }

                return self::$instance;
            }

            private function __construct()
            {
                $this->init();
            }

            public function init()
            {
                require_once dirname(__FILE__) . '/libs/constants/constants.php';
                require_once dirname(__FILE__) . '/libs/class-wc-xendit-api.php';

                require_once dirname(__FILE__) . '/libs/helpers/class-wc-payment-fees.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-expired.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-oauth-data.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-xendit-logger.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-xendit-site-data.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-phone-number-format.php';
                require_once dirname(__FILE__) . '/libs/helpers/class-wc-sanitized-webhook.php';

                require_once dirname(__FILE__) . '/libs/class-wc-xendit-helper.php';
                require_once dirname(__FILE__) . '/libs/class-wc-xendit-invoice.php';
                require_once dirname(__FILE__) . '/libs/class-wc-xendit-cc.php';
                require_once dirname(__FILE__) . '/libs/class-wc-xendit-cc-addons.php';

                require_once dirname(__FILE__) . '/libs/cronjob/wc-cron-xendit-site-data.php';
                require_once dirname(__FILE__) . '/libs/blocks/class-wc-xendit-blocks.php';

                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
                add_filter('woocommerce_payment_gateways', array($this, 'add_xendit_payment_gateway'));
            }

            /**
             * Adds plugin action links
             *
             * @since 1.0.0
             */
            public function plugin_action_links($links)
            {
                $setting_link = $this->get_setting_link();

                $plugin_links = array(
                    '<a href="' . $setting_link . '">' . esc_html(__('Settings', 'woo-xendit-virtual-accounts')) . '</a>',
                    '<a href="https://docs.xendit.co/integrations/woocommerce/">' . __('Docs', 'woo-xendit-virtual-accounts') . '</a>',
                    '<a href="https://help.xendit.co/hc/en-us">' . esc_html(__('Support', 'woo-xendit-virtual-accounts')) . '</a>',
                );
                return array_merge($plugin_links, $links);
            }

            /**
             * Get setting link.
             *
             * @return string Setting link
             * @since 1.0.0
             *
             */
            public function get_setting_link()
            {
                return admin_url('admin.php?page=wc-settings&tab=checkout&section=xendit_gateway');
            }

            /**
             * Show the payment settings in admin
             *
             * @param array $methods
             * @return array
             */
            public function xendit_payment_gateway_settings(array $methods = [])
            {
                return array_merge(
                    $methods ?? [],
                    array(
                        'WC_Xendit_Invoice',
                        $this->should_load_addons() ? 'WC_Xendit_CC_Addons' : 'WC_Xendit_CC'
                    )
                );
            }

            /**
             * Add xendit payment methods
             *
             * @param array $methods
             * @return array $methods
             */
            public function add_xendit_payment_gateway($methods)
            {
                $methods[] = 'WC_Xendit_Invoice';

                // For admin
                if (is_admin()) {
                    return $this->xendit_payment_gateway_settings($methods);
                }

                $cc_methods = 'WC_Xendit_CC';
                if ($this->should_load_addons()) {
                    $cc_methods = 'WC_Xendit_CC_Addons';
                }

                $methods[] = $cc_methods;

                return $methods;
            }

            /**
             * Determines if the addons should be loaded.
             *
             * This method checks if the necessary classes and functions for the addons are available in the system.
             *
             * @return bool Returns true if the addons should be loaded, false otherwise.
             */
            public function should_load_addons()
            {
                if (class_exists('WC_Subscriptions_Order') && function_exists('wcs_create_renewal_order')) {
                    return true;
                }

                if (class_exists('WC_Pre_Orders_Order')) {
                    return true;
                }

                return false;
            }

            /**
             * Plugin url.
             *
             * @return string
             */
            public static function plugin_url()
            {
                return untrailingslashit(plugins_url('/', __FILE__));
            }

            /**
             * Plugin url.
             *
             * @return string
             */
            public static function plugin_abspath()
            {
                return trailingslashit(plugin_dir_path(__FILE__));
            }
        }

        $GLOBALS['wc_xendit_pg'] = WC_Xendit_PG::get_instance();
    }

    add_action('rest_api_init', function () {
        register_rest_route('xendit-wc/v1', '/disconnect', array(
            'methods' => 'DELETE',
            'callback' => 'xendit_disconect',
            'permission_callback' => function () {
                return current_user_can('administrator') || current_user_can('shop_manager');
            },
        ));
    });

    function xendit_disconect()
    {
        // Delete OAuth data
        WC_Xendit_Oauth::removeXenditOAuth();

        // Delete API keys
        $main_settings = get_option('woocommerce_xendit_gateway_settings');
        foreach (['secret_key', 'secret_key_dev', 'api_key', 'api_key_dev'] as $key) {
            if (isset($main_settings[$key])) {
                unset($main_settings[$key]);
            }
        }
        update_option('woocommerce_xendit_gateway_settings', $main_settings);

        // Delete merchant info
        delete_transient('xendit_merchant_info');

        // Response
        $response = new WP_REST_Response(['message' => 'success']);
        $response->set_status(201);

        return $response;
    }

    add_action('rest_api_init', function () {
        register_rest_route('xendit-wc/v1', '/oauth_status', array(
            'methods' => 'GET',
            'callback' => 'xendit_oauth_status',
            'permission_callback' => function () {
                return current_user_can('administrator') || current_user_can('shop_manager');
            },
        ));
    });

    function xendit_oauth_status()
    {
        $data = ['is_connected' => false];
        $oauth_data = WC_Xendit_Oauth::getXenditOAuth();
        if (!empty($oauth_data)) {
            $data['is_connected'] = true;
        } else {
            $data['error_code'] = get_transient('xendit_oauth_error');
        }

        // Create the response object
        $response = new WP_REST_Response($data);
        // Add a custom status code
        $response->set_status(200);
        return $response;
    }

    add_action('woocommerce_api_wc_xendit_callback', 'check_xendit_response');
    function check_xendit_response()
    {
        global $wpdb, $woocommerce;

        if (isset($_REQUEST['xendit_mode'])) {
            try {
                if ($_REQUEST['xendit_mode'] == 'xendit_invoice_callback') {
                    $xendit = WC_Xendit_Invoice::instance();
                } elseif ($_REQUEST['xendit_mode'] == 'xendit_cc_callback') {
                    $xendit = WC_Xendit_CC::instance();
                }

                $xendit_status = $xendit->developmentmode == 'yes' ? "[Development]" : "[Production]";

                $script_base = str_replace(array("https://", "http://"), "", home_url());
                $script_base = str_replace($_SERVER['SERVER_NAME'], "", $script_base);
                $script_base = rtrim($script_base, '/');

                $data = file_get_contents("php://input");
                $response = json_decode($data);
                $response = WC_Xendit_Sanitized_Webhook::map_and_sanitize_invoice_webhook($response);

                $identifier = $response->external_id;

                $order = false;
                if (($_SERVER["REQUEST_METHOD"] === "POST")) {
                    if ($identifier) {
                        $exploded_ext_id = explode("-", $identifier);
                        $order_id = end($exploded_ext_id);

                        if (WC_Xendit_PG_Helper::is_advanced_order_number_active()) {
                            // 1. Try direct meta query
                            $orders = wc_get_orders(array(
                                'meta_key' => '_order_number',
                                'meta_value' => $order_id,
                                'limit' => 1
                            ));
                            
                            if (!empty($orders)) {
                                $order = $orders[0];
                                $order_id = $order->get_id();
                            }

                            if (!$order || !$order instanceof WC_Order) {
                                header('HTTP/1.1 404 Order Not Found');
                                echo 'Order not found';
                                exit;
                            }
                        }

                        $order = new WC_Order($order_id);
                        if (!$order->is_paid()) {
                            $xendit->validate_payment($response);
                        } else {
                            WC_Xendit_PG_Logger::log("{$xendit_status} [" . $identifier . "] Order ID $order_id is already updated.");
                            echo 'Order status is already updated';
                            exit;
                        }
                    }
                } else {
                    WC_Xendit_PG_Logger::log("{$xendit_status} [" . $identifier . "] Callback Request: Invalid callback!");
                    header('HTTP/1.1 501 Invalid Callback');
                    echo 'Invalid Callback';
                    exit;
                }
            } catch (Exception $e) {
                WC_Xendit_PG_Logger::log("{$xendit_status} [" . $identifier . "] Error in processing callback. " . $e->getMessage());
                header('HTTP/1.1 401');
                echo esc_html('Error in processing callback. ' . $e->getMessage());
                exit;
            }
        }
    }

    add_action('woocommerce_cancel_unpaid_orders', 'custome_cancel_unpaid_orders');
    function custome_cancel_unpaid_orders()
    {
        $xendit_invoice = WC_Xendit_Invoice::instance();
        $xendit_invoice->custome_cancel_unpaid_orders();
    }

    add_action('woocommerce_api_wc_xendit_oauth', 'xendit_oauth');
    function xendit_oauth()
    {
        if (($_SERVER["REQUEST_METHOD"] !== "POST")) {
            header('HTTP/1.1 501 Not accessible on browser');
            echo 'Not accessible on browser';
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');

        try {
            $data = file_get_contents("php://input");
            $response = json_decode($data, true);
            $response = WC_Xendit_Sanitized_Webhook::map_and_sanitized_oauth_webhook($response);
            $is_connected = false;

            if (empty($response['oauth_data']) || empty($response['public_key_dev']) || empty($response['public_key_prod'])) {
                throw new Exception("INVALID_OAUTH_RESPONSE", 1);
            }

            // Delete OAuth error cache
            delete_transient('xendit_oauth_error');

            if (!empty($response['oauth_data']['validate_key'])
                    && $response['oauth_data']['validate_key'] !== WC_Xendit_Oauth::getValidationKey()
            ) {
                throw new Exception("VALIDATE_KEY_MISMATCH", 1);
            }

            if (isset($response['error_code'])) {
                set_transient('xendit_oauth_error', $response["error_code"], 10);
            } else {
                $is_connected = true;

                // Update Oauth
                WC_Xendit_Oauth::updateXenditOAuth($response);

                // Update Public keys
                WC_Xendit_Invoice::instance()->update_public_keys(
                    $response['public_key_prod'],
                    $response['public_key_dev'],
                );
            }

            header('HTTP/1.1 200 Success');
            $res = array('is_connected' => $is_connected);
            die(json_encode($res, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            switch ($e->getMessage()) {
                case 'VALIDATE_KEY_MISMATCH':
                    $res = array(
                        'error_code' => 'VALIDATE_KEY_MISMATCH',
                        'message' => 'Validation key is mismatch'
                    );
                    header('HTTP/1.1 400 Validation Error');
                    break;

                case 'INVALID_OAUTH_RESPONSE':
                    $res = array(
                        'error_code' => 'INVALID_OAUTH_RESPONSE',
                        'message' => 'Invalid OAuth response'
                    );
                    header('HTTP/1.1 400 Validation Error');
                    break;

                default:
                    $res = array(
                        'error_code' => 'SERVER_ERROR',
                        'message' => 'Oops, something wrong happened! Please try again.'
                    );
                    header('HTTP/1.1 500 Server Error');
                    break;
            }

            die(json_encode($res, JSON_PRETTY_PRINT));
        }
    }

    /**
     * Keep the old callback during transitioning period
     */
    $callback_modes = array(
        'xendit_invoice_callback',
        'xendit_cc_callback'
    );
    if (!isset($_REQUEST['wc-api']) && isset($_REQUEST['xendit_mode']) && in_array($_REQUEST['xendit_mode'], $callback_modes)) {
        add_action('init', 'check_xendit_response');
    }

    // register jquery and style on initialization
    add_action('init', 'xendit_register_script');
    function xendit_register_script()
    {
        wp_register_style('xendit_pg_style', plugins_url('/assets/css/xendit-pg-style.css', __FILE__), false, '1.0.1', 'all');
    }

    // use the registered jquery and style above
    add_action('wp_enqueue_scripts', 'xendit_enqueue_style');
    function xendit_enqueue_style()
    {
        wp_enqueue_style('xendit_pg_style');
    }

    add_action('admin_enqueue_scripts', 'xendit_admin_scripts');
    function xendit_admin_scripts($hook)
    {
        if ('post.php' !== $hook) {
            return;
        }

        wp_register_script('sweetalert',  plugins_url().'/assets/js/frontend/sweetalert.min.js', null, null, true);
        wp_enqueue_script('sweetalert');
    }

    add_filter('woocommerce_available_payment_gateways', 'xendit_show_hide_cc_old_method');
    function xendit_show_hide_cc_old_method($gateways)
    {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');

        //latest PG version contain merged codes
        if (defined('WC_XENDIT_VERSION') && version_compare(WC_XENDIT_VERSION, '1.5.1', '>=') && is_plugin_active(plugin_basename(WC_XENDIT_MAIN_FILE))) {
            //check if both CC payment methods are enabled
            if (isset($gateways['xendit']) && isset($gateways['xendit_cc'])) {
                unset($gateways['xendit']);
            }
        }

        return $gateways;
    }

    /**
     * Migrate subscriptions with old payment method "xendit" to the new "xendit_cc" if:
     * - Subscription status is still active
     * - API key is not empty
     *
     * @return void
     */
    add_action('init', 'migrate_xendit_subscription');
    function migrate_xendit_subscription()
    {
        if (!is_admin()) {
            return;
        }

        if (!function_exists('get_option')) {
            return;
        }

        $should_not_migrate = get_transient('xendit_should_not_migrate_subscription');

        if ($should_not_migrate) {
            return;
        }

        $main_settings = get_option('woocommerce_xendit_gateway_settings');
        $development_mode = $main_settings['developmentmode'] ?? '';
        $secret_key = $development_mode == 'yes' && isset($main_settings['secret_key_dev'])
            ? $main_settings['secret_key_dev'] : (
                $main_settings['secret_key'] ?? ""
            );

        if (!$secret_key) {
            return;
        }

        $query_args = array(
            'post_type' => 'shop_subscription',
            'posts_per_page' => 100,
            'paged' => 1,
            'offset' => 0,
            'order' => 'DESC',
            'fields' => 'ids',
            'post_status' => 'wc-active',
            'meta_query' => array(
                array(
                    'key' => '_payment_method',
                    'value' => 'xendit',
                    'compare' => '=',
                )
            )
        );

        $subscription_post_ids = get_posts($query_args);

        if (empty($subscription_post_ids)) {
            set_transient('xendit_should_not_migrate_subscription', true, 86400); //expire in 24 hours
        }

        foreach ($subscription_post_ids as $post_id) {
            update_post_meta($post_id, '_payment_method', 'xendit_cc');
        }
    }

    add_action('woocommerce_review_order_before_submit', 'xendit_add_disclaimer_text', 9);
    function xendit_add_disclaimer_text()
    {
        $chosen_payment_method = WC()->session->get('chosen_payment_method');

        if (!empty($chosen_payment_method) && strpos($chosen_payment_method, 'xendit') !== false) {
            echo '<p>'.esc_html__('By using this payment method, you agree that all submitted data for your order will be processed by payment processor.', 'woo-xendit-virtual-accounts').'</p>';
        }
    }

    add_filter('woocommerce_cart_needs_payment', 'filter_cart_needs_payment_callback', 100, 2);
    function filter_cart_needs_payment_callback($needs_payment, $cart)
    {
        return $cart->total > 0 ? $needs_payment : false;
    }

    add_action('woocommerce_admin_order_totals_after_total', 'xendit_custom_coupon_display');
    function xendit_custom_coupon_display($order_id)
    {
        global $pagenow, $typenow;

        $order = wc_get_order($order_id);
        $coupons = $order->get_items('coupon');
        $has_xendit_card_promotion = has_xendit_card_promotion($coupons);

        include WC_XENDIT_PG_PLUGIN_PATH . '/libs/views/checkout/custom-coupon-display.php';
    }

    /**
     * @param $coupons
     * @return bool
     */
    function has_xendit_card_promotion($coupons)
    {
        // check wether only has card promotion as coupon
        if ($coupons) {
            $xendit_card_tag = 0;
            $coupon_tag = 0;
            foreach ($coupons as $coupon) {
                if (strpos($coupon->get_code(), "xendit_card_promotion_") !== false) {
                    $xendit_card_tag = $xendit_card_tag + 1;
                } else {
                    $coupon_tag = $coupon_tag + 1;
                }
            }

            if ($xendit_card_tag > 0 && $coupon_tag === 0) {
                ?>
                <script>
                    var couponLabelName = '<?php echo 'Card Promotion';?>';
                </script>
                <?php
                return true;
            } elseif ($xendit_card_tag > 0 && $coupon_tag > 0) {
                ?>
                <script>
                    var couponLabelName = '<?php echo 'Coupon and Card Promotion';?>';
                </script>
                <?php
                return true;
            }
        }

        return false;
    }

    add_filter('woocommerce_thankyou_order_received_text', 'xendit_woo_redirect_invoice', 10, 2);
    function xendit_woo_redirect_invoice($str, $order)
    {
        if (empty($_GET['order_id']) || !is_object($order)) {
            return $str;
        }

        if ('processing' === $order->get_status() || 'completed' === $order->get_status() || 'on-hold' === $order->get_status()) {
            return $str;
        }

        $order_id = wc_clean($_GET['order_id']);
        $order = wc_get_order($order_id);
        $invoice_url = $order->get_meta('Xendit_invoice_url');
        $delay = 3;

        include WC_XENDIT_PG_PLUGIN_PATH . '/libs/views/checkout/redirect-invoice.php';
        return $str;
    }

    add_action('admin_notices', 'show_admin_notice_warning_on_test_mode');
    function show_admin_notice_warning_on_test_mode()
    {
        $xendit_invoice = WC_Xendit_Invoice::instance();
        if ($xendit_invoice->developmentmode == 'yes' && $xendit_invoice->id == 'xendit_gateway') {
            print(wp_kses('
                <div class="notice notice-warning">
                    <p>'.__('Xendit payments in TEST mode. Disable "Test Environment" in settings to accept payments. Your Xendit account must also be activated. Learn more <a href=\"https://docs.xendit.co/getting-started/activate-account\" target=\"_blank\">here</a>', 'woo-xendit-virtual-accounts').'</p>
                </div>', 
                ['div'=>['class'=>true], 'p'=>true, 'a' => ['href' => true, 'target' => true]])
            );
        }
    }
}

/**
 * Registers WC_Xendit_Blocks as a payment method for WooCommerce blocks.
 *
 * This method checks if the `AbstractPaymentMethodType` class from WooCommerce Blocks exists.
 * If it does, it registers WC_Xendit_Blocks as a payment method by adding an action to the `woocommerce_blocks_payment_method_type_registration` hook.
 *
 * @return void
 */
add_action('woocommerce_blocks_loaded', 'woocommerce_xendit_gateway_woocommerce_block_support');
function woocommerce_xendit_gateway_woocommerce_block_support()
{
    if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                if (class_exists('WC_Xendit_Blocks')) {
                    $payment_method_registry->register(new WC_Xendit_Blocks());
                }
            }
        );
    }
}
