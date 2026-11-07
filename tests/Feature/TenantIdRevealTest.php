<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TenantDetail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The tenant's full ID number never rides in the landlord's page HTML: the page carries only the masked value
 * and the Show button fetches the number from an authorized, audited, uncached route.
 */
class TenantIdRevealTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const DETAILS = [
        'emergency_contact_name'         => 'Ana Cruz',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_number'       => '0917 123 4567',
        'id_type'                        => 'PhilSys',
        'id_number'                      => '1234-5678-9012-3456',
    ];

    private function tenancyWithDetails(string $status = 'Occupied'): array
    {
        $parts = $this->makeTenancy(['rental_status' => $status]);
        TenantDetail::saveFor($parts['tenant'], self::DETAILS);

        return $parts;
    }

    public function test_the_page_html_never_contains_the_full_id_number(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancyWithDetails();

        $html = $this->actingAs($landlord)->get(route('landlord.tenancies.show', $reservation))
            ->assertOk()
            ->assertSee('••••••••••••3456')
            ->getContent();

        $this->assertStringNotContainsString('1234-5678-9012-3456', $html);
        $this->assertStringNotContainsString('1234567890123456', $html);
        $this->assertStringContainsString(route('landlord.tenancies.tenantDetails.id', $reservation), $html);
        $this->assertStringNotContainsString('@js(', $html, 'a directive left uncompiled in an attribute breaks the Alpine block');
    }

    public function test_the_landlord_can_reveal_the_id_for_reserved_occupied_and_completed_tenancies(): void
    {
        foreach (['Reserved', 'Occupied', 'Completed'] as $status) {
            ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancyWithDetails($status);

            $response = $this->actingAs($landlord)
                ->getJson(route('landlord.tenancies.tenantDetails.id', $reservation))
                ->assertOk()
                ->assertExactJson(['id_number' => '1234-5678-9012-3456']);

            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'), "{$status}: response must not be cached");
        }
    }

    public function test_each_reveal_is_audited_without_the_number(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancyWithDetails();

        $this->actingAs($landlord)->getJson(route('landlord.tenancies.tenantDetails.id', $reservation))->assertOk();

        $log = AuditLog::where('action', 'tenant_details.reveal_id')->latest('log_id')->first();

        $this->assertNotNull($log);
        $this->assertSame($landlord->user_id, $log->actor_id);
        $this->assertSame($reservation->reservation_id, (int) $log->auditable_id);
        $this->assertStringNotContainsString('1234-5678', json_encode($log->getAttributes()));
    }

    public function test_other_landlords_tenants_and_early_stage_tenancies_are_refused(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->tenancyWithDetails();
        $url = route('landlord.tenancies.tenantDetails.id', $reservation);

        $this->actingAs($this->makeLandlord())->getJson($url)->assertForbidden();
        $this->actingAs($tenant)->getJson($url)->assertForbidden();
        $this->assertSame(0, AuditLog::where('action', 'tenant_details.reveal_id')->count());

        ['landlord' => $landlord, 'reservation' => $negotiating] = $this->tenancyWithDetails('Under Negotiation');
        $this->actingAs($landlord)->getJson(route('landlord.tenancies.tenantDetails.id', $negotiating))->assertForbidden();
    }

    public function test_an_undecryptable_id_is_tolerated_everywhere(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->tenancyWithDetails();

        // What a changed APP_KEY looks like: a value the current key cannot decrypt.
        DB::table('tenant_details')->where('user_id', $tenant->user_id)->update(['id_number' => 'not-a-valid-ciphertext']);

        $detail = TenantDetail::findOrFail($tenant->user_id);
        $this->assertNull($detail->id_number);
        $this->assertFalse($detail->isComplete());
        $this->assertNull($detail->maskedIdNumber());
        $this->assertFalse(TenantDetail::completeFor($tenant));

        $this->actingAs($landlord)->get(route('landlord.tenancies.show', $reservation))->assertOk();
        $this->actingAs($landlord)->getJson(route('landlord.tenancies.tenantDetails.id', $reservation))->assertNotFound();
    }

    public function test_the_id_is_still_encrypted_at_rest_and_round_trips(): void
    {
        ['tenant' => $tenant] = $this->tenancyWithDetails();

        $raw = DB::table('tenant_details')->where('user_id', $tenant->user_id)->value('id_number');

        $this->assertStringNotContainsString('1234-5678', $raw);
        $this->assertSame('1234-5678-9012-3456', TenantDetail::findOrFail($tenant->user_id)->id_number);
    }

    public function test_a_failed_validation_does_not_flash_the_id_into_the_session(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rental_status' => 'Occupied']);
        $tenant->forceFill(['is_walk_in' => true, 'created_by_landlord_id' => $landlord->user_id])->save();

        $this->actingAs($landlord)->from('/landlord/dashboard')
            ->patch(route('landlord.tenancies.tenantDetails.update', $reservation), [
                'id_number' => '9999-8888',
                'id_type'   => 'Not A Real Type',
            ])
            ->assertSessionHasErrors('id_type')
            ->assertSessionMissing('_old_input.id_number');
    }
}
