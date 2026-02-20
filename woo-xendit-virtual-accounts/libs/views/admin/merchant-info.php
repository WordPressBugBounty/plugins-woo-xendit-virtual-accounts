<?php
if (empty($this->is_connected)) {
    return;
}
?>
<h3 class="wc-settings-sub-title"><?php echo esc_html('Xendit Merchant Info'); ?></h3>
<table class="form-table">
    <tbody>
        <tr>
            <th class="titledesc"><?php echo esc_html('Merchant'); ?></th>
            <td>
                <table>
                    <tr>
                        <th class="titledesc" style="padding: 0; width: 85px;"><?php echo esc_html('Business ID'); ?></th>
                        <td style="padding: 0;"><?php echo esc_html($this->merchant_info['business_id'] ?? ''); ?></td>
                    </tr>
                    <tr>
                        <th class="titledesc" style="padding: 0; width: 85px;"><?php echo esc_html('Name'); ?></th>
                        <td style="padding: 0;"><?php echo esc_html($this->merchant_info['name'] ?? ''); ?></td>
                    </tr>
                </table>
            </td>
        </tr>
    </tbody>
</table>
