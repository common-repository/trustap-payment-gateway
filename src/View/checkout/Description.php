<?php

defined('ABSPATH') || exit;

?>

<div><?php echo esc_html($description) ?></div>
<p class="validate-required woocommerce-invalid woocommerce-invalid-required-field"
   id="tos_checkbox_field">
    <span class="woocommerce-input-wrapper">
        <label class="checkbox">
            <input type="checkbox"
                   class="input-checkbox"
                   name="tos_checkbox"
                   id="tos_checkbox"
                   value="1">
            <?php
            echo wp_kses_post(
                __('I agree with Trustap\'s <a href="https://www.trustap.com/terms" target="_blank">
                    Terms of Use</a>',
                'trustap-payment-gateway'
            ));
            ?>
            <abbr class="required" title="required">*</abbr>
        </label>
    </span>
</p>
