<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

class UnitFlashTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_a_message_flashed_to_the_units_forwarding_route_survives_to_the_units_page(): void
    {
        $landlord = $this->makeLandlord();
        $property = $this->makeProperty($landlord);

        $this->actingAs($landlord)
            ->withSession(['success' => 'Unit updated.'])
            ->get(route('landlord.properties.units.index', $property))
            ->assertRedirect(route('landlord.units.index', ['property' => $property->property_id]))
            ->assertSessionHas('success', 'Unit updated.');
    }

    public function test_a_message_flashed_to_the_conversation_forwarding_route_survives_to_the_inbox(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id, 'type' => 'inquiry',
        ]);

        $this->actingAs($landlord)
            ->withSession(['status' => 'Conversation marked as resolved.'])
            ->get(route('conversations.show', $conversation))
            ->assertRedirect()
            ->assertSessionHas('status', 'Conversation marked as resolved.');
    }
}
