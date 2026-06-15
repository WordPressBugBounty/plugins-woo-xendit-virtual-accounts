<?php
/**
 * Toggle switch field view.
 *
 * Variables available:
 * @var string $field_key  The HTML input name/id attribute.
 * @var string $checked    'checked' or ''.
 * @var string $title      Field label text.
 * @var string $desc       Optional description text.
 */
defined('ABSPATH') || exit;
?>
<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($title); ?></label>
    </th>
    <td class="forminp">
        <label class="xendit-toggle-switch">
            <input type="hidden" name="<?php echo esc_attr($field_key); ?>" value="no" />
            <input type="checkbox"
                   id="<?php echo esc_attr($field_key); ?>"
                   name="<?php echo esc_attr($field_key); ?>"
                   value="yes"
                   <?php echo esc_attr($checked); ?> />
            <span class="xendit-toggle-slider"></span>
        </label>
        <?php if ($desc) : ?>
            <p class="description"><?php echo wp_kses_post($desc); ?></p>
        <?php endif; ?>
    </td>
</tr>
<style>
    .xendit-toggle-switch {
        position: relative;
        display: inline-block;
        width: 48px;
        height: 26px;
    }
    .xendit-toggle-switch input[type="hidden"] { display: none; }
    .xendit-toggle-switch input[type="checkbox"] {
        opacity: 0;
        width: 0;
        height: 0;
        position: absolute;
    }
    .xendit-toggle-slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background-color: #ccc;
        border-radius: 26px;
        transition: background-color 0.2s;
    }
    .xendit-toggle-slider::before {
        content: "";
        position: absolute;
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: #fff;
        border-radius: 50%;
        transition: transform 0.2s;
    }
    .xendit-toggle-switch input[type="checkbox"]:checked + .xendit-toggle-slider {
        background-color: #2196F3;
    }
    .xendit-toggle-switch input[type="checkbox"]:checked + .xendit-toggle-slider::before {
        transform: translateX(22px);
    }
</style>
