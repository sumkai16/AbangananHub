<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
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
    'payout_status',
    'paid_out_at',
    'paid_out_by',
    'payout_reference',
    'recorded_by',
    'reference_no',
    'payment_notes',
    'voided_at',
    'voided_by',
    'void_reason',
    'void_note',
    'replaces_payment_id',
];

    protected $casts = [
        'billing_period' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
        'paid_out_at' => 'datetime',
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
     * The admin who recorded that this payment's payout was actually sent.
     */
    public function payoutRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_out_by', 'user_id');
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
        return $this->payout_status === 'Pending Payout';
    }

    public function isPaidOut(): bool
    {
        return $this->payout_status === 'Paid Out';
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
            && $this->payout_status === null;
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
