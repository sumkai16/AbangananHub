<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A landlord recording a payment, attaching a lease or rating a tenant used to be silent for the tenant.
 */
class ActionNotificationsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['rentals.rent_overdue_grace_days' => 0]);
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 20)->startOfDay());
    }

    private function countFor(User $user, string $type): int
    {
        return Notification::where('user_id', $user->user_id)->where('type', $type)->count();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'payment_type'   => 'Monthly',
            'billing_period' => '2026-08-01',
            'amount'         => 5000,
            'payment_method' => 'Cash',
            'paid_at'        => '2026-10-19',
        ], $overrides);
    }

    public function test_a_recorded_payment_notifies_the_tenant_once_even_when_spread_over_months(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])
            ->post(route('landlord.payments.store', $t['reservation']), $this->payload(['amount' => 10000]))
            ->assertSessionHas('success');

        $this->assertSame(1, $this->countFor($t['tenant'], 'payment'));
        $this->assertSame(0, $this->countFor($t['landlord'], 'payment'));
    }

    public function test_an_api_recorded_payment_notifies_the_tenant(): void
    {
        $t = $this->makeTenancy();

        Sanctum::actingAs($t['landlord']);
        $this->postJson("/api/v1/landlord/tenancies/{$t['reservation']->reservation_id}/payments", $this->payload())
            ->assertCreated();

        $this->assertSame(1, $this->countFor($t['tenant'], 'payment'));
    }

    public function test_a_lease_upload_notifies_the_tenant(): void
    {
        $t = $this->makeTenancy();

        $this->actingAs($t['landlord'])->post(route('landlord.leases.upload', $t['reservation']), [
            'lease_file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success');

        $this->assertSame(1, $this->countFor($t['tenant'], 'agreement'));
    }

    public function test_rating_a_tenant_notifies_them_on_web_and_api(): void
    {
        $web = $this->makeTenancy();
        $this->actingAs($web['landlord'])
            ->post(route('landlord.reservations.rateTenant.store', $web['reservation']), ['rating' => 5])
            ->assertSessionHas('success');
        $this->assertSame(1, $this->countFor($web['tenant'], 'tenant_rating'));

        $api = $this->makeTenancy();
        Sanctum::actingAs($api['landlord']);
        $this->postJson("/api/v1/landlord/reservations/{$api['reservation']->reservation_id}/rate-tenant", ['rating' => 4])
            ->assertCreated();
        $this->assertSame(1, $this->countFor($api['tenant'], 'tenant_rating'));
    }

    public function test_a_walk_in_tenant_gets_nothing(): void
    {
        $walkIn = User::factory()->create(['is_walk_in' => true]);
        $t = $this->makeTenancy(tenant: $walkIn);

        $this->actingAs($t['landlord'])->post(route('landlord.payments.store', $t['reservation']), $this->payload());
        $this->actingAs($t['landlord'])->post(route('landlord.leases.upload', $t['reservation']), [
            'lease_file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'),
        ]);
        $this->actingAs($t['landlord'])->post(route('landlord.reservations.rateTenant.store', $t['reservation']), ['rating' => 5]);

        $this->assertSame(0, Notification::where('user_id', $walkIn->user_id)->count());
    }
}
