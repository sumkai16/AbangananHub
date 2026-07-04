<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Property extends Model
{
    protected $primaryKey = 'property_id';
    protected $fillable = [
        'landlord_id',
        'title',
        'description',
        'property_type',
        'address',
        'latitude',
        'longitude',
        'verification_status',
    ];

    protected function casts(): array
    {
        return [
            'latitude'   => 'decimal:7',
            'longitude'  => 'decimal:7',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function media()
    {
        return $this->hasMany(PropertyMedia::class, 'property_id', 'property_id');
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(
            Amenity::class,        // related model
            'property_amenities',  // pivot table name
            'property_id',         // FK on pivot pointing to THIS model
            'amenity_id'           // FK on pivot pointing to Amenity
        );
    }
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'property_id', 'property_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'property_id', 'property_id');
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class, 'property_id', 'property_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'property_id', 'property_id');
    }

    public function reports()
    {
        return $this->hasMany(Report::class, 'property_id', 'property_id');
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
    public function units()
    {
        return $this->hasMany(PropertyUnit::class, 'property_id', 'property_id');
    }

    public function getMinRentalFeeAttribute(): ?string
    {
        return $this->units
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->min('rental_fee');
    }

    public function getOccupancyLimitAttribute(): ?int
    {
        return $this->units
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->max('occupancy_limit');
    }

    public function getAvailabilityStatusAttribute(): string
    {
        $hasAvailable = $this->units
            ->where('availability_status', 'Available')
            ->where('verification_status', 'Approved')
            ->isNotEmpty();

        return $hasAvailable ? 'Available' : 'Unavailable';
    }
}