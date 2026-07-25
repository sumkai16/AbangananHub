<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    'recorded_by',
    'reference_no',
    'payment_notes',
];

    protected $casts = [
        'billing_period' => 'date',
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
        'released_at' => 'datetime',
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

    public function isPaid(): bool
    {
        return $this->status === 'Paid';
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
}
