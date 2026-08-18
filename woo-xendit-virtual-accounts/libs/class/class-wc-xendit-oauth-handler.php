<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles the OAuth webhook callback business logic.
 *
 * Extracted from the xendit_oauth() closure so it can be unit-tested
 * without relying on php://input, header(), or die().
 *
 *
 * @since 7.2.1
 */
class WC_Xendit_Oauth_Handler
{
    /**
     * WC_Xendit_Invoice (or compatible mock) used to update public keys.
     * Injected to allow unit-testing without the real singleton.
     *
     * @var object|null
     */
    private $invoice;

    /**
     * @param object|null $invoice  Optional invoice instance for DI / testing.
     */
    public function __construct($invoice = null)
    {
        $this->invoice = $invoice;
    }

    /**
     * Process the decoded OAuth webhook payload.
     *
     * @param  array $raw_data  Decoded JSON body from the request.
     * @return array{status_code: int, status_text: string, body: array}
     */
    public function handle(array $raw_data): array
    {
        try {
            if (empty($raw_data['oauth_data']) || empty($raw_data['public_key_dev']) || empty($raw_data['public_key_prod'])) {
                throw new Exception("INVALID_OAUTH_RESPONSE", 1);
            }

            $response = WC_Xendit_Sanitized_Webhook::map_and_sanitized_oauth_webhook($raw_data);

            // Clear any cached OAuth error from a previous attempt.
            delete_transient('xendit_oauth_error');

            // CVE-2026-66473 fix (CWE-862): always require a non-empty
            // validate_key that matches the stored key.  The original `&&`
            // allowed an attacker to bypass this check by omitting the field.
            if (!isset($response['oauth_data']['validate_key'])
                || empty($response['oauth_data']['validate_key'])
                || $response['oauth_data']['validate_key'] !== WC_Xendit_Oauth::getValidationKey()
            ) {
                throw new Exception("VALIDATE_KEY_MISMATCH", 1);
            }

            $is_connected = false;

            // error_code is not mapped by the sanitizer, so inspect raw input.
            if (isset($raw_data['error_code'])) {
                set_transient('xendit_oauth_error', $raw_data['error_code'], 10);
            } else {
                $is_connected = true;

                WC_Xendit_Oauth::updateXenditOAuth($response);

                $this->get_invoice()->update_public_keys(
                    $response['public_key_prod'],
                    $response['public_key_dev']
                );
            }

            return [
                'status_code' => 200,
                'status_text' => 'Success',
                'body'        => ['is_connected' => $is_connected],
            ];
        } catch (Exception $e) {
            switch ($e->getMessage()) {
                case 'VALIDATE_KEY_MISMATCH':
                    return [
                        'status_code' => 400,
                        'status_text' => 'Validation Error',
                        'body'        => [
                            'error_code' => 'VALIDATE_KEY_MISMATCH',
                            'message'    => 'Validation key is mismatch',
                        ],
                    ];

                case 'INVALID_OAUTH_RESPONSE':
                    return [
                        'status_code' => 400,
                        'status_text' => 'Validation Error',
                        'body'        => [
                            'error_code' => 'INVALID_OAUTH_RESPONSE',
                            'message'    => 'Invalid OAuth response',
                        ],
                    ];

                default:
                    return [
                        'status_code' => 500,
                        'status_text' => 'Server Error',
                        'body'        => [
                            'error_code' => 'SERVER_ERROR',
                            'message'    => 'Oops, something wrong happened! Please try again.',
                        ],
                    ];
            }
        }
    }

    public function disconnect(): void
    {
        WC_Xendit_Oauth::removeXenditOAuth();

        $settings = get_option(WC_Xendit_Oauth::GATEWAY_SETTINGS_OPTION, []);
        $keys_to_remove = array_merge(WC_Xendit_Oauth::CREDENTIAL_KEYS, WC_Xendit_Oauth::MERCHANT_INFO_KEYS);

        foreach ($keys_to_remove as $key) {
            unset($settings[$key]);
        }
        update_option(WC_Xendit_Oauth::GATEWAY_SETTINGS_OPTION, $settings);

        WC_Xendit_Oauth::clearMerchantCache();
    }

    /**
     * Returns the injected invoice instance, or falls back to the real singleton.
     *
     * @return object
     */
    private function get_invoice()
    {
        if ($this->invoice === null) {
            $this->invoice = WC_Xendit_Invoice::instance();
        }

        return $this->invoice;
    }
}
