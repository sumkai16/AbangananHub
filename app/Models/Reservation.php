<?php

namespace App\Models;

use App\Events\MessageSent;
use App\Support\LeaseTerms;
use App\Support\Payments;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Reservation extends Model
{
    protected $primaryKey = 'reservation_id';

    /**
     * Statuses that mean "this reservation no longer holds its unit".
     *
     * Every query asking whether a unit is spoken for filters on this, so a new
     * terminal status only has to be added here. 'Completed' joined the two
     * originals when end-of-tenancy landed — before that an Occupied
     * reservation had no exit at all and held its unit forever.
     */
    public const TERMINAL_STATUSES = ['Cancelled', 'Rejected', 'Completed'];

    /** Every value `reservations.rental_status` can hold (an ENUM since 2026_10_18_000002). */
    public const STATUSES = [
        'Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed',
        'Reserved', 'Occupied', 'Completed', 'Rejected', 'Cancelled',
    ];

    protected $fillable = [
        'property_id',
        'unit_id',
        'tenant_id',
        'conversation_id',
        'reservation_date',
        'target_move_in_date',
        'target_move_out_date',
        'duration_of_stay',
        'agreed_monthly_rent',
        'rent_due_day',
        'occupants_count',
        'rental_status',
        'agreement_terms_notes',
        'lease_snapshot',
        'lease_file_path',
        'lease_file_name',
        'lease_uploaded_at',
        'agreed_at',
        'agreed_ip',
        'remarks',
        'rejection_reason',
        'landlord_tc_accepted_at',
        'tenant_tc_accepted_at',
        'tenant_confirmed_move_in_at',
        'move_in_paid_at',
        'keys_turned_over_at',
        'move_in_deadline_at',
        'move_in_disputed_at',
        'move_in_dispute_reason',
        'move_in_last_reminder_on',
        'handover_at',
        'handover_proposed_by',
        'handover_proposed_at',
        'handover_confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'target_move_in_date' => 'date',
            'target_move_out_date' => 'date',
            'agreed_monthly_rent' => 'decimal:2',
            'lease_snapshot' => 'array',
            'lease_uploaded_at' => 'datetime',
            'agreed_at' => 'datetime',
            'landlord_tc_accepted_at' => 'datetime',
            'tenant_tc_accepted_at' => 'datetime',
            'tenant_confirmed_move_in_at' => 'datetime',
            'move_in_paid_at' => 'datetime',
            'keys_turned_over_at' => 'datetime',
            'move_in_deadline_at' => 'datetime',
            'move_in_disputed_at' => 'datetime',
            'move_in_last_reminder_on' => 'date',
            'handover_at' => 'datetime',
            'handover_proposed_at' => 'datetime',
            'handover_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Tenant confirms they have physically moved in.
     *
     * This is the escrow release point: the held payment goes to the landlord
     * in the same transaction that marks the unit occupied. Previously this
     * only flipped the status and left the payment sitting on 'Held' forever,
     * so money never actually reached the landlord unless an admin noticed and
     * released it by hand from the admin Payments screen.
     *
     * Callers must hold a lock on this reservation — see
     * Tenant\AgreementController::confirmMoveIn.
     */
    public function confirmMoveIn(): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        // A dispute freezes the clock for admin review; the tenant confirming
        // their own move-in here would release the deposit out from under it.
        if ($this->move_in_disputed_at !== null) {
            return false;
        }

        // Locked, not just checked: this row is about to move money.
        $heldPayment = $this->payments()
            ->where('status', 'Held')
            ->lockForUpdate()
            ->first();

        if (! $heldPayment) {
            return false;
        }

        $this->rental_status = 'Occupied';
        $this->tenant_confirmed_move_in_at = now();
        $this->move_in_deadline_at = null;
        $this->save();

        $heldPayment->update([
            'status'         => 'Released',
            'released_at'    => now(),
            // Null = released by the platform, not an admin. release_reason is what
            // distinguishes a tenant confirming from a timer firing — released_by is
            // null for both, so it cannot carry that distinction on its own.
            'released_by'    => null,
            'release_reason' => 'tenant_confirmed',
            // The money is now owed to the landlord — see
            // docs/specs/2026-07-26-landlord-payout-design.md.
            'payout_owed_at' => now(),
        ]);

        $this->releasedPayment = $heldPayment;

        if ($this->unit) {
            $this->unit->availability_status = 'Occupied';
            $this->unit->save();
        }

        return true;
    }

    /**
     * Set by confirmMoveIn() so the caller can broadcast the release after the
     * transaction commits — broadcasting inside it would announce a payout that
     * a rollback could still undo.
     */
    public ?Payment $releasedPayment = null;

    /**
     * The single held payment for this reservation, if any.
     *
     * Both clocks exist only while money is held — no held payment means
     * nothing to protect and no deadline to run.
     */
    public function heldPayment(): ?Payment
    {
        return $this->payments()->where('status', 'Held')->first();
    }

    /**
     * Which clock is running.
     *
     * There is one deadline column and two clocks; turnover is the switch
     * between them. Before turnover the deadline belongs to the landlord
     * (Clock 1), after it to the tenant (Clock 2).
     */
    public function isTurnoverClock(): bool
    {
        return $this->keys_turned_over_at === null;
    }

    /**
     * Clock 1's deadline: how long the landlord has to turn over the keys.
     *
     * Derived from the move-in date both parties negotiated rather than a flat
     * timer — a deposit for a move-in next week and one for a move-in in two
     * months cannot share a countdown.
     *
     * max() guards a deposit paid after an optimistic target date already
     * slipped, which would otherwise produce an already-expired deadline and
     * escalate on the first nightly run.
     */
    public function computeTurnoverDeadline(): ?Carbon
    {
        $payment = $this->heldPayment();

        if (! $payment || ! $payment->paid_at) {
            return null;
        }

        if (! $this->target_move_in_date) {
            return $payment->paid_at->copy()
                ->addDays(config('rentals.turnover_grace_days_no_date'));
        }

        $start = $this->target_move_in_date->greaterThan($payment->paid_at)
            ? $this->target_move_in_date->copy()
            : $payment->paid_at->copy();

        return $start->addDays(config('rentals.turnover_grace_days'));
    }

    // ─── Key handover scheduling (Clock 1) ───────────────────

    /**
     * Whether a handover slot is agreed by both parties, as opposed to merely
     * proposed by one of them.
     */
    public function hasConfirmedHandover(): bool
    {
        return $this->handover_at !== null && $this->handover_confirmed_at !== null;
    }

    public function hasProposedHandover(): bool
    {
        return $this->handover_at !== null && $this->handover_confirmed_at === null;
    }

    /**
     * The furthest a confirmed slot may push Clock 1.
     *
     * Measured from the ORIGINAL deadline, not from now: a cap that moves with
     * each reschedule isn't a cap. Repeated reschedules converge on this bound.
     */
    public function handoverDeadlineCeiling(): ?Carbon
    {
        return $this->computeTurnoverDeadline()
            ?->copy()
            ->addDays(config('rentals.handover_max_extension_days'));
    }

    /**
     * Either party puts a slot forward. Not binding until the other confirms,
     * so this deliberately does not touch move_in_deadline_at — a proposal
     * alone must not be able to move the escrow clock in either direction.
     */
    public function proposeHandover(Carbon $slot, int $proposedBy): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        // Once the keys are with the tenant there is nothing left to schedule,
        // and a dispute freezes the row for admin review.
        if ($this->keys_turned_over_at !== null || $this->move_in_disputed_at !== null) {
            return false;
        }

        if (! $this->heldPayment()) {
            return false;
        }

        $this->handover_at = $slot;
        $this->handover_proposed_by = $proposedBy;
        $this->handover_proposed_at = now();
        // Back to unconfirmed. move_in_deadline_at keeps its last confirmed
        // value until the new slot is agreed — otherwise proposing a reschedule
        // would silently shorten the window the other party is relying on.
        $this->handover_confirmed_at = null;

        return $this->save();
    }

    /**
     * The other party agrees. This is the point the slot becomes the basis for
     * Clock 1, replacing the target_move_in_date the tenant guessed at inquiry
     * time and nothing has been able to edit since.
     */
    public function confirmHandover(int $confirmedBy): bool
    {
        if (! $this->hasProposedHandover()) {
            return false;
        }

        // The proposer confirming their own slot would make agreement
        // meaningless — and this value feeds an escalation deadline.
        if ($this->handover_proposed_by === $confirmedBy) {
            return false;
        }

        if ($this->keys_turned_over_at !== null || $this->move_in_disputed_at !== null) {
            return false;
        }

        if (! $this->heldPayment()) {
            return false;
        }

        $this->handover_confirmed_at = now();
        $this->move_in_deadline_at = $this->deadlineForConfirmedHandover();

        return $this->save();
    }

    /**
     * Clock 1's deadline once a slot is agreed: the slot plus the same grace
     * the negotiated move-in date gets, clamped to the ceiling. The grace is
     * intentional — the pair agreed to meet, not to forfeit on a one-day slip.
     */
    public function deadlineForConfirmedHandover(): ?Carbon
    {
        if ($this->handover_at === null) {
            return null;
        }

        $deadline = $this->handover_at->copy()
            ->addDays(config('rentals.turnover_grace_days'));

        $ceiling = $this->handoverDeadlineCeiling();

        return $ceiling && $deadline->greaterThan($ceiling) ? $ceiling : $deadline;
    }

    /**
     * True when the agreed slot wanted a later deadline than the ceiling
     * allowed, so the UI can say so rather than showing a date nobody chose.
     */
    public function handoverDeadlineWasCapped(): bool
    {
        if (! $this->hasConfirmedHandover()) {
            return false;
        }

        $ceiling = $this->handoverDeadlineCeiling();

        return $ceiling !== null
            && $this->handover_at->copy()
                ->addDays(config('rentals.turnover_grace_days'))
                ->greaterThan($ceiling);
    }

    /**
     * Landlord asserts the keys changed hands. Starts Clock 2.
     *
     * Deliberately moves no money: this is a self-interested claim by the party
     * who gets paid, so it is the weaker of the two turnover assertions. The
     * tenant's confirmation is what releases the deposit.
     */
    public function markKeysTurnedOver(): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        if ($this->keys_turned_over_at !== null) {
            return false;
        }

        // A dispute freezes this row for admin review; re-arming a payout
        // clock on it here would let a landlord undo that freeze unilaterally.
        if ($this->move_in_disputed_at !== null) {
            return false;
        }

        if (! $this->heldPayment()) {
            return false;
        }

        $this->keys_turned_over_at = now();
        $this->move_in_deadline_at = now()->addDays(config('rentals.move_in_confirmation_days'));
        $this->move_in_last_reminder_on = null;

        return $this->save();
    }

    /**
     * Tenant asserts the keys never arrived. Freezes the clock for admin review.
     *
     * Freezes rather than pauses — no arithmetic on remaining time. A human
     * resolves it, so partial-time bookkeeping would be a bug farm for a case
     * that never runs unattended.
     */
    public function disputeMoveIn(string $reason): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return false;
        }

        if ($this->move_in_disputed_at !== null) {
            return false;
        }

        $this->move_in_disputed_at = now();
        $this->move_in_dispute_reason = $reason;
        $this->move_in_deadline_at = null;

        return $this->save();
    }

    /**
     * Whole days left on whichever clock is running. Negative once overdue.
     */
    public function daysUntilMoveInDeadline(): ?int
    {
        if (! $this->move_in_deadline_at || $this->move_in_disputed_at) {
            return null;
        }

        // why: Carbon 3's diffInDays() returns a float and can carry
        // floating-point noise (e.g. 4.0000000001157 for an exact 4-day
        // gap). Truncating with (int) can drop that to 3 if the noise ever
        // lands just under the integer, and ProcessMoveInDeadlines compares
        // this value with a strict in_array([...], true) — silently
        // skipping a reminder. round() first, then cast.
        return $this->wholeDaysUntil($this->move_in_deadline_at);
    }

    private function wholeDaysUntil(Carbon $at): int
    {
        // why: Carbon 3's diffInDays() returns a float and can carry
        // floating-point noise — see daysUntilMoveInDeadline() above.
        return (int) round(now()->startOfDay()->diffInDays(
            $at->copy()->startOfDay(),
            false
        ));
    }

    /**
     * The whole live-clock state in one answer, for any client that has to
     * render a countdown.
     *
     * There is one deadline column and two clocks, and deciding which is
     * running is not a lookup — it depends on turnover, on a dispute, and (for
     * Clock 1 before the first nightly backfill) on a value that is computed
     * rather than stored. `_move-in-clock.blade.php` derived all of that
     * inline; the mobile client would have had to derive it again, and two
     * clients disagreeing about which escrow clock is live is exactly the
     * failure worth spending a method to avoid.
     *
     * Returns null when no clock is running — a clock exists only while money
     * is held, which is the same condition `chat-panel.blade.php` uses to
     * decide whether to render the partial at all.
     */
    public function moveInClockState(): ?array
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
            return null;
        }

        // Use the eager-loaded collection when there is one: this is called
        // per row on the reservations list, and heldPayment() would issue a
        // query each time.
        $held = $this->relationLoaded('payments')
            ? $this->payments->firstWhere('status', 'Held')
            : $this->heldPayment();

        if (! $held) {
            return null;
        }

        $onTurnoverClock = $this->isTurnoverClock();
        $disputed = $this->move_in_disputed_at !== null;

        // Within Clock 1 the computed value covers the window before the
        // nightly backfill has written move_in_deadline_at.
        $deadlineAt = $onTurnoverClock
            ? ($this->move_in_deadline_at ?? $this->computeTurnoverDeadline())
            : $this->move_in_deadline_at;

        return [
            'active_clock'   => $onTurnoverClock ? 'turnover' : 'confirmation',
            'deadline_at'    => $deadlineAt,
            'days_remaining' => $disputed || ! $deadlineAt ? null : $this->wholeDaysUntil($deadlineAt),
            'disputed'       => $disputed,
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function unit()
    {
        return $this->belongsTo(PropertyUnit::class, 'unit_id', 'unit_id');
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reservation_id', 'reservation_id');
    }

    public function lateFeeWaivers()
    {
        return $this->hasMany(LateFeeWaiver::class, 'reservation_id', 'reservation_id');
    }

    public function paymentClaims()
    {
        return $this->hasMany(PaymentClaim::class, 'reservation_id', 'reservation_id');
    }

    public function renewals()
    {
        return $this->hasMany(LeaseRenewal::class, 'reservation_id', 'reservation_id')->orderBy('rent_effective_from');
    }

    /**
     * True once the initial payment is in escrow, paid out, or recorded by the
     * landlord, or the move-in money was paid to the landlord directly and confirmed.
     * The reservation stays "Rental Agreement Signed" while escrow is funded, so the
     * status alone cannot stop a second charge.
     */
    public function hasSettledInitialPayment(): bool
    {
        if ($this->move_in_paid_at !== null) {
            return true;
        }

        return $this->payments()
            ->where('payment_type', 'Initial')
            ->whereIn('status', ['Held', 'Paid', 'Released'])
            ->exists();
    }

    public function depositCharges()
    {
        return $this->hasMany(DepositCharge::class, 'reservation_id', 'reservation_id')->orderByDesc('charged_at');
    }

    /**
     * Where this tenancy's signed lease stands:
     *   'uploaded'      — a signed paper copy is attached (walk-in, or any tenancy)
     *   'signed_online' — the tenant e-signed on the platform
     *   'missing'       — neither yet
     */
    public function leaseStatus(): string
    {
        if ($this->lease_file_path) {
            return 'uploaded';
        }

        return $this->agreed_at ? 'signed_online' : 'missing';
    }

    public function viewings()
    {
        return $this->hasMany(ViewingRequest::class, 'reservation_id', 'reservation_id');
    }

    /**
     * The one Pending/Confirmed viewing still ahead, if any — at most one
     * exists per reservation (enforced in ViewingController::store). A
     * viewing whose time has passed doesn't count, so the tenant can ask for
     * a second look.
     */
    public function activeViewing(): ?ViewingRequest
    {
        return $this->viewings()->open()->latest('viewing_id')->first();
    }

    /**
     * The newest viewing in any state — what the chat card shows.
     */
    public function latestViewing(): ?ViewingRequest
    {
        return $this->viewings()->latest('viewing_id')->first();
    }

    public function canRequestViewing(): bool
    {
        return in_array($this->rental_status, config('rentals.viewing_statuses'), true);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
    }

    /** The post-move-in chat with the landlord. Walk-in tenants have none. */
    public function tenancyThread()
    {
        return $this->hasOne(Conversation::class, 'reservation_id', 'reservation_id')
            ->where('type', Conversation::TYPE_TENANCY);
    }

    public function concerns()
    {
        return $this->hasMany(TenantConcern::class, 'reservation_id', 'reservation_id');
    }

    /**
     * Create (or reattach) the tenancy chat once the tenant is actually living here.
     *
     * One thread per tenant, landlord and property: renting the same place again later
     * reuses it and just points it at the new tenancy. Walk-ins get nothing, since the
     * tenant can't log in to read it.
     */
    public function openTenancyThread(): ?Conversation
    {
        $tenant = $this->tenant;
        $landlordId = $this->property?->landlord_id;

        if (! $tenant || $tenant->is_walk_in || ! $landlordId) {
            return null;
        }

        $thread = Conversation::firstOrNew([
            'tenant_id' => $this->tenant_id,
            'landlord_id' => $landlordId,
            'property_id' => $this->property_id,
            'type' => Conversation::TYPE_TENANCY,
        ]);
        $thread->fill(['reservation_id' => $this->reservation_id, 'unit_id' => $this->unit_id, 'status' => 'Open'])->save();

        return $thread;
    }

    public function tenantRating()
    {
        return $this->hasOne(TenantRating::class, 'reservation_id', 'reservation_id');
    }
    // ─── Status Helpers ──────────────────────────────────────

    public function isRentalStatus(string $status): bool
    {
        return $this->rental_status === $status;
    }

    public function isAgreementSigned(): bool
    {
        return $this->rental_status === 'Rental Agreement Signed';
    }

    public function isOccupied(): bool
    {
        return $this->rental_status === 'Occupied';
    }

    /**
     * How a contract reads on the Contracts page, with a tone for its pill (ok, warn, bad, muted). A signed agreement
     * waits for its move-in money; once that is confirmed (or for a contract added by hand with a future date) the
     * unit is held and it waits for the move-in.
     *
     * @return array{label: string, tone: string}
     */
    public function contractStatus(): array
    {
        return match ($this->rental_status) {
            'Rental Agreement Signed' => $this->move_in_paid_at
                ? ['label' => 'Awaiting move-in', 'tone' => 'warn']
                : ['label' => 'Awaiting payment', 'tone' => 'muted'],
            'Reserved' => ['label' => 'Awaiting move-in', 'tone' => 'warn'],
            'Occupied' => $this->occupiedContractStatus(),
            'Completed' => ['label' => 'Ended', 'tone' => 'muted'],
            default => ['label' => $this->rental_status, 'tone' => 'muted'],
        };
    }

    private function occupiedContractStatus(): array
    {
        if ($this->isLeaseExpired()) {
            return ['label' => 'Past end date', 'tone' => 'bad'];
        }

        $daysLeft = $this->target_move_out_date
            ? (int) round(now()->startOfDay()->diffInDays($this->target_move_out_date->copy()->startOfDay(), false))
            : null;

        return $daysLeft !== null && $daysLeft <= (int) config('rentals.renewal_window_days', 60)
            ? ['label' => 'Ends in '.$daysLeft.' '.($daysLeft === 1 ? 'day' : 'days'), 'tone' => 'warn']
            : ['label' => 'Active', 'tone' => 'ok'];
    }

    public function isLeaseExpired(): bool
    {
        return $this->rental_status === 'Occupied'
            && $this->target_move_out_date
            && $this->target_move_out_date->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->rental_status === 'Completed';
    }

    /**
     * A tenancy the landlord entered by hand rather than one that came through
     * the inquiry pipeline.
     *
     * Keyed off the tenant, not the missing conversation: a landlord can
     * legitimately delete a thread, and a walk-in stays a walk-in either way.
     */
    public function isWalkIn(): bool
    {
        return (bool) $this->tenant?->is_walk_in;
    }

    // ─── Rent terms (the ledger's inputs) ────────────────────

    /**
     * The rent for one billing month: the newest renewal whose new rent has started by then, otherwise the
     * contract's own rent (monthlyRent()). A renewal never changes a month before its rent_effective_from, so
     * months already billed keep their amounts. Renewals are loaded once and reused across the ledger's months.
     */
    public function rentFor(Carbon $month): float
    {
        if (! $this->relationLoaded('renewals')) {
            $this->load('renewals');
        }

        $start = $month->copy()->startOfMonth();
        $renewal = $this->renewals
            ->filter(fn (LeaseRenewal $r) => $r->rent_effective_from->lte($start))
            ->sortBy('rent_effective_from')
            ->last();

        return $renewal ? (float) $renewal->new_monthly_rent : $this->monthlyRent();
    }

    /** The rent this month, for display. */
    public function currentRent(): float
    {
        return $this->rentFor(now());
    }

    /**
     * The contract's rent as first agreed (renewals aside, see rentFor()).
     *
     * Falls back to the unit's listed price so every pre-existing platform
     * reservation has a rent without a backfill. The override exists because a
     * walk-in rent is negotiated at the door and often isn't the list price.
     */
    public function monthlyRent(): float
    {
        return (float) ($this->agreed_monthly_rent ?? $this->unit?->rental_fee ?? 0);
    }

    /**
     * The security deposit the tenant agreed to. Once a lease is issued this is the frozen amount, so a
     * landlord editing the unit afterwards can't change what the tenant signed or has to pay. Before that
     * (an inquiry), it is the unit's current deposit.
     */
    public function agreedDeposit(): float
    {
        $snapshot = $this->lease_snapshot;

        if (is_array($snapshot) && array_key_exists('deposit', $snapshot)) {
            return (float) ($snapshot['deposit'] ?? 0);
        }

        return (float) ($this->unit?->security_deposit ?? 0);
    }

    /**
     * Day of the month rent falls due.
     *
     * Defaults to the day they moved in, which is how an informal Philippine
     * rental almost always works — "same date every month". Clamped to 28 so
     * the day exists in February; a 31st due date would silently skip months.
     */
    public function rentDueDay(): int
    {
        $day = $this->rent_due_day
            ?? $this->target_move_in_date?->day
            ?? config('rentals.rent_due_day_default');

        return max(1, min(28, (int) $day));
    }

    /**
     * The date rent starts accruing. The agreed move-in date; failing that the day
     * the tenant actually confirmed moving in (an online inquiry may name no
     * target date); and only then the date the reservation was made, which is
     * the inquiry day and would bill months before anyone lived there.
     */
    public function tenancyStartDate(): ?Carbon
    {
        $start = $this->target_move_in_date ?? $this->tenant_confirmed_move_in_at ?? $this->reservation_date;

        return $start ? Carbon::parse($start) : null;
    }

    /**
     * Derives the duration label from the move-in/move-out pair instead of
     * the underlying duration_of_stay column.
     *
     * duration_of_stay is a legacy free-text column nothing in the live app
     * writes anymore — only ReservationSeeder and BuildsEscrowFixtures still
     * populate it, for dev fixtures. An accessor of the same name takes
     * precedence over the raw attribute, so every existing read site (tenant
     * and landlord reservation lists, admin detail, ReservationResource)
     * keeps working unchanged, now driven by real dates instead of free text.
     */
    public function getDurationOfStayAttribute(): ?string
    {
        if (! $this->target_move_in_date) {
            return null;
        }

        if (! $this->target_move_out_date) {
            return 'Open-ended';
        }

        // Carbon 3's diffInMonths() returns a float — round it (RULES.md).
        $months = (int) round($this->target_move_in_date->diffInMonths($this->target_move_out_date));

        return $months === 1 ? '1 month' : "{$months} months";
    }

    /**
     * Close out an occupied tenancy and hand the unit back.
     *
     * Deliberately a separate status from Cancelled: a completed tenancy is a
     * successful one, and folding it into Cancelled would erase that from the
     * landlord's history and from analytics. Moves no money — the ledger
     * records what was collected and is not settled or reversed here.
     *
     * Callers must hold a lock on this reservation.
     */
    public function endTenancy(?Carbon $on = null): bool
    {
        if ($this->rental_status !== 'Occupied') {
            return false;
        }

        $this->rental_status = 'Completed';
        $this->target_move_out_date = $on ?? now();
        $saved = $this->save();

        if ($saved) {
            // Already sets vacated_at and flips the unit back to Available,
            // which fires PropertyUnitObserver and logs the occupancy activity.
            $this->releaseUnit();
        }

        return $saved;
    }

    // ─── State Transitions ───────────────────────────────────

    public function advanceToNegotiation(): bool
    {
        if ($this->rental_status !== 'Inquiry') {
            return false;
        }
        $this->rental_status = 'Under Negotiation';

        return $this->save();
    }

    /**
     * The landlord sends the agreement with the final terms agreed in chat (step 6 of the reservation flow,
     * plans/reservation-final-terms.md). $terms is SendAgreementRequest's validated data; any term left out keeps
     * what the inquiry or the listing said, so an older client that sends only the extra-terms text still works.
     * A plain string is taken as the extra terms (the seeder and older callers).
     */
    public function advanceToPendingAgreement(array|string|null $terms = []): bool
    {
        if ($this->rental_status !== 'Under Negotiation') {
            return false;
        }

        $terms = is_string($terms) ? ['agreement_terms_notes' => $terms] : ($terms ?? []);
        $given = fn (string $key) => array_key_exists($key, $terms) && $terms[$key] !== null && $terms[$key] !== '';

        foreach (['agreed_monthly_rent', 'target_move_in_date', 'target_move_out_date', 'rent_due_day', 'occupants_count', 'agreement_terms_notes'] as $key) {
            if ($given($key)) {
                $this->{$key} = $terms[$key];
            }
        }

        // Pin the rent to what the tenant is about to sign. Online tenancies never had an agreed rent (only
        // walk-ins did), so checkout and the ledger read the unit's live price, and a landlord editing it after
        // signing changed what the tenant paid. Set before the snapshot so the lease and the charge agree.
        $this->agreed_monthly_rent ??= $this->unit?->rental_fee;

        // Freeze the lease the tenant is about to sign, with the deposit and advance agreed for this lease rather
        // than the listing's. Done here, not at the call sites, so every path that issues the agreement does it.
        $this->lease_snapshot = LeaseTerms::snapshot($this, true, array_filter([
            'deposit' => $given('security_deposit') ? (float) $terms['security_deposit'] : null,
            'advance_months' => $given('advance_months') ? (int) $terms['advance_months'] : null,
        ], fn ($v) => $v !== null));
        $this->rental_status = 'Pending Rental Agreement';

        return $this->save();
    }

    /**
     * Where a request stands on the landlord's Reservations page and whose turn it is: label, a short "next" line, and
     * a tone for its pill (warn = the landlord acts, info = the tenant acts, muted = nothing to do).
     * Needs paymentClaims loaded for a signed agreement.
     *
     * @return array{label: string, next: string, tone: string}
     */
    public function requestStage(): array
    {
        $tenant = $this->tenant?->first_name ?? 'The tenant';

        if ($this->rental_status === 'Rental Agreement Signed') {
            $report = $this->paymentClaims->where('status', 'Pending')->where('claim_type', 'MoveIn')->first();

            return $report
                ? ['label' => 'Payment reported', 'next' => '₱'.number_format((float) $report->amount, 2).' to confirm', 'tone' => 'warn']
                : ['label' => 'Signed', 'next' => $tenant.' pays you ₱'.number_format($this->moveInDue(), 2), 'tone' => 'info'];
        }

        return match ($this->rental_status) {
            'Inquiry' => ['label' => 'New inquiry', 'next' => 'You reply', 'tone' => 'warn'],
            'Under Negotiation' => ['label' => 'Discussing', 'next' => 'Send the agreement once agreed', 'tone' => 'warn'],
            'Pending Rental Agreement' => ['label' => 'Agreement sent', 'next' => $tenant.' signs', 'tone' => 'info'],
            'Rejected' => ['label' => 'Rejected', 'next' => $this->rejection_reason ?: 'You declined', 'tone' => 'muted'],
            'Cancelled' => ['label' => 'Cancelled', 'next' => 'Closed', 'tone' => 'muted'],
            default => ['label' => $this->rental_status, 'next' => 'On the Contracts page', 'tone' => 'muted'],
        };
    }

    /** Months of rent paid ahead at move-in, as agreed in the lease (one when the lease does not say). */
    public function advanceMonths(): int
    {
        $snapshot = $this->lease_snapshot;

        return is_array($snapshot) && isset($snapshot['advance_months']) ? (int) $snapshot['advance_months'] : 1;
    }

    /**
     * Starting values for the Send-agreement form: what the tenant asked for at inquiry, and the listing's rent,
     * deposit and standard terms. The landlord edits them to what was agreed in chat.
     *
     * @return array<string, mixed>
     */
    public function agreementDefaults(): array
    {
        return [
            'rent' => (float) ($this->agreed_monthly_rent ?? $this->unit?->rental_fee ?? 0),
            'deposit' => (float) ($this->unit?->security_deposit ?? 0),
            'advance' => 1,
            'move_in' => $this->target_move_in_date?->toDateString() ?? '',
            'move_out' => $this->target_move_out_date?->toDateString() ?? '',
            'due_day' => $this->rent_due_day ?? '',
            'occupants' => $this->occupants_count ?? 1,
            'limit' => $this->unit?->occupancy_limit,
            'terms' => $this->agreement_terms_notes ?? $this->property?->default_lease_terms ?? '',
        ];
    }

    /** What the tenant pays the landlord at move-in: the agreed advance months of rent plus the deposit. */
    public function moveInDue(): float
    {
        return round($this->advanceMonths() * $this->monthlyRent() + $this->agreedDeposit(), 2);
    }

    public function signAgreement(string $ip): bool
    {
        if ($this->rental_status !== 'Pending Rental Agreement') {
            return false;
        }
        $this->rental_status = 'Rental Agreement Signed';
        $this->agreed_at = now();
        $this->agreed_ip = $ip;

        return $this->save();
    }

    /**
     * Move in once the tenant has paid the landlord directly (move_in_paid_at set by a confirmed claim or
     * a landlord-recorded payment). No escrow: nothing is held, so nothing is released, and either party
     * may confirm, the same reasoning as confirmWalkInMoveIn(). Refused while a Held payment exists, which
     * must go through confirmMoveIn() so the money is released.
     *
     * Callers must hold a lock on this reservation.
     */
    public function markOccupied(bool $byTenant = false): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed' || $this->move_in_paid_at === null) {
            return false;
        }

        if ($this->move_in_disputed_at !== null || $this->payments()->where('status', 'Held')->exists()) {
            return false;
        }

        $this->rental_status = 'Occupied';
        if ($byTenant) {
            $this->tenant_confirmed_move_in_at = now();
        }
        $this->save();

        if ($this->unit) {
            $this->unit->availability_status = 'Occupied';
            $this->unit->save();
        }

        return true;
    }

    /**
     * The walk-in equivalent of markOccupied() — a walk-in reserved for a
     * future date (see LeaseContractCreator) has no
     * agreement, no escrow and no held payment to release, so unlike the
     * platform pipeline's confirmMoveIn() this touches only the two status
     * fields. The landlord's own assertion is enough here, same as
     * markKeysTurnedOver() is for the platform pipeline's turnover step.
     */
    public function confirmWalkInMoveIn(): bool
    {
        if ($this->rental_status !== 'Reserved') {
            return false;
        }
        $this->rental_status = 'Occupied';
        $this->save();

        if ($this->unit) {
            $this->unit->availability_status = 'Occupied';
            $this->unit->save();
        }

        return true;
    }

    /**
     * Why this reservation cannot be cancelled or rejected right now, or null if it can.
     *
     * There is no refund path in the app, so ending a reservation while money is held would strand that money
     * (the escrow scan only looks at 'Rental Agreement Signed'). A checkout still open may also be paid after
     * the cancel. Both are refused until an administrator has dealt with the payment. Admin overrides
     * (Admin\ReservationController::forceCancel / forceReject) deliberately bypass this.
     */
    public function cancellationBlockedReason(): ?string
    {
        // The list page calls this once per row with payments already eager-loaded; elsewhere it is one query.
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();

        $held = (float) $payments->where('status', 'Held')->sum('amount');

        if ($held > 0) {
            return 'A payment of ₱'.number_format($held, 2).' is held by AbangananHub for this reservation, so it cannot be cancelled here. Message each other, or use Report a Problem and an administrator will sort it out.';
        }

        $cutoff = now()->subMinutes((int) config('rentals.checkout_stale_minutes', 30));

        $checkoutOpen = $payments->contains(fn ($p) => $p->status === 'Pending'
            && $p->paymongo_checkout_session_id
            && $p->created_at?->gte($cutoff));

        return $checkoutOpen
            ? 'A payment is being processed for this reservation. Wait for it to finish, then try again.'
            : null;
    }

    /**
     * Warning for a cancel confirmation when move-in money went straight to the landlord: nothing is held,
     * so nothing can be returned here. Null when no direct payment was settled.
     */
    public function directPaymentNotice(string $payee): ?string
    {
        if ($this->move_in_paid_at === null) {
            return null;
        }

        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $paid = (float) $payments->where('status', 'Paid')->whereNotNull('recorded_by')->sum('amount');

        return ($paid > 0 ? '₱'.number_format($paid, 2).' was' : 'The move-in payment was')
            ." paid directly to {$payee}. AbangananHub does not hold it and cannot return it, so settle it with each other.";
    }

    /**
     * The one thing this tenant should do next on a reservation, for the list's primary button (label + url),
     * or null when there is nothing to do. Before this the list offered only Cancel and Chat on an agreement
     * waiting to be signed or paid, and the agreement was reachable only from inside the chat.
     *
     * @return array{label: string, url: string}|null
     */
    public function tenantNextStep(): ?array
    {
        $paid = $this->move_in_paid_at !== null || ($this->relationLoaded('payments')
            ? $this->payments->contains(fn ($p) => $p->payment_type === 'Initial' && in_array($p->status, ['Held', 'Paid', 'Released'], true))
            : $this->hasSettledInitialPayment());

        return match ($this->rental_status) {
            'Pending Rental Agreement' => ['label' => 'Review & sign', 'url' => route('agreements.show', $this)],
            'Rental Agreement Signed'  => ['label' => $paid ? 'Open agreement' : (Payments::online() ? 'Pay now' : 'Pay your landlord'), 'url' => route('agreements.show', $this)],
            'Occupied'                 => ['label' => 'Rent & payments', 'url' => route('tenancy.show', $this)],
            'Completed'                => ['label' => 'View tenancy', 'url' => route('tenancy.show', $this)],
            default                    => null,
        };
    }

    public function reject(?string $reason = null): bool
    {
        if (in_array($this->rental_status, ['Occupied', ...self::TERMINAL_STATUSES], true)) {
            return false;
        }

        return DB::transaction(function () use ($reason) {
            // Same row the checkout locks, so a payment being started and this cannot both pass.
            static::whereKey($this->getKey())->lockForUpdate()->first();

            $this->unsetRelation('payments');

            if ($this->cancellationBlockedReason() !== null) {
                return false;
            }

            $this->rental_status = 'Rejected';
            if ($reason !== null) {
                $this->rejection_reason = $reason;
            }
            $saved = $this->save();

            if ($saved) {
                $this->cancelLinkedConversation();
                $this->releaseUnitIfUnclaimed();
            }

            return $saved;
        });
    }

    public function cancel(): bool
    {
        if (in_array($this->rental_status, ['Occupied', ...self::TERMINAL_STATUSES], true)) {
            return false;
        }

        return DB::transaction(function () {
            static::whereKey($this->getKey())->lockForUpdate()->first();

            $this->unsetRelation('payments');

            if ($this->cancellationBlockedReason() !== null) {
                return false;
            }

            $this->rental_status = 'Cancelled';
            $saved = $this->save();

            if ($saved) {
                $this->cancelLinkedConversation();
                $this->releaseUnitIfUnclaimed();
            }

            return $saved;
        });
    }

    /**
     * Reservations that hold their unit: a tenant living there, a contract waiting for its move-in date, or a
     * signed agreement whose move-in money the landlord has confirmed. Plain inquiries do not hold a unit; several
     * can compete for it.
     */
    public function scopeHoldingUnit(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereIn('rental_status', ['Reserved', 'Occupied'])
            ->orWhere(fn ($q) => $q->where('rental_status', 'Rental Agreement Signed')->whereNotNull('move_in_paid_at')));
    }

    /** True when another reservation already holds this one's unit (see scopeHoldingUnit). */
    public function unitHeldByAnother(): bool
    {
        return self::where('unit_id', $this->unit_id)
            ->where('reservation_id', '!=', $this->reservation_id)
            ->holdingUnit()
            ->exists();
    }

    /**
     * The move-in money is settled, so the unit is kept for this tenant until they move in: it reads Reserved and
     * leaves the browse results. Callers hold the reservation lock and have checked unitHeldByAnother().
     */
    public function holdUnit(): void
    {
        if ($this->unit && ! in_array($this->unit->availability_status, ['Reserved', 'Occupied'], true)) {
            $this->unit->availability_status = 'Reserved';
            $this->unit->save();
        }
    }

    /**
     * A unit can have several live reservations at once (competing inquiries,
     * or an inquiry on a unit someone already occupies). Dropping one must not
     * re-list the unit while another still holds it.
     */
    public function releaseUnitIfUnclaimed(): void
    {
        if (! $this->unitHeldByAnother()) {
            $this->releaseUnit();
        }
    }

    public function releaseUnit(): void
    {
        if ($this->unit && $this->unit->availability_status !== 'Available') {
            $this->unit->availability_status = 'Available';
            $this->unit->vacated_at = now();
            $this->unit->save();
        }
    }

    protected function cancelLinkedConversation(): void
    {
        if ($this->conversation && !$this->conversation->isCancelled()) {
            $this->conversation->update(['status' => 'Cancelled']);
        }
    }

    public function postSystemMessage(string $text): void
    {
        if (!$this->conversation_id) {
            return;
        }

        $message = Message::create([
            'conversation_id' => $this->conversation_id,
            'sender_id' => null,
            'message' => $text,
            'kind' => 'system',
            'is_read' => true,
        ]);

        // MessageSent implements ShouldBroadcastNow, so this reaches out to
        // Reverb synchronously, inline in whatever transaction the caller is
        // in (e.g. AgreementController::sign()). A stopped/unreachable Reverb
        // throws BroadcastException here, which would otherwise abort and
        // roll back the entire caller action just because nobody's listening
        // for the live update — the message row above is already written and
        // is the message of record; the broadcast is a nice-to-have real-time
        // nudge on top of it. Same pattern as Notification::notify().
        try {
            MessageSent::dispatch($message);
        } catch (BroadcastException $e) {
            Log::warning('Message broadcast failed, continuing without it', [
                'message_id' => $message->message_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Per-status tab counts for a reservation list, in ONE grouped query.
     *
     * Returns ['all' => n, '<status>' => n, ...] with every requested status
     * present (0 when none). Replaces one COUNT per tab — 8 identical queries
     * per page load on both the landlord and tenant reservation lists.
     *
     * @param  Builder  $base  already scoped/filtered, not yet ordered
     */
    public static function statusCounts($base, array $statuses): array
    {
        $byStatus = (clone $base)
            ->selectRaw('rental_status, COUNT(*) as aggregate')
            ->groupBy('rental_status')
            ->pluck('aggregate', 'rental_status');

        $counts = ['all' => (int) $byStatus->sum()];
        foreach ($statuses as $status) {
            $counts[$status] = (int) ($byStatus[$status] ?? 0);
        }

        return $counts;
    }
}
