<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\BuildsEscrowFixtures;
use App\Http\Controllers\Admin\PaymentController;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Self-checking verification of the move-in escrow logic.
 *
 * This project has no automated test suite (see RULES.md → Testing), but the
 * nightly release is the only code in the app that moves money with nobody
 * watching, and its correctness depends on states that take a week to reach by
 * hand. This command builds each of those states by backdating, runs the real
 * command against them, and asserts the outcomes.
 *
 * It asserts on database outcomes rather than on return values, so a check
 * fails when the money ends up in the wrong place — not merely when a method
 * returns something unexpected.
 */
class EscrowVerify extends Command
{
    use BuildsEscrowFixtures;

    protected $signature = 'escrow:verify {--keep : leave the fixtures in place for inspection}';

    protected $description = 'Verify the move-in escrow deadlines end to end and print a pass/fail report';

    private array $results = [];

    private string $group = '';

    private array $snapshot = [];

    private User $landlord;

    private User $otherLandlord;

    private User $tenant;

    private User $otherTenant;

    private User $admin;

    public function handle(): int
    {
        if (! $this->guardEnvironment()) {
            return self::FAILURE;
        }

        // ShouldBroadcastNow would try to reach Reverb synchronously. A stopped
        // Reverb would then fail checks that have nothing to do with escrow.
        config(['broadcasting.default' => 'null']);

        $this->purgeFixtures();
        $this->snapshotRealRows();

        $this->landlord      = $this->fixtureUser('landlord', 'Fixture', 'Landlord', 'Landlord');
        $this->otherLandlord = $this->fixtureUser('landlord2', 'Other', 'Landlord', 'Landlord');
        $this->tenant        = $this->fixtureUser('tenant', 'Fixture', 'Tenant', 'Tenant');
        $this->otherTenant   = $this->fixtureUser('tenant2', 'Other', 'Tenant', 'Tenant');
        $this->admin         = $this->fixtureUser('admin', 'Fixture', 'Admin', 'Admin');

        try {
            $this->checkDeadlineMath();
            $this->checkClockOne();
            $this->checkReminders();
            $this->checkAutoRelease();
            $this->checkHumanActions();
            $this->checkAdminRelease();
            $this->checkAuthorization();
        } finally {
            $this->restoreRealRows();

            if (! $this->option('keep')) {
                $this->purgeFixtures();
            }
        }

        return $this->report();
    }

    // ─── Groups ──────────────────────────────────────────────

    private function checkDeadlineMath(): void
    {
        $this->group = 'DEADLINE MATH';

        $this->check('target date in future → target + 7d', function () {
            $r = $this->scenario('math-future', [
                'target_move_in_date' => today()->addDays(10),
                'paid_at'             => now(),
            ]);

            return $this->eq(
                $r->computeTurnoverDeadline()?->toDateString(),
                today()->addDays(17)->toDateString(),
                'deadline'
            );
        });

        $this->check('target date already past → paid_at + 7d', function () {
            $r = $this->scenario('math-past', [
                'target_move_in_date' => today()->subDays(10),
                'paid_at'             => now(),
            ]);

            return $this->eq(
                $r->computeTurnoverDeadline()?->toDateString(),
                today()->addDays(7)->toDateString(),
                'deadline'
            );
        });

        $this->check('no target date → paid_at + 14d', function () {
            $r = $this->scenario('math-null', [
                'target_move_in_date' => null,
                'paid_at'             => now(),
            ]);

            return $this->eq(
                $r->computeTurnoverDeadline()?->toDateString(),
                today()->addDays(14)->toDateString(),
                'deadline'
            );
        });

        $this->check('no held payment → no deadline computable', function () {
            $r = $this->scenario('math-unpaid', ['payment_status' => null]);

            return $this->eq($r->computeTurnoverDeadline(), null, 'deadline');
        });
    }

    private function checkClockOne(): void
    {
        $this->group = 'CLOCK 1 — landlord turnover';

        $this->check('backfill sets the turnover deadline', function () {
            $r = $this->scenario('c1-backfill', [
                'target_move_in_date' => today()->addDays(3),
                'paid_at'             => now(),
            ]);

            $this->runNightly();

            return $this->eq(
                $r->fresh()->move_in_deadline_at?->toDateString(),
                today()->addDays(10)->toDateString(),
                'deadline'
            );
        });

        $this->check('backfill does not rewrite an existing deadline', function () {
            $r = $this->scenario('c1-idempotent', [
                'target_move_in_date' => today()->addDays(3),
                'paid_at'             => now(),
            ]);

            $this->runNightly();
            $first = $r->fresh()->move_in_deadline_at;
            $this->runNightly();
            $second = $r->fresh()->move_in_deadline_at;

            return $this->eq($second?->toDateTimeString(), $first?->toDateTimeString(), 'deadline after 2nd run');
        });

        $this->check('overdue turnover escalates to admin review', function () {
            $r = $this->scenario('c1-escalate', [
                'paid_at'             => now()->subDays(30),
                'target_move_in_date' => today()->subDays(20),
                'move_in_deadline_at' => now()->subDay(),
            ]);

            $this->runNightly();
            $f = $r->fresh();

            return $this->all([
                $f->move_in_disputed_at !== null ? null : 'move_in_disputed_at: expected set, got null',
                str_contains((string) $f->move_in_dispute_reason, 'did not turn over')
                    ? null : 'reason: expected the system sentence, got ' . $this->fmt($f->move_in_dispute_reason),
                $this->eq($f->move_in_deadline_at, null, 'deadline cleared'),
            ]);
        });

        $this->check('escalation leaves the deposit Held', function () {
            $r = $this->scenario('c1-escalate-held', [
                'paid_at'             => now()->subDays(30),
                'move_in_deadline_at' => now()->subDay(),
            ]);

            $this->runNightly();

            return $this->eq($this->payment($r)->status, 'Held', 'payment status');
        });

        $this->check('an already-disputed row is not re-escalated', function () {
            $r = $this->scenario('c1-already-disputed', [
                'move_in_deadline_at'    => now()->subDay(),
                'move_in_disputed_at'    => now()->subDays(2),
                'move_in_dispute_reason' => 'Tenant wrote this.',
            ]);

            $this->runNightly();

            return $this->eq($r->fresh()->move_in_dispute_reason, 'Tenant wrote this.', 'reason');
        });

        $this->check('no held payment → never escalated', function () {
            $r = $this->scenario('c1-no-payment', [
                'payment_status'      => null,
                'move_in_deadline_at' => now()->subDay(),
            ]);

            $this->runNightly();

            return $this->eq($r->fresh()->move_in_disputed_at, null, 'move_in_disputed_at');
        });
    }

    private function checkReminders(): void
    {
        $this->group = 'CLOCK 2 — reminders';

        foreach ([5 => false, 4 => true, 1 => true] as $daysLeft => $shouldSend) {
            $this->check("{$daysLeft} days left → " . ($shouldSend ? 'reminder sent' : 'no reminder'), function () use ($daysLeft, $shouldSend) {
                $r = $this->scenario("c2-reminder-{$daysLeft}", [
                    'keys_turned_over_at' => now()->subDays(7 - $daysLeft),
                    'move_in_deadline_at' => now()->addDays($daysLeft),
                ]);

                $this->runNightly();

                return $this->eq($this->reminderCount($r), $shouldSend ? 1 : 0, 'reminders');
            });
        }

        $this->check('last day → reminder sent AND deposit still Held', function () {
            $r = $this->scenario('c2-reminder-0', [
                'keys_turned_over_at' => now()->subDays(7),
                'move_in_deadline_at' => today()->addHours(23),
            ]);

            $this->runNightly();

            return $this->all([
                $this->eq($this->reminderCount($r), 1, 'reminders'),
                $this->eq($this->payment($r)->status, 'Held', 'payment status'),
            ]);
        });

        $this->check('same-day re-run does not re-send', function () {
            $r = $this->scenario('c2-reminder-dupe', [
                'keys_turned_over_at' => now()->subDays(6),
                'move_in_deadline_at' => now()->addDay(),
            ]);

            $this->runNightly();
            $this->runNightly();

            return $this->eq($this->reminderCount($r), 1, 'reminders after 2 runs');
        });
    }

    private function checkAutoRelease(): void
    {
        $this->group = 'CLOCK 2 — auto-release';

        $this->check('deadline day passed → deposit released to landlord', function () {
            $r = $this->scenario('c2-release', [
                'keys_turned_over_at' => now()->subDays(8),
                'move_in_deadline_at' => today()->subDay()->addHours(9),
            ]);

            $this->runNightly();
            $f = $r->fresh();
            $p = $this->payment($r);

            return $this->all([
                $this->eq($p->status, 'Released', 'payment status'),
                $this->eq($p->release_reason, 'auto_expiry', 'release_reason'),
                $this->eq($p->released_by, null, 'released_by'),
                $this->eq($f->rental_status, 'Occupied', 'rental_status'),
                $this->eq($f->move_in_deadline_at, null, 'deadline cleared'),
                $this->eq(
                    Reservation::find($f->reservation_id)->unit->availability_status,
                    'Occupied',
                    'unit status'
                ),
            ]);
        });

        $this->check('auto-release leaves tenant_confirmed_move_in_at NULL', function () {
            $r = $this->scenario('c2-release-flag', [
                'keys_turned_over_at' => now()->subDays(8),
                'move_in_deadline_at' => today()->subDay()->addHours(9),
            ]);

            $this->runNightly();

            return $this->eq($r->fresh()->tenant_confirmed_move_in_at, null, 'tenant_confirmed_move_in_at');
        });

        $this->check('second run does not release again', function () {
            $r = $this->scenario('c2-release-once', [
                'keys_turned_over_at' => now()->subDays(8),
                'move_in_deadline_at' => today()->subDay()->addHours(9),
            ]);

            $this->runNightly();
            $first = $this->payment($r)->released_at;
            $this->runNightly();
            $second = $this->payment($r)->released_at;

            return $this->eq(
                optional($second)->toDateTimeString(),
                optional($first)->toDateTimeString(),
                'released_at after 2nd run'
            );
        });

        $this->check('disputed reservation is never released', function () {
            $r = $this->scenario('c2-release-disputed', [
                'keys_turned_over_at' => now()->subDays(8),
                'move_in_deadline_at' => today()->subDay()->addHours(9),
                'move_in_disputed_at' => now()->subDay(),
                'move_in_dispute_reason' => 'Keys never arrived.',
            ]);

            $this->runNightly();

            return $this->eq($this->payment($r)->status, 'Held', 'payment status');
        });

        $this->check('no turnover → never released by Clock 2', function () {
            $r = $this->scenario('c2-release-nokeys', [
                'move_in_deadline_at' => today()->subDay()->addHours(9),
            ]);

            $this->runNightly();

            return $this->eq($this->payment($r)->status, 'Held', 'payment status');
        });
    }

    private function checkHumanActions(): void
    {
        $this->group = 'HUMAN ACTIONS';

        $this->check('landlord marks turnover → starts the tenant clock', function () {
            $r = $this->scenario('h-turnover');

            $ok = $r->markKeysTurnedOver();
            $f  = $r->fresh();

            return $this->all([
                $this->eq($ok, true, 'return value'),
                $f->keys_turned_over_at !== null ? null : 'keys_turned_over_at: expected set, got null',
                $this->eq($f->move_in_deadline_at?->toDateString(), today()->addDays(7)->toDateString(), 'deadline'),
            ]);
        });

        $this->check('marking turnover twice is rejected', function () {
            $r = $this->scenario('h-turnover-twice');
            $r->markKeysTurnedOver();

            return $this->eq($r->fresh()->markKeysTurnedOver(), false, 'second call');
        });

        $this->check('turnover blocked while disputed', function () {
            $r = $this->scenario('h-turnover-disputed', [
                'move_in_disputed_at'    => now(),
                'move_in_dispute_reason' => 'Keys never arrived.',
            ]);

            return $this->eq($r->markKeysTurnedOver(), false, 'return value');
        });

        $this->check('tenant confirms with NO turnover ever marked → still releases', function () {
            $r = $this->scenario('h-confirm-noturnover');

            $ok = $r->confirmMoveIn();
            $p  = $this->payment($r);

            return $this->all([
                $this->eq($ok, true, 'return value'),
                $this->eq($p->status, 'Released', 'payment status'),
                $this->eq($p->release_reason, 'tenant_confirmed', 'release_reason'),
                $r->fresh()->tenant_confirmed_move_in_at !== null
                    ? null : 'tenant_confirmed_move_in_at: expected set, got null',
            ]);
        });

        $this->check('tenant confirm blocked while disputed', function () {
            $r = $this->scenario('h-confirm-disputed', [
                'move_in_disputed_at'    => now(),
                'move_in_dispute_reason' => 'Keys never arrived.',
            ]);

            return $this->all([
                $this->eq($r->confirmMoveIn(), false, 'return value'),
                $this->eq($this->payment($r)->status, 'Held', 'payment status'),
            ]);
        });

        $this->check('dispute freezes the clock', function () {
            $r = $this->scenario('h-dispute', [
                'keys_turned_over_at' => now()->subDay(),
                'move_in_deadline_at' => now()->addDays(6),
            ]);

            $ok = $r->disputeMoveIn('The landlord has not responded.');
            $f  = $r->fresh();

            return $this->all([
                $this->eq($ok, true, 'return value'),
                $this->eq($f->move_in_deadline_at, null, 'deadline cleared'),
                $this->eq($f->daysUntilMoveInDeadline(), null, 'daysUntilMoveInDeadline'),
            ]);
        });

        $this->check('disputing twice is rejected', function () {
            $r = $this->scenario('h-dispute-twice');
            $r->disputeMoveIn('First report.');

            return $this->eq($r->fresh()->disputeMoveIn('Second report.'), false, 'second call');
        });
    }

    private function checkAdminRelease(): void
    {
        $this->group = 'ADMIN RESOLUTION';

        $this->check('release on a disputed reservation clears the dispute', function () {
            $r = $this->scenario('a-disputed', [
                'move_in_disputed_at'    => now()->subDay(),
                'move_in_dispute_reason' => 'Landlord did not turn over the keys by the deadline.',
            ]);

            $err = $this->adminRelease($this->payment($r));
            $f   = $r->fresh();
            $p   = $this->payment($r);

            return $this->all([
                $this->eq($p->status, 'Released', 'payment status'),
                $this->eq($p->release_reason, 'admin_manual', 'release_reason'),
                $this->eq($f->move_in_disputed_at, null, 'move_in_disputed_at cleared'),
                $this->eq($f->rental_status, 'Occupied', 'rental_status'),
                $this->eq($f->tenant_confirmed_move_in_at, null, 'tenant_confirmed_move_in_at'),
            ], $err);
        });

        $this->check('release after turnover completes the lifecycle', function () {
            $r = $this->scenario('a-turnedover', [
                'keys_turned_over_at' => now()->subDays(2),
                'move_in_deadline_at' => now()->addDays(5),
            ]);

            $err = $this->adminRelease($this->payment($r));
            $f   = $r->fresh();

            return $this->all([
                $this->eq($this->payment($r)->status, 'Released', 'payment status'),
                $this->eq($f->rental_status, 'Occupied', 'rental_status'),
                $this->eq($f->move_in_deadline_at, null, 'deadline cleared'),
            ], $err);
        });

        $this->check('release mid-Clock-1 does NOT assert occupancy', function () {
            $r = $this->scenario('a-midclock1', [
                'move_in_deadline_at' => now()->addDays(5),
                'unit_status'         => 'Reserved',
            ]);

            $err = $this->adminRelease($this->payment($r));
            $f   = $r->fresh();

            return $this->all([
                $this->eq($this->payment($r)->status, 'Released', 'payment status'),
                $this->eq($f->rental_status, 'Rental Agreement Signed', 'rental_status'),
                $this->eq(
                    Reservation::find($f->reservation_id)->unit->availability_status,
                    'Reserved',
                    'unit status'
                ),
            ], $err);
        });

        $this->check('disputed rows appear in the admin review queue', function () {
            $r = $this->scenario('a-queue', [
                'move_in_disputed_at'    => now(),
                'move_in_dispute_reason' => 'Landlord did not turn over the keys by the deadline.',
            ]);

            $inQueue = Reservation::whereNotNull('move_in_disputed_at')
                ->where('reservation_id', $r->reservation_id)
                ->exists();

            return $this->eq($inQueue, true, 'present in queue');
        });
    }

    private function checkAuthorization(): void
    {
        $this->group = 'AUTHORIZATION';

        $this->check('another landlord cannot mark turnover', function () {
            $r = $this->scenario('z-authz-landlord');

            return $this->all([
                $this->eq(Gate::forUser($this->landlord)->allows('markTurnedOver', $r), true, 'owner allowed'),
                $this->eq(Gate::forUser($this->otherLandlord)->allows('markTurnedOver', $r), false, 'other landlord allowed'),
            ]);
        });

        $this->check('another tenant cannot act on the agreement', function () {
            $r = $this->scenario('z-authz-tenant');

            return $this->all([
                $this->eq(Gate::forUser($this->tenant)->allows('sign', $r), true, 'owner allowed'),
                $this->eq(Gate::forUser($this->otherTenant)->allows('sign', $r), false, 'other tenant allowed'),
            ]);
        });
    }

    // ─── Plumbing ────────────────────────────────────────────

    private function scenario(string $label, array $o = []): Reservation
    {
        return $this->makeScenario(array_merge([
            'label'    => $label,
            'landlord' => $this->landlord,
            'tenant'   => $this->tenant,
        ], $o));
    }

    private function payment(Reservation $r): Payment
    {
        return Payment::where('reservation_id', $r->reservation_id)->latest('payment_id')->first();
    }

    private function reminderCount(Reservation $r): int
    {
        return Notification::where('type', 'move_in_confirmation_reminder')
            ->where('link', 'like', '%/reservations/' . $r->reservation_id . '/agreement%')
            ->count();
    }

    private function runNightly(): void
    {
        Artisan::call('reservations:process-move-in-deadlines');
    }

    /**
     * The controller returns a redirect, which needs session plumbing that does
     * not exist in a console process. The database mutation happens inside the
     * transaction well before the response is built, so the assertions that
     * follow are what actually decide the check — the exception is captured
     * only so it can be reported if they fail.
     */
    private function adminRelease(Payment $payment): ?\Throwable
    {
        Auth::login($this->admin);

        try {
            app(PaymentController::class)->release($payment);

            return null;
        } catch (\Throwable $e) {
            return $e;
        } finally {
            Auth::logout();
        }
    }

    private function check(string $name, \Closure $fn): void
    {
        try {
            $problem = $fn();
        } catch (\Throwable $e) {
            $problem = get_class($e) . ': ' . $e->getMessage();
        }

        $this->results[] = [
            'group' => $this->group,
            'name'  => $name,
            'pass'  => $problem === null,
            'note'  => $problem === null ? '' : (string) $problem,
        ];
    }

    private function eq($actual, $expected, string $what): ?string
    {
        if ($actual instanceof \DateTimeInterface) {
            $actual = $actual->format('Y-m-d H:i:s');
        }

        return $actual === $expected
            ? null
            : $what . ': expected ' . $this->fmt($expected) . ', got ' . $this->fmt($actual);
    }

    private function all(array $problems, ?\Throwable $err = null): ?string
    {
        $problems = array_values(array_filter($problems));

        if ($problems === []) {
            return null;
        }

        $message = implode('; ', $problems);

        return $err ? $message . ' | threw: ' . $err->getMessage() : $message;
    }

    private function fmt($v): string
    {
        if ($v === null) {
            return 'null';
        }

        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }

        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d H:i:s');
        }

        return is_scalar($v) ? (string) $v : gettype($v);
    }

    // ─── Protecting the developer's own data ─────────────────

    /**
     * The nightly command processes every reservation in the database, not just
     * fixtures. Snapshot the escrow columns of everything that exists before
     * fixtures are built, so a verification run cannot quietly mutate the rows
     * the developer was already working with.
     */
    private function snapshotRealRows(): void
    {
        $this->snapshot['reservations'] = DB::table('reservations')->get([
            'reservation_id', 'rental_status', 'keys_turned_over_at', 'move_in_deadline_at',
            'move_in_disputed_at', 'move_in_dispute_reason', 'move_in_last_reminder_on',
            'tenant_confirmed_move_in_at',
        ]);

        $this->snapshot['payments'] = DB::table('payments')->get([
            'payment_id', 'status', 'released_at', 'released_by', 'release_reason',
        ]);

        $this->snapshot['units'] = DB::table('property_units')->get([
            'unit_id', 'availability_status',
        ]);
    }

    /**
     * Restored through the query builder deliberately: an Eloquent save would
     * fire the reservation observer and notify real users about status
     * transitions that only ever existed inside this command.
     */
    private function restoreRealRows(): void
    {
        foreach ($this->snapshot['reservations'] ?? [] as $row) {
            DB::table('reservations')->where('reservation_id', $row->reservation_id)->update([
                'rental_status'               => $row->rental_status,
                'keys_turned_over_at'         => $row->keys_turned_over_at,
                'move_in_deadline_at'         => $row->move_in_deadline_at,
                'move_in_disputed_at'         => $row->move_in_disputed_at,
                'move_in_dispute_reason'      => $row->move_in_dispute_reason,
                'move_in_last_reminder_on'    => $row->move_in_last_reminder_on,
                'tenant_confirmed_move_in_at' => $row->tenant_confirmed_move_in_at,
            ]);
        }

        foreach ($this->snapshot['payments'] ?? [] as $row) {
            DB::table('payments')->where('payment_id', $row->payment_id)->update([
                'status'         => $row->status,
                'released_at'    => $row->released_at,
                'released_by'    => $row->released_by,
                'release_reason' => $row->release_reason,
            ]);
        }

        foreach ($this->snapshot['units'] ?? [] as $row) {
            DB::table('property_units')->where('unit_id', $row->unit_id)->update([
                'availability_status' => $row->availability_status,
            ]);
        }
    }

    // ─── Reporting ───────────────────────────────────────────

    private function report(): int
    {
        $passed = 0;
        $failed = 0;
        $group  = null;

        $this->newLine();

        foreach ($this->results as $r) {
            if ($r['group'] !== $group) {
                $group = $r['group'];
                $this->newLine();
                $this->line('  <fg=cyan;options=bold>' . $group . '</>');
            }

            if ($r['pass']) {
                $passed++;
                $this->line('  <fg=green>PASS</>  ' . $r['name']);
            } else {
                $failed++;
                $this->line('  <fg=red;options=bold>FAIL</>  ' . $r['name']);
                $this->line('        <fg=red>' . $r['note'] . '</>');
            }
        }

        $this->newLine();

        $summary = $passed . ' passed, ' . $failed . ' failed';

        if ($failed === 0) {
            $this->line('  <fg=black;bg=green;options=bold> ' . $summary . ' </>');
        } else {
            $this->line('  <fg=white;bg=red;options=bold> ' . $summary . ' </>');
        }

        if ($this->option('keep')) {
            $this->newLine();
            $this->line('  Fixtures kept. Remove them with <options=bold>php artisan escrow:scenarios --clean</>');
        }

        $this->newLine();

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
