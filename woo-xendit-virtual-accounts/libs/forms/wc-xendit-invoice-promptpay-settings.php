<?php
if (! defined('ABSPATH')) {
    exit;
}

return apply_filters(
    'wc_xendit_promptpay_settings',
    array(
        'channel_name' => array(
            'title' => esc_html(__('Payment Channel Name', 'woocommerce-xendit')),
            'type' => 'text',
            'description' => wp_kses(__('Your payment channel name will be changed into <strong><span class="channel-name-format"></span></strong>', 'woocommerce-xendit'), ['strong'=>[], 'span'=>['class'=>true]]),
            'placeholder' => 'PromptPay',
        ),
        'payment_description' => array(
            'title' => esc_html(__('Payment Description', 'woocommerce-xendit')),
            'type' => 'textarea',
            'css' => 'width: 400px;',
            'description' => wp_kses(__('Change your payment description for <strong><span class="channel-name-format"></span></strong>', 'woocommerce-xendit'), ['strong'=>[], 'span'=>['class'=>true]]),
            'placeholder' => 'Pay at your nearest PromptPay via Xendit',
        )
    )
);
