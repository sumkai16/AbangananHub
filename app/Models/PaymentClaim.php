<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant's "I paid the landlord directly". Not money: nothing reads a claim as a payment. Only when the
 * landlord confirms does PaymentClaims write ordinary Paid rows (payments.payment_claim_id points back here).
 */
class PaymentClaim extends Model
{
    protected $primaryKey = 'payment_claim_id';

    public const TYPES = ['MoveIn', 'Monthly'];

    /** The apps a QR payment can come from, plus Cash: handed over in person, reported without a reference number. */
    public const METHODS = ['GCash', 'Maya', 'GoTyme', 'Bank Transfer', 'Cash', 'Other'];

    protected $fillable = [
        'reservation_id',
        'claim_type',
        'billing_period',
        'amount',
        'payment_method',
        'reference_no',
        'paid_on',
        'note',
        'status',
        'filed_by',
        'decided_by',
        'decided_at',
        'rejection_reason',
        'disputed_at',
        'dispute_report_id',
    ];

    protected function casts(): array
    {
        return [
            'billing_period' => 'date',
            'paid_on' => 'date',
            'amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'disputed_at' => 'datetime',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function filer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'filed_by', 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'payment_claim_id', 'payment_claim_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isCash(): bool
    {
        return $this->payment_method === 'Cash';
    }

    /** A rejected report the tenant has not yet sent to an admin. */
    public function canBeDisputed(): bool
    {
        return $this->status === 'Rejected' && $this->disputed_at === null;
    }

    public function disputeReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'dispute_report_id', 'report_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'Pending');
    }
}
