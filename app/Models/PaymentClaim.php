<?php

namespace App\Models;

use App\Services\LandlordRentDueCount;
use App\Services\RentLedger;
use App\Support\RentPaymentAllocator;
use Illuminate\Support\Carbon;
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

    /**
     * A waiting report is part of the sidebar's Payments number, so filing, deciding or withdrawing one clears
     * the cached count. Hooked here so every path that touches a claim is covered.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $claim) => LandlordRentDueCount::forgetForReservation($claim->reservation_id));
        static::deleted(fn (self $claim) => LandlordRentDueCount::forgetForReservation($claim->reservation_id));
    }

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

    /**
     * The months a rent report spreads over, oldest first: what the landlord's confirmation will write. A report
     * for the oldest unpaid month with enough money in it covers that month and the ones after it, which is how the
     * ledger's allocator already splits any lump of rent, so this asks that same class and cannot drift from it.
     * A confirmed report reads back from the rows it wrote, since the ledger already contains them.
     *
     * @return array<int, array{billing_period: Carbon, amount: float}>
     */
    public function monthsCovered(?Reservation $reservation = null): array
    {
        if ($this->claim_type !== 'Monthly' || ! $this->billing_period) {
            return [];
        }

        if ($this->status === 'Confirmed') {
            return $this->payments()->orderBy('billing_period')->get()
                ->map(fn (Payment $p) => ['billing_period' => $p->billing_period, 'amount' => (float) $p->amount])
                ->all();
        }

        $reservation ??= $this->reservation;

        return RentPaymentAllocator::allocate(
            RentLedger::for($reservation)->periods(),
            $this->billing_period->copy()->startOfMonth(),
            fn (Carbon $month) => $reservation->rentFor($month),
            (float) $this->amount,
        );
    }

    /**
     * What the report is for, in words: "move-in payment", "rent for August 2026", or "rent for Aug 2026 – Oct 2026"
     * when the money spreads over several months. Used in the landlord's notification and on the confirm card.
     */
    public function coverageLabel(?Reservation $reservation = null): string
    {
        if ($this->claim_type === 'MoveIn') {
            return 'move-in payment';
        }

        $months = $this->monthsCovered($reservation);

        if (count($months) <= 1) {
            return 'rent for '.$this->billing_period->format('F Y');
        }

        return 'rent for '.$months[0]['billing_period']->format('M Y').' – '.end($months)['billing_period']->format('M Y');
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
