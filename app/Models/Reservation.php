<?php

namespace App\Models;

use App\Events\MessageSent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

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
        'agreed_at',
        'agreed_ip',
        'remarks',
        'rejection_reason',
        'landlord_tc_accepted_at',
        'tenant_tc_accepted_at',
        'tenant_confirmed_move_in_at',
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
            'agreed_at' => 'datetime',
            'landlord_tc_accepted_at' => 'datetime',
            'tenant_tc_accepted_at' => 'datetime',
            'tenant_confirmed_move_in_at' => 'datetime',
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
        return (int) round(now()->startOfDay()->diffInDays(
            $this->move_in_deadline_at->copy()->startOfDay(),
            false
        ));
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

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id', 'conversation_id');
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
     * What this tenant owes per month.
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
     * The date rent starts accruing. Move-in where there is one, otherwise the
     * date the reservation was made — a walk-in always supplies a move-in date,
     * so this fallback only ever covers odd legacy rows.
     */
    public function tenancyStartDate(): ?Carbon
    {
        $start = $this->target_move_in_date ?? $this->reservation_date;

        return $start ? Carbon::parse($start) : null;
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

    public function advanceToPendingAgreement(?string $terms = null): bool
    {
        if ($this->rental_status !== 'Under Negotiation') {
            return false;
        }
        if ($terms !== null) {
            $this->agreement_terms_notes = $terms;
        }
        $this->rental_status = 'Pending Rental Agreement';
        return $this->save();
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

    public function markOccupied(): bool
    {
        if ($this->rental_status !== 'Rental Agreement Signed') {
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

    public function reject(?string $reason = null): bool
    {
        if (in_array($this->rental_status, ['Occupied', ...self::TERMINAL_STATUSES], true)) {
            return false;
        }
        $this->rental_status = 'Rejected';
        if ($reason !== null) {
            $this->rejection_reason = $reason;
        }
        $saved = $this->save();

        if ($saved) {
            $this->cancelLinkedConversation();
            $this->releaseUnit();
        }

        return $saved;
    }

    public function cancel(): bool
    {
        if (in_array($this->rental_status, ['Occupied', ...self::TERMINAL_STATUSES], true)) {
            return false;
        }
        $this->rental_status = 'Cancelled';
        $saved = $this->save();

        if ($saved) {
            $this->cancelLinkedConversation();
            $this->releaseUnit();
        }

        return $saved;
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
            'is_system' => true,
            'is_read' => true,
        ]);

        // Without this the row exists but only the party who triggered the
        // transition ever sees it — they get a fresh render from their own
        // POST, while the other side's thread stays stale until reload.
        MessageSent::dispatch($message);
    }
}