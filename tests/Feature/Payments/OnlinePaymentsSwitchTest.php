<?php

namespace Tests\Feature\Payments;

use App\Models\Setting;
use App\Models\User;
use App\Support\Payments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class OnlinePaymentsSwitchTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_off_means_web_and_api_checkouts_are_refused_and_paymongo_is_never_called(): void
    {
        config(['rentals.online_payments_enabled' => false, 'services.paymongo.secret_key' => 'sk_test_x']);
        Http::fake();
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();

        $this->actingAs($tenant)->post(route('payments.rentCheckout', $reservation), ['accept_terms' => '1'])
            ->assertSessionHasErrors(['payment' => Payments::OFFLINE_MESSAGE]);

        Sanctum::actingAs($tenant);
        $this->postJson("/api/v1/tenant/tenancy/{$reservation->reservation_id}/pay-rent")->assertStatus(409);

        $reservation->update(['rental_status' => 'Rental Agreement Signed']);
        $this->postJson("/api/v1/tenant/reservations/{$reservation->reservation_id}/pay")->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_the_setting_round_trips_and_overrides_the_file_default(): void
    {
        Setting::put('online_payments_enabled', '1');
        $this->assertTrue(Setting::effective('online_payments_enabled'));

        Setting::put('online_payments_enabled', '0');
        $this->assertFalse(Setting::effective('online_payments_enabled'), 'Off is stored, not deleted, so it survives an env default of on.');
        $this->assertSame('Off', Setting::display('online_payments_enabled', Setting::effective('online_payments_enabled')));
    }

    public function test_an_admin_can_switch_it_from_system_settings(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin)->patch(route('admin.settings.update'), ['settings' => ['online_payments_enabled' => '0']])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Setting::effective('online_payments_enabled'));
        $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk()->assertSee('Online payments (PayMongo)');
    }
}
