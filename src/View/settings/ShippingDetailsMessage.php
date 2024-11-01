<?php

defined('ABSPATH') || exit;

?>

<div class="trustap-shipping-details-container">
    <img src="<?php echo wp_kses_post($icon) ?>"
         alt="<?php echo esc_attr($img_alt) ?>"
         class="trustap-status-icon">
    <p>
        <?php echo esc_html($message) ?>
    </p>
</div>
