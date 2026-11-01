<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Payment extends Model
{
    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'reservation_id',
        'payment_type',
        'billing_period',
        'amount',
        'service_fee',
        'payment_method',
        'paymongo_payment_intent_id',
        'paymongo_payment_id',
        'paymongo_checkout_session_id',
        'status',
        'paid_at',
        'released_at',
        'released_by',
        'release_reason',
        'payout_id',
        'payout_owed_at',
        'recorded_by',
        'reference_no',
        'payment_notes',
        'voided_at',
        'voided_by',
        'void_reason',
        'void_note',
        'replaces_payment_id',
        'payment_claim_id',
        'refunded_at',
        'refunded_by',
        'refund_reason',
        'paymongo_refund_id',
        'refund_status',
        'refund_transfer_link',
        'refund_includes_fee',
    ];

    protected $casts = [
        'billing_period' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'released_at' => 'datetime',
        'payout_owed_at' => 'datetime',
        'voided_at' => 'datetime',
        'refunded_at' => 'datetime',
        'refund_includes_fee' => 'boolean',
    ];

    /** Named causes a recorded payment can be voided for. Keys are the enum members. */
    public const VOID_REASONS = [
        'wrong_amount'  => 'Wrong amount entered',
        'wrong_month'   => 'Wrong billing month',
        'wrong_tenancy' => 'Recorded against the wrong tenant',
        'duplicate'     => 'Duplicate entry',
        'not_received'  => 'Payment did not clear',
        'other'         => 'Other',
    ];

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    /**
     * The landlord who typed this payment in, on the manually recorded ones.
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by', 'user_id');
    }

    /**
     * The transfer that settled this payment, once an admin has sent it. Null while the money is
     * still owed to the landlord (or was never payout-eligible).
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class, 'payout_id', 'payout_id');
    }

    /** Owed to the landlord and not yet in a payout: the admin payout queue. */
    public function scopePendingPayout($query)
    {
        return $query->whereNotNull('payout_owed_at')->whereNull('payout_id');
    }

    /** Sent to the landlord: in a payout PayMongo (or the admin) has confirmed. */
    public function scopePaidOut($query)
    {
        return $query->whereHas('payout', fn ($q) => $q->where('status', 'Sent'));
    }

    /** In a payout whose PayMongo transfer is not confirmed yet. */
    public function scopeInTransit($query)
    {
        return $query->whereHas('payout', fn ($q) => $q->where('status', 'Processing'));
    }

    /** Owed and not yet sent: still in the queue, or on its way. What a landlord counts as "pending". */
    public function scopeAwaitingPayout($query)
    {
        return $query->whereNotNull('payout_owed_at')->where(fn ($q) => $q
            ->whereNull('payout_id')
            ->orWhereHas('payout', fn ($p) => $p->where('status', 'Processing')));
    }

    /**
     * 'Pending Payout' / 'Paid Out' / null, derived from `payout_owed_at` and `payout_id` (there is
     * no stored status). Kept as an attribute so views and the mobile payload read the same name.
     */
    public function getPayoutStatusAttribute(): ?string
    {
        if ($this->payout_id !== null) {
            // Only read the payout when it is already loaded: this attribute is read per row in lists.
            return $this->relationLoaded('payout') && $this->payout?->isProcessing() ? 'Payout Processing' : 'Paid Out';
        }

        return $this->payout_owed_at !== null ? 'Pending Payout' : null;
    }

    /**
     * Pesos to PayMongo's centavos. Rounds rather than truncating: 512.05 * 100
     * is 51204.99999999999 in floating point, and a plain (int) cast sends 51204.
     */
    public static function toCentavos(float|int|string $pesos): int
    {
        return (int) round((float) $pesos * 100);
    }

    public function isPaid(): bool
    {
        return $this->status === 'Paid';
    }

    /**
     * Maps PayMongo's `payment_method_used` (or the `source.type`/`type` on a
     * paid checkout session's payments[0], depending on which shape PayMongo
     * hands back) to this table's payment_method enum label. Returns null if
     * the method can't be identified, so callers can fall back to whatever
     * the placeholder already had rather than overwrite it with garbage.
     */
    public static function resolvePaymongoMethod(?string $method): ?string
    {
        return match ($method) {
            'gcash' => 'GCash',
            'qrph' => 'QRPh',
            default => null,
        };
    }

    /**
     * Every row funded by the same PayMongo checkout, this one included, oldest first: an online move-in is
     * one payment on PayMongo's side but several rows here (rent, deposit). A row with no session id is a group
     * of one. Anything that moves the money (settle, release, refund) must move the whole group together.
     */
    public function checkoutGroup(): \Illuminate\Support\Collection
    {
        if (! $this->paymongo_checkout_session_id) {
            return collect([$this]);
        }

        return static::where('paymongo_checkout_session_id', $this->paymongo_checkout_session_id)
            ->orderBy('payment_id')
            ->get();
    }

    /**
     * Everything the tenant was charged in this row's checkout: the rent, the deposit and the online fee.
     * A single row outside any group is just its own amount plus its fee.
     */
    public function checkoutTotal(): float
    {
        return round((float) $this->checkoutGroup()->sum(fn (self $p) => (float) $p->amount + (float) $p->service_fee), 2);
    }

    /** Escrow ends for this row: it is now owed to the landlord. Callers hold the lock and release the whole group. */
    public function markReleased(string $reason, ?int $releasedBy = null): void
    {
        // The observer reads the landlord off these once the row is saved; rows fetched as a collection may not
        // lazy-load them (Model::preventLazyLoading), so load them explicitly.
        $this->loadMissing('reservation.property');

        $this->update([
            'status' => 'Released',
            'released_at' => now(),
            // Null = released by the platform (tenant confirmed, or the timer ran out); release_reason says which.
            'released_by' => $releasedBy,
            'release_reason' => $reason,
            // The money is now owed to the landlord, see docs/specs/2026-07-26-landlord-payout-design.md.
            'payout_owed_at' => now(),
        ]);
    }

    /** The tenant's "I paid" report this row was written from, when a landlord confirmed one. */
    public function claim(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentClaim::class, 'payment_claim_id', 'payment_claim_id');
    }

    /**
     * Money the landlord collected offline and entered themselves, as opposed
     * to money PayMongo settled.
     *
     * These two are not the same evidence and must never render as though they
     * were — a recorded payment is one party's assertion, a settled one has a
     * PayMongo id behind it. `recorded_by` is the only thing that carries the
     * distinction, which is why it is never null on a recorded row.
     */
    public function isManuallyRecorded(): bool
    {
        return $this->recorded_by !== null;
    }

    /**
     * An unfinished checkout the tenant has walked away from. With a PayMongo session it takes
     * `rentals.checkout_stale_minutes`; a placeholder that never got a session id (the request died between
     * creating the row and saving the id) is dead after a few minutes. Nothing else ever clears a Pending row,
     * so without this the tenant is locked out of paying.
     */
    public function isStalePending(): bool
    {
        if ($this->status !== 'Pending' || $this->created_at === null) {
            return false;
        }

        $minutes = $this->paymongo_checkout_session_id
            ? (int) config('rentals.checkout_stale_minutes', 30)
            : 5;

        return $this->created_at->lt(now()->subMinutes($minutes));
    }

    /**
     * Money can still arrive after a reservation ended: the tenant opened a checkout, the reservation was
     * cancelled or rejected, then the checkout was paid. The payment is real, there is no refund path in the
     * app, and nothing else looks at a Cancelled reservation's payments, so tell the admins instead of leaving
     * it Held where nobody will find it.
     */
    public function alertAdminsIfStranded(): void
    {
        $reservation = $this->reservation;

        if (! $reservation || ! in_array($reservation->rental_status, ['Cancelled', 'Rejected'], true)) {
            return;
        }

        User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
            ->pluck('user_id')
            ->each(fn ($adminId) => Notification::notify(
                $adminId,
                'payment',
                'Payment received for an ended reservation',
                sprintf(
                    '₱%s was paid on reservation #%d after it was %s. The money is held and needs an administrator to resolve.',
                    number_format((float) $this->amount, 2),
                    $reservation->reservation_id,
                    strtolower($reservation->rental_status),
                ),
                route('admin.payments.index', ['status' => 'Held']),
            ));
    }

    /**
     * PayMongo took a different amount from the one this checkout asked for, so the payment is left Pending
     * instead of being settled. One alert per payment per day: the poll fallback runs on every page load.
     */
    public function alertAdminsAmountMismatch(int $paidCentavos): void
    {
        if (! Cache::add('paymongo-amount-mismatch:'.$this->payment_id, true, now()->addDay())) {
            return;
        }

        User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))
            ->pluck('user_id')
            ->each(fn ($adminId) => Notification::notify(
                $adminId,
                'payment',
                'Online payment amount does not match',
                sprintf(
                    'Payment #%d expected ₱%s but PayMongo took ₱%s. It was not settled; check the checkout in the PayMongo dashboard.',
                    $this->payment_id,
                    number_format($this->checkoutTotal(), 2),
                    number_format($paidCentavos / 100, 2),
                ),
                route('admin.payments.index', ['status' => 'Pending']),
            ));
    }

    public function isHeld(): bool
    {
        return $this->status === 'Held';
    }

    public function isReleased(): bool
    {
        return $this->status === 'Released';
    }

    public function isPendingPayout(): bool
    {
        return $this->payout_owed_at !== null && $this->payout_id === null;
    }

    public function isPaidOut(): bool
    {
        return $this->payout_id !== null;
    }

    /** A landlord-confirmed or landlord-recorded payment that still counts. PayMongo payments have PayMongo's own. */
    public function hasReceipt(): bool
    {
        return $this->isManuallyRecorded() && $this->status === 'Paid';
    }

    /** "Four thousand pesos only" / "Four thousand pesos and 50/100 only", as printed on Philippine receipts. */
    public function amountInWords(): ?string
    {
        if (! class_exists(\NumberFormatter::class)) {
            return null;
        }

        $pesos = (int) floor((float) $this->amount);
        $centavos = (int) round(((float) $this->amount - $pesos) * 100);
        $words = ucfirst((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($pesos));

        return $words . ' ' . ($pesos === 1 ? 'peso' : 'pesos')
            . ($centavos > 0 ? sprintf(' and %02d/100', $centavos) : '') . ' only';
    }

    public function isVoided(): bool
    {
        return $this->status === 'Voided';
    }

    /**
     * Only a landlord's own assertion can be voided, and only while the
     * platform still owes nothing against it. A PayMongo-settled payment is
     * evidence, not an assertion — it goes through a refund, which this app
     * does not have.
     *
     * This is for the view only. It is not the control — the controller
     * re-asserts all three conditions under a lock before writing anything.
     */
    public function canBeVoided(): bool
    {
        return $this->isManuallyRecorded()
            && $this->status === 'Paid'
            && $this->payout_owed_at === null;
    }

    public function voidReasonLabel(): ?string
    {
        return $this->void_reason ? (self::VOID_REASONS[$this->void_reason] ?? $this->void_reason) : null;
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by', 'user_id');
    }

    /** The voided row this payment was entered to replace, if it was a correction. */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'replaces_payment_id', 'payment_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(Payment::class, 'replaces_payment_id', 'payment_id');
    }
}
