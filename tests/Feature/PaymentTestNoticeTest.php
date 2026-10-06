<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * The checkout "Test mode" notice shows only while PayMongo runs on test keys.
 */
class PaymentTestNoticeTest extends TestCase
{
    public function test_notice_shows_on_test_keys(): void
    {
        config(['services.paymongo.test_mode' => true]);

        $this->assertStringContainsString('No real money is charged', Blade::render('<x-payment-test-notice />'));
    }

    public function test_notice_is_hidden_on_live_keys(): void
    {
        config(['services.paymongo.test_mode' => false]);

        $this->assertSame('', trim(Blade::render('<x-payment-test-notice />')));
    }
}
