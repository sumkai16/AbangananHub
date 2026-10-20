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
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id])
            ->assertSessionHasNoErrors();

        $old->refresh();
        $this->assertSame('Open', $old->status);
        $this->assertSame($unit->unit_id, $old->unit_id);
        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
        $this->assertTrue(Reservation::where('conversation_id', $old->conversation_id)->where('rental_status', 'Inquiry')->exists());
    }
}
