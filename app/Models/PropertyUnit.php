<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyUnit extends Model
{
    protected $primaryKey = 'unit_id';

 protected $fillable = [
    'property_id',
    'unit_label',
    'unit_type',
    'floor',
    'description',
    'rental_fee',
    'security_deposit',
    'occupancy_limit',
    'availability_status',
    'verification_status',
    'rejection_reason',
    'vacated_at',
];

    protected function casts(): array
    {
        return [
            'rental_fee' => 'decimal:2',
            'vacated_at' => 'datetime',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }
public function scopeMaintenance($query)
{
    return $query->where('availability_status', 'Maintenance');
}
 public function media()
{
    return $this->hasMany(UnitMedia::class, 'unit_id', 'unit_id');
}

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'unit_amenities', 'unit_id', 'amenity_id');
    }

    public function activeReservation()
    {
        return $this->hasOne(Reservation::class, 'unit_id', 'unit_id')
                     ->whereIn('rental_status', ['Rental Agreement Signed', 'Occupied'])
                     ->latestOfMany('reservation_id');
    }

    // ─── Status Helpers ──────────────────────────────────────
    public function isApproved(): bool
    {
        return $this->verification_status === 'Approved';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'Pending';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'Rejected';
    }

    // ─── Scopes ──────────────────────────────────────────────
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'Approved');
    }

    public function scopeAvailable($query)
    {
        return $query->where('availability_status', 'Available')
                     ->where('verification_status', 'Approved');
    }

    public function scopeReserved($query)
    {
        return $query->where('availability_status', 'Reserved');
    }

    public function scopeOccupied($query)
    {
        return $query->where('availability_status', 'Occupied');
    }
}