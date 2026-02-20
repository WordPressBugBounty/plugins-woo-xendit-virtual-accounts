<?php

echo wp_kses("<h2>Xendit</h2><p style='margin-bottom: 10px;'>".
            __('Accept payments with Xendit. See our 
                <a href="https://docs.xendit.co/integrations/woocommerce/steps-to-integrate" target="_blank">documentation</a> for the full guide', 
                'woo-xendit-virtual-accounts')."</p><br />", 
                ['h2' => true, 'p' => true, 'a' => ['href' => true, 'target' => true]]
            );

if (!$this->is_connected) {
    $top = '<div class="oauth-container">';
    $top .= "<button class='components-button is-primary' id='woocommerce_xendit_connect_button'>" . esc_html(__('Connect to Xendit', 'woo-xendit-virtual-accounts')) . "</button>";
    $top .= '
    <ul>
            <li>'. esc_html('1. Click "Connect to Xendit"') .'</li>
            <li>'. esc_html('2. Log in to your Xendit dashboard (If you haven\'t)') .'</li>
            <li>'. esc_html('3. Click "Allow"') .'</li>
            <li>'. esc_html('4. Done') .'</li>
    </ul>';
    $top .= '<em>If you\'re having trouble with the "Connect" button, click <a href="#" id="woocommerce_xendit_connect_api_key_button">here</a> to connect manually using your API keys.</em>';
    $top .= '</div>';

    $top_allowed_html = [
        'div' => [
            'class' => true,
            'style' => true
        ],
        'button' => [
            'class' => true,
            'id' => true
        ],
        'ul' => true,
        'li' => true,
        'em' => true,
        'a' => [
            'href' => true,
            'id' => true
        ]
    ];            

    $content = '<div class="api-keys-container" style="display: none;"><table class="form-table">';
    $content .= '<a href="#" id="woocommerce_xendit_connect_oauth_button"><< '. esc_html('Back') .'</a>';
    $content .= $this->generate_api_key_settings_html();
    $content .= '</table>';

    $content .= '</div>';

    $content .= '
            <style>
                    .submit{display:none;}
            </style>
                    <script>
                    jQuery(document).ready(function($) {
                        $("#woocommerce_xendit_connect_api_key_button").on("click", function() {
                            $(".oauth-container").hide();
                            $(".api-keys-container").show();
                            $(".submit").show();
                        });
                        $("#woocommerce_xendit_connect_oauth_button").on("click", function() {
                            $(".oauth-container").show();
                            $(".api-keys-container").hide();
                            $(".submit").hide();
                        });
                    });
            </script>
        ';

    $connect_allowed_html =[
        'div' => [
            'class' => true,
            'style' => true
        ],
        'a' => [
            'href' => true,
            'id' => true
        ],
        'table' => [
            'class' => true
        ],
        'style' => true,
        'script' => true,
        'tr' => [
            'valign' => true
        ],
        'th' => [
            'scope' => true,
            'class' => true
        ],
        'td' => [
            'class' => true
        ],
        'fieldset' => true,
        'legend' => [
            'class' => true
        ],
        'span' => true,
        'input' => [
            'class' => true,
            'type' => true,
            'name' => true,
            'id' => true,
            'value' => true,
            'checked' => true,
            'disabled' => true,
            'style' => true,
            'placeholder' => true
        ]

    ];

    $full_content = $top.$content;
    $allowed_html = array_merge($top_allowed_html, $connect_allowed_html);
    echo wp_kses($full_content, $allowed_html);
} else {
    $content = "<button class='components-button is-secondary' disabled>" . esc_html__('Connected', 'woo-xendit-virtual-accounts') . "</button>";
    $content .= sprintf("<button class='components-button is-secondary' id='woocommerce_%1s_disconnect_button'>" . esc_html__('Disconnect', 'woo-xendit-virtual-accounts') . "</button>", $this->id);

    $disconnect_allowed_html =[
        'button' => [
            'class' => true,
            'disabled' => true,
            'id' => true
        ]
    ];

    echo wp_kses($content,  $disconnect_allowed_html);
}

?>