<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\DepositCharge;
use App\Models\Favorite;
use App\Models\LeaseTemplate;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PaymentClaim;
use App\Models\PaymentQrShare;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\TenantConcern;
use App\Models\User;
use App\Models\UserPayoutAccount;
use App\Models\ViewingRequest;
use App\Services\LandlordNavCounts;
use App\Services\LandlordRentDueCount;
use App\Services\RentLedger;
use Database\Seeders\AmenitySeeder;
use Database\Seeders\DefenseRentalsSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\LandlordSeeder;
use Database\Seeders\PropertySeeder;
use Database\Seeders\ReportSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\TenantSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The landlord and tenant side of the defense world (DefenseRentalsSeeder): every scenario in SCENARIOS exists,
 * Maria's sidebar numbers are all above zero, the presenter's rental really is two months overdue, the pages the
 * script opens render, and a second run adds nothing. Seeds the base world first, inside the test's own transaction.
 */
class DefenseRentalsSeedTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['broadcasting.default' => 'null']);

        $this->seedWorld();
        // The sidebar's Payments number is cached for ten minutes; start from a clean slate.
        LandlordRentDueCount::forget($this->maria()->user_id);
    }

    /**
     * The base world, then the defense rentals. The test database is shared with whatever else is running tests, and
     * two runs inserting the same seeded users at once deadlock in MySQL (which rolls the whole transaction back), so
     * a deadlock starts the test's transaction again and retries.
     */
    private function seedWorld(): void
    {
        $seeders = [UserSeeder::class, LandlordSeeder::class, AmenitySeeder::class, PropertySeeder::class, TenantSeeder::class, ReviewSeeder::class, ReportSeeder::class, DemoSeeder::class, DefenseRentalsSeeder::class];

        for ($attempt = 1; ; $attempt++) {
            try {
                foreach ($seeders as $seeder) {
                    $this->seed($seeder);
                }

                return;
            } catch (QueryException $e) {
                if ($attempt >= 8 || ! str_contains($e->getMessage(), 'Deadlock')) {
                    throw $e;
                }
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                usleep(random_int(300, 1500) * 1000);
                DB::beginTransaction();
            }
        }
    }

    private function maria(): User
    {
        return User::where('email', 'landlord@abangananhub.com')->firstOrFail();
    }

    private function axcee(): User
    {
        return User::where('email', 'axcee@abangananhub.com')->firstOrFail();
    }

    private function reservationOf(string $email, string $propertyTitle, ?string $status = null): Reservation
    {
        return Reservation::where('tenant_id', User::where('email', $email)->firstOrFail()->user_id)
            ->whereHas('property', fn ($q) => $q->where('title', $propertyTitle))
            ->when($status, fn ($q) => $q->where('rental_status', $status))
            ->latest('reservation_id')->firstOrFail();
    }

    public function test_every_listed_scenario_has_the_who_where_shows_shape_and_a_unique_key(): void
    {
        $keys = array_column(DefenseRentalsSeeder::SCENARIOS, 'key');
        $this->assertSame($keys, array_values(array_unique($keys)));

        foreach (DefenseRentalsSeeder::SCENARIOS as $scenario) {
            $this->assertSame(['key', 'who', 'where', 'shows'], array_keys($scenario));
            $this->assertNotEmpty(User::where('email', $scenario['who'])->first(), "{$scenario['key']}: {$scenario['who']} does not exist");
        }
    }

    public function test_every_sidebar_number_on_marias_account_is_above_zero(): void
    {
        $maria = $this->maria();
        $counts = LandlordNavCounts::for($maria->user_id);

        foreach (['reservations', 'viewings', 'concerns', 'properties', 'reviews', 'rentals'] as $key) {
            $this->assertGreaterThan(0, $counts[$key], "Maria's {$key} badge is zero");
        }
        $this->assertGreaterThan(0, LandlordRentDueCount::for($maria->user_id), "Maria's Payments badge is zero");
    }

    public function test_the_presenters_rental_is_two_months_overdue_with_a_deposit_and_a_charge(): void
    {
        $reservation = $this->reservationOf('axcee@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Occupied');
        $summary = RentLedger::for($reservation->fresh())->summary();

        $this->assertSame('overdue', $summary['paymentStatus']);
        $this->assertSame(2, $summary['overdueCount']);
        $this->assertSame(19000.0, (float) $summary['overdueAmount']);
        $this->assertSame(9500.0, (float) $summary['monthlyRent']);
        $this->assertGreaterThan(0, $summary['depositCollected']);
        $this->assertSame(1, DepositCharge::where('reservation_id', $reservation->reservation_id)->count());
        $this->assertSame(1, TenantConcern::where('reservation_id', $reservation->reservation_id)->where('status', 'Open')->count());
    }

    public function test_the_signed_agreement_waits_for_payment_with_no_qr_and_two_unread_messages(): void
    {
        $reservation = $this->reservationOf('axcee@abangananhub.com', 'Studio Apartments, Lahug');

        $this->assertSame('Rental Agreement Signed', $reservation->rental_status);
        $this->assertNull($reservation->qr_requested_at);
        $this->assertFalse($reservation->moveInSettled());
        $this->assertSame(0, PaymentQrShare::whereHas('conversation', fn ($q) => $q->where('tenant_id', $this->axcee()->user_id)->where('property_id', $reservation->property_id))->count());
        $this->assertSame(2, Message::where('conversation_id', $reservation->conversation_id)->where('sender_id', $this->maria()->user_id)->where('is_read', false)->count());
        $this->assertNotNull($reservation->paymentDeadline() ?? now(), 'the agreement has a deadline or auto-cancel is off');
    }

    public function test_the_cancelled_reservation_shows_a_refund_on_its_way(): void
    {
        $reservation = $this->reservationOf('axcee@abangananhub.com', 'Casa Verde Rooms, Mandaue');

        $this->assertSame('Cancelled', $reservation->rental_status);
        $refunded = Payment::where('reservation_id', $reservation->reservation_id)->where('status', 'Refunded')->get();
        $this->assertNotEmpty($refunded);
        $this->assertSame('pending', $refunded->first()->refund_status);

        $this->actingAs($this->axcee())->get(route('reservations.index'))->assertOk()->assertSee('refund is on its way');
        $this->assertSame(3, Favorite::where('tenant_id', $this->axcee()->user_id)->count());
    }

    public function test_the_landlord_side_scenarios_exist(): void
    {
        $maria = $this->maria();

        // A QR request open for the last ten minutes: field, chat line and notification.
        $kyle = $this->reservationOf('kyle.bautista@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion');
        $this->assertNotNull($kyle->qr_requested_at);
        $this->assertTrue($kyle->qr_requested_at->gt(now()->subHour()));
        $this->assertTrue(Message::where('conversation_id', $kyle->conversation_id)->where('kind', 'system')->where('message', 'like', '%payment QR%')->exists());
        $this->assertTrue(Notification::where('user_id', $maria->user_id)->where('title', 'like', 'Kyle asks for your payment QR')->exists());

        // A payment report waiting, and the QR Maria shared in that chat.
        $noemi = $this->reservationOf('noemi.gutierrez@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion');
        $this->assertSame(1, PaymentClaim::where('reservation_id', $noemi->reservation_id)->where('status', 'Pending')->where('payment_method', 'GCash')->count());
        $this->assertTrue(PaymentQrShare::whereHas('conversation', fn ($q) => $q->where('tenant_id', $noemi->tenant_id))->exists());

        // Ending soon, inside the renewal window; completed with a charge and a partly returned deposit.
        $liam = $this->reservationOf('liam.dizon@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Occupied');
        $this->assertTrue($liam->target_move_out_date->lte(now()->addDays((int) config('rentals.renewal_window_days', 60))));
        $ana = $this->reservationOf('ana.rivera@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Completed');
        $this->assertSame(1, DepositCharge::where('reservation_id', $ana->reservation_id)->count());
        $this->assertGreaterThan(0, (float) $ana->deposit_returned_amount);

        // A new inquiry with a pending viewing.
        $bianca = $this->reservationOf('bianca.uy@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Inquiry');
        $viewing = ViewingRequest::where('reservation_id', $bianca->reservation_id)->firstOrFail();
        $this->assertSame('Pending', $viewing->status);
        $this->assertTrue($viewing->scheduled_at->isFuture());

        // Three reviews without a reply, a rejected and a suspended listing, the two policies, the payout account.
        $this->assertGreaterThanOrEqual(3, Review::where('landlord_id', $maria->user_id)->whereNull('landlord_reply')->whereIn('tenant_id', User::whereIn('email', ['ana.rivera@abangananhub.com', 'liam.dizon@abangananhub.com', 'noemi.gutierrez@abangananhub.com'])->pluck('user_id'))->count());
        $rejected = Property::where('landlord_id', $maria->user_id)->where('title', 'Tisa Garden Rooms, Cebu City')->firstOrFail();
        $this->assertSame('Rejected', $rejected->verification_status);
        $this->assertNotEmpty($rejected->rejection_reason);
        $this->assertSame('Suspended', Property::where('landlord_id', $maria->user_id)->where('title', 'Mambaling Annex Rooms, Cebu City')->value('publication_status'));
        $apartment = Property::where('landlord_id', $maria->user_id)->where('title', '1-Bedroom Apartment in Poblacion, Consolacion')->firstOrFail();
        $unitPolicy = PropertyUnit::where('property_id', $apartment->property_id)->where('unit_label', 'Unit 3')->value('lease_template_id');
        $this->assertNotNull($apartment->lease_template_id);
        $this->assertNotNull($unitPolicy);
        $this->assertNotSame($apartment->lease_template_id, $unitPolicy);
        $this->assertSame(2, LeaseTemplate::where('landlord_id', $maria->user_id)->count());
        $this->assertNotNull(UserPayoutAccount::find($maria->user_id)?->payout_approved_at);

        // Walk-in with no account, the extra payment stories, and the clean tenant.
        $this->assertTrue(User::where('is_walk_in', true)->where('created_by_landlord_id', $maria->user_id)->exists());
        $summary = fn (string $email) => RentLedger::for($this->reservationOf($email, '1-Bedroom Apartment in Poblacion, Consolacion', 'Occupied')->fresh())->summary();
        // Half a month paid: "partial" until its due date (the 28th), "overdue" for the rest after it. Either way half is owed.
        $rico = $summary('rico.salazar@abangananhub.com');
        $this->assertContains($rico['paymentStatus'], ['partial', 'overdue']);
        $this->assertSame(4750.0, (float) $rico['outstanding']);
        $this->assertSame('paid_ahead', $summary('selena.tan@abangananhub.com')['paymentStatus']);
        $jun = $this->reservationOf('jun.pascual@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Occupied');
        $this->assertTrue(Payment::where('reservation_id', $jun->reservation_id)->where('payment_type', 'Monthly')->where('payment_method', 'Cash')->exists());
        $clean = User::where('email', 'tenant.new@abangananhub.com')->firstOrFail();
        $this->assertSame(0, Reservation::where('tenant_id', $clean->user_id)->count());
        $this->assertSame(0, Conversation::where('tenant_id', $clean->user_id)->count());
    }

    public function test_the_pages_the_script_opens_render(): void
    {
        $axcee = $this->axcee();
        $overdue = $this->reservationOf('axcee@abangananhub.com', '1-Bedroom Apartment in Poblacion, Consolacion', 'Occupied');
        $signed = $this->reservationOf('axcee@abangananhub.com', 'Studio Apartments, Lahug');

        $this->actingAs($axcee)->get(route('tenancy.show', $overdue))->assertOk()->assertSee('Overdue')->assertSee('19,000.00');
        $this->actingAs($axcee)->get(route('tenancy.index'))->assertOk();
        $this->actingAs($axcee)->get(route('agreements.show', $signed))->assertOk()->assertSee('Ask Maria to share a payment QR');
        $this->actingAs($axcee)->get(route('reservations.index'))->assertOk();
        $this->actingAs($axcee)->get(route('conversations.index'))->assertOk();

        $maria = $this->maria();
        foreach (['landlord.dashboard', 'landlord.payments.index', 'landlord.reservations.index', 'landlord.concerns.index', 'landlord.reviews.index', 'landlord.contracts.index', 'landlord.properties.index', 'landlord.leases.index'] as $route) {
            $this->actingAs($maria)->get(route($route))->assertOk();
        }
    }

    public function test_a_second_run_adds_nothing(): void
    {
        $count = fn () => [
            'users' => User::count(), 'properties' => Property::count(), 'units' => PropertyUnit::count(),
            'reservations' => Reservation::count(), 'payments' => Payment::count(), 'claims' => PaymentClaim::count(),
            'conversations' => Conversation::count(), 'messages' => Message::count(), 'notifications' => Notification::count(),
            'reviews' => Review::count(), 'charges' => DepositCharge::count(), 'concerns' => TenantConcern::count(),
            'viewings' => ViewingRequest::count(), 'favorites' => Favorite::count(), 'templates' => LeaseTemplate::count(),
            'qr' => PaymentQrShare::count(), 'payout' => UserPayoutAccount::count(),
        ];
        $before = $count();

        $this->seed(DefenseRentalsSeeder::class);

        $this->assertSame($before, $count());
    }
}
