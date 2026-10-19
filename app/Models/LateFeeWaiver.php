<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A landlord dropping the late fee on one billing month. RentLedger::periods() reads these and gives
 * the month `late_fee = 0`. The waiver is also written to the audit log.
 */
class LateFeeWaiver extends Model
{
    protected $primaryKey = 'waiver_id';

    protected $fillable = [
        'reservation_id',
        'billing_period',
        'waived_by',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'billing_period' => 'date',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by', 'user_id');
    }
}
