<?php 
if (!defined('ABSPATH')) {
    exit;
}

class WC_Xendit_Sanitized_Webhook {
    private static function safe_sanitize($data, $key, $sanitize_fn = 'wc_clean') {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data) || !array_key_exists($key, $data)) {
            return '';
        }

        $value = $data[$key];

        if (is_string($value)) {
            return $sanitize_fn($value);
        }

        if (is_scalar($value)) {
            return $sanitize_fn((string)$value);
        }

        return '';
    }

    public static function map_and_sanitize_invoice_webhook($data) {
        $sanitized_data = array(
            'id' => self::safe_sanitize($data, 'id'),
            'status' => self::safe_sanitize($data, 'status'),
            'channel' => self::safe_sanitize($data, 'channel'),
            'is_live' => isset($data->is_live) ? $data->is_live : false,
            'platform' => self::safe_sanitize($data, 'platform'),
            'invoice_id' => self::safe_sanitize($data, 'invoice_id'),
            'business_id' => self::safe_sanitize($data, 'business_id'),
            'external_id' => self::safe_sanitize($data, 'external_id'),
            'callback_id' => self::safe_sanitize($data, 'callback_id'),
            'version' => self::safe_sanitize($data, 'version'),
            'signature' => self::safe_sanitize($data, 'signature')
        );

        if (!empty($data->description)) {
            $sanitized_data['description'] = self::safe_sanitize($data, 'description', 'sanitize_textarea_field');
        }

        if ($sanitized_data['channel'] == 'CREDIT_CARD' 
            && !empty($data->credit_card_token)
            && !empty($data->credit_card_charge_id)) {

            $sanitized_data = array_merge($sanitized_data, array(
                'credit_card_token' => self::safe_sanitize($data, 'credit_card_token'),
                'credit_card_charge_id' => self::safe_sanitize($data, 'credit_card_charge_id')
            ));
        }

        if (!empty($data->customer)) {
            $customer = self::check_and_sanitize_customer($data->customer);
            $sanitized_data = array_merge($sanitized_data, array('customer' => $customer));
        }

        $json_encoded = json_encode($sanitized_data);

        return json_decode($json_encoded);
    }

    private static function check_and_sanitize_addresses($data) {
        $addresses = array();
        foreach ($data as $address) {
            $currentAddress = array(
                'city' => self::safe_sanitize($address, 'city'),
                'state' => self::safe_sanitize($address, 'state'),
                'country' => self::safe_sanitize($address, 'country'),
                'postal_code' => self::safe_sanitize($address, 'postal_code'),
                'street_line1' => self::safe_sanitize($address, 'street_line1'),
            );

            if (!empty($address->street_line2)) {
                $currentAddress['street_line2'] = self::safe_sanitize($address, 'street_line2');
            }   

            array_push($addresses, $currentAddress);
        }

        return $addresses;
    }

    private static function check_and_sanitize_customer($data) {
        $customer = array(
            'email' => self::safe_sanitize($data, 'email', 'sanitize_email')
        );

        if (!empty($data->surname)) {
            $customer['surname'] = self::safe_sanitize($data, 'surname');
        }

        if (!empty($data->given_name)) {
            $customer['given_name'] = self::safe_sanitize($data, 'given_name');
        }

        if (!empty($data->mobile_number)) {
            $customer['mobile_number'] = self::safe_sanitize($data, 'mobile_number');
        }

        if (!empty($data->phone_number)) {
            $customer['phone_number'] = self::safe_sanitize($data, 'phone_number');
        }

        if (!empty($data->addresses)) {
            $customer = array_merge($customer, array('addresses' => self::check_and_sanitize_addresses($data->addresses)));
        }

        return $customer;
    }

    public static function map_and_sanitized_oauth_webhook($data) {
        $oauth_data = array(
            'business_id' => self::safe_sanitize($data['oauth_data'], 'business_id'),
            'platform' => self::safe_sanitize($data['oauth_data'], 'platform'),
            'validate_key' => self::safe_sanitize($data, 'validate_key'),
            'id' => self::safe_sanitize($data['oauth_data'], 'id'),
            'store_url' => self::safe_sanitize($data['oauth_data'], 'store_url'),
            'oauth_data_production' => array(
                'access_token' => self::safe_sanitize($data['oauth_data']['oauth_data_production'], 'access_token'),
                'refresh_token' => self::safe_sanitize($data['oauth_data']['oauth_data_production'], 'refresh_token'),
                'expires_at' => self::safe_sanitize($data['oauth_data']['oauth_data_production'], 'expires_at'),
                'token_type' => self::safe_sanitize($data['oauth_data']['oauth_data_production'], 'token_type'),
                'scope' => self::safe_sanitize($data['oauth_data']['oauth_data_production'], 'scope')
            ),
            'oauth_data_development' => array(
                'access_token' => self::safe_sanitize($data['oauth_data']['oauth_data_development'], 'access_token'),
                'refresh_token' => self::safe_sanitize($data['oauth_data']['oauth_data_development'], 'refresh_token'),
                'expires_at' => self::safe_sanitize($data['oauth_data']['oauth_data_development'], 'expires_at'),
                'token_type' => self::safe_sanitize($data['oauth_data']['oauth_data_development'], 'token_type'),
                'scope' => self::safe_sanitize($data['oauth_data']['oauth_data_development'], 'scope')
            )
        );

        if (!empty($data['oauth_data']['version'])) {
            $oauth_data['version'] = self::safe_sanitize($data['oauth_data'], 'version');
        }

        $webhook = array(
            'public_key_dev' => self::safe_sanitize($data, 'public_key_dev'),
            'public_key_prod' => self::safe_sanitize($data, 'public_key_prod'),
            'expiry_date' => self::safe_sanitize($data, 'expiry_date'),
            'oauth_data' => $oauth_data
        );

        return $webhook;
    }

    public static function map_and_sanitize_intg_notification_webhook($data): IntegrationNotification {
        // Validate required presence first
        $required_keys = ['_id', 'trigger', 'status', 'app_mode', 'integration_name', 'business_id', 'signature', 'signature_version', 'woocommerce_order_id'];

        foreach ( $required_keys as $key ) {
            if (empty($data[$key])) {
                $message = sprintf("Integration notif missing required key: %s", $key);
                WC_Xendit_PG_Logger::log($message);
                throw new InvalidArgumentException(esc_html($message));
            }
        }

        $sanitized_data = array(
            '_id' => self::safe_sanitize($data, '_id'),
            'trigger' => self::safe_sanitize($data, 'trigger'),
            'status' => self::safe_sanitize($data, 'status'),
            'webhook_id' => self::safe_sanitize($data, 'webhook_id'),
            'app_mode' => self::safe_sanitize($data, 'app_mode'),
            'integration_name' => self::safe_sanitize($data, 'integration_name'),
            'business_id' => self::safe_sanitize($data, 'business_id'),
            'signature' => self::safe_sanitize($data, 'signature'),
            'signature_version' => isset($data['signature_version']) ? absint($data['signature_version']) : 0,
            'createdAt' => self::safe_sanitize($data, 'createdAt'),
            'updatedAt' => self::safe_sanitize($data, 'updatedAt'),
            'woocommerce_order_id' => isset($data['woocommerce_order_id']) ? absint($data['woocommerce_order_id']) : 0,
        );

        // Nested session object
        if (!empty($data['session']) && is_array($data['session'])) {
            $sanitized_session = [];
            foreach ($data['session'] as $k => $v) {
                $sanitized_session[$k] = self::safe_sanitize($data['session'], $k);
            }
            $sanitized_data['session'] = $sanitized_session;
        }

        // Nested payment_request object
        if (!empty($data['payment_request']) && is_array($data['payment_request'])) {
            $sanitized_data['payment_request'] = array(
                'payment_request_id' => self::safe_sanitize($data['payment_request'], 'payment_request_id'),
                'payment_id'         => self::safe_sanitize( $data['payment_request'], 'payment_id' ),
                'status'             => self::safe_sanitize($data['payment_request'], 'status'),
            );
        }

        // Nested payment_token object
        if (!empty($data['payment_token']) && is_array($data['payment_token'])) {
            $sanitized_data['payment_token'] = array(
                'payment_token_id' => self::safe_sanitize($data['payment_token'], 'payment_token_id'),
                'status'           => self::safe_sanitize($data['payment_token'], 'status'),
            );
        }

        // Nested refund object
        if (!empty($data['refund']) && is_array($data['refund'])) {
            $sanitized_data['refund'] = array(
                'refund_id' => self::safe_sanitize($data['refund'], 'refund_id'),
                'status'    => self::safe_sanitize($data['refund'], 'status'),
                'amount'    => self::safe_sanitize($data['refund'], 'amount'),
                'currency'  => self::safe_sanitize($data['refund'], 'currency'),
            );
        }

        return new IntegrationNotification($sanitized_data);
    }
}