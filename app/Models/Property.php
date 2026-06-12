<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $fillable = [
        'landlord_id',
        'title',
        'description',
        'property_type',
        'address',
        'latitude',
        'longitude',
        'rental_fee',
        'occupancy_limit',
        'availability_status',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'latitude'   => 'decimal:7',
            'longitude'  => 'decimal:7',
            'rental_fee' => 'decimal:2',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    public function media()
    {
        return $this->hasMany(PropertyMedia::class);
    }

    public function amenities()
    {
        return $this->belongsToMany(Amenity::class, 'property_amenities');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    // ─── Status Helpers ──────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->availability_status === 'Available';
    }

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
        return $query->where('availability_status', 'Available');
    }
}