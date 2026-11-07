<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Regression: a tenant whose earlier thread about a property was cancelled
 * could never inquire about it again. conversations is unique on
 * (tenant, landlord, property), and the inquiry skipped the cancelled row
 * and tried to insert a second one.
 *
 * Builds its own fixtures, rolled back at the end of the test.
 */
class InquiryConversationTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_inquiry_reopens_a_cancelled_conversation(): void
    {
        config(['broadcasting.default' => 'null']);

        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);
        $tenant = $this->makeTenant();

        $old = Conversation::create([
            'tenant_id'   => $tenant->user_id,
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'status'      => 'Cancelled',
        ]);

        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame('Open', $old->status);
        $this->assertSame($unit->unit_id, $old->unit_id);
        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
        $this->assertTrue(Reservation::where('conversation_id', $old->conversation_id)->where('rental_status', 'Inquiry')->exists());
    }

    /**
     * Regression: a thread closed (Resolved) when an earlier tenancy moved in stayed closed when the same tenant
     * inquired about another unit of the property, so the new deal was hidden from the landlord's Active list.
     */
    public function test_inquiry_reopens_a_conversation_resolved_by_an_earlier_move_in(): void
    {
        config(['broadcasting.default' => 'null']);

        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);
        $firstUnit = $this->makeUnit($property);
        $secondUnit = $this->makeUnit($property);
        $tenant = $this->makeTenant();

        $old = Conversation::create([
            'tenant_id'   => $tenant->user_id,
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'unit_id'     => $firstUnit->unit_id,
            'status'      => 'Resolved',
        ]);

        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $secondUnit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame('Open', $old->status);
        $this->assertSame($secondUnit->unit_id, $old->unit_id);
        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
    }
}
