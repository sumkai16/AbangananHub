<?php

namespace Tests\Feature;

use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Formal lease: frozen terms, walk-in signed copies. See
 * plans/formal-lease-agreement.md.
 *
 * Builds its own landlord, tenant and unit inside a rolled-back transaction;
 * files go to a faked disk.
 */
class LeaseTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private PropertyUnit $unit;
    private User $landlord;
    private User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
        Storage::fake('local');

        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        $this->unit = $this->makeUnit($this->makeProperty($this->landlord));
    }

    private function negotiatingReservation(): Reservation
    {
        return Reservation::create([
            'tenant_id'        => $this->tenant->user_id,
            'property_id'      => $this->unit->property_id,
            'unit_id'          => $this->unit->unit_id,
            'rental_status'    => 'Under Negotiation',
            'reservation_date' => today(),
            'target_move_in_date' => today()->addWeek(),
        ]);
    }

    private function walkIn(array $overrides = [])
    {
        return $this->actingAs($this->landlord)->post(route('landlord.tenants.walkIn.store'), array_merge([
            'first_name'     => 'Lease',
            'last_name'      => 'Tester',
            'contact_number' => '09170000000',
            'unit_id'        => $this->unit->unit_id,
            // Future move-in, so no initial payment is required.
            'move_in_date'   => today()->addDays(3)->toDateString(),
            'lease_mode'     => 'later',
        ], $overrides));
    }

    private function latestWalkIn(): Reservation
    {
        return Reservation::where('unit_id', $this->unit->unit_id)->latest('reservation_id')->firstOrFail();
    }

    public function test_sending_the_agreement_freezes_the_lease(): void
    {
        $property = $this->unit->property;
        $property->update(['house_rules' => ['No Smoking']]);
        $reservation = $this->negotiatingReservation();

        $this->actingAs($this->landlord)->patch(route('landlord.reservations.advanceAgreement', $reservation), [
            'accept_tc'             => '1',
            'agreement_terms_notes' => 'Tenant waters the plants.',
        ])->assertSessionHas('success');

        $reservation->refresh();
        $this->assertSame(['No Smoking'], $reservation->lease_snapshot['house_rules']);
        $this->assertSame('Tenant waters the plants.', $reservation->lease_snapshot['extra_terms']);

        // Editing the property afterwards must not rewrite what the tenant signs.
        $property->update(['house_rules' => ['Curfew']]);

        $this->actingAs($this->tenant)->get(route('agreements.show', $reservation))
            ->assertOk()
            ->assertSee('No Smoking')
            ->assertDontSee('Curfew')
            ->assertSee('Tenant waters the plants.');
    }

    public function test_reservation_without_a_snapshot_still_renders(): void
    {
        $reservation = $this->negotiatingReservation();
        $reservation->update(['rental_status' => 'Pending Rental Agreement']);
        $this->assertNull($reservation->fresh()->lease_snapshot);

        $this->actingAs($this->tenant)->get(route('agreements.show', $reservation))->assertOk()->assertSee('Lease Agreement');
        $this->actingAs($this->landlord)->get(route('landlord.leases.show', $reservation))->assertOk();
    }

    public function test_walk_in_with_signed_lease(): void
    {
        $this->walkIn([
            'lease_mode' => 'now',
            'lease_file' => UploadedFile::fake()->create('lease.pdf', 200, 'application/pdf'),
        ])->assertRedirect();

        $reservation = $this->latestWalkIn();
        // walkIn() uses a future move-in date, so the unit is reserved, not occupied yet.
        $this->assertSame('Reserved', $reservation->rental_status);
        $this->assertNotNull($reservation->lease_snapshot);
        $this->assertSame('lease.pdf', $reservation->lease_file_name);
        $this->assertSame('uploaded', $reservation->leaseStatus());
        Storage::disk('local')->assertExists($reservation->lease_file_path);

        $this->actingAs($this->landlord)->get(route('landlord.leases.file', $reservation))->assertOk();
    }

    public function test_walk_in_upload_later_then_upload_and_replace(): void
    {
        $this->walkIn()->assertRedirect();
        $reservation = $this->latestWalkIn();
        $this->assertSame('missing', $reservation->leaseStatus());

        $this->actingAs($this->landlord)->get(route('landlord.tenancies.show', $reservation))->assertOk()->assertSee('Lease missing');
        $this->actingAs($this->landlord)->get(route('landlord.leases.show', $reservation))->assertOk()->assertSee('signature over printed name');

        $this->actingAs($this->landlord)->post(route('landlord.leases.upload', $reservation), [
            'lease_file' => UploadedFile::fake()->create('signed.jpg', 120, 'image/jpeg'),
        ])->assertSessionHas('success');
        $first = $reservation->fresh()->lease_file_path;
        Storage::disk('local')->assertExists($first);

        $this->actingAs($this->landlord)->post(route('landlord.leases.upload', $reservation), [
            'lease_file' => UploadedFile::fake()->create('signed-v2.pdf', 100, 'application/pdf'),
        ])->assertSessionHas('success');

        $reservation->refresh();
        Storage::disk('local')->assertMissing($first);
        Storage::disk('local')->assertExists($reservation->lease_file_path);
        $this->assertSame('signed-v2.pdf', $reservation->lease_file_name);
    }

    public function test_upload_now_requires_a_file(): void
    {
        $this->walkIn(['lease_mode' => 'now'])->assertSessionHasErrors('lease_file');
    }

    public function test_bad_lease_files_are_refused(): void
    {
        $this->walkIn()->assertRedirect();
        $reservation = $this->latestWalkIn();

        $this->actingAs($this->landlord)->post(route('landlord.leases.upload', $reservation), [
            'lease_file' => UploadedFile::fake()->create('lease.docx', 50),
        ])->assertSessionHasErrors('lease_file');

        $this->actingAs($this->landlord)->post(route('landlord.leases.upload', $reservation), [
            'lease_file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'),
        ])->assertSessionHasErrors('lease_file');
    }

    public function test_other_landlords_and_tenants_cannot_reach_the_lease(): void
    {
        $this->walkIn([
            'lease_mode' => 'now',
            'lease_file' => UploadedFile::fake()->create('lease.pdf', 50, 'application/pdf'),
        ]);
        $reservation = $this->latestWalkIn();

        $other = $this->makeLandlord();

        $this->actingAs($other)->get(route('landlord.leases.show', $reservation))->assertForbidden();
        $this->actingAs($other)->get(route('landlord.leases.file', $reservation))->assertForbidden();
        $this->actingAs($other)->post(route('landlord.leases.upload', $reservation), [
            'lease_file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        // Landlord routes are behind the landlord middleware.
        $this->actingAs($this->tenant)->get(route('landlord.leases.file', $reservation))->assertStatus(403);
    }
}
