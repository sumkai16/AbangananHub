<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Reservation;
use App\Models\TenantConcern;
use App\Models\UnitMedia;
use App\Services\ImageUploader;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The post-move-in chat between a current tenant and their landlord, and the
 * concern cards inside it. See plans/tenant-concerns-chat.md.
 */
class TenantConcernsTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config(['broadcasting.default' => 'null']);

        // Never reach Cloudinary from a test.
        $this->app->instance(ImageUploader::class, new class extends ImageUploader
        {
            public function upload(UploadedFile $file, string $folder, array $options = []): array
            {
                return ['url' => 'https://img.test/'.$file->getClientOriginalName(), 'public_id' => $folder.'/'.$file->getClientOriginalName()];
            }
        });
    }

    /** A real 1x1 PNG; UploadedFile::fake()->image() needs GD, which CI may not have. */
    private function photo(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    }

    private function threadOf(Reservation $reservation): Conversation
    {
        return Conversation::tenancies()->where('reservation_id', $reservation->reservation_id)->firstOrFail();
    }

    private function report($tenant, Conversation $thread, array $overrides = [])
    {
        return $this->actingAs($tenant)->post(route('concerns.store', $thread), $overrides + [
            'category' => 'Plumbing',
            'description' => 'The faucet in the bathroom is leaking.',
        ]);
    }

    public function test_becoming_occupied_opens_exactly_one_tenancy_thread(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy(['rental_status' => 'Rental Agreement Signed']);
        $this->assertSame(0, Conversation::tenancies()->where('tenant_id', $tenant->user_id)->count());

        $reservation->update(['rental_status' => 'Occupied']);
        $reservation->update(['agreed_monthly_rent' => 5200]);

        $this->assertSame(1, Conversation::tenancies()->where('tenant_id', $tenant->user_id)->count());
        $this->assertSame($reservation->reservation_id, $this->threadOf($reservation)->reservation_id);
    }

    public function test_walk_in_tenants_get_no_thread(): void
    {
        $walkIn = $this->makeTenant();
        $walkIn->forceFill(['is_walk_in' => true])->save();

        ['reservation' => $reservation] = $this->makeTenancy([], null, $walkIn);

        $this->assertSame(0, Conversation::tenancies()->where('reservation_id', $reservation->reservation_id)->count());
    }

    public function test_an_inquiry_never_attaches_to_the_tenancy_thread(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $tenancyThread = $this->threadOf($reservation);

        $this->actingAs($tenant)
            ->post(route('conversations.store'), ['property_id' => $reservation->property_id])
            ->assertRedirect();

        $inquiry = Conversation::inquiries()->where('tenant_id', $tenant->user_id)->where('property_id', $reservation->property_id)->first();
        $this->assertNotNull($inquiry);
        $this->assertNotSame($tenancyThread->conversation_id, $inquiry->conversation_id);
        $this->assertSame(2, Conversation::where('tenant_id', $tenant->user_id)->where('landlord_id', $landlord->user_id)->count());
    }

    public function test_tenant_reports_a_concern_with_photos(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);

        $this->report($tenant, $thread, [
            'photos' => [$this->photo('a.png'), $this->photo('b.png')],
        ])->assertCreated();

        $concern = TenantConcern::where('conversation_id', $thread->conversation_id)->firstOrFail();
        $this->assertSame('Open', $concern->status);
        $this->assertCount(2, $concern->photos);
        $this->assertSame('https://img.test/a.png', $concern->photos[0]['url']);

        $card = Message::where('concern_id', $concern->concern_id)->firstOrFail();
        $this->assertSame($tenant->user_id, $card->sender_id);
        $this->assertSame('The faucet in the bathroom is leaking.', $card->message);
        $this->assertSame('concern', $card->kind);

        $this->assertTrue(Notification::where('user_id', $landlord->user_id)->where('type', 'concern')->exists());
    }

    public function test_concern_input_is_validated(): void
    {
        ['tenant' => $tenant, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);

        $this->report($tenant, $thread, ['category' => 'Nonsense'])->assertSessionHasErrors('category');
        $this->report($tenant, $thread, ['description' => ''])->assertSessionHasErrors('description');
        $this->report($tenant, $thread, ['photos' => array_map(fn ($i) => $this->photo("p{$i}.png"), range(1, 4))])
            ->assertSessionHasErrors('photos');
        $this->assertSame(0, TenantConcern::where('conversation_id', $thread->conversation_id)->count());
    }

    public function test_only_the_tenant_can_report_and_only_in_a_tenancy_thread(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);

        $this->report($landlord, $thread)->assertForbidden();
        $this->report($this->makeTenant(), $thread)->assertForbidden();

        $inquiry = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $reservation->property_id, 'type' => Conversation::TYPE_INQUIRY,
        ]);
        $this->report($tenant, $inquiry)->assertForbidden();
    }

    public function test_status_moves_follow_who_may_make_them(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        $this->report($tenant, $thread)->assertCreated();
        $concern = TenantConcern::where('conversation_id', $thread->conversation_id)->firstOrFail();
        $url = route('concerns.status', $concern);

        // Tenant can't start work, a stranger can't touch it, nobody repeats the current status.
        $this->actingAs($tenant)->patch($url, ['status' => 'In Progress'])->assertForbidden();
        $this->actingAs($tenant)->patch($url, ['status' => 'Fixed'])->assertForbidden();
        $this->actingAs($this->makeLandlord())->patch($url, ['status' => 'Fixed'])->assertForbidden();
        $this->actingAs($landlord)->patch($url, ['status' => 'Open'])->assertForbidden();

        $this->actingAs($landlord)->patch($url, ['status' => 'In Progress'])->assertOk();
        $this->assertSame('In Progress', $concern->fresh()->status);

        $this->actingAs($landlord)->patch($url, ['status' => 'Fixed'])->assertOk();
        $fixed = $concern->fresh();
        $this->assertSame('Fixed', $fixed->status);
        $this->assertNotNull($fixed->fixed_at);

        // The tenant can only send a Fixed one back to Open.
        $this->actingAs($tenant)->patch($url, ['status' => 'Open'])->assertOk();
        $this->assertNull($concern->fresh()->fixed_at);

        $this->actingAs($landlord)->patch($url, ['status' => 'Bogus'])->assertSessionHasErrors('status');
    }

    public function test_a_status_change_narrates_in_the_thread_and_notifies_the_other_party(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        $this->report($tenant, $thread);
        $concern = TenantConcern::where('conversation_id', $thread->conversation_id)->firstOrFail();

        $this->actingAs($landlord)->patch(route('concerns.status', $concern), ['status' => 'Fixed'])->assertOk();

        $line = Message::where('concern_id', $concern->concern_id)->where('kind', 'system')->firstOrFail();
        $this->assertStringContainsString('marked the "Plumbing" concern as fixed', $line->message);
        $this->assertNull($line->sender_id);
        $this->assertTrue(Notification::where('user_id', $tenant->user_id)->where('title', 'Concern fixed')->exists());
    }

    public function test_ending_the_tenancy_makes_the_thread_read_only(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        $this->report($tenant, $thread);
        $concern = TenantConcern::where('conversation_id', $thread->conversation_id)->firstOrFail();

        $reservation->update(['rental_status' => 'Completed']);

        $this->assertSame('Resolved', $thread->fresh()->status);
        $this->actingAs($tenant)->postJson(route('messages.store', $thread), ['message' => 'hello?'])->assertForbidden();
        $this->report($tenant, $thread)->assertForbidden();
        $this->actingAs($landlord)->patch(route('concerns.status', $concern), ['status' => 'Fixed'])->assertForbidden();
    }

    public function test_the_chat_entry_point_lands_both_sides_in_the_thread(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);

        foreach ([$tenant, $landlord] as $user) {
            $this->actingAs($user)->get(route('tenancy.chat', $reservation))
                ->assertRedirect(route('conversations.index', ['box' => 'tenancy', 'active' => $thread->conversation_id]));
        }

        $this->actingAs($tenant)->get(route('tenancy.chat', [$reservation, 'concern' => 'new']))
            ->assertRedirect(route('conversations.index', ['box' => 'tenancy', 'active' => $thread->conversation_id, 'concern' => 'new']));

        $this->actingAs($this->makeTenant())->get(route('tenancy.chat', $reservation))->assertForbidden();
    }

    public function test_the_inbox_keeps_the_two_boxes_apart(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();

        $inquiry = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $reservation->property_id, 'type' => Conversation::TYPE_INQUIRY,
        ]);
        $tenancy = $this->threadOf($reservation);
        foreach ([$inquiry, $tenancy] as $c) {
            $m = new Message(['conversation_id' => $c->conversation_id, 'sender_id' => $landlord->user_id, 'message' => 'x', 'is_read' => false]);
            $m->save();
        }

        $inquiries = $this->actingAs($tenant)->get(route('conversations.index'))->viewData('conversations');
        $this->assertSame([$inquiry->conversation_id], $inquiries->pluck('conversation_id')->all());

        $tenancies = $this->actingAs($tenant)->get(route('conversations.index', ['box' => 'tenancy']))->viewData('conversations');
        $this->assertSame([$tenancy->conversation_id], $tenancies->pluck('conversation_id')->all());
    }

    public function test_each_side_sees_the_right_controls_in_the_tenancy_panel(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        $this->report($tenant, $thread, ['category' => 'Door & lock', 'description' => 'The lock is stuck.'])->assertCreated();
        $this->report($tenant, $thread, ['category' => 'Pests', 'description' => 'Ants in the kitchen.'])->assertCreated();
        // Plain messages from both sides: Model::preventLazyLoading only trips on 2+ models,
        // so a single-message thread would hide a missing eager load.
        foreach ([[$tenant, 'Hello po'], [$landlord, 'Hi, I will check it tomorrow']] as [$from, $text]) {
            $thread->messages()->create(['sender_id' => $from->user_id, 'message' => $text]);
        }
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($tenant)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()
            ->assertSee('Report a concern')
            ->assertSee('The lock is stuck.')
            ->assertDontSee('Mark fixed')
            ->assertDontSee('Mark as Resolved');

        $this->actingAs($landlord)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()
            ->assertSee('Mark fixed')
            ->assertSee('2</span> open concerns', false)
            ->assertDontSee('Report a concern');

        $reservation->update(['rental_status' => 'Completed']);
        $this->actingAs($tenant)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()
            ->assertSee('The chat is read-only')
            ->assertDontSee('Report a concern');
    }

    public function test_the_tenancy_chat_shows_the_stayed_property_to_both_sides_with_its_photo(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        UnitMedia::create([
            'unit_id' => $reservation->unit_id, 'media_type' => 'Image', 'media_url' => 'https://img.test/room.jpg',
        ]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($landlord)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()
            ->assertSee('Currently staying at')
            ->assertSee($reservation->property->title)
            ->assertSee($reservation->unit->unit_label)
            ->assertSee('https://img.test/room.jpg', false)
            ->assertSee('Monthly rent')
            ->assertSee('Rent status')
            ->assertSee('Nothing to fix right now.');

        $this->report($tenant, $thread, ['category' => 'Pests', 'description' => 'Ants in the kitchen.'])->assertCreated();
        $this->actingAs($landlord)->get(route('conversations.show', $thread), $ajax)
            ->assertSee('Ants in the kitchen.')
            ->assertDontSee('Nothing to fix right now.');

        $this->actingAs($tenant)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()
            ->assertSee('Currently staying at')
            ->assertDontSee('Remind about rent');

        $this->actingAs($landlord)->get(route('conversations.index', ['box' => 'tenancy']))->assertOk();
    }

    public function test_the_inquiry_panel_is_unchanged_and_the_inbox_shows_the_box_switch(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $inquiry = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $reservation->property_id, 'type' => Conversation::TYPE_INQUIRY,
        ]);

        $this->actingAs($tenant)->get(route('conversations.show', $inquiry), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertDontSee('Report a concern');

        $this->actingAs($tenant)->get(route('conversations.index', ['box' => 'tenancy']))
            ->assertOk()->assertSee('My home')->assertSee('Inquiries');
        $this->actingAs($landlord)->get(route('conversations.index'))
            ->assertOk()->assertSee('Tenants');

        // A tenant with no tenancy chat never sees the switch.
        $this->actingAs($this->makeTenant())->get(route('conversations.index'))
            ->assertOk()->assertDontSee('My home');
    }

    public function test_the_landlord_chat_can_remind_a_tenant_who_owes_rent(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $thread = $this->threadOf($reservation);
        $reservation->forceFill(['target_move_in_date' => now()->subMonths(2)->startOfMonth()])->save();
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($landlord)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()->assertSee('Remind about rent');
        $this->actingAs($tenant)->get(route('conversations.show', $thread), $ajax)
            ->assertOk()->assertDontSee('Remind about rent');

        $before = Notification::where('user_id', $tenant->user_id)->count();
        $this->actingAs($landlord)->postJson(route('landlord.tenancies.remind', $reservation))
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertGreaterThan($before, Notification::where('user_id', $tenant->user_id)->count());

        $tenant->forceFill(['is_walk_in' => true])->save();
        $this->actingAs($landlord)->postJson(route('landlord.tenancies.remind', $reservation))
            ->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_the_inquiry_chat_shows_the_property_card_to_both_sides(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $inquiry = Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $reservation->property_id, 'unit_id' => $reservation->unit_id, 'type' => Conversation::TYPE_INQUIRY,
        ]);
        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->actingAs($landlord)->get(route('conversations.show', $inquiry), $ajax)
            ->assertOk()
            ->assertSee('Inquiring about')
            ->assertSee($reservation->property->title)
            ->assertSee('Monthly rent');

        $this->actingAs($tenant)->get(route('conversations.show', $inquiry), $ajax)
            ->assertOk()
            ->assertSee('Inquiring about')
            ->assertSee($reservation->property->title);

        $this->actingAs($landlord)->get(route('conversations.index', ['active' => $inquiry->conversation_id]))->assertOk();
    }

    public function test_the_landlords_tenant_list_links_to_the_chat_with_the_open_count(): void
    {
        ['tenant' => $tenant, 'landlord' => $landlord, 'reservation' => $reservation] = $this->makeTenancy();
        $this->report($tenant, $this->threadOf($reservation))->assertCreated();

        $this->actingAs($landlord)->get(route('landlord.tenants.index'))
            ->assertOk()
            ->assertSee(route('tenancy.chat', $reservation), false)
            ->assertSee('1 open concern');
    }
}
