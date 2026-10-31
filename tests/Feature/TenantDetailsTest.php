<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\TenantDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * Emergency contact and ID on file (plans/stakeholder-feedback-pass.md, phase 2): required to sign,
 * encrypted at rest, and visible only to the landlord of a tenancy.
 */
class TenantDetailsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const DETAILS = [
        'emergency_contact_name'         => 'Ana Cruz',
        'emergency_contact_relationship' => 'Parent',
        'emergency_contact_number'       => '0917 123 4567',
        'id_type'                        => 'PhilSys',
        'id_number'                      => '1234-5678-9012-3456',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    private function pendingAgreement(): array
    {
        $parts = $this->makeTenancy(['rental_status' => 'Under Negotiation']);
        $parts['reservation']->advanceToPendingAgreement();

        return $parts;
    }

    private function sign(User $tenant, Reservation $reservation, array $extra = [])
    {
        return $this->actingAs($tenant)->post(route('agreements.sign', $reservation), array_merge([
            'agree'     => '1',
            'accept_tc' => '1',
        ], $extra));
    }

    // ─── signing ────────────────────────────────────────────────

    public function test_signing_is_refused_without_an_emergency_contact_and_id(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();

        $this->sign($tenant, $reservation)->assertSessionHasErrors([
            'emergency_contact_name', 'emergency_contact_number', 'id_type', 'id_number',
        ]);

        $this->assertSame('Pending Rental Agreement', $reservation->fresh()->rental_status);
        $this->assertNull(TenantDetail::find($tenant->user_id));
    }

    public function test_signing_with_the_details_saves_them_and_signs(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();

        $this->sign($tenant, $reservation, self::DETAILS)->assertSessionHasNoErrors();

        $this->assertSame('Rental Agreement Signed', $reservation->fresh()->rental_status);

        $detail = TenantDetail::findOrFail($tenant->user_id);
        $this->assertSame('Ana Cruz', $detail->emergency_contact_name);
        $this->assertSame('1234-5678-9012-3456', $detail->id_number);
        $this->assertTrue($detail->isComplete());
    }

    public function test_a_tenant_with_details_on_file_is_not_asked_again(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->sign($tenant, $reservation)->assertSessionHasNoErrors();

        $this->assertSame('Rental Agreement Signed', $reservation->fresh()->rental_status);
    }

    public function test_the_signing_page_asks_for_the_fields_only_when_none_are_on_file(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('name="id_number"', false);

        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->actingAs($tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertDontSee('name="id_number"', false)
            ->assertSee('Emergency contact on file');
    }

    public function test_the_mobile_sign_endpoint_enforces_the_same_rule(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();

        $this->actingAs($tenant, 'sanctum')
            ->postJson("/api/v1/tenant/reservations/{$reservation->reservation_id}/agreement/sign", ['agree' => true, 'accept_tc' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['emergency_contact_name', 'id_number']);

        $this->assertSame('Pending Rental Agreement', $reservation->fresh()->rental_status);
    }

    // ─── validation and storage ─────────────────────────────────

    public function test_the_id_number_is_stored_encrypted(): void
    {
        $tenant = $this->makeTenant();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $raw = DB::table('tenant_details')->where('user_id', $tenant->user_id)->value('id_number');

        $this->assertNotSame('1234-5678-9012-3456', $raw);
        $this->assertStringNotContainsString('1234', $raw);
        $this->assertSame('1234-5678-9012-3456', TenantDetail::find($tenant->user_id)->id_number);
    }

    public function test_an_id_type_outside_the_list_and_a_bad_phone_number_are_refused(): void
    {
        $tenant = $this->makeTenant();

        $this->actingAs($tenant)->patch(route('profile.tenantDetails.update'), array_merge(self::DETAILS, [
            'id_type'                  => 'Library Card',
            'emergency_contact_number' => 'call me',
        ]))->assertSessionHasErrors(['id_type', 'emergency_contact_number']);
    }

    public function test_a_blank_form_never_wipes_what_is_saved(): void
    {
        $tenant = $this->makeTenant();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->actingAs($tenant)->patch(route('profile.tenantDetails.update'), [
            'emergency_contact_name'   => '',
            'emergency_contact_number' => '',
            'id_type'                  => '',
            'id_number'                => '',
        ])->assertSessionHas('warning');

        $this->assertSame('Ana Cruz', TenantDetail::find($tenant->user_id)->emergency_contact_name);
        $this->assertSame('1234-5678-9012-3456', TenantDetail::find($tenant->user_id)->id_number);
    }

    public function test_account_settings_saves_a_correction(): void
    {
        $tenant = $this->makeTenant();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->actingAs($tenant)->get(route('profile.edit'))->assertOk()->assertSee('Emergency contact');

        $this->actingAs($tenant)->patch(route('profile.tenantDetails.update'), ['id_number' => '9999-0000'])
            ->assertSessionHas('success');

        $detail = TenantDetail::find($tenant->user_id);
        $this->assertSame('9999-0000', $detail->id_number);
        $this->assertSame('Ana Cruz', $detail->emergency_contact_name);
    }

    public function test_the_masked_id_hides_all_but_the_last_four(): void
    {
        $detail = new TenantDetail(['id_number' => '1234 5678 9012']);

        $this->assertSame('••••••••9012', $detail->maskedIdNumber());
        $this->assertNull((new TenantDetail)->maskedIdNumber());
    }

    // ─── who can see them ───────────────────────────────────────

    public function test_the_landlord_of_an_occupied_tenancy_sees_the_details(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->actingAs($landlord)->get(route('landlord.tenancies.show', $reservation))
            ->assertOk()
            ->assertSee('Ana Cruz')
            ->assertSee('PhilSys')
            // The full number is in the page for the Show button; the visible default is masked.
            ->assertSee('••••••••••••3456');
    }

    public function test_another_landlord_cannot_read_or_edit_them(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        TenantDetail::saveFor($tenant, self::DETAILS);
        $stranger = $this->makeLandlord();

        $this->actingAs($stranger)->get(route('landlord.tenancies.show', $reservation))->assertForbidden();
        $this->actingAs($stranger)->patch(route('landlord.tenancies.tenantDetails.update', $reservation), ['id_number' => 'x'])
            ->assertForbidden();

        $this->assertSame('1234-5678-9012-3456', TenantDetail::find($tenant->user_id)->id_number);
    }

    public function test_details_are_hidden_while_the_tenancy_is_still_being_negotiated(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rental_status' => 'Under Negotiation']);
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->assertFalse($landlord->can('viewTenantDetails', $reservation));

        $reservation->update(['rental_status' => 'Occupied']);

        $this->assertTrue($landlord->can('viewTenantDetails', $reservation->fresh()));
    }

    public function test_the_landlord_cannot_edit_an_online_tenants_details(): void
    {
        ['landlord' => $landlord, 'tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();

        $this->actingAs($landlord)->patch(route('landlord.tenancies.tenantDetails.update', $reservation), self::DETAILS)
            ->assertForbidden();

        $this->assertNull(TenantDetail::find($tenant->user_id));
    }

    public function test_a_landlord_fills_in_a_walk_in_tenants_details(): void
    {
        $landlord = $this->makeLandlord();
        $walkIn = User::factory()->create(['is_walk_in' => true, 'created_by_landlord_id' => $landlord->user_id]);
        $walkIn->assignRole('Tenant');
        ['reservation' => $reservation] = $this->makeTenancy([], $landlord, $walkIn);

        $this->actingAs($landlord)->patch(route('landlord.tenancies.tenantDetails.update', $reservation), self::DETAILS)
            ->assertRedirect(route('landlord.tenancies.show', $reservation))
            ->assertSessionHas('success');

        $this->assertSame('Ana Cruz', TenantDetail::find($walkIn->user_id)->emergency_contact_name);
    }

    public function test_add_tenant_stores_the_optional_details(): void
    {
        $landlord = $this->makeLandlord();

        $this->actingAs($landlord)->post(route('landlord.tenants.store'), array_merge([
            'first_name'     => 'Walk',
            'last_name'      => 'In',
            'contact_number' => '09170000000',
        ], self::DETAILS))->assertRedirect();

        $walkIn = User::where('first_name', 'Walk')->where('last_name', 'In')->firstOrFail();
        $this->assertSame('Ana Cruz', TenantDetail::find($walkIn->user_id)->emergency_contact_name);
    }

    public function test_the_id_number_never_reaches_the_lease_snapshot(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->pendingAgreement();
        TenantDetail::saveFor($tenant, self::DETAILS);

        $this->assertStringNotContainsString('1234-5678', json_encode($reservation->fresh()->lease_snapshot));
        $this->assertStringNotContainsString('PhilSys', json_encode($reservation->fresh()->lease_snapshot));
    }
}
