<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Xendit OAuth
 *
 * @since 2.27.0
 */

class WC_Xendit_Oauth
{
    const XENDIT_OAUTH_OPTION_NAME = 'woocommerce_xendit_oauth_data';

    const XENDIT_VALIDATION_KEY_OPTION_NAME = 'woocommerce_xendit_oauth_validation_key';

    const GATEWAY_SETTINGS_OPTION = 'woocommerce_xendit_gateway_settings';

    const MERCHANT_CACHE_TRANSIENT = 'xendit_merchant_info';

    /**
     * API credential keys stored inside the gateway settings option.
     */
    const CREDENTIAL_KEYS = ['secret_key', 'secret_key_dev', 'api_key', 'api_key_dev'];

    /**
     * Merchant info keys cached inside the gateway settings option.
     */
    const MERCHANT_INFO_KEYS = ['business_id', 'name'];

    /**
     * @param array $data
     * @return void
     */
    public static function updateXenditOAuth(array $data = [])
    {
        if (!empty($data['oauth_data'])) {
            $oauth = get_option(self::XENDIT_OAUTH_OPTION_NAME);
            if (empty($oauth)) {
                add_option(self::XENDIT_OAUTH_OPTION_NAME, $data['oauth_data']);
            } else {
                update_option(self::XENDIT_OAUTH_OPTION_NAME, $data['oauth_data']);
            }
        }
    }

    public static function removeXenditOAuth()
    {
        $xenditClass = new WC_Xendit_PG_API();
        $xenditClass->uninstallApp();

        delete_option(self::XENDIT_OAUTH_OPTION_NAME);

        return true;
    }

    public static function getXenditOAuth()
    {
        return get_option(self::XENDIT_OAUTH_OPTION_NAME);
    }

    public static function disconnect(): void
    {
        self::removeXenditOAuth();

        $settings = get_option(self::GATEWAY_SETTINGS_OPTION, []);
        $keys_to_remove = array_merge(self::CREDENTIAL_KEYS, self::MERCHANT_INFO_KEYS);

        foreach ($keys_to_remove as $key) {
            unset($settings[$key]);
        }
        update_option(self::GATEWAY_SETTINGS_OPTION, $settings);

        self::clearMerchantCache();
    }

    public static function clearMerchantCache(): void
    {
        delete_transient(self::MERCHANT_CACHE_TRANSIENT);

        $settings = get_option(self::GATEWAY_SETTINGS_OPTION, []);
        $changed = false;

        foreach (self::MERCHANT_INFO_KEYS as $key) {
            if (isset($settings[$key])) {
                unset($settings[$key]);
                $changed = true;
            }
        }

        if ($changed) {
            update_option(self::GATEWAY_SETTINGS_OPTION, $settings);
        }
    }

    /*
    * param: $key string
    *
    * return boolean
    */
    public static function updateValidationKey($key)
    {
        $oauth = get_option(self::XENDIT_VALIDATION_KEY_OPTION_NAME);

        return empty($oauth) ? add_option(self::XENDIT_VALIDATION_KEY_OPTION_NAME, $key) : update_option(self::XENDIT_VALIDATION_KEY_OPTION_NAME, $key) ;
    }

    public static function getValidationKey()
    {
        return get_option(self::XENDIT_VALIDATION_KEY_OPTION_NAME);
    }
}
