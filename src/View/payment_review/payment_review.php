<!-- payment-review-template.php -->
<?php
get_header();
wp_enqueue_style('woocommerce-general');
wp_enqueue_style('woocommerce-layout');
wp_enqueue_style('woocommerce-smallscreen');
wp_enqueue_style('woocommerce-general');
wp_enqueue_style('woocommerce-layout');
wp_enqueue_style('woocommerce-smallscreen');
wp_enqueue_script('wc-cart-fragments');
?>

<html>
<head>
    <title>Payment Review Page</title>
    <?php wp_head(); ?>
    <style>


        body {
            text-align: left;
            margin: 40px;
            padding: 20px;
        }
        h1 {
            margin-bottom: 40px;
        }

        h2 {
            margin-bottom: 20px;
        }

        p {
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
<?php

if (isset($order_id)) {
    ?>
    <h2>Your order has been received</h2>
    <p>Your payment for order id: <?php echo esc_html($order_id); ?> is currently under review. You will be notified once the review is complete, please allow up to 48 hours.</p>
    <?php
}
?>

</body>
</html>

<?php
get_footer();
wp_footer();
exit();
?>
