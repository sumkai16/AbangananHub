<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\UnitMedia;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A property cannot be deleted under a live tenancy or unreleased money (the FKs cascade to reservations and
 * payments), and deleting a clean one purges its files. Media rows carry no Cloudinary id here, so nothing
 * reaches the network.
 */
class PropertyDeletionTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function tenancy(string $status): array
    {
        return $this->makeTenancy(['rental_status' => $status]);
    }

    // ─── the reason ─────────────────────────────────────────────

    public function test_a_property_with_no_reservations_can_be_deleted(): void
    {
        $property = $this->makeProperty($this->makeLandlord());

        $this->assertNull($property->deletionBlockedReason());
    }

    public function test_a_live_reservation_blocks_deletion(): void
    {
        foreach (['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Reserved', 'Occupied'] as $status) {
            ['reservation' => $reservation] = $this->tenancy($status);

            $this->assertNotNull($reservation->property->deletionBlockedReason(), "{$status} should block deletion");
        }
    }

    public function test_a_held_payment_blocks_deletion_even_on_a_finished_reservation(): void
    {
        ['reservation' => $reservation] = $this->tenancy('Completed');
        $this->makePayment($reservation, ['payment_type' => 'Initial', 'billing_period' => null, 'status' => 'Held']);

        $this->assertNotNull($reservation->property->deletionBlockedReason());
    }

    public function test_a_pending_payout_blocks_deletion_but_a_paid_out_one_does_not(): void
    {
        ['reservation' => $reservation] = $this->tenancy('Completed');
        $this->makePayment($reservation, ['status' => 'Released', 'payout_status' => 'Pending Payout']);

        $this->assertNotNull($reservation->property->deletionBlockedReason());

        ['reservation' => $settled] = $this->tenancy('Completed');
        $this->makePayment($settled, ['status' => 'Released', 'payout_status' => 'Paid Out']);

        $this->assertNull($settled->property->deletionBlockedReason());
    }

    public function test_only_terminal_reservations_with_settled_money_allow_deletion(): void
    {
        ['reservation' => $reservation] = $this->tenancy('Cancelled');
        $this->makePayment($reservation, ['status' => 'Refunded']);

        $this->assertNull($reservation->property->deletionBlockedReason());
    }

    // ─── the controllers ────────────────────────────────────────

    public function test_the_web_delete_is_refused_with_the_reason_and_deletes_nothing(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancy('Occupied');

        $this->actingAs($landlord)->from('/landlord/properties')
            ->delete(route('properties.destroy', $reservation->property_id))
            ->assertRedirect('/landlord/properties')
            ->assertSessionHas('error');

        $this->assertDatabaseHas('properties', ['property_id' => $reservation->property_id]);
        $this->assertDatabaseHas('reservations', ['reservation_id' => $reservation->reservation_id]);
    }

    public function test_the_api_delete_is_refused_with_a_validation_error(): void
    {
        ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancy('Occupied');

        $this->actingAs($landlord, 'sanctum')
            ->deleteJson('/api/v1/landlord/properties/'.$reservation->property_id)
            ->assertStatus(422)
            ->assertJsonValidationErrors('property');

        $this->assertDatabaseHas('properties', ['property_id' => $reservation->property_id]);
    }

    public function test_a_clean_property_is_deleted_and_its_private_files_are_purged(): void
    {
        Storage::fake('local');

        ['landlord' => $landlord, 'reservation' => $reservation] = $this->tenancy('Completed');
        $property = $reservation->property;
        $unit = $reservation->unit;

        Storage::disk('local')->put('property-documents/deed.pdf', 'x');
        Storage::disk('local')->put('leases/lease.pdf', 'x');
        Storage::disk('local')->put('property-documents/keep.pdf', 'x');

        PropertyDocument::create([
            'property_id'   => $property->property_id,
            'document_type' => 'Proof of Ownership',
            'file_path'     => 'property-documents/deed.pdf',
            'file_name'     => 'deed.pdf',
        ]);
        $reservation->forceFill(['lease_file_path' => 'leases/lease.pdf'])->save();
        UnitMedia::create(['unit_id' => $unit->unit_id, 'media_type' => 'Image', 'media_url' => '']);

        $this->actingAs($landlord)
            ->delete(route('properties.destroy', $property))
            ->assertRedirect(route('landlord.properties.index'));

        $this->assertDatabaseMissing('properties', ['property_id' => $property->property_id]);
        Storage::disk('local')->assertMissing('property-documents/deed.pdf');
        Storage::disk('local')->assertMissing('leases/lease.pdf');
        Storage::disk('local')->assertExists('property-documents/keep.pdf');
    }

    public function test_files_are_kept_when_the_delete_is_refused(): void
    {
        Storage::fake('local');

        ['reservation' => $reservation] = $this->tenancy('Occupied');
        $property = $reservation->property;
        Storage::disk('local')->put('property-documents/deed.pdf', 'x');
        PropertyDocument::create([
            'property_id'   => $property->property_id,
            'document_type' => 'Proof of Ownership',
            'file_path'     => 'property-documents/deed.pdf',
            'file_name'     => 'deed.pdf',
        ]);

        $this->assertNotNull($property->deleteWithFiles());

        Storage::disk('local')->assertExists('property-documents/deed.pdf');
        $this->assertInstanceOf(Property::class, $property->fresh());
    }

    public function test_delete_with_files_never_aborts_on_a_missing_file(): void
    {
        Storage::fake('local');

        $property = $this->makeProperty($this->makeLandlord());
        PropertyDocument::create([
            'property_id'   => $property->property_id,
            'document_type' => 'Other',
            'file_path'     => 'property-documents/never-existed.pdf',
            'file_name'     => 'x.pdf',
        ]);

        $this->assertNull($property->deleteWithFiles());

        $this->assertNull($property->fresh());
    }
}
