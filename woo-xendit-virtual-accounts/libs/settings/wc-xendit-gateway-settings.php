<?php
if (! defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_xendit_gateway_settings',
    array(
        'general_options' => array(
            'title' => esc_html__('Xendit Payment Gateway Options', 'woo-xendit-virtual-accounts'),
            'type' => 'title',
        ),

        'enabled' => array(
            'title' => esc_html__('Enable', 'woo-xendit-virtual-accounts'),
            'type' => 'checkbox',
            'label' => esc_html__('Enable Xendit Gateway', 'woo-xendit-virtual-accounts'),
            'default' => 'no',
        ),

        'developmentmode' => array(
            'title' => esc_html__('Test Environment', 'woo-xendit-virtual-accounts'),
            'type' => 'checkbox',
            'label' => esc_html__('Enable Test Environment - Please uncheck for processing real transaction', 'woo-xendit-virtual-accounts'),
            'default' => 'no',
        ),

        'dummy_api_key' => array(
            'class' => 'xendit_live',
            'title' => esc_html__('Xendit Public API Key', 'woo-xendit-virtual-accounts') . '<br/>[' . esc_html__('Live Mode', 'woo-xendit-virtual-accounts'). ']',
            'type' => 'password',
            'default' => esc_html('****'),
        ),

        'dummy_secret_key' => array(
            'class' => 'xendit_live',
            'title' => esc_html__('Xendit Secret API Key', 'woo-xendit-virtual-accounts') . '<br/>[' . esc_html__('Live Mode', 'woo-xendit-virtual-accounts') . ']',
            'type' => 'password',
            'default' => esc_html('****'),
        ),

        'dummy_api_key_dev' => array(
            'class' => 'xendit_dev',
            'title' => esc_html__('Xendit Public API Key', 'woo-xendit-virtual-accounts') . '<br/>[' . esc_html__('Test Mode', 'woo-xendit-virtual-accounts') . ']',
            'type' => 'password',
            'default' => esc_html('****'),
        ),

        'dummy_secret_key_dev' => array(
            'class' => 'xendit_dev',
            'title' => esc_html__('Xendit Secret API Key', 'woo-xendit-virtual-accounts') . '<br/>[' . esc_html__('Test Mode', 'woo-xendit-virtual-accounts') . ']',
            'type' => 'password',
            'default' => esc_html('****'),
        ),

        'api_keys_help_text' => array(
            'type' => 'title',
            'title' => '',
            'description' => wp_kses(
                __('Find your API keys <a href="https://dashboard.xendit.co/settings/developers#api-keys" target="_blank">here</a> (switch between Test and Live modes using the options on the top left of your Xendit dashboard)', 'woo-xendit-virtual-accounts'),
                ['a' => ['href' => true, 'target' => true], 'br' => [], 'b' => []]
            ),
        ),

        'external_id_format' => array(
            'title' => esc_html__('External ID Format', 'woo-xendit-virtual-accounts'),
            'class' => 'xendit-ext-id',
            'type' => 'text',
            'description' => wp_kses(__('External ID of the payment that will be created on Xendit, for example <b><span id="ext-id-example"></span></b>.<br/> Must be between 6 to 54 characters', 'woo-xendit-virtual-accounts'), ['b' => [], 'br' => [], 'span' => ['id' => true]]),
            'default' => 'woocommerce-xendit',
        ),

        'send_site_data_button' => array(
            'title' => esc_html__('Site Data Collection', 'woo-xendit-virtual-accounts'),
            'type' => 'button',
            'description' => esc_html__('Allow Xendit to retrieve this store\'s plugin and environment information for debugging purposes. E.g. WordPress version, WooCommerce version', 'woo-xendit-virtual-accounts'),
            'class' => 'button-primary',
            'default' => esc_html__('Send site data to Xendit', 'woo-xendit-virtual-accounts')
        ),

        'channel_options' => array(
            'title' => esc_html__('Checkout Display Options', 'woo-xendit-virtual-accounts'),
            'type' => 'title',
        ),

        'channel_name' => array(
            'title' => esc_html__('Payment Channel Name', 'woo-xendit-virtual-accounts'),
            'type' => 'text',
            // translators: %1s: Xendit title.
            'description' => sprintf(wp_kses(__('Your payment channel name will be changed into <strong><span class="channel-name-format">%1s</span></strong>', 'woo-xendit-virtual-accounts'), ['strong'=>[], 'span'=>['class'=>true]]), $this->get_xendit_title()),
            'placeholder' => esc_html($this->default_title)
        ),

        'payment_description' => array(
            'title' => esc_html__('Payment Description', 'woo-xendit-virtual-accounts'),
            'type' => 'textarea',
            'css' => 'width: 400px;',
            // translators: %1s: Xendit title.
            'description' => sprintf(wp_kses(__('Change your payment description for <strong><span class="channel-name-format">%1s</span></strong>', 'woo-xendit-virtual-accounts'), ['strong'=>[], 'span'=>['class'=>true]]), $this->get_xendit_title()),
            'placeholder' => esc_html__('Pay your order via Xendit Payment Gateway', 'woo-xendit-virtual-accounts')
        ),

        'woocommerce_options' => array(
            'title' => esc_html__('WooCommerce Order & Checkout Options', 'woo-xendit-virtual-accounts'),
            'type' => 'title',
        ),

        'success_payment_xendit' => array(
            'title' => esc_html__('Successful Payment Status', 'woo-xendit-virtual-accounts'),
            'type' => 'select',
            'description' => esc_html__('The status that WooCommerce should show when a payment is successful', 'woo-xendit-virtual-accounts'),
            'default' => 'processing',
            'class' => 'form-control',
            'options' => array(
                    'default' => esc_html('Default'),
                    'pending' => esc_html('Pending payment'),
                    'processing' => esc_html('Processing'),
                    'completed' => esc_html('Completed'),
                    'on-hold' => esc_html('On Hold'),
            ),
        ),

        'redirect_after' => array(
            'title' => esc_html__('Display Invoice Page After', 'woo-xendit-virtual-accounts'),
            'type' => 'select',
            'description' => esc_html__('Choose "Order received page" to get better tracking of your order conversion if you are using an analytic platform', 'woo-xendit-virtual-accounts'),
            'default' => 'CHECKOUT_PAGE',
            'class' => 'form-control',
            'options' => array(
                    'CHECKOUT_PAGE' => esc_html__('Checkout page', 'woo-xendit-virtual-accounts'),
                    'ORDER_RECEIVED_PAGE' => esc_html__('Order received page', 'woo-xendit-virtual-accounts'),
            ),
        ),

        'xenplatform_options' => array(
            'title' => esc_html__('XenPlatform Options', 'woo-xendit-virtual-accounts'),
            'type' => 'title',
        ),

        'enable_xenplatform' => array(
            'title' => esc_html__('XenPlatform User', 'woo-xendit-virtual-accounts'),
            'type' => 'checkbox',
            'label' => esc_html__('Enable your XenPlatform Sub Account in WooCommerce', 'woo-xendit-virtual-accounts'),
            'default' => ''
        ),

        'on_behalf_of' => array(
            'title' => esc_html__('On Behalf Of', 'woo-xendit-virtual-accounts'),
            'class' => 'form-control xendit-xenplatform',
            'type' => 'text',
            'description' => esc_html__('Your Xendit Sub Account Business ID. All transactions will be linked to this account', 'woo-xendit-virtual-accounts'),
            'default' => '',
            'placeholder' => 'e.g. 5f57be181c4ff635452d817d'
        ),
        'invoice_expire_duration' => array(
            'title' => esc_html__('Invoice Expire Time', 'woo-xendit-virtual-accounts'),
            'class' => 'form-control xendit-xenplatform',
            'type' => 'text',
            'default' => '1',
            'placeholder' => 'e.g. 30'
        ),
        'invoice_expire_unit' => array(
            'title' => '',
            'type' => 'select',
            'default' => 'DAYS',
            'class' => 'form-control xendit-xenplatform',
            'options' => array(
                'MINUTES' => esc_html('Minutes'),
                'HOURS' => esc_html('Hours'),
                'DAYS' => esc_html('Days'),
            ),
        ),
    )
);