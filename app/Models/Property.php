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
    'house_rules',
    'property_type',
    'address',
    'latitude',
    'longitude',
    'verification_status',
];

    protected function casts(): array
{
    return [
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
        'house_rules' => 'array',
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

    /**
     * Base tenant-facing browse query: approved properties that have at
     * least one available, approved unit, plus the aggregate columns the
     * listing UIs rely on (min_rental_fee, avg_rating, review_count).
     * Shared by the web PropertyController query and the API.
     */
    public function scopeBrowsable($query)
    {
        return $query
            ->where('verification_status', 'Approved')
            ->whereHas('units', function ($q) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved');
            })
            ->withMin(['units as min_rental_fee' => function ($q) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved');
            }], 'rental_fee')
            ->withAvg(['reviews as avg_rating' => function ($q) {
                $q->where('is_hidden', false);
            }], 'rating')
            ->withCount(['reviews as review_count' => function ($q) {
                $q->where('is_hidden', false);
            }]);
    }

    /**
     * Apply tenant browse filters (location, type, price_max, verified)
     * and sorting (newest | price_low | price_high).
     */
    public function scopeBrowseFilters($query, array $filters)
    {
        if (!empty($filters['location'])) {
            $location = $filters['location'];
            $query->where(function ($q) use ($location) {
                $q->where('address', 'like', '%' . $location . '%')
                  ->orWhere('title', 'like', '%' . $location . '%');
            });
        }

        if (!empty($filters['type'])) {
            $query->where('property_type', $filters['type']);
        }

        if (!empty($filters['price_max'])) {
            $priceMax = $filters['price_max'];
            $query->whereHas('units', function ($q) use ($priceMax) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved')
                  ->where('rental_fee', '<=', $priceMax);
            });
        }

        if (!empty($filters['verified'])) {
            $query->whereHas('landlord.rentalBusiness');
        }

        match ($filters['sort'] ?? null) {
            'price_low'  => $query->orderBy('min_rental_fee', 'asc'),
            'price_high' => $query->orderByDesc('min_rental_fee'),
            default      => $query->latest('created_at'),
        };

        return $query;
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