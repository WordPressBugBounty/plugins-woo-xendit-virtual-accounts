<?php

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

/**
 * Class WC_Xendit_Test_Helper.
 *
 * This helper class should ONLY be used for unit tests!.
 */
class WC_Xendit_Test_Helper extends PhpUnitTestCase
{

    /**
     * @var WC_Xendit_PG $xenditPG
     */
    protected static $xenditPG;

    /**
     * @return WC_Xendit_PG
     */
    public static function getXenditPG(): WC_Xendit_PG
    {
        if (is_null(self::$xenditPG)) {
            self::$xenditPG = WC_Xendit_PG::get_instance();
        }
        return self::$xenditPG;
    }

    /**
     * @return array|string[]
     */
    public static function getWCPaymentGateways(): array
    {
        return array_map(
            function ($gateway) {
                return get_class($gateway);
            },
            WC()->payment_gateways->payment_gateways()
        );
    }

    /**
     * @return void
     */
    public static function reloadWCPaymentGateway()
    {
        WC()->payment_gateways()->payment_gateways = [];
        WC()->payment_gateways()->init();
    }
}
