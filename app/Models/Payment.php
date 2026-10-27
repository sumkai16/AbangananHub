<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Payment extends Model
{
    /**
     * The status this row had before its last update. PaymentObserver runs after the enclosing
     * transaction commits, by which time getOriginal('status') already holds the NEW value, so
     * it cannot tell Pending -> Held from anything else. A plain property (not an attribute),
     * so it is never saved.
     */
    public ?string $previousStatus = null;

    protected static function booted(): void
    {
        static::updating(function (Payment $payment) {
            $payment->previousStatus = $payment->getOriginal('status');
        });
    }

    protected $primaryKey = 'payment_id';

    protected $fillable = [
        'reservation_id',
        'payment_type',
        'billing_period',
        'amount',
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
    ];

    protected $casts = [
        'billing_period' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
        'payout_owed_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function scopePaidOut($query)
    {
        return $query->whereNotNull('payout_id');
    }

    /**
     * 'Pending Payout' / 'Paid Out' / null, derived from `payout_owed_at` and `payout_id` (there is
     * no stored status). Kept as an attribute so views and the mobile payload read the same name.
     */
    public function getPayoutStatusAttribute(): ?string
    {
        if ($this->payout_id !== null) {
            return 'Paid Out';
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
     * The one place a Pending checkout becomes settled. The webhook and the poll fallback can both see the same
     * Pending row, so the row is re-read under a lock and only the first caller wins. Returns the settled row,
     * or null when it was no longer Pending (a replayed webhook, a second poll): callers must then do nothing.
     */
    public function settlePending(array $attributes): ?Payment
    {
        return DB::transaction(function () use ($attributes) {
            $locked = static::whereKey($this->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'Pending') {
                return null;
            }

            $locked->update($attributes);

            return $locked;
        });
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
