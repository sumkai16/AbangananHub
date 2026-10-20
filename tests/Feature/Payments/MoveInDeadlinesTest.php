<?php

namespace Tests\Feature\Payments;

use App\Console\Commands\ProcessMoveInDeadlines;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Console\OutputStyle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use ReflectionMethod;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Support\CreatesMarketplaceFixtures;
use Tests\Support\CreatesPaymentFixtures;
use Tests\TestCase;

/**
 * The nightly escrow clock (`reservations:process-move-in-deadlines`). It is the
 * only place in the app where money moves with nobody clicking: a Held deposit
 * is released to the landlord when the tenant's confirmation window runs out.
 * These tests pin both what each of its four phases does and, just as
 * important, what it must leave alone.
 *
 *   1. backfill  - derive Clock 1 (keys-turnover deadline) once a deposit is Held
 *   2. reminders - Clock 2 nudges at 4 / 1 / 0 days left, at most once a day
 *   3. escalate  - Clock 1 ran out: dispute-queue it for an admin, never refund
 *   4. release   - Clock 2 ran out: Held -> Released, unit Occupied, payout owed
 *
 * Time is frozen at 09:00 on 14 Oct 2026 and the rentals config is set
 * explicitly, so none of this depends on config defaults or the wall clock.
 * The command is global (it scans every reservation), so every assertion looks
 * only at the rows its own test created.
 */
class MoveInDeadlinesTest extends TestCase
{
    use CreatesMarketplaceFixtures, CreatesPaymentFixtures, DatabaseTransactions;

    private const COMMAND = 'reservations:process-move-in-deadlines';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default'                => 'null',
            'rentals.move_in_confirmation_days'   => 7,
            'rentals.turnover_grace_days'         => 7,
            'rentals.turnover_grace_days_no_date' => 14,
            'rentals.reminder_days_remaining'     => [4, 1, 0],
        ]);

        $this->travelTo(Carbon::parse('2026-10-14 09:00:00'));
    }

    // ─── Phase 1: backfill Clock 1 ──────────────────────────────

    public function test_a_held_deposit_gets_its_turnover_deadline_from_the_move_in_date(): void
    {
        $e = $this->makeEscrow(
            ['target_move_in_date' => '2026-10-20'],
            ['paid_at' => '2026-10-10 08:00:00'],
        );

        $this->runCommand();

        // Move-in date is later than the payment, so the grace runs from it: 20 Oct + 7 days.
        $this->assertSame('2026-10-27', $e['reservation']->fresh()->move_in_deadline_at->toDateString());
    }

    public function test_the_turnover_deadline_runs_from_the_payment_date_when_move_in_was_already_missed(): void
    {
        $e = $this->makeEscrow(
            ['target_move_in_date' => '2026-10-01'],
            ['paid_at' => '2026-10-12 08:00:00'],
        );

        $this->runCommand();

        // Deposit paid after the target date slipped: 12 Oct + 7 days, not 1 Oct + 7 (already past).
        $reservation = $e['reservation']->fresh();
        $this->assertSame('2026-10-19', $reservation->move_in_deadline_at->toDateString());
        $this->assertNull($reservation->move_in_disputed_at, 'A fresh deadline must not escalate on its first night.');
    }

    public function test_the_turnover_deadline_falls_back_to_a_longer_window_when_there_is_no_move_in_date(): void
    {
        $e = $this->makeEscrow(
            ['target_move_in_date' => null],
            ['paid_at' => '2026-10-12 08:00:00'],
        );

        $this->runCommand();

        $this->assertSame('2026-10-26', $e['reservation']->fresh()->move_in_deadline_at->toDateString());
    }

    public function test_backfill_leaves_reservations_that_are_not_waiting_on_a_turnover_alone(): void
    {
        $wrongStatus = $this->makeEscrow(['rental_status' => 'Pending Rental Agreement', 'target_move_in_date' => '2026-10-20']);
        $notHeld = $this->makeEscrow(['target_move_in_date' => '2026-10-20'], ['status' => 'Pending', 'paid_at' => null]);
        $keysGiven = $this->makeEscrow(['target_move_in_date' => '2026-10-20', 'keys_turned_over_at' => '2026-10-13 10:00:00']);
        $disputed = $this->makeEscrow(['target_move_in_date' => '2026-10-20', 'move_in_disputed_at' => '2026-10-13 10:00:00', 'move_in_dispute_reason' => 'No keys']);

        $this->runCommand();

        $this->assertNull($wrongStatus['reservation']->fresh()->move_in_deadline_at, 'Not yet signed: no clock.');
        $this->assertNull($notHeld['reservation']->fresh()->move_in_deadline_at, 'No money held: no clock.');
        $this->assertNull($keysGiven['reservation']->fresh()->move_in_deadline_at, 'Keys already handed over: Clock 1 is finished.');
        $this->assertNull($disputed['reservation']->fresh()->move_in_deadline_at, 'A dispute freezes the clock.');
    }

    public function test_backfill_never_overwrites_a_deadline_that_is_already_set(): void
    {
        $e = $this->makeEscrow(
            ['target_move_in_date' => '2026-10-20', 'move_in_deadline_at' => '2026-12-01 12:00:00'],
            ['paid_at' => '2026-10-10 08:00:00'],
        );

        $this->runCommand();

        $this->assertSame('2026-12-01', $e['reservation']->fresh()->move_in_deadline_at->toDateString(), 'A rescheduled handover slot must survive the nightly run.');
    }

    // ─── Phase 2: Clock 2 reminders ─────────────────────────────

    public function test_the_tenant_is_reminded_when_four_one_or_zero_days_are_left(): void
    {
        $four = $this->makeAwaitingConfirmation(4);
        $one = $this->makeAwaitingConfirmation(1);
        $zero = $this->makeAwaitingConfirmation(0);

        $this->runCommand();

        foreach ([$four, $one, $zero] as $e) {
            $this->assertSame(1, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));
            $this->assertSame('2026-10-14', $e['reservation']->fresh()->move_in_last_reminder_on->toDateString());
            $this->assertSame(0, $this->countNotifications($e['landlord'], 'move_in_confirmation_reminder'), 'Only the tenant is nudged.');
        }

        $this->assertStringContainsString('last day', $this->reminderMessage($zero['tenant']));
        $this->assertStringContainsString('1 day(s) left', $this->reminderMessage($one['tenant']));
    }

    public function test_no_reminder_goes_out_before_the_reminder_window(): void
    {
        $five = $this->makeAwaitingConfirmation(5);
        $three = $this->makeAwaitingConfirmation(3);
        $two = $this->makeAwaitingConfirmation(2);

        $this->runCommand();

        foreach ([$five, $three, $two] as $e) {
            $this->assertSame(0, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));
            $this->assertNull($e['reservation']->fresh()->move_in_last_reminder_on);
        }
    }

    public function test_reminders_skip_reservations_that_are_disputed_unconfirmed_or_not_signed(): void
    {
        $disputed = $this->makeAwaitingConfirmation(1, ['move_in_disputed_at' => '2026-10-13 10:00:00', 'move_in_dispute_reason' => 'No keys']);
        $noKeys = $this->makeAwaitingConfirmation(1, ['keys_turned_over_at' => null]);
        $occupied = $this->makeAwaitingConfirmation(1, ['rental_status' => 'Occupied']);

        $this->runCommand();

        foreach ([$disputed, $noKeys, $occupied] as $e) {
            $this->assertSame(0, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));
        }
    }

    public function test_running_the_command_twice_in_one_day_reminds_once(): void
    {
        $e = $this->makeAwaitingConfirmation(1);

        $this->runCommand();
        $this->runCommand();

        $this->assertSame(1, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));
    }

    public function test_a_reminder_sent_on_an_earlier_day_does_not_block_the_next_threshold(): void
    {
        // Deadline 18 Oct: 4 days left on the 14th, 3 on the 15th (silent), 1 on the 17th.
        $e = $this->makeAwaitingConfirmation(4);

        $this->runCommand();
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));

        $this->travelTo(Carbon::parse('2026-10-15 09:00:00'));
        $this->runCommand();
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'), '3 days left is not a threshold.');

        $this->travelTo(Carbon::parse('2026-10-17 09:00:00'));
        $this->runCommand();
        $this->assertSame(2, $this->countNotifications($e['tenant'], 'move_in_confirmation_reminder'));
        $this->assertSame('2026-10-17', $e['reservation']->fresh()->move_in_last_reminder_on->toDateString());
    }

    public function test_the_last_day_reminder_does_not_release_the_deposit_that_same_night(): void
    {
        // The reminder promises the whole of today; the nightly run at 23:00 must honour that.
        $e = $this->makeAwaitingConfirmation(0);
        $this->travelTo(Carbon::parse('2026-10-14 23:00:00'));

        $this->runCommand();

        $this->assertSame('Held', $e['payment']->fresh()->status);
        $this->assertSame('Rental Agreement Signed', $e['reservation']->fresh()->rental_status);
    }

    // ─── Phase 3: Clock 1 expiry -> admin review ────────────────

    public function test_an_overdue_turnover_is_escalated_to_admin_review_without_moving_money(): void
    {
        $e = $this->makeEscrow(
            ['move_in_deadline_at' => '2026-10-13 12:00:00'],
            ['paid_at' => '2026-10-01 08:00:00'],
        );

        $this->runCommand();

        $reservation = $e['reservation']->fresh();
        $this->assertNotNull($reservation->move_in_disputed_at);
        $this->assertSame('Landlord did not turn over the keys by the deadline.', $reservation->move_in_dispute_reason);
        $this->assertNull($reservation->move_in_deadline_at, 'A dispute freezes the clock.');
        $this->assertSame('Rental Agreement Signed', $reservation->rental_status, 'Escalation is not a refund or a release.');

        $payment = $e['payment']->fresh();
        $this->assertSame('Held', $payment->status, 'The deposit stays in escrow for an admin to decide.');
        $this->assertNull($payment->released_at);

        $this->assertSame(1, $this->countSystemMessages($e['conversation']));
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'turnover_overdue'));
        $this->assertSame(1, $this->countNotifications($e['landlord'], 'turnover_overdue'));
    }

    public function test_a_deadline_that_is_exactly_now_counts_as_overdue(): void
    {
        $e = $this->makeEscrow(['move_in_deadline_at' => now()]);

        $this->runCommand();

        $this->assertNotNull($e['reservation']->fresh()->move_in_disputed_at);
    }

    public function test_a_backfilled_deadline_that_is_already_past_is_escalated_in_the_same_run(): void
    {
        $e = $this->makeEscrow(
            ['target_move_in_date' => '2026-09-01'],
            ['paid_at' => '2026-09-01 08:00:00'],
        );

        $this->runCommand();

        $reservation = $e['reservation']->fresh();
        $this->assertNotNull($reservation->move_in_disputed_at);
        $this->assertNull($reservation->move_in_deadline_at);
        $this->assertSame('Held', $e['payment']->fresh()->status);
    }

    public function test_escalation_leaves_alone_reservations_that_are_not_overdue_or_not_eligible(): void
    {
        $future = $this->makeEscrow(['move_in_deadline_at' => '2026-10-15 12:00:00']);
        // Same-day deadline already passed an hour ago, but keys changed hands: Clock 2 owns it, not Clock 1.
        $keysGiven = $this->makeEscrow(['move_in_deadline_at' => '2026-10-14 08:00:00', 'keys_turned_over_at' => '2026-10-10 10:00:00']);
        $notHeld = $this->makeEscrow(['move_in_deadline_at' => '2026-10-13 12:00:00'], ['status' => 'Paid']);
        $wrongStatus = $this->makeEscrow(['rental_status' => 'Occupied', 'move_in_deadline_at' => '2026-10-13 12:00:00']);

        $this->runCommand();

        foreach ([$future, $keysGiven, $notHeld, $wrongStatus] as $e) {
            $this->assertNull($e['reservation']->fresh()->move_in_disputed_at);
            $this->assertSame(0, $this->countNotifications($e['tenant'], 'turnover_overdue'));
            $this->assertSame(0, $this->countSystemMessages($e['conversation']));
        }
    }

    public function test_an_already_disputed_reservation_is_not_escalated_again(): void
    {
        $e = $this->makeEscrow([
            'move_in_deadline_at'    => '2026-10-13 12:00:00',
            'move_in_disputed_at'    => '2026-10-12 10:00:00',
            'move_in_dispute_reason' => 'Tenant says the keys never came.',
        ]);

        $this->runCommand();

        $reservation = $e['reservation']->fresh();
        $this->assertSame('2026-10-12', $reservation->move_in_disputed_at->toDateString());
        $this->assertSame('Tenant says the keys never came.', $reservation->move_in_dispute_reason, 'The tenant\'s own words must not be overwritten.');
        $this->assertSame(0, $this->countNotifications($e['tenant'], 'turnover_overdue'));
        $this->assertSame(0, $this->countSystemMessages($e['conversation']));
    }

    public function test_running_the_command_twice_escalates_once(): void
    {
        $e = $this->makeEscrow(['move_in_deadline_at' => '2026-10-13 12:00:00']);

        $this->runCommand();
        $this->runCommand();

        $this->assertSame(1, $this->countSystemMessages($e['conversation']));
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'turnover_overdue'));
        $this->assertSame(1, $this->countNotifications($e['landlord'], 'turnover_overdue'));
    }

    // ─── Phase 4: Clock 2 expiry -> release ─────────────────────

    public function test_an_expired_confirmation_window_releases_the_deposit_to_the_landlord(): void
    {
        $e = $this->makeAwaitingConfirmation(-1);
        $rent = $this->makePayment($e['reservation'], ['billing_period' => '2026-10-01', 'status' => 'Paid']);

        $this->runCommand();

        $payment = $e['payment']->fresh();
        $this->assertSame('Released', $payment->status);
        $this->assertSame('auto_expiry', $payment->release_reason);
        $this->assertSame('Pending Payout', $payment->payout_status);
        $this->assertNull($payment->released_by, 'No human released this.');
        $this->assertNotNull($payment->released_at);

        $reservation = $e['reservation']->fresh();
        $this->assertSame('Occupied', $reservation->rental_status);
        $this->assertNull($reservation->move_in_deadline_at);
        $this->assertNull($reservation->tenant_confirmed_move_in_at, 'A timeout is not a tenant confirmation.');
        $this->assertSame('Occupied', $e['unit']->fresh()->availability_status);

        $this->assertSame('Paid', $rent->fresh()->status, 'Only the Held deposit moves.');

        $this->assertSame(1, $this->countSystemMessages($e['conversation']));
        $this->assertSame(1, $this->countNotifications($e['landlord'], 'payment_released'));
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'payment_released'));
    }

    public function test_a_release_still_works_for_a_reservation_with_no_conversation(): void
    {
        $e = $this->makeAwaitingConfirmation(-1, [], withConversation: false);

        $this->runCommand();

        $this->assertSame('Released', $e['payment']->fresh()->status);
        $this->assertSame('Occupied', $e['reservation']->fresh()->rental_status);
    }

    public function test_the_window_closes_at_midnight_not_at_the_deadline_instant(): void
    {
        $justPassed = $this->makeAwaitingConfirmation(-1, ['move_in_deadline_at' => '2026-10-13 23:59:59']);
        $startOfToday = $this->makeAwaitingConfirmation(0, ['move_in_deadline_at' => '2026-10-14 00:00:00']);
        $earlierToday = $this->makeAwaitingConfirmation(0, ['move_in_deadline_at' => '2026-10-14 08:00:00']);
        $later = $this->makeAwaitingConfirmation(3);

        $this->runCommand();

        $this->assertSame('Released', $justPassed['payment']->fresh()->status, 'Yesterday\'s deadline is over.');
        foreach ([$startOfToday, $earlierToday, $later] as $e) {
            $this->assertSame('Held', $e['payment']->fresh()->status, 'The deadline day itself still belongs to the tenant.');
            $this->assertSame('Rental Agreement Signed', $e['reservation']->fresh()->rental_status);
        }
    }

    public function test_a_disputed_reservation_is_never_auto_released(): void
    {
        $e = $this->makeAwaitingConfirmation(-3, [
            'move_in_disputed_at'    => '2026-10-10 10:00:00',
            'move_in_dispute_reason' => 'Keys never arrived.',
        ]);

        $this->runCommand();

        $this->assertSame('Held', $e['payment']->fresh()->status, 'A dispute freezes the escrow for an admin.');
        $this->assertNull($e['payment']->fresh()->release_reason);
        $this->assertSame('Rental Agreement Signed', $e['reservation']->fresh()->rental_status);
        $this->assertSame('Available', $e['unit']->fresh()->availability_status);
        $this->assertSame(0, $this->countNotifications($e['landlord'], 'payment_released'));
    }

    public function test_the_deposit_is_not_released_when_the_keys_were_never_turned_over(): void
    {
        $e = $this->makeAwaitingConfirmation(-3, ['keys_turned_over_at' => null]);

        $this->runCommand();

        // Clock 2 never started; the late deadline is Clock 1's, which escalates instead of paying out.
        $this->assertSame('Held', $e['payment']->fresh()->status);
        $this->assertNotNull($e['reservation']->fresh()->move_in_disputed_at);
        $this->assertSame('Rental Agreement Signed', $e['reservation']->fresh()->rental_status);
    }

    public function test_the_release_step_on_its_own_refuses_a_reservation_whose_keys_were_never_turned_over(): void
    {
        // Through the whole command, the escalation step runs first and disputes this
        // reservation, which would hide a missing keys check in the release step.
        // Calling the release step alone pins that check by itself.
        $e = $this->makeAwaitingConfirmation(-3, ['keys_turned_over_at' => null]);

        $command = $this->app->make(ProcessMoveInDeadlines::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput));
        (new ReflectionMethod($command, 'releaseExpiredConfirmations'))->invoke($command);

        $this->assertSame('Held', $e['payment']->fresh()->status);
        $this->assertSame('Rental Agreement Signed', $e['reservation']->fresh()->rental_status);
    }

    public function test_release_skips_reservations_with_no_held_deposit_or_the_wrong_status(): void
    {
        $alreadyPaid = $this->makeAwaitingConfirmation(-1, [], ['status' => 'Paid']);
        $cancelled = $this->makeAwaitingConfirmation(-1, ['rental_status' => 'Cancelled']);

        $this->runCommand();

        $this->assertSame('Rental Agreement Signed', $alreadyPaid['reservation']->fresh()->rental_status, 'No Held payment: nothing to release, so no status flip either.');
        $this->assertSame('Paid', $alreadyPaid['payment']->fresh()->status);
        $this->assertSame(0, $this->countNotifications($alreadyPaid['landlord'], 'payment_released'));

        $this->assertSame('Held', $cancelled['payment']->fresh()->status, 'A cancelled reservation never pays out here.');
        $this->assertSame('Cancelled', $cancelled['reservation']->fresh()->rental_status);
    }

    public function test_running_the_command_twice_releases_once(): void
    {
        $e = $this->makeAwaitingConfirmation(-1);

        $this->runCommand();
        $releasedAt = $e['payment']->fresh()->released_at;

        $this->travelTo(Carbon::parse('2026-10-14 23:00:00'));
        $this->runCommand();

        $this->assertSame(1, Payment::where('reservation_id', $e['reservation']->reservation_id)->where('status', 'Released')->count());
        $this->assertTrue($releasedAt->equalTo($e['payment']->fresh()->released_at), 'The second run must not re-stamp the release.');
        $this->assertSame(1, $this->countSystemMessages($e['conversation']));
        $this->assertSame(1, $this->countNotifications($e['landlord'], 'payment_released'));
        $this->assertSame(1, $this->countNotifications($e['tenant'], 'payment_released'));
    }

    public function test_a_failure_on_one_release_rolls_that_row_back_and_does_not_stop_the_others(): void
    {
        Log::spy();

        $bad = $this->makeAwaitingConfirmation(-1);
        $good = $this->makeAwaitingConfirmation(-1);

        $badPaymentId = $bad['payment']->payment_id;
        Payment::updating(function (Payment $payment) use ($badPaymentId) {
            if ($payment->payment_id === $badPaymentId) {
                throw new \RuntimeException('simulated failure');
            }
        });

        $this->artisan(self::COMMAND)->assertExitCode(0);

        // The failing row is rolled back as a whole: no half-released reservation, no stray message or notice.
        $this->assertSame('Held', $bad['payment']->fresh()->status);
        $this->assertSame('Rental Agreement Signed', $bad['reservation']->fresh()->rental_status);
        $this->assertSame('Available', $bad['unit']->fresh()->availability_status);
        $this->assertSame(0, $this->countSystemMessages($bad['conversation']));
        $this->assertSame(0, $this->countNotifications($bad['landlord'], 'payment_released'));
        $this->assertSame(0, $this->countNotifications($bad['tenant'], 'payment_released'));

        $this->assertSame('Released', $good['payment']->fresh()->status);
        $this->assertSame('Occupied', $good['reservation']->fresh()->rental_status);
        $this->assertSame(1, $this->countNotifications($good['landlord'], 'payment_released'));

        Log::shouldHaveReceived('error')->once();
    }

    public function test_an_auto_released_move_in_is_not_announced_as_a_tenant_confirmation(): void
    {
        $e = $this->makeAwaitingConfirmation(-1);

        $this->runCommand();

        $this->assertSame('Released', $e['payment']->fresh()->status);
        $this->assertSame(0, Notification::where('user_id', $e['landlord']->user_id)->where('title', 'like', 'Move-in confirmed%')->count());
        $this->assertSame(0, Notification::where('user_id', $e['tenant']->user_id)->where('title', 'Move-in confirmed')->count());
    }

    // ─── helpers ────────────────────────────────────────────────

    private function runCommand(): void
    {
        $this->artisan(self::COMMAND)->assertExitCode(0);
    }

    /**
     * A signed reservation whose deposit is Held, with a conversation so the
     * system messages have somewhere to go.
     *
     * @return array{landlord: User, tenant: User, reservation: Reservation, payment: Payment, unit: PropertyUnit, conversation: ?Conversation}
     */
    private function makeEscrow(array $reservation = [], array $payment = [], bool $withConversation = true): array
    {
        $t = $this->makeTenancy(array_merge(['rental_status' => 'Rental Agreement Signed'], $reservation));
        $unit = $t['reservation']->unit;

        $conversation = null;
        if ($withConversation) {
            $conversation = Conversation::create([
                'tenant_id'   => $t['tenant']->user_id,
                'landlord_id' => $t['landlord']->user_id,
                'property_id' => $t['reservation']->property_id,
                'unit_id'     => $t['reservation']->unit_id,
                'status'      => 'Open',
            ]);
            $t['reservation']->update(['conversation_id' => $conversation->conversation_id]);
        }

        $payment = $this->makePayment($t['reservation'], array_merge([
            'payment_type'   => 'Initial',
            'billing_period' => null,
            'payment_method' => 'GCash',
            'status'         => 'Held',
            'paid_at'        => '2026-10-10 08:00:00',
        ], $payment));

        return [
            'landlord'     => $t['landlord'],
            'tenant'       => $t['tenant'],
            'reservation'  => $t['reservation'],
            'payment'      => $payment,
            'unit'         => $unit,
            'conversation' => $conversation,
        ];
    }

    /**
     * Clock 2 running: keys handed over, tenant's confirmation due $daysLeft
     * calendar days from today (negative = already over) at noon.
     */
    private function makeAwaitingConfirmation(int $daysLeft, array $reservation = [], array $payment = [], bool $withConversation = true): array
    {
        return $this->makeEscrow(array_merge([
            'keys_turned_over_at' => now()->subDays(3),
            'move_in_deadline_at' => now()->startOfDay()->addDays($daysLeft)->setTime(12, 0),
        ], $reservation), $payment, $withConversation);
    }

    private function countNotifications(User $user, string $type): int
    {
        return Notification::where('user_id', $user->user_id)->where('type', $type)->count();
    }

    private function countSystemMessages(?Conversation $conversation): int
    {
        if (! $conversation) {
            return 0;
        }

        return Message::where('conversation_id', $conversation->conversation_id)->where('kind', 'system')->count();
    }

    private function reminderMessage(User $tenant): string
    {
        return (string) Notification::where('user_id', $tenant->user_id)
            ->where('type', 'move_in_confirmation_reminder')
            ->value('message');
    }
}
