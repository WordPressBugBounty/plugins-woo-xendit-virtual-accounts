<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xendit Gateway Trait
 *
 * Shared behavior for all Xendit payment gateways: merchant connection,
 * cache invalidation, and refund delegation.
 *
 * Expects the using class to have:
 *   - $this->xenditClass       (WC_Xendit_PG_API instance)
 *   - $this->use_transaction_id_for_refund_order_id (bool)  — whether refunds need a transaction ID
 *   - $this->notification_url (string)
 *
 * @since 7.0.0
 */
trait Xendit_Gateway_Trait
{
    /**
     * Process a refund via the shared WC_Xendit_Refund helper.
     *
     * Gateways can set $this->use_transaction_id_for_refund_order_id to control whether
     * a transaction ID is required. Defaults to true.
     *
     * @param int    $order_id
     * @param float  $amount
     * @param string $reason
     * @return true|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        try {
            $use_transaction_id_for_refund_order_id = isset($this->use_transaction_id_for_refund_order_id) ? $this->use_transaction_id_for_refund_order_id : false;

            if (is_null($amount) || floatval($amount) <= 0) {
                return new WP_Error('invalid_amount', __('Refund amount must be greater than zero.', 'woo-xendit-virtual-accounts'));
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                return new WP_Error('order_not_found', __('Order not found.', 'woo-xendit-virtual-accounts'));
            }

            $currency = $order->get_currency();
            // this is based on class-wc-xendit-invoice.php and class-wc-xendit-cc.php in validate_payment function
            // where we set the charge id inside as a transaction id in $order object
            // example: https://github.com/xendit/woocommerce-wp-plugin-va/blob/master/libs/class-wc-xendit-invoice.php#L622
            $transaction_id = $order->get_transaction_id();

            if ($use_transaction_id_for_refund_order_id && empty($transaction_id)) {
                return new WP_Error('missing_transaction_id', __('This order has no associated payment ID for refunding.', 'woo-xendit-virtual-accounts'));
            }

            $store_url = !empty($this->notification_url) ? $this->notification_url : home_url();
            $refund_idempotency_id = wp_generate_uuid4();

            $body = array(
                'woocommerce_order_id'  => $order->get_id(),
                'amount'                => strval($amount),
                'currency'              => $currency,
                'reason'                => $reason,
                'store_url'             => $store_url,
                'refund_idempotency_id' => $refund_idempotency_id,
                'reference_id'          => WC_Xendit_PG_Helper::generate_external_id($order, $this->external_id_format)
            );

            if ($use_transaction_id_for_refund_order_id && !empty($transaction_id)) {
                $body['credit_card_charge_id'] = $transaction_id;
            }

            // Persist idempotency ID to order meta BEFORE calling TPI.
            // The webhook can arrive while TPI is still polling, so the meta
            // must already be in the database when the notification handler runs.
            $existing_xendit_refund_idempotency_ids = $order->get_meta('xendit_refund_idempotency_ids');
            $xendit_refund_idempotency_ids = !empty($existing_xendit_refund_idempotency_ids)
                ? sprintf('%s, %s', $existing_xendit_refund_idempotency_ids, $refund_idempotency_id)
                : $refund_idempotency_id;
            $order->update_meta_data('xendit_refund_idempotency_ids', $xendit_refund_idempotency_ids);
            $order->save();

            $response = $this->xenditClass->createWooCommerceRefund($body);

            if (!empty($response['error_code'])) {
                $error_code = isset($response['error_code']) ? $response['error_code'] : 'unknown_error_occurred';
                $message = isset($response['message']) ? $response['message'] : 'Unknown error occurred.';
                WC_Xendit_PG_Logger::log('[Refund] TPI error: error_code='.$error_code.', message='.$message);

                return new WP_Error($error_code, $message);
            }

            $status = isset($response['status']) ? $response['status'] : '';
            $refund_id = isset($response['refund_id']) ? $response['refund_id'] : '';

            if (empty($status) || empty($refund_id)) {
                WC_Xendit_PG_Logger::log('[Refund] TPI response missing required fields: status=' . ($status ?? '') . ', refund_id=' . ($refund_id ?? ''));
                return new WP_Error('invalid_refund_response', __('Refund response is missing from response.', 'woo-xendit-virtual-accounts'));
            }

            // Store refund_id in order meta
            $existing_xendit_refund_ids = $order->get_meta('xendit_refund_ids');
            $xendit_refund_ids = !empty($existing_xendit_refund_ids)
                ? sprintf('%s, %s', $existing_xendit_refund_ids, $refund_id)
                : $refund_id;
            $order->update_meta_data('xendit_refund_ids', $xendit_refund_ids);

            if ($status === 'PENDING') {
                $pending_message = __('Refund is still processing. Please check from Xendit dashboard for the final status.', 'woo-xendit-virtual-accounts');
                $order->add_order_note($pending_message);
                $order->save();

                WC_Xendit_PG_Logger::log('[Refund] ' . $pending_message);
                return new WP_Error('refund_pending_progress', $pending_message);
            }

            if ($status === 'FAILED') {
                $order->save();

                $message = sprintf(
                    /* translators: %1$s: Refund Status, %2%s: Refund Id. */
                    __('Refund status is %1$1s for id of %2$2s. Please try again in a while.', 'woo-xendit-virtual-accounts'),
                    $status,
                    $refund_id
                );
                WC_Xendit_PG_Logger::log($message);
                return new WP_Error('refund_failed_status', $message);
            }

            // SUCCEEDED
            $order->save();

            return true;
        } catch (Exception $e) {
            WC_Xendit_PG_Logger::log('[Refund] Error: '.$e->getMessage());
            return new WP_Error('refund_error', $e->getMessage());
        }
    }
}
