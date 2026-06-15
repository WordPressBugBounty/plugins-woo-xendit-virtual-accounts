<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xendit Webhook Signature Verifier
 * 
 * @since 6.1.0
 */
class WC_Xendit_Signature_Verifier
{
    public static function verify_signature($callback_id, $invoice_id, $status, $signature, $version = 1)
    {
        try {
            if ($version !== 1) throw new Exception("Unidentified signature version from server");
            
            if (empty($callback_id) || empty($invoice_id) || empty($status) || empty($signature)) {
                WC_Xendit_PG_Logger::log('Signature verification failed: Missing required fields');
                return false;
            }

            $message = sprintf('%s.%s.%s', $callback_id, $invoice_id, $status);

            $signature_binary = base64_decode($signature, true);
            if ($signature_binary === false) {
                WC_Xendit_PG_Logger::log('Signature verification failed: Invalid base64 signature');
                return false;
            }

            $public_keys = self::load_public_keys();
            if (empty($public_keys)) {
                WC_Xendit_PG_Logger::log('Signature verification failed: No public keys available');
                return false;
            }

            foreach ($public_keys as $index => $public_key_pem) {
                if (self::verify_ecdsa($message, $signature_binary, $public_key_pem)) {
                    WC_Xendit_PG_Logger::log(sprintf('Signature verification success for id %s with %s status', $invoice_id, $status));
                    return true;
                }
            }

            WC_Xendit_PG_Logger::log(sprintf('Signature verification failed for id %s with %s status', $invoice_id, $status));
            return false;
        } catch (Exception $e) {
            WC_Xendit_PG_Logger::log('Signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify signature for IntegrationNotification callback payloads.
     *
     * Constructs the message from the integration notification payload using 10 dot-joined parts:
     * {_id}.{status}.{session.payment_session_id}.{session.status}.{payment_token.payment_token_id}.
     * {payment_token.status}.{payment_request.payment_request_id}.{payment_request.status}.
     * {refund.refund_id}.{refund.status}?woocommerce_order_id={woocommerce_order_id}
     *
     * @param IntegrationNotification  $integNotif  The full IntegrationNotificationPayload
     * @param string $signature   The base64-encoded ECDSA signature
     * @return bool True if signature is valid, false otherwise
     */
    public static function verify_integration_notification_signature(IntegrationNotification $integNotif, string $signature): bool
    {
        try {
            if (empty($signature)) {
                WC_Xendit_PG_Logger::log('Session signature verification failed: Missing signature');
                return false;
            }

            $parts = [
                $integNotif->_id,
                $integNotif->status,
                $integNotif->session->payment_session_id ?? '',
                $integNotif->session->status ?? '',
                $integNotif->payment_token->payment_token_id ?? '',
                $integNotif->payment_token->status ?? '',
                $integNotif->payment_request->payment_request_id ?? '',
                $integNotif->payment_request->status ?? '',
                $integNotif->refund->refund_id ?? '',
                $integNotif->refund->status ?? '',
            ];
            $message = implode('.', $parts)."?woocommerce_order_id=".$integNotif->woocommerce_order_id;

            $signature_binary = base64_decode($signature, true);
            if ($signature_binary === false) {
                WC_Xendit_PG_Logger::log('Session signature verification failed: Invalid base64 signature');
                return false;
            }

            $public_keys = self::load_public_keys();
            if (empty($public_keys)) {
                WC_Xendit_PG_Logger::log('Session signature verification failed: No public keys available');
                return false;
            }

            foreach ($public_keys as $public_key_pem) {
                if (self::verify_ecdsa($message, $signature_binary, $public_key_pem)) {
                    WC_Xendit_PG_Logger::log(sprintf('Session signature verification success for id %s', $integNotif->_id ?? 'unknown'));
                    return true;
                }
            }

            WC_Xendit_PG_Logger::log(sprintf('Session signature verification failed for id %s', $integNotif->_id ?? 'unknown'));
            return false;
        } catch (Exception $e) {
            WC_Xendit_PG_Logger::log('Session signature verification error: ' . $e->getMessage());
            return false;
        }
    }

    private static function load_public_keys()
    {
        if (XENDIT_ENV === 'production' && !defined('INTEGRATION_NOTIFICATION_SIGNATURE_PUBLIC_KEYS')) {
            WC_Xendit_PG_Logger::log('Public key constant not defined ');
            return [];
        }

        // write to log but don't return anything if it is failed
        if (XENDIT_ENV === 'staging' && !defined('INTEGRATION_NOTIFICATION_SIGNATURE_PUBLIC_KEYS_STAGING')) {
            WC_Xendit_PG_Logger::log('Public key constant not defined for staging');
        }

        $keys = XENDIT_ENV === 'production' ? INTEGRATION_NOTIFICATION_SIGNATURE_PUBLIC_KEYS : INTEGRATION_NOTIFICATION_SIGNATURE_PUBLIC_KEYS_STAGING;
        
        if (!is_array($keys)) {
            WC_Xendit_PG_Logger::log('Public keys must be an array');
            return [];
        }

        return $keys;
    }
    private static function verify_ecdsa($message, $signature, $public_key_pem)
    {
        try {
            $public_key = openssl_pkey_get_public($public_key_pem);
            if ($public_key === false) {
                $error = openssl_error_string();
                WC_Xendit_PG_Logger::log('Failed to load public key: ' . $error);
                while (openssl_error_string() !== false);
                return false;
            }

            $signature_der = self::raw_to_der_signature($signature);

            $result = openssl_verify($message, $signature_der, $public_key, OPENSSL_ALGO_SHA384);
            
            // Free the key resource (only needed for PHP < 8.0)
            if (PHP_VERSION_ID < 80000) {
                openssl_free_key($public_key);
            }

            if ($result === 1) {
                return true;
            } elseif ($result === 0) {
                return false;
            } else {
                WC_Xendit_PG_Logger::log('OpenSSL verify error: ' . openssl_error_string());
                return false;
            }

        } catch (Exception $e) {
            WC_Xendit_PG_Logger::log('ECDSA verification error: ' . $e->getMessage());
            return false;
        }
    }
    private static function raw_to_der_signature($signature)
    {
        // P-384 raw signature is always exactly 96 bytes (48-byte R + 48-byte S).
        // DER-encoded P-384 signatures are typically ~102-104 bytes.
        // A raw signature's first byte may coincidentally be 0x30 (the DER SEQUENCE tag),
        // so we must check length first to avoid false DER detection.
        if (strlen($signature) === 96) {
            $r = substr($signature, 0, 48);
            $s = substr($signature, 48, 48);

            $r = ltrim($r, "\0");
            if (empty($r)) $r = "\0";
            $s = ltrim($s, "\0");
            if (empty($s)) $s = "\0";

            if (ord($r[0]) & 0x80) {
                $r = "\0" . $r;
            }
            if (ord($s[0]) & 0x80) {
                $s = "\0" . $s;
            }

            $der_r = "\x02" . chr(strlen($r)) . $r;
            $der_s = "\x02" . chr(strlen($s)) . $s;
            $der = "\x30" . chr(strlen($der_r . $der_s)) . $der_r . $der_s;

            return $der;
        }

        // Not 96 bytes — check if it looks like valid DER
        if (strlen($signature) > 0 && ord($signature[0]) === 0x30) {
            WC_Xendit_PG_Logger::log('Signature already in DER format');
            return $signature;
        }

        WC_Xendit_PG_Logger::log('Invalid signature length: ' . strlen($signature) . ', expected 96 for P-384');
        return $signature;
    }
}
