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

    public function isPaid(): bool
    {
        return $this->status === 'Paid';
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
