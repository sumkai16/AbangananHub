<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One GCash transfer an admin sent to a landlord, covering one or more payments. The GCash details
 * are copied onto the row when it is created, so the record still says where the money went after
 * the landlord changes their payout account.
 */
class Payout extends Model
{
    protected $primaryKey = 'payout_id';

    protected $fillable = [
        'landlord_id',
        'amount',
        'gcash_number',
        'gcash_account_name',
        'payout_reference',
        'paid_out_by',
        'paid_out_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_out_at' => 'datetime',
    ];

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    /** The admin who recorded that the transfer was sent. */
    public function paidOutBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_out_by', 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payout_id', 'payout_id');
    }
}
