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
    'city_municipality',
    'barangay',
    'latitude',
    'longitude',
    'verification_status',
    'publication_status',
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

    public function documents()
    {
        return $this->hasMany(PropertyDocument::class, 'property_id', 'property_id');
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

    public function isSuspended(): bool
    {
        return $this->publication_status === 'Suspended';
    }

    public function isDraft(): bool
    {
        return $this->publication_status === 'Draft';
    }

    /**
     * Which wizard step an in-progress Draft should resume at, derived from
     * what the row actually has rather than a stored pointer — a stored
     * `current_step` column would drift the moment something is edited
     * outside the wizard. A property row only exists once Location (step 2)
     * is saved, so that step is never a resume target.
     */
    public function resumeWizardStep(): string
    {
        if ($this->amenities()->count() === 0) {
            return 'amenities';
        }

        if ($this->documents()->count() === 0) {
            return 'documents';
        }

        if ($this->units()->count() === 0) {
            return 'units';
        }

        return 'review';
    }

    /**
     * The public "Verified Property" badge's single source of truth — a
     * property is only badge-worthy once a document actually backs it, not
     * merely because the listing itself was approved.
     */
    public function hasVerifiedDocuments(): bool
    {
        return $this->documents()->currentlyValid()->exists();
    }

    /**
     * "Is this listing publicly viewable at all" — the single source of
     * truth for the property-page 404 gate and the tenant reservation gate.
     * Deliberately does NOT require an available unit (the public page
     * renders fine with none); that stricter condition is scopeBrowsable().
     *
     * Two independent facts, both required: verification_status answers "is
     * this legitimate" (admin's call), publication_status answers "should it
     * be live right now" (landlord's call day-to-day, admin's call when
     * Suspended). Neither implies the other.
     */
    public function isLive(): bool
    {
        return $this->verification_status === 'Approved'
            && $this->publication_status === 'Published';
    }

    // ─── Scopes ──────────────────────────────────────────────

    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'Approved');
    }

    /**
     * Excludes in-progress wizard Drafts. Every admin-facing query over all
     * properties (the approval queue and its counts, the catalogue) must
     * route through this rather than hand-checking publication_status, so a
     * half-built listing never surfaces where an admin would mistake it for
     * something actually awaiting review.
     */
    public function scopeSubmitted($query)
    {
        return $query->where('publication_status', '!=', 'Draft');
    }

    /**
     * Query equivalent of isLive() — every tenant-facing visibility check
     * (browse, the Areas header menu, gates) must route through this scope
     * or isLive() rather than comparing verification_status directly, so a
     * future visibility rule only needs to change in one place.
     */
    public function scopeLive($query)
    {
        return $query
            ->where('verification_status', 'Approved')
            ->where('publication_status', 'Published');
    }

    /**
     * Base tenant-facing browse query: live properties that have at
     * least one available, approved unit, plus the aggregate columns the
     * listing UIs rely on (min_rental_fee, avg_rating, review_count).
     * Shared by the web PropertyController query and the API.
     */
    public function scopeBrowsable($query)
    {
        return $query
            ->live()
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