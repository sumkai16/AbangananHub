<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Broadcasting\Broadcasters\NullBroadcaster;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * A stopped Reverb throws BroadcastException from every ShouldBroadcastNow event. The saved row is the
 * record of truth, so neither the PayMongo settle nor a sent chat message may fail because of it.
 */
class BroadcastFailureTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function breakBroadcasting(): void
    {
        app(BroadcastManager::class)->extend('failing', fn () => new class extends NullBroadcaster
        {
            public function broadcast(array $channels, $event, array $payload = [])
            {
                throw new BroadcastException('Reverb is down');
            }
        });

        config(['broadcasting.connections.failing' => ['driver' => 'failing'], 'broadcasting.default' => 'failing']);
    }

    public function test_funding_the_escrow_survives_a_dead_broadcaster_and_still_notifies(): void
    {
        $t = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $payment = $this->makePendingCheckout($t['reservation'], 'cs_test_1');

        $this->breakBroadcasting();

        $payment->update(['status' => 'Held', 'paid_at' => now()]);

        $this->assertSame('Held', $payment->fresh()->status);
        $this->assertSame(1, Notification::where('user_id', $t['tenant']->user_id)->where('title', 'Payment received')->count());
        $this->assertSame(1, Notification::where('user_id', $t['landlord']->user_id)->where('title', 'Tenant completed payment')->count());
    }

    public function test_sending_a_message_saves_it_once_and_answers_200_when_reverb_is_down(): void
    {
        $t = $this->makeTenancy();
        $conversation = Conversation::create([
            'tenant_id' => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);

        $this->breakBroadcasting();

        $this->actingAs($t['tenant'])
            ->postJson(route('messages.store', $conversation), ['message' => 'Hello there'])
            ->assertOk()
            ->assertJsonPath('message', 'Hello there');

        $this->assertSame(1, Message::where('conversation_id', $conversation->conversation_id)->where('message', 'Hello there')->count());
    }
}
