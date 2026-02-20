<?php
// Targeting admin shop order page with only xendit card promotion coupon
if (is_admin() && in_array($pagenow, ['post.php', 'post-new.php']) && 'shop_order' === $typenow && $has_xendit_card_promotion === true) {
    // Get the actual total price for each item before discounted
    $items = $order->get_items();
    $subtotals = array();
    foreach ($items as $item) {
        array_push($subtotals, wc_price($item->get_subtotal()));
    }

    ?>
    <script>
        var subtotals = <?php echo json_encode($subtotals); ?>;
        var tableOrderItem = document.getElementsByClassName('woocommerce_order_items');
        var tableOrderItemRow = tableOrderItem[0].rows;

        for (var i = 1; i < tableOrderItemRow.length; i++) {
            // Replace discounted total price with price before discounted
            var lineCost = tableOrderItemRow[i].getElementsByClassName('line_cost')[0];
            var subTotalItemSection = lineCost.getElementsByClassName('woocommerce-Price-amount amount')[0];
            var subTotalItem = subTotalItemSection.getElementsByTagName('bdi')[0];
            subTotalItem.innerHTML = subtotals[i - 1];

            // Remove discount information
            var discount = lineCost.getElementsByClassName('wc-order-item-discount')[0];
            discount.innerHTML = '';

            // Change coupon label
            var totalItemSection = document.getElementsByClassName('wc-order-data-row wc-order-totals-items wc-order-items-editable')[0];
            var couponList = totalItemSection.getElementsByClassName('wc_coupon_list')[0];
            var coupons = couponList.getElementsByTagName('li');
            totalItemSection.innerHTML = totalItemSection.innerHTML.replaceAll(coupons[0].textContent, couponLabelName);
        }
    </script>
    <?php
}
