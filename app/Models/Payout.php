<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One GCash transfer to a landlord, covering one or more payments: sent automatically through PayMongo
 * (`Processing` until PayMongo confirms it, then `Sent`, or `Failed`), or recorded by an admin who sent it
 * by hand (`Sent` straight away). The GCash details are copied onto the row when it is created, so the
 * record still says where the money went after the landlord changes their payout account.
 */
class Payout extends Model
{
    protected $primaryKey = 'payout_id';

    protected $fillable = [
        'landlord_id',
        'amount',
        'status',
        'gcash_number',
        'gcash_account_name',
        'payout_reference',
        'paymongo_transfer_id',
        'failure_reason',
        'resolved_at',
        'paid_out_by',
        'paid_out_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_out_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function isProcessing(): bool
    {
        return $this->status === 'Processing';
    }

    public function isSent(): bool
    {
        return $this->status === 'Sent';
    }

    public function isFailed(): bool
    {
        return $this->status === 'Failed';
    }

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
