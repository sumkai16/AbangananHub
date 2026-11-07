<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\PaymentClaim;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\TestCase;

/** payment-claims:remind: a tenant's "I paid" the landlord has not answered is nudged at 1 and 3 days. */
class PaymentClaimRemindersTest extends TestCase
{
    use CreatesMarketplaceFixtures, DatabaseTransactions;

    private $landlord;

    private $tenant;

    private Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->landlord = $this->makeLandlord();
        $this->tenant = $this->makeTenant();
        $unit = $this->makeUnit($this->makeProperty($this->landlord));

        $this->reservation = Reservation::create([
            'tenant_id' => $this->tenant->user_id,
            'property_id' => $unit->property_id,
            'unit_id' => $unit->unit_id,
            'rental_status' => 'Rental Agreement Signed',
            'reservation_date' => today(),
            'target_move_in_date' => today()->addWeek(),
        ]);
    }

    private function claim(int $hoursAgo, string $status = 'Pending'): PaymentClaim
    {
        $claim = $this->reservation->paymentClaims()->create([
            'claim_type' => 'MoveIn',
            'amount' => 7000,
            'payment_method' => 'GCash',
            'reference_no' => 'REF-'.uniqid(),
            'paid_on' => today(),
            'status' => $status,
            'filed_by' => $this->tenant->user_id,
        ]);
        DB::table('payment_claims')->where('payment_claim_id', $claim->payment_claim_id)
            ->update(['created_at' => now()->subHours($hoursAgo)]);

        return $claim->fresh();
    }

    private function run_(): void
    {
        $this->artisan('payment-claims:remind')->assertSuccessful();
    }

    private function notices(int $userId, string $title): int
    {
        return Notification::where('user_id', $userId)->where('title', $title)->count();
    }

    public function test_a_fresh_report_is_left_alone(): void
    {
        $claim = $this->claim(5);

        $this->run_();

        $this->assertSame(0, $claim->fresh()->reminders_sent);
        $this->assertSame(0, $this->notices($this->landlord->user_id, 'Payment still to confirm'));
    }

    public function test_the_landlord_is_nudged_after_a_day_and_again_after_three_once_each(): void
    {
        $claim = $this->claim(26);

        $this->run_();
        $this->assertSame(1, $claim->fresh()->reminders_sent);
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Payment still to confirm'));
        $this->assertSame(0, $this->notices($this->tenant->user_id, 'Your landlord has not answered yet'));

        // Same day again: nothing more.
        $this->run_();
        $this->assertSame(1, $this->notices($this->landlord->user_id, 'Payment still to confirm'));

        DB::table('payment_claims')->where('payment_claim_id', $claim->payment_claim_id)->update(['created_at' => now()->subHours(75)]);
        $this->run_();

        $this->assertSame(2, $claim->fresh()->reminders_sent);
        $this->assertSame(2, $this->notices($this->landlord->user_id, 'Payment still to confirm'));
        // The last nudge also tells the tenant, once.
        $this->assertSame(1, $this->notices($this->tenant->user_id, 'Your landlord has not answered yet'));

        // No third nudge, however old it gets.
        DB::table('payment_claims')->where('payment_claim_id', $claim->payment_claim_id)->update(['created_at' => now()->subDays(30)]);
        $this->run_();
        $this->assertSame(2, $this->notices($this->landlord->user_id, 'Payment still to confirm'));
    }

    public function test_answered_reports_are_not_nudged(): void
    {
        foreach (['Confirmed', 'Rejected', 'Withdrawn'] as $status) {
            $this->claim(100, $status);
        }

        $this->run_();

        $this->assertSame(0, $this->notices($this->landlord->user_id, 'Payment still to confirm'));
    }
}
