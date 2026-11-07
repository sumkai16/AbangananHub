<?php

namespace Tests\Feature\Payments;

use App\Models\Setting;
use App\Models\User;
use App\Support\ServiceFee;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The online payment fee: the formula, and the two System Settings that drive it. How a checkout charges and
 * stores it is pinned in TenantCheckoutTest; how it is settled in PayMongoWebhookTest.
 */
class ServiceFeeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_there_is_no_fee_until_a_rate_is_set(): void
    {
        config(['rentals.online_payment_fee_percent' => 0, 'rentals.online_payment_fee_fixed' => 0]);

        $this->assertSame(0.0, ServiceFee::for(5000));
    }

    public function test_the_fee_is_a_percentage_of_the_rent_plus_a_fixed_amount(): void
    {
        config(['rentals.online_payment_fee_percent' => 2, 'rentals.online_payment_fee_fixed' => 10]);

        $this->assertSame(110.0, ServiceFee::for(5000));
        $this->assertSame(160.01, ServiceFee::for(7500.50), '2% of 7,500.50 is 150.01, plus 10.');
    }

    public function test_either_part_alone_works_and_the_result_is_rounded_to_the_centavo(): void
    {
        config(['rentals.online_payment_fee_percent' => 0, 'rentals.online_payment_fee_fixed' => 15]);
        $this->assertSame(15.0, ServiceFee::for(4000));

        config(['rentals.online_payment_fee_percent' => 2.5, 'rentals.online_payment_fee_fixed' => 0]);
        $this->assertSame(33.33, ServiceFee::for(1333.33));
    }

    public function test_nothing_owed_means_no_fee(): void
    {
        config(['rentals.online_payment_fee_percent' => 2, 'rentals.online_payment_fee_fixed' => 10]);

        $this->assertSame(0.0, ServiceFee::for(0));
    }

    public function test_an_admin_saves_the_fee_from_system_settings_and_it_is_used(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->patch(route('admin.settings.update'), ['settings' => [
            'online_payment_fee_percent' => '2.5',
            'online_payment_fee_fixed' => '12.50',
        ]])->assertSessionHasNoErrors();

        $this->assertSame(2.5, Setting::effective('online_payment_fee_percent'));
        $this->assertSame(12.5, Setting::effective('online_payment_fee_fixed'));
        $this->assertSame('2.5', Setting::display('online_payment_fee_percent', Setting::effective('online_payment_fee_percent')));

        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Online payment fee (percent)')->assertSee('step="0.01"', false);
    }

    public function test_the_fee_settings_have_limits_and_a_blank_clears_them(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->patch(route('admin.settings.update'), ['settings' => ['online_payment_fee_percent' => '25']])
            ->assertSessionHasErrors('settings.online_payment_fee_percent');
        $this->actingAs($admin)->patch(route('admin.settings.update'), ['settings' => ['online_payment_fee_fixed' => '-1']])
            ->assertSessionHasErrors('settings.online_payment_fee_fixed');

        Setting::put('online_payment_fee_percent', '3');
        $this->assertSame(3.0, Setting::effective('online_payment_fee_percent'));

        Setting::put('online_payment_fee_percent', '');
        $this->assertSame(0, Setting::effective('online_payment_fee_percent'), 'Cleared = back to the file default of 0.');
    }
}
