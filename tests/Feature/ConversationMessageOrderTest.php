<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/**
 * Without an ORDER BY, MySQL returned a conversation's messages in index order (grouped by
 * sender), so the chat showed all of one person's lines before the other's.
 */
class ConversationMessageOrderTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    public function test_messages_load_oldest_first_regardless_of_sender_or_insert_order(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($landlord));

        $conversation = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $unit->property_id, 'unit_id' => $unit->unit_id,
        ]);

        // Inserted scrambled: the newest line first, senders interleaved.
        foreach ([['d', $landlord, 4], ['a', $tenant, 40], ['c', $tenant, 10], ['b', $landlord, 30]] as [$text, $sender, $minutesAgo]) {
            $m = new Message(['conversation_id' => $conversation->conversation_id, 'sender_id' => $sender->user_id, 'message' => $text, 'is_read' => true]);
            $m->sent_at = now()->subMinutes($minutesAgo);
            $m->save();
        }

        $this->assertSame(['a', 'b', 'c', 'd'], $conversation->fresh()->messages->pluck('message')->all());
        $this->assertSame(['a', 'b', 'c', 'd'], Conversation::with('messages')->find($conversation->conversation_id)->messages->pluck('message')->all());
    }
}
