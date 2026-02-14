<?php
if (! defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_xendit_cc_settings',
    array(
        'channel_name' => array(
            'title' => esc_html__('Payment Channel Name', 'woo-xendit-virtual-accounts'),
            'type' => 'text',
            // translators: %1s: Xendit title.
            'description' => sprintf(wp_kses(__('Your payment channel name will be changed into <strong><span class="channel-name-format">%1s</span></strong>', 'woo-xendit-virtual-accounts'), ['strong'=> true, 'span'=>['class'=>true]]), $this->get_xendit_title()),
            'placeholder' => 'Credit Card (Xendit)'
        ),
        'payment_description' => array(
            'title'       => esc_html__('Payment Description', 'woo-xendit-virtual-accounts'),
            'type'        => 'textarea',
            'css'         => 'width: 400px;',
            // translators: %1s: Xendit title.
            'description' => sprintf(wp_kses(__('Change your payment description for <strong><span class="channel-name-format">%1s</span></strong>', 'woo-xendit-virtual-accounts'), ['strong'=> true, 'span'=>['class'=>true]]), $this->get_xendit_title()),
            'placeholder' => esc_html__('Pay with your credit card via Xendit', 'woo-xendit-virtual-accounts'),
        ),
        'statement_descriptor' => array(
            'title'       => esc_html('Statement Descriptor'),
            'type'        => 'text',
            'description' => esc_html__('Extra information about a charge. This will appear on your customer’s credit card statement', 'woo-xendit-virtual-accounts'),
            'default'     => '',
            'desc_tip'    => true,
        ),
        'credit_card_icons' => array(
            'title' => esc_html('Credit card icons'),
            'type' => 'multiselect',
            'description' => esc_html('The credit card icon show on checkout page'),
            'default' => '',
            'class' => 'form-control wc-enhanced-select',
            'options' => array(
                'visa' => esc_html('Visa'),
                'mastercard' => esc_html('Mastercard'),
                'amex' => esc_html('AMEX'),
                'jcb' => esc_html('JCB'),
            ),
        ),
    )
);
