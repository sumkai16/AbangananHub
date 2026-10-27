<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The D4 fixes: returning tenants, the conversation unique key, who a landlord may start a thread with, and
 * the signing hint for the mobile app.
 */
class ApiConsistencyTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);
    }

    public function test_a_returning_tenant_can_inquire_again_after_cancelling_via_the_api(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $property = $this->makeProperty($landlord);
        $unit = $this->makeUnit($property);
        $old = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $property->property_id, 'status' => 'Cancelled',
        ]);

        Sanctum::actingAs($tenant);
        $this->postJson('/api/v1/tenant/reservations', ['unit_id' => $unit->unit_id])->assertCreated();

        $this->assertSame('Open', $old->fresh()->status);
        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
        $this->assertSame($old->conversation_id, Reservation::where('tenant_id', $tenant->user_id)->sole()->conversation_id);
    }

    public function test_starting_a_chat_about_a_different_unit_reuses_the_thread_on_web_and_api(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $property = $this->makeProperty($landlord);
        $a = $this->makeUnit($property);
        $b = $this->makeUnit($property, ['unit_label' => 'Unit 2']);
        $existing = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $property->property_id, 'unit_id' => $a->unit_id,
        ]);

        $this->actingAs($tenant)
            ->post(route('conversations.store'), ['property_id' => $property->property_id, 'unit_id' => $b->unit_id])
            ->assertRedirect(route('conversations.show', $existing));

        Sanctum::actingAs($tenant);
        $this->postJson('/api/v1/conversations', ['property_id' => $property->property_id, 'unit_id' => $b->unit_id])
            ->assertOk()
            ->assertJsonPath('data.conversation_id', $existing->conversation_id);

        $this->assertSame(1, Conversation::where('tenant_id', $tenant->user_id)->where('property_id', $property->property_id)->count());
    }

    public function test_a_landlord_can_only_start_a_thread_with_a_tenant_they_already_deal_with(): void
    {
        $t = $this->makeTenancy();
        $stranger = $this->makeTenant();

        Sanctum::actingAs($t['landlord']);

        $this->postJson('/api/v1/conversations', [
            'property_id' => $t['reservation']->property_id, 'tenant_id' => $stranger->user_id,
        ])->assertForbidden();

        $this->postJson('/api/v1/conversations', [
            'property_id' => $t['reservation']->property_id, 'tenant_id' => $t['tenant']->user_id,
        ])->assertSuccessful();
    }

    public function test_signing_without_tenant_details_says_so(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Pending Rental Agreement']);

        Sanctum::actingAs($t['tenant']);

        $this->postJson("/api/v1/tenant/reservations/{$t['reservation']->reservation_id}/agreement/sign", [])
            ->assertStatus(422)
            ->assertJsonPath('needs_tenant_details', true)
            ->assertJsonValidationErrors(['agree', 'accept_tc']);
    }
}
