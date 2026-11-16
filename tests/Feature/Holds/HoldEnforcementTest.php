<?php

namespace Tests\Feature\Holds;

use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Models\User;
use App\Services\Holds\HoldRestrictions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * A tenant on an active unpaid-rent hold cannot start anything new, can still pay, and can still talk to the landlord
 * who reported them. A hold that is only pending restricts nothing. See plans/tenant-payment-holds.md.
 */
class HoldEnforcementTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private function hold(User $tenant, ?User $landlord, string $status = TenantHold::ACTIVE): TenantHold
    {
        return TenantHold::create([
            'tenant_id'   => $tenant->user_id,
            'landlord_id' => $landlord?->user_id,
            'amount_owed' => 5000,
            'reason'      => 'Two months of rent unpaid.',
            'status'      => $status,
            'flagged_at'  => now(),
        ]);
    }

    private function chat(User $tenant, User $landlord): Conversation
    {
        $property = $this->makeProperty($landlord);

        return Conversation::create([
            'tenant_id'   => $tenant->user_id,
            'landlord_id' => $landlord->user_id,
            'property_id' => $property->property_id,
        ]);
    }

    private function newInquiry(User $landlord): array
    {
        $property = $this->makeProperty($landlord);

        return ['property_id' => $property->property_id];
    }

    public function test_a_pending_hold_restricts_nothing(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord, TenantHold::PENDING);

        $this->assertFalse($tenant->isOnHold());

        $this->actingAs($tenant)
            ->post(route('conversations.store'), $this->newInquiry($landlord))
            ->assertRedirect()
            ->assertSessionMissing('warning');

        $unit = $this->makeUnit($this->makeProperty($landlord));
        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1])
            ->assertSessionMissing('warning');
    }

    public function test_an_active_hold_blocks_new_reservations_and_new_chats_on_the_web(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord);
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1])
            ->assertRedirect('/account/on-hold')
            ->assertSessionHas('warning');

        $this->actingAs($tenant)
            ->post(route('conversations.store'), $this->newInquiry($this->makeLandlord()))
            ->assertRedirect('/account/on-hold');

        $this->assertSame(0, Reservation::where('tenant_id', $tenant->user_id)->count());
    }

    public function test_an_active_hold_blocks_a_viewing_request_but_not_the_landlords_own_viewing_call(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1]);
        $reservation = Reservation::where('tenant_id', $tenant->user_id)->firstOrFail();

        $this->hold($tenant, $landlord);

        $this->actingAs($tenant)
            ->post(route('viewings.store', $reservation), ['slot' => now()->addDays(2)->toDateTimeString()])
            ->assertRedirect('/account/on-hold');

        // The landlord posts to the same route; the hold is about the tenant, so it is not blocked here.
        $this->actingAs($landlord)
            ->post(route('viewings.store', $reservation), ['slot' => now()->addDays(2)->toDateTimeString()])
            ->assertSessionMissing('warning');
    }

    public function test_an_active_hold_returns_403_with_a_code_on_the_api(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord);
        $unit = $this->makeUnit($this->makeProperty($landlord));

        Sanctum::actingAs($tenant);

        $this->postJson('/api/v1/conversations', $this->newInquiry($this->makeLandlord()))
            ->assertForbidden()
            ->assertJsonPath('code', 'account_on_hold');

        $this->postJson('/api/v1/tenant/reservations', ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_on_hold');
    }

    public function test_a_held_tenant_can_still_reach_the_pay_rent_route(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));
        $this->actingAs($tenant)
            ->post(route('reservations.store'), ['unit_id' => $unit->unit_id, 'target_move_in_date' => now()->addWeek()->toDateString(), 'occupants_count' => 1]);
        $reservation = Reservation::where('tenant_id', $tenant->user_id)->firstOrFail();

        $this->hold($tenant, $landlord);

        // The inquiry has no rent to pay, so the controller's own policy may refuse. What matters is that the request
        // got past the hold and into the controller (PaymentController), instead of being sent to the hold page.
        try {
            $response = $this->actingAs($tenant)->post(route('payments.rentCheckout', $reservation));
            $this->assertNotSame('/account/on-hold', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->addToAssertionCount(1); // reached the controller's authorization, not stopped by the hold
        }
    }

    public function test_a_held_tenant_can_chat_with_the_flagging_landlord_only(): void
    {
        config(['broadcasting.default' => 'null']);
        $flagging = $this->makeLandlord();
        $other = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $flagging);
        $withFlagging = $this->chat($tenant, $flagging);
        $withOther = $this->chat($tenant, $other);

        $this->actingAs($tenant)->postJson(route('messages.store', $withFlagging), ['message' => 'I will pay on Friday.'])
            ->assertSuccessful();
        $this->actingAs($tenant)->postJson(route('messages.store', $withOther), ['message' => 'Hello'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_on_hold');

        $this->actingAs($tenant)->get(route('conversations.show', $withFlagging))->assertRedirect();
        $this->actingAs($tenant)->get(route('conversations.show', $withOther))->assertForbidden();

        $inbox = $this->actingAs($tenant)->get(route('conversations.index'));
        $ids = $inbox->viewData('conversations')->pluck('conversation_id')->all();
        $this->assertContains($withFlagging->conversation_id, $ids);
        $this->assertNotContains($withOther->conversation_id, $ids);
    }

    public function test_chat_rules_hold_on_the_api_too(): void
    {
        config(['broadcasting.default' => 'null']);
        $flagging = $this->makeLandlord();
        $other = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $flagging);
        $withFlagging = $this->chat($tenant, $flagging);
        $withOther = $this->chat($tenant, $other);

        Sanctum::actingAs($tenant);

        $this->postJson("/api/v1/conversations/{$withFlagging->conversation_id}/messages", ['message' => 'Paying soon.'])->assertSuccessful();
        $this->postJson("/api/v1/conversations/{$withOther->conversation_id}/messages", ['message' => 'Hi'])
            ->assertForbidden()->assertJsonPath('code', 'account_on_hold');
        $this->getJson("/api/v1/conversations/{$withOther->conversation_id}")
            ->assertForbidden()->assertJsonPath('code', 'account_on_hold');
        $this->getJson("/api/v1/conversations/{$withFlagging->conversation_id}")->assertSuccessful();

        $listed = collect($this->getJson('/api/v1/conversations')->json('data'))->pluck('conversation_id')->all();
        $this->assertContains($withFlagging->conversation_id, $listed);
        $this->assertNotContains($withOther->conversation_id, $listed);
    }

    public function test_a_pending_hold_still_lets_the_tenant_chat_with_the_flagging_landlord(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord, TenantHold::PENDING);

        $this->assertTrue(HoldRestrictions::chatAllowed($tenant, $landlord->user_id));
        // ... and, since nothing is restricted yet, so can every other chat.
        $this->assertTrue(HoldRestrictions::chatAllowed($tenant, $this->makeLandlord()->user_id));
    }

    public function test_a_hold_an_admin_made_on_a_duplicate_account_allows_no_chat(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, null);
        $conversation = $this->chat($tenant, $landlord);

        $this->assertFalse(HoldRestrictions::chatAllowed($tenant, $landlord->user_id));
        $this->actingAs($tenant)->postJson(route('messages.store', $conversation), ['message' => 'Hi'])
            ->assertForbidden()->assertJsonPath('code', 'account_on_hold');
    }

    public function test_a_released_hold_restricts_nothing(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord, TenantHold::RELEASED);

        $this->assertFalse(HoldRestrictions::isRestricted($tenant));
        $this->assertTrue(HoldRestrictions::chatAllowed($tenant, $this->makeLandlord()->user_id));
    }

    public function test_landlords_are_unaffected_and_keep_seeing_their_chats(): void
    {
        config(['broadcasting.default' => 'null']);
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $landlord);
        $conversation = $this->chat($tenant, $landlord);

        $this->assertFalse(HoldRestrictions::isRestricted($landlord));
        $this->actingAs($landlord)->postJson(route('messages.store', $conversation), ['message' => 'Please pay.'])->assertSuccessful();

        $ids = $this->actingAs($landlord)->get(route('conversations.index'))->viewData('conversations')->pluck('conversation_id')->all();
        $this->assertContains($conversation->conversation_id, $ids);
    }

    public function test_a_user_who_is_both_tenant_on_hold_and_landlord_still_chats_as_landlord(): void
    {
        config(['broadcasting.default' => 'null']);
        $dual = $this->makeLandlord();
        $flagging = $this->makeLandlord();
        $theirTenant = $this->makeTenant();
        $this->hold($dual, $flagging);

        $asLandlord = $this->chat($theirTenant, $dual);   // the dual user is the landlord here
        $asTenantElsewhere = $this->chat($dual, $this->makeLandlord());

        $this->assertTrue(HoldRestrictions::canUseConversation($dual, $asLandlord));
        $this->assertFalse(HoldRestrictions::canUseConversation($dual, $asTenantElsewhere));
    }

    public function test_the_bell_panel_and_unread_count_leave_out_chats_with_other_landlords(): void
    {
        $flagging = $this->makeLandlord();
        $other = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $flagging);
        $withFlagging = $this->chat($tenant, $flagging);
        $withOther = $this->chat($tenant, $other);
        $withFlagging->messages()->create(['sender_id' => $flagging->user_id, 'message' => 'Rent is due.', 'is_read' => false]);
        $withOther->messages()->create(['sender_id' => $other->user_id, 'message' => 'Hello there', 'is_read' => false]);

        $panel = $this->actingAs($tenant)->get(route('conversations.recentMessages'));
        $ids = $panel->viewData('conversations')->pluck('conversation_id')->all();
        $this->assertContains($withFlagging->conversation_id, $ids);
        $this->assertNotContains($withOther->conversation_id, $ids);
        $this->assertSame(1, $panel->viewData('unreadCount'));

        $this->actingAs($tenant)->getJson(route('conversations.unreadCount'))->assertJsonPath('count', 1);
    }

    public function test_a_held_tenant_cannot_post_a_concern_to_another_landlords_chat(): void
    {
        $flagging = $this->makeLandlord();
        $other = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $this->hold($tenant, $flagging);
        $withOther = $this->chat($tenant, $other);

        $this->actingAs($tenant)
            ->postJson(route('concerns.store', $withOther), ['category' => 'other', 'description' => 'The sink is leaking badly.'])
            ->assertForbidden();
    }
}
