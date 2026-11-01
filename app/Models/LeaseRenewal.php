<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contract extended by its landlord: a new move-out date and the rent from `rent_effective_from` on.
 * Months before that date keep the rent they had, so a renewal never rewrites what was already billed.
 */
class LeaseRenewal extends Model
{
    protected $primaryKey = 'lease_renewal_id';

    protected $fillable = [
        'reservation_id',
        'new_move_out_date',
        'new_monthly_rent',
        'rent_effective_from',
        'renewed_by',
    ];

    protected function casts(): array
    {
        return [
            'new_move_out_date' => 'date',
            'new_monthly_rent' => 'decimal:2',
            'rent_effective_from' => 'date',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function renewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renewed_by', 'user_id');
    }
}
