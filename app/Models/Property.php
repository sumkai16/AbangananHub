<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Property extends Model
{
    /**
     * Price stops the search bar's budget slider moves along. Not linear on purpose:
     * most rents sit low (median ~₱3,000, max ~₱22,000), so the steps are fine there
     * and coarse above ₱10,000. Position 0 means "no minimum", the last means "no maximum".
     */
    public const SEARCH_PRICE_STOPS = [0, 1000, 1500, 2000, 2500, 3000, 3500, 4000, 4500, 5000, 6000, 7000, 8000, 9000, 10000, 12500, 15000, 17500, 20000, 25000, 30000];

    protected $primaryKey = 'property_id';
   protected $fillable = [
    'landlord_id',
    'title',
    'description',
    'house_rules',
    'property_type',
    'living_arrangement',
    'occupancy_preference',
    'water_included',
    'electricity_included',
    'internet_included',
    'association_fees_included',
    'utilities_separately_metered',
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
        'latitude'                      => 'decimal:7',
        'longitude'                     => 'decimal:7',
        'house_rules'                   => 'array',
        'water_included'                => 'boolean',
        'electricity_included'          => 'boolean',
        'internet_included'             => 'boolean',
        'association_fees_included'     => 'boolean',
        'utilities_separately_metered'  => 'boolean',
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
     * property is only badge-worthy once its ownership document (Proof of
     * Ownership, or Authorization/SPA for a non-owner landlord) is currently
     * verified, not merely because the listing itself was approved, nor
     * because some other unrelated document type (a permit, a tax
     * declaration) happens to be on file. See PropertyDocument::OWNERSHIP_TYPES.
     *
     * Checks the already-loaded `documents` relation when available (index
     * page, one query for N cards via with('documents')) instead of issuing
     * a fresh query per property.
     */
    public function hasVerifiedDocuments(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->contains(
                fn ($document) => $document->status === 'Verified'
                    && (! $document->expiry_date || ! $document->expiry_date->isPast())
                    && in_array($document->document_type, PropertyDocument::OWNERSHIP_TYPES, true)
            );
        }

        return $this->documents()
            ->currentlyValid()
            ->whereIn('document_type', PropertyDocument::OWNERSHIP_TYPES)
            ->exists();
    }

    /**
     * Query-side twin of hasVerifiedDocuments(), for filtering a set of
     * properties (the browse page's "Verified" tab) rather than checking one.
     */
    public function scopeVerified($query)
    {
        return $query->whereHas('documents', function ($q) {
            $q->currentlyValid()->whereIn('document_type', PropertyDocument::OWNERSHIP_TYPES);
        });
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
     * Apply tenant browse filters (location, type, price_min/price_max, verified, amenities)
     * and sorting (newest | price_low | price_high | top_rated).
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

        if (!empty($filters['price_min']) || !empty($filters['price_max'])) {
            $priceMin = $filters['price_min'] ?? null;
            $priceMax = $filters['price_max'] ?? null;
            $query->whereHas('units', function ($q) use ($priceMin, $priceMax) {
                $q->where('availability_status', 'Available')
                  ->where('verification_status', 'Approved');

                if (!empty($priceMin)) {
                    $q->where('rental_fee', '>=', $priceMin);
                }
                if (!empty($priceMax)) {
                    $q->where('rental_fee', '<=', $priceMax);
                }
            });
        }

        if (!empty($filters['verified'])) {
            $query->verified();
        }

        // "Must have" — AND semantics: a property matches only once every
        // selected amenity is present, on the property itself OR on any of
        // its units (a tenant filtering "Wi-Fi" doesn't care which level it's
        // assigned at — same treatment the show page gives Building/Room
        // amenities as one combined offering).
        if (!empty($filters['amenities'])) {
            foreach ($filters['amenities'] as $amenityId) {
                $query->where(function ($q) use ($amenityId) {
                    // Qualified column: the units.amenities join pulls in
                    // both amenities.amenity_id and unit_amenities.amenity_id,
                    // so a bare "amenity_id" is ambiguous to MySQL.
                    $q->whereHas('amenities', fn ($aq) => $aq->where('amenities.amenity_id', $amenityId))
                      ->orWhereHas('units.amenities', fn ($aq) => $aq->where('amenities.amenity_id', $amenityId));
                });
            }
        }

        // Property-level facts (see App\Support\BrowseFilters — values are whitelisted before they get here).
        if (!empty($filters['living'])) {
            $query->where('living_arrangement', $filters['living']);
        }

        if (!empty($filters['for'])) {
            // A tenant of that gender can take a matching property or one with no preference.
            $query->whereIn('occupancy_preference', [\App\Support\BrowseFilters::SUITABLE_FOR[$filters['for']], 'No Preference']);
        }

        if (!empty($filters['furnishing'])) {
            $query->whereHas('units', fn ($q) => $q->where('availability_status', 'Available')
                ->where('verification_status', 'Approved')
                ->where('furnishing_status', $filters['furnishing']));
        }

        // "Must allow": the property must not carry the restricting rule. A property with no rules at all
        // (NULL) carries none, so it matches — NOT JSON_CONTAINS(NULL, …) would otherwise drop it.
        foreach ((array) ($filters['rules'] ?? []) as $ruleKey) {
            $restriction = \App\Support\BrowseFilters::RULES[$ruleKey][1] ?? null;
            if ($restriction) {
                $query->where(fn ($q) => $q->whereNull('house_rules')->orWhereJsonDoesntContain('house_rules', $restriction));
            }
        }

        match ($filters['sort'] ?? null) {
            'price_low'  => $query->orderBy('min_rental_fee', 'asc'),
            'price_high' => $query->orderByDesc('min_rental_fee'),
            'top_rated'  => $query->orderByDesc('avg_rating')->orderByDesc('review_count'),
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
    /**
     * Every area that has something bookable, for the "Where" search box's
     * suggestion dropdown: each city, and each barangay within it ("Poblacion,
     * Carcar City"). `value` is what gets typed into the box, and it matches
     * scopeBrowseFilters' address LIKE because addresses are "Barangay, City, Cebu".
     *
     * Cached for ten minutes; a new listing's area shows up in suggestions within that.
     *
     * @return list<array{label: string, value: string, sub: string, type: 'city'|'barangay'}>
     */
    public static function searchLocations(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('search.locations', now()->addMinutes(10), function () {
            $rows = static::live()
                ->whereHas('units', fn ($q) => $q->where('availability_status', 'Available')->where('verification_status', 'Approved'))
                ->selectRaw('city_municipality, barangay, COUNT(*) as cnt')
                ->groupBy('city_municipality', 'barangay')
                ->get();

            $count = fn (int $n) => $n.' '.\Illuminate\Support\Str::plural('listing', $n);

            $cities = $rows->groupBy('city_municipality')
                ->map(fn ($group, $city) => ['label' => $city, 'value' => $city, 'sub' => $count((int) $group->sum('cnt')), 'type' => 'city', 'n' => (int) $group->sum('cnt')])
                ->sortByDesc('n');

            $barangays = $rows->filter(fn ($r) => filled($r->barangay) && $r->barangay !== $r->city_municipality)
                ->map(fn ($r) => ['label' => $r->barangay, 'value' => $r->barangay.', '.$r->city_municipality, 'sub' => $r->city_municipality.' · '.$count((int) $r->cnt), 'type' => 'barangay', 'n' => (int) $r->cnt])
                ->sortByDesc('n');

            return $cities->concat($barangays)->map(fn ($i) => \Illuminate\Support\Arr::except($i, 'n'))->values()->all();
        });
    }
    /**
     * The quick-pick price ranges in the search bar's Budget popover, each with
     * how many live listings have an available unit inside it (so a tenant never
     * picks an empty range). Bounds are inclusive, matching scopeBrowseFilters.
     * Breaks sit where the Cebu market does: bedspaces and rooms under ₱5,000,
     * apartments and condos above. Cached for ten minutes.
     *
     * @return list<array{label: string, min: int|null, max: int|null, count: int}>
     */
    public static function budgetBands(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('search.budget_bands', now()->addMinutes(10), function () {
            return collect([
                ['Up to ₱3,000', null, 3000],
                ['₱3,000 – ₱5,000', 3000, 5000],
                ['₱5,000 – ₱10,000', 5000, 10000],
                ['₱10,000 – ₱20,000', 10000, 20000],
                ['₱20,000 & up', 20000, null],
            ])->map(fn ($b) => [
                'label' => $b[0],
                'min' => $b[1],
                'max' => $b[2],
                'count' => static::live()->whereHas('units', function ($q) use ($b) {
                    $q->where('availability_status', 'Available')->where('verification_status', 'Approved');
                    if ($b[1] !== null) {
                        $q->where('rental_fee', '>=', $b[1]);
                    }
                    if ($b[2] !== null) {
                        $q->where('rental_fee', '<=', $b[2]);
                    }
                })->count(),
            ])->all();
        });
    }
    /**
     * How many live listings have an available unit in each step of the budget
     * slider: one count per gap between SEARCH_PRICE_STOPS (so bar i sits between
     * slider positions i and i+1). A listing counts once per bar even with several
     * units in it; anything at or above the top stop lands in the last bar.
     * Cached for ten minutes.
     *
     * @return list<int>
     */
    public static function budgetHistogram(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('search.budget_histogram', now()->addMinutes(10), function () {
            $stops = self::SEARCH_PRICE_STOPS;
            $bins = count($stops) - 1;
            $seen = array_fill(0, $bins, []);

            PropertyUnit::query()
                ->where('availability_status', 'Available')
                ->where('verification_status', 'Approved')
                ->whereHas('property', fn ($q) => $q->live())
                ->get(['property_id', 'rental_fee'])
                ->each(function ($unit) use ($stops, $bins, &$seen) {
                    $fee = (float) $unit->rental_fee;
                    $bin = $bins - 1;
                    for ($i = 0; $i < $bins; $i++) {
                        if ($fee < $stops[$i + 1]) {
                            $bin = $i;
                            break;
                        }
                    }
                    $seen[$bin][$unit->property_id] = true;
                });

            return array_map('count', $seen);
        });
    }
}
