<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A day the landlord isn't available for viewings, across all their
 * properties. See plans/unit-viewing-scheduling.md.
 */
class LandlordBlockedDate extends Model
{
    protected $primaryKey = 'blocked_date_id';

    protected $fillable = ['landlord_id', 'date', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
