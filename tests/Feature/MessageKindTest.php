<?php

namespace Tests\Feature;

use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * messages.kind replaced the is_system / is_inquiry_summary flags. The two flag names survive as
 * read-only attributes so views, the chat JS and the mobile payload keep working.
 */
class MessageKindTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private function thread(): array
    {
        $t = $this->makeTenancy();
        $conversation = Conversation::create([
            'tenant_id'   => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);
        $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);

        return [$t, $conversation];
    }

    public function test_a_plain_message_defaults_to_user(): void
    {
        [$t, $conversation] = $this->thread();

        $message = $conversation->messages()->create(['sender_id' => $t['tenant']->user_id, 'message' => 'hello'])->fresh();

        $this->assertSame('user', $message->kind);
        $this->assertFalse($message->is_system);
        $this->assertFalse($message->is_inquiry_summary);
    }

    public function test_a_reservation_system_line_is_kind_system_without_a_sender(): void
    {
        [$t] = $this->thread();

        $t['reservation']->fresh()->postSystemMessage('Keys handed over.');

        $line = Message::where('conversation_id', $t['reservation']->fresh()->conversation_id)->sole();
        $this->assertSame('system', $line->kind);
        $this->assertTrue($line->is_system);
        $this->assertNull($line->sender_id);
        $this->assertTrue($line->is_read);
    }

    public function test_a_system_line_about_a_concern_keeps_its_concern_id(): void
    {
        [, $conversation] = $this->thread();

        $conversation->postSystemMessage('Marked as fixed.', null);

        $this->assertSame('system', $conversation->messages()->sole()->kind);
    }

    public function test_the_inquiry_card_reads_as_a_summary(): void
    {
        [$t, $conversation] = $this->thread();

        $card = $conversation->messages()->create([
            'sender_id' => $t['tenant']->user_id, 'message' => '', 'kind' => 'inquiry_summary',
        ])->fresh();

        $this->assertTrue($card->is_inquiry_summary);
        $this->assertFalse($card->is_system);
    }

    public function test_the_api_payload_has_kind_concern_id_and_the_old_flags(): void
    {
        [$t, $conversation] = $this->thread();
        $message = $conversation->messages()->create([
            'sender_id' => $t['tenant']->user_id, 'message' => 'x', 'kind' => 'concern',
        ])->fresh();

        $payload = (new MessageResource($message))->resolve();

        $this->assertSame('concern', $payload['kind']);
        $this->assertArrayHasKey('concern_id', $payload);
        $this->assertFalse($payload['is_system']);
        $this->assertFalse($payload['is_inquiry_summary']);
    }

    public function test_kind_rejects_values_outside_the_enum(): void
    {
        [$t, $conversation] = $this->thread();

        $this->expectException(QueryException::class);

        $conversation->messages()->create(['sender_id' => $t['tenant']->user_id, 'message' => 'x', 'kind' => 'nonsense']);
    }
}
