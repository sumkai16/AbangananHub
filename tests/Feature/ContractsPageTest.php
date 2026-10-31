<?php

namespace Tests\Feature;

use App\Models\LandlordTenant;
use App\Models\Reservation;
use App\Models\TenantDetail;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * The landlord's Contracts page: list, Add contract and the view modal's details.
 * plans/landlord-tenants-contracts-direct-payments.md, phase 2.
 */
class ContractsPageTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    private function contract($unit, $tenant, string $status, array $extra = []): Reservation
    {
        return Reservation::create($extra + [
            'tenant_id' => $tenant->user_id, 'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
            'rental_status' => $status, 'reservation_date' => '2026-06-01',
            'target_move_in_date' => '2026-06-01', 'target_move_out_date' => '2027-05-31',
            'agreed_monthly_rent' => 5000, 'rent_due_day' => 1,
        ]);
    }

    private function validForm(LandlordTenant $row, $unit, array $overrides = []): array
    {
        return $overrides + [
            'landlord_tenant_id' => $row->landlord_tenant_id,
            'unit_id' => $unit->unit_id,
            'move_in_date' => '2026-11-01',
            'move_out_date' => '2027-04-30',
            'agreed_monthly_rent' => 4500,
            'rent_due_day' => 1,
            'occupants_count' => 1,
            'lease_mode' => 'later',
        ];
    }

    public function test_the_page_lists_this_landlords_contracts_grouped_by_property_and_not_inquiries(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord, ['title' => 'Tunghaan Dorm']);
        $living = $this->makeTenant();
        $asking = $this->makeTenant();

        $this->contract($this->makeUnit($property, ['unit_label' => 'Rm 3']), $living, 'Occupied');
        $this->contract($this->makeUnit($property, ['unit_label' => 'Rm 4']), $asking, 'Inquiry');

        $stranger = $this->makeTenant();
        $this->contract($this->makeUnit($this->makeProperty($this->makeLandlord(), ['title' => 'Someone Else Place'])), $stranger, 'Occupied');

        $this->actingAs($landlord)->get(route('landlord.contracts.index'))
            ->assertOk()
            ->assertSee('Tunghaan Dorm')
            ->assertSee($living->first_name)
            ->assertSee('TN-0001')
            ->assertDontSee($asking->last_name)
            ->assertDontSee('Someone Else Place');
    }

    public function test_adding_a_contract_with_a_future_move_in_reserves_the_unit_and_makes_the_tenant_active(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $tenant = $this->makeTenant();
        $row = LandlordTenant::ensureFor($landlord->user_id, $tenant->user_id);

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($row, $unit))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $contract = Reservation::where('tenant_id', $tenant->user_id)->firstOrFail();
        $this->assertSame('Reserved', $contract->rental_status);
        $this->assertSame('4500.00', $contract->agreed_monthly_rent);
        $this->assertSame('Reserved', $unit->fresh()->availability_status);
        $this->assertNotNull($contract->lease_snapshot);
        $this->assertSame('Awaiting move-in', $contract->contractStatus()['label']);
        $this->assertSame('Active', LandlordTenant::statusFrom($row->reservations()->pluck('rental_status')));
    }

    public function test_a_contract_that_has_already_started_needs_the_move_in_money(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $row = LandlordTenant::ensureFor($landlord->user_id, $this->makeTenant()->user_id);

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($row, $unit, ['move_in_date' => '2026-10-01']))
            ->assertSessionHasErrors('initial_amount');

        $this->assertSame('Available', $unit->fresh()->availability_status);
    }

    public function test_a_contract_needs_an_end_date(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $row = LandlordTenant::ensureFor($landlord->user_id, $this->makeTenant()->user_id);

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($row, $unit, ['move_out_date' => '']))
            ->assertSessionHasErrors('move_out_date');
    }

    public function test_another_landlords_tenant_or_unit_is_refused(): void
    {
        $landlord = $this->makeLandlord();
        $other = $this->makeLandlord();
        $myUnit = $this->makeUnit($this->makeProperty($landlord));
        $theirUnit = $this->makeUnit($this->makeProperty($other));
        $myRow = LandlordTenant::ensureFor($landlord->user_id, $this->makeTenant()->user_id);
        $theirRow = LandlordTenant::ensureFor($other->user_id, $this->makeTenant()->user_id);

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($theirRow, $myUnit))
            ->assertSessionHasErrors('landlord_tenant_id');

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($myRow, $theirUnit))
            ->assertForbidden();

        $this->assertFalse(Reservation::whereIn('unit_id', [$myUnit->unit_id, $theirUnit->unit_id])->exists());
    }

    public function test_a_unit_with_a_live_contract_cannot_get_a_second_one(): void
    {
        $landlord = $this->makeLandlord();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $this->contract($unit, $this->makeTenant(), 'Rental Agreement Signed');
        $row = LandlordTenant::ensureFor($landlord->user_id, $this->makeTenant()->user_id);

        $this->actingAs($landlord)
            ->post(route('landlord.contracts.store'), $this->validForm($row, $unit))
            ->assertStatus(409);
    }

    public function test_the_view_shows_lease_terms_and_the_emergency_contact_to_the_owner_only(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        TenantDetail::create([
            'user_id' => $tenant->user_id, 'emergency_contact_name' => 'Liza Santos',
            'emergency_contact_relationship' => 'Parent', 'emergency_contact_number' => '09185550456',
        ]);
        $contract = $this->contract($this->makeUnit($this->makeProperty($landlord)), $tenant, 'Occupied');

        $this->actingAs($landlord)->get(route('landlord.contracts.show', $contract))
            ->assertOk()
            ->assertSee('Liza Santos')
            ->assertSee('₱5,000.00')
            ->assertSee('End contract');

        $this->actingAs($this->makeLandlord())->get(route('landlord.contracts.show', $contract))->assertForbidden();
    }

    public function test_status_reads_ending_soon_and_past_end_date(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $soon = $this->contract($this->makeUnit($property), $this->makeTenant(), 'Occupied', ['target_move_out_date' => '2026-11-13']);
        $past = $this->contract($this->makeUnit($property), $this->makeTenant(), 'Occupied', ['target_move_out_date' => '2026-10-01']);
        $unpaid = $this->contract($this->makeUnit($property), $this->makeTenant(), 'Rental Agreement Signed');

        $this->assertSame('Ends in 30 days', $soon->contractStatus()['label']);
        $this->assertSame('Past end date', $past->contractStatus()['label']);
        $this->assertSame('Awaiting payment', $unpaid->contractStatus()['label']);
    }

    public function test_the_modal_holds_the_lease_documents_and_no_view_contract_button(): void
    {
        $landlord = $this->makeLandlord();
        $contract = $this->contract($this->makeUnit($this->makeProperty($landlord)), $this->makeTenant(), 'Occupied');

        $this->actingAs($landlord)->get(route('landlord.contracts.show', $contract))
            ->assertOk()
            ->assertSee('Documents')
            ->assertSee('Lease missing')
            ->assertSee('Upload signed copy')
            ->assertSee(route('landlord.leases.show', $contract), false)
            ->assertSee(route('landlord.leases.upload', $contract), false)
            ->assertDontSee('Open signed copy')
            ->assertDontSee('View contract');
    }

    public function test_uploading_the_lease_from_the_modal_comes_back_to_it(): void
    {
        Storage::fake('local');
        $landlord = $this->makeLandlord();
        $contract = $this->contract($this->makeUnit($this->makeProperty($landlord)), $this->makeTenant(), 'Occupied');

        $this->actingAs($landlord)->post(route('landlord.leases.upload', $contract), [
            'from' => 'contracts',
            'lease_file' => UploadedFile::fake()->create('lease.pdf', 120, 'application/pdf'),
        ])->assertRedirect(route('landlord.contracts.index', ['open' => $contract->reservation_id]))
            ->assertSessionHas('success');

        $contract->refresh();
        $this->assertNotNull($contract->lease_uploaded_at);
        Storage::disk('local')->assertExists($contract->lease_file_path);

        $this->actingAs($landlord)->get(route('landlord.contracts.show', $contract))
            ->assertSee('Signed copy uploaded')->assertSee('Open signed copy')->assertSee('Replace signed copy');
    }

    public function test_a_wrong_file_from_the_modal_reopens_it_with_the_error(): void
    {
        Storage::fake('local');
        $landlord = $this->makeLandlord();
        $contract = $this->contract($this->makeUnit($this->makeProperty($landlord)), $this->makeTenant(), 'Occupied');

        $this->actingAs($landlord)->post(route('landlord.leases.upload', $contract), [
            'from' => 'contracts',
            'lease_file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
        ])->assertRedirect(route('landlord.contracts.index', ['open' => $contract->reservation_id]))
            ->assertSessionHas('error', 'Upload the signed lease as a PDF, JPG or PNG.');

        $this->assertNull($contract->fresh()->lease_file_path);
    }

    public function test_another_landlord_cannot_upload_a_lease(): void
    {
        Storage::fake('local');
        $contract = $this->contract($this->makeUnit($this->makeProperty($this->makeLandlord())), $this->makeTenant(), 'Occupied');

        $this->actingAs($this->makeLandlord())->post(route('landlord.leases.upload', $contract), [
            'from' => 'contracts',
            'lease_file' => UploadedFile::fake()->create('lease.pdf', 120, 'application/pdf'),
        ])->assertForbidden();

        $this->assertNull($contract->fresh()->lease_file_path);
    }
}
