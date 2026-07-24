<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record that one rent-reminder milestone has fired for one billing period.
 *
 * Exists purely so the nightly reminder command is idempotent — see the
 * migration. No updated_at: a reminder is written once and never changes.
 */
class RentReminder extends Model
{
    protected $primaryKey = 'reminder_id';

    public const UPDATED_AT = null;

    protected $fillable = [
        'reservation_id',
        'billing_period',
        'milestone',
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
}
