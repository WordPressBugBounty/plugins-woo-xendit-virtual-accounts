<?php
if ($this->description) {
    $test_description = '';
    if ($this->developmentmode == 'yes') {
        $test_description = wp_kses(__('<strong>TEST MODE</strong> - Real payment will not be detected', 'woo-xendit-virtual-accounts'), ['strong' => []]);
    }

    echo wp_kses('<p>' . esc_html($this->description) . '</p>
        <p style="color: red; font-size:80%; margin-top:10px;">' . esc_html($test_description) . '</p>', ['p' => ['style' => true]]);
}
