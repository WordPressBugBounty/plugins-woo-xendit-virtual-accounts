<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles payment_session trigger from integration notification webhook.
 *
 * Pure logic — no header()/exit calls. Returns a result object that the
 * thin wrapper function in woocommerce-xendit-pg.php translates into an
 * HTTP response.
 *
 * @since 7.0.0
 */
final class WC_Xendit_Payment_Session_Notification_Handler
{
    /**
     * Process a payment session notification.
     *
     * @param WC_Order                          $order   The WooCommerce order.
     * @param IntegrationNotification           $payload Sanitized webhook payload.
     * @param WC_Xendit_Payment_Session_Gateway|null $gateway Optional gateway instance (defaults to singleton).
     * @return WC_Xendit_Notification_Result
     */
    public static function handle($order, IntegrationNotification $payload, $gateway = null): WC_Xendit_Notification_Result
    {
        // Skip if order is already paid (idempotent for payment notifications)
        if ($order->is_paid()) {
            WC_Xendit_PG_Logger::log('[Integration notification process] Order #' . $payload->woocommerce_order_id . ' is already paid. Skipping.');
            return new WC_Xendit_Notification_Result(200, 'Order already processed');
        }

        $status             = strtoupper($payload->session->status);
        $payment_session_id = $payload->session->payment_session_id;
        $payment_id         = $payload->session->payment_id;
        $payment_request_id = $payload->session->payment_request_id;

        if ($gateway === null) {
            $gateway = WC_Xendit_Payment_Session_Gateway::instance();
        }
        $success_status = $gateway->success_payment_xendit;

        $notes = $gateway->build_order_notes(
            $payment_session_id,
            $status,
            $order->get_currency(),
            $order->get_total()
        );

        if ($status === 'COMPLETED') {
            WC_Xendit_PG_Helper::complete_payment($order, $notes, $success_status, $payment_session_id);

            $order->update_meta_data('payment_request_id', $payment_request_id);
            $order->update_meta_data('payment_id', $payment_id);
            $order->save();
        } elseif ($status === 'CANCELED' || $status === 'EXPIRED') {
            WC_Xendit_PG_Helper::cancel_order($order, $notes);
        } else {
            WC_Xendit_PG_Logger::log('[Integration notification process] Payment session unknown status: ' . $status . ' for order #' . $order->get_id());
            return new WC_Xendit_Notification_Result(400, 'Status Unknown Error');
        }

        return new WC_Xendit_Notification_Result(200, 'Integration notification processed successfully for payment session');
    }
}
