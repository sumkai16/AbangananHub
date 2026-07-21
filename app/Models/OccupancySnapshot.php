<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OccupancySnapshot extends Model
{
    protected $primaryKey = 'snapshot_id';

    protected $fillable = [
        'landlord_id',
        'snapshot_date',
        'total_units',
        'available_units',
        'reserved_units',
        'occupied_units',
        'maintenance_units',
        'occupancy_rate',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'occupancy_rate' => 'decimal:2',
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }
}
