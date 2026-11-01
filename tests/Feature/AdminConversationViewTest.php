<?php

namespace Tests\Feature;

use App\Models\Conversation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

class AdminConversationViewTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    public function test_system_rows_render_as_dividers_and_an_empty_inquiry_summary_gets_a_label(): void
    {
        $t = $this->makeTenancy();
        $conversation = Conversation::create([
            'tenant_id'   => $t['tenant']->user_id,
            'landlord_id' => $t['landlord']->user_id,
            'property_id' => $t['reservation']->property_id,
        ]);

        $conversation->messages()->create(['sender_id' => $t['tenant']->user_id, 'message' => '', 'kind' => 'inquiry_summary']);
        $conversation->postSystemMessage('Escrow was funded for this unit.');

        $this->actingAs($this->makeUserWithRole('Admin'))
            ->get(route('admin.conversations.show', $conversation))
            ->assertOk()
            ->assertSee('Escrow was funded for this unit.')
            ->assertSee('Inquiry summary')
            ->assertDontSee('Unknown');
    }
}
