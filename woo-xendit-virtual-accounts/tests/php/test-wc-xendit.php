<?php

class WC_Xendit_Test extends WP_Xendit_UnitTestCase
{

    const XENDIT_PAYMENT_GATEWAY_ID = 'xendit_gateway';
    const NUMBER_OF_PAYMENT_CHANNELS = 90;

    public function set_up()
    {
        parent::set_up();
    }

    /**
     * @return void
     */
    public function test_constants_defined()
    {
        $this->assertTrue(defined('WC_XENDIT_PG_VERSION'));
        $this->assertTrue(defined('WC_XENDIT_PG_MAIN_FILE'));
        $this->assertTrue(defined('WC_XENDIT_PG_PLUGIN_PATH'));
    }

    /**
     * Make sure all Xendit payment channels loaded
     * @return void
     */
    public function test_all_payment_channels_existing_has_options()
    {
        $payment_channels = WC_Xendit_Test_Helper::getXenditPG()->woocommerce_xendit_payment_settings();
        $this->assertTrue(is_array($payment_channels));
        $this->assertTrue(count($payment_channels) == self::NUMBER_OF_PAYMENT_CHANNELS);
    }
}
