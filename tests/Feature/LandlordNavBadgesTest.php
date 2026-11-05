<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use App\Services\LandlordRentDueCount;
use App\Services\RentLedger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/** The numbers on the landlord sidebar: Payments (who owes or waits on an answer) and Messages (chats waiting on you). */
class LandlordNavBadgesTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Cache::flush();
    }

    /** A tenancy that started in August and has paid nothing, so August to October are owed. */
    private function owingTenancy(?\App\Models\User $landlord = null): array
    {
        return $this->makeTenancy([], $landlord);
    }

    private function paidUp(Reservation $reservation): void
    {
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            $this->makePayment($reservation, ['billing_period' => $month, 'amount' => 5000]);
        }
    }

    public function test_payments_count_matches_the_tenancies_the_page_marks_overdue(): void
    {
        $a = $this->owingTenancy();
        $b = $this->owingTenancy($a['landlord']);
        $this->paidUp($b['reservation']);
        $this->owingTenancy($a['landlord']);

        $owing = collect([$a, $b])->filter(fn ($t) => RentLedger::for($t['reservation']->fresh())->summary()['outstanding'] > 0)->count() + 1;

        $this->assertSame(2, $owing);
        $this->assertSame($owing, LandlordRentDueCount::for($a['landlord']->user_id));
    }

    public function test_rent_that_is_owed_but_not_due_yet_is_not_counted(): void
    {
        // Today is Oct 14 and this month's rent falls due on the 20th: the tenant owes it, but nobody has to chase it.
        $t = $this->makeTenancy(['target_move_in_date' => '2026-10-01', 'rent_due_day' => 20]);
        $summary = RentLedger::for($t['reservation']->fresh())->summary();

        $this->assertGreaterThan(0, $summary['outstanding']);
        $this->assertNotSame('overdue', $summary['paymentStatus']);
        $this->assertSame(0, LandlordRentDueCount::for($t['landlord']->user_id));

        // A report waiting for an answer still counts.
        $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-10-01', 'amount' => 5000, 'payment_method' => 'Cash',
            'paid_on' => '2026-10-10', 'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);
        $this->assertSame(1, LandlordRentDueCount::for($t['landlord']->user_id));
    }

    public function test_a_tenant_who_owes_and_has_a_report_waiting_counts_once(): void
    {
        $t = $this->owingTenancy();
        $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-08-01', 'amount' => 5000, 'payment_method' => 'Cash',
            'paid_on' => '2026-10-10', 'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);

        $this->assertSame(1, LandlordRentDueCount::for($t['landlord']->user_id));
    }

    public function test_a_report_alone_counts_for_a_tenancy_that_owes_nothing(): void
    {
        $t = $this->owingTenancy();
        $this->paidUp($t['reservation']);
        $this->assertSame(0, LandlordRentDueCount::for($t['landlord']->user_id));

        $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-11-01', 'amount' => 5000, 'payment_method' => 'Cash',
            'paid_on' => '2026-10-10', 'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);

        $this->assertSame(1, LandlordRentDueCount::for($t['landlord']->user_id));
    }

    public function test_another_landlords_tenants_are_not_counted(): void
    {
        $mine = $this->owingTenancy();
        $this->owingTenancy();

        $this->assertSame(1, LandlordRentDueCount::for($mine['landlord']->user_id));
    }

    public function test_the_number_is_cached_and_cleared_when_a_payment_is_recorded(): void
    {
        $t = $this->owingTenancy();
        $id = $t['landlord']->user_id;

        $this->assertSame(1, LandlordRentDueCount::for($id));

        // Cached: rows written around the model (no events) do not change what is shown.
        foreach (['2026-08-01', '2026-09-01', '2026-10-01'] as $month) {
            \DB::table('payments')->insert([
                'reservation_id' => $t['reservation']->reservation_id, 'payment_type' => 'Monthly', 'billing_period' => $month,
                'amount' => 5000, 'payment_method' => 'Cash', 'status' => 'Paid', 'paid_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->assertSame(1, LandlordRentDueCount::for($id));

        // A recorded payment goes through the model, so the observer clears the number.
        $this->makePayment($t['reservation'], ['billing_period' => '2026-10-01', 'amount' => 0.01]);
        $this->assertSame(0, LandlordRentDueCount::for($id));
    }

    public function test_deciding_a_report_clears_the_number(): void
    {
        $t = $this->owingTenancy();
        $this->paidUp($t['reservation']);
        $claim = $t['reservation']->paymentClaims()->create([
            'claim_type' => 'Monthly', 'billing_period' => '2026-11-01', 'amount' => 5000, 'payment_method' => 'Cash',
            'paid_on' => '2026-10-10', 'status' => 'Pending', 'filed_by' => $t['tenant']->user_id,
        ]);
        $this->assertSame(1, LandlordRentDueCount::for($t['landlord']->user_id));

        $claim->update(['status' => 'Rejected', 'rejection_reason' => 'Nothing arrived']);

        $this->assertSame(0, LandlordRentDueCount::for($t['landlord']->user_id));
    }

    public function test_the_sidebar_shows_the_payments_number(): void
    {
        $t = $this->owingTenancy();

        $html = $this->actingAs($t['landlord'])->get(route('landlord.dashboard'))->assertOk()->getContent();

        // The Payments item carries the count as its badge.
        $this->assertMatchesRegularExpression('/<span class="sr-only">1 waiting<\/span><span aria-hidden="true">1<\/span>/', $html);
    }

    private function chat($landlord, $tenant, string $status = 'Active'): Conversation
    {
        $t = $this->makeTenancy([], $landlord, $tenant);

        return Conversation::create([
            'tenant_id' => $tenant->user_id, 'landlord_id' => $landlord->user_id,
            'property_id' => $t['reservation']->property_id, 'status' => $status,
        ]);
    }

    public function test_a_chat_with_an_unread_latest_message_counts_once_however_many_messages(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $chat = $this->chat($landlord, $tenant);

        $chat->messages()->create(['sender_id' => $tenant->user_id, 'message' => 'Hi', 'is_read' => false]);
        $chat->messages()->create(['sender_id' => $tenant->user_id, 'message' => 'Still there?', 'is_read' => false]);

        $this->assertSame(1, Conversation::unreadCountFor($landlord->user_id));
    }

    public function test_closed_chats_and_my_own_messages_are_not_counted(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();

        $cancelled = $this->chat($landlord, $tenant, 'Cancelled');
        $cancelled->messages()->create(['sender_id' => $tenant->user_id, 'message' => 'Hi', 'is_read' => false]);

        $resolved = $this->chat($landlord, $this->makeTenant(), 'Resolved');
        $resolved->messages()->create(['sender_id' => $resolved->tenant_id, 'message' => 'Hi', 'is_read' => false]);

        $mine = $this->chat($landlord, $this->makeTenant());
        $mine->messages()->create(['sender_id' => $landlord->user_id, 'message' => 'Hello', 'is_read' => false]);

        $this->assertSame(0, Conversation::unreadCountFor($landlord->user_id));
    }

    public function test_every_messages_number_on_a_page_uses_the_same_rule(): void
    {
        $landlord = $this->makeLandlord();
        $tenant = $this->makeTenant();
        $open = $this->chat($landlord, $tenant);
        $open->messages()->create(['sender_id' => $tenant->user_id, 'message' => 'Hi', 'is_read' => false]);
        $open->messages()->create(['sender_id' => $tenant->user_id, 'message' => 'Hello?', 'is_read' => false]);
        $closed = $this->chat($landlord, $this->makeTenant(), 'Cancelled');
        $closed->messages()->create(['sender_id' => $closed->tenant_id, 'message' => 'Bye', 'is_read' => false]);

        $html = $this->actingAs($landlord)->get(route('landlord.dashboard'))->assertOk()->getContent();

        // The Alpine store's first value, which the sidebar, the top bar and the floating bubble all read.
        $this->assertStringContainsString('messages: 1,', $html);
        $this->actingAs($landlord)->getJson(route('conversations.unreadCount'))->assertOk()->assertExactJson(['count' => 1]);
    }
}
