<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** Conversations are private to admins until a participant asks for a review (plans/conversation-scam-detection.md, B). */
class ConversationAdminReviewTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private User $tenant;
    private User $landlord;
    private User $admin;
    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->makeTenant();
        $this->landlord = $this->makeLandlord();
        $this->admin = $this->makeUserWithRole('Admin');
        $property = $this->makeProperty($this->landlord);
        $this->conversation = Conversation::create([
            'tenant_id' => $this->tenant->user_id,
            'landlord_id' => $this->landlord->user_id,
            'property_id' => $property->property_id,
            'type' => Conversation::TYPE_INQUIRY,
        ]);
        $this->conversation->messages()->create(['sender_id' => $this->tenant->user_id, 'message' => 'Secret hello about the rent', 'kind' => 'user']);
    }

    public function test_an_admin_cannot_open_a_thread_nobody_asked_about(): void
    {
        $this->actingAs($this->admin)->get(route('admin.conversations.show', $this->conversation))
            ->assertRedirect(route('admin.conversations.index'))
            ->assertSessionHas('error');
    }

    public function test_the_admin_list_shows_no_message_text_and_no_link_for_a_private_thread(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.conversations.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Secret hello about the rent', $html);
        $this->assertStringNotContainsString(route('admin.conversations.show', $this->conversation), $html);
        $this->assertStringContainsString('Private', $html);
    }

    public function test_a_participant_can_ask_and_then_the_admin_can_read(): void
    {
        $this->actingAs($this->tenant)->post(route('conversations.adminReview.request', $this->conversation))
            ->assertRedirect();

        $this->assertTrue($this->conversation->fresh()->adminMayRead());
        $this->assertSame($this->tenant->user_id, (int) $this->conversation->fresh()->admin_review_requested_by);

        $this->actingAs($this->admin)->get(route('admin.conversations.show', $this->conversation))
            ->assertOk()
            ->assertSee('Secret hello about the rent')
            ->assertSee('the tenant');

        $html = $this->actingAs($this->admin)->get(route('admin.conversations.index'))->getContent();
        $this->assertStringContainsString('Review requested', $html);
        $this->assertStringContainsString(route('admin.conversations.show', $this->conversation), $html);
    }

    public function test_the_request_is_audited_and_admins_are_told(): void
    {
        $this->actingAs($this->landlord)->post(route('conversations.adminReview.request', $this->conversation));

        $row = AuditLog::where('action', 'conversation.review_requested')->sole();
        $this->assertSame($this->landlord->user_id, $row->actor_id);
        $this->assertSame($this->conversation->conversation_id, (int) $row->auditable_id);
        $this->assertStringNotContainsString('Secret hello', json_encode($row->getAttributes()));

        $this->assertSame(1, Notification::where('user_id', $this->admin->user_id)->where('type', 'conversation_review')->count());
    }

    public function test_someone_outside_the_thread_cannot_ask(): void
    {
        $stranger = $this->makeTenant();

        $this->actingAs($stranger)->post(route('conversations.adminReview.request', $this->conversation))->assertForbidden();

        $this->assertFalse($this->conversation->fresh()->adminMayRead());
    }

    public function test_an_admin_cannot_open_a_thread_by_asking_themselves(): void
    {
        $this->actingAs($this->admin)->post(route('conversations.adminReview.request', $this->conversation))->assertForbidden();

        $this->assertFalse($this->conversation->fresh()->adminMayRead());
    }

    public function test_finishing_the_review_closes_the_thread_again_and_is_audited(): void
    {
        $this->conversation->requestAdminReview($this->tenant);

        $this->actingAs($this->admin)->post(route('admin.conversations.endReview', $this->conversation))
            ->assertRedirect(route('admin.conversations.index'));

        $this->assertFalse($this->conversation->fresh()->adminMayRead());
        $this->assertSame(1, AuditLog::where('action', 'conversation.review_ended')->count());

        $this->actingAs($this->admin)->get(route('admin.conversations.show', $this->conversation))
            ->assertRedirect(route('admin.conversations.index'));
    }

    public function test_asking_again_after_a_finished_review_reopens_it(): void
    {
        $this->conversation->requestAdminReview($this->tenant);
        $this->conversation->endAdminReview();

        $this->actingAs($this->landlord)->post(route('conversations.adminReview.request', $this->conversation));

        $this->assertTrue($this->conversation->fresh()->adminMayRead());
    }

    public function test_participants_see_the_notice_while_an_admin_can_read(): void
    {
        $this->actingAs($this->tenant)->get(route('conversations.show', $this->conversation), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('Ask an admin to review')->assertDontSee('administrator can read this conversation');

        $this->conversation->requestAdminReview($this->tenant);

        $this->actingAs($this->landlord)->get(route('conversations.show', $this->conversation), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('administrator can read this conversation')->assertDontSee('Ask an admin to review');
    }

    public function test_tenancy_threads_get_the_request_button_and_the_notice_too(): void
    {
        $thread = Conversation::create([
            'tenant_id' => $this->tenant->user_id,
            'landlord_id' => $this->landlord->user_id,
            'property_id' => $this->conversation->property_id,
            'type' => Conversation::TYPE_TENANCY,
        ]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($this->tenant)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()->assertSee('Ask an admin to review');

        $thread->requestAdminReview($this->tenant);

        $this->actingAs($this->landlord)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()->assertSee('administrator can read this conversation');
    }

    public function test_only_a_non_admin_participant_can_finish_nothing(): void
    {
        $this->conversation->requestAdminReview($this->tenant);

        $this->actingAs($this->tenant)->post(route('admin.conversations.endReview', $this->conversation))->assertForbidden();

        $this->assertTrue($this->conversation->fresh()->adminMayRead());
    }
}
