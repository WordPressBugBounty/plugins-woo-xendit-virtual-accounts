<?php
$payment_gateway = wc_get_payment_gateway_by_order($order_id);
if ($payment_gateway->id != $this->id) {
    return;
}

$return = '<div style="text-align:left;"><strong>' . $this->checkout_msg . '</strong><br /><br /></div>';

$allowed_html = array(
    'div' => array(
        'style' => true
    ),
    'strong' => true,
    'br' => true
);

if ($this->developmentmode == 'yes') {
    $testDescription = sprintf(wp_kses(__('<strong>TEST MODE.</strong> The bank account numbers shown below are for testing only. Real payments will not be detected', 'woo-xendit-virtual-accounts'), ['strong' => []]));
    $return .= '<div style="text-align:left;">' . $testDescription . '</div>';
}

echo wp_kses($return, $allowed_html);
