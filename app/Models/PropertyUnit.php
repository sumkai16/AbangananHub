<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PropertyUnit extends Model
{
    protected $primaryKey = 'unit_id';

    protected $fillable = [
        'property_id',
        'unit_label',
        'floor',
        'bedrooms',
        'bathrooms',
        'floor_area_sqm',
        'furnishing_status',
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
            'rental_fee'        => 'decimal:2',
            'floor_area_sqm'    => 'decimal:2',
            'vacated_at'        => 'datetime',
        ];
    }

    /**
     * The decimal:2 cast renders 24 as "24.00", which reads wrong beside a
     * unit label — every display site wants "24 sqm" / "24.5 sqm", so the
     * formatting lives here rather than in each of the six views. "sqm" over
     * the m² symbol to match how PH real estate listings actually write it
     * (Lamudi, Dot Property, local FB groups) — not the m² glyph.
     */
    public function getFloorAreaLabelAttribute(): ?string
    {
        return $this->floor_area_sqm === null
            ? null
            : rtrim(rtrim(number_format((float) $this->floor_area_sqm, 2), '0'), '.') . ' sqm';
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    public function leaseTemplate()
    {
        return $this->belongsTo(LeaseTemplate::class, 'lease_template_id', 'lease_template_id');
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
            ->whereIn('rental_status', ['Rental Agreement Signed', 'Reserved', 'Occupied'])
            ->latestOfMany('reservation_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'unit_id', 'unit_id');
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

    /**
     * What the landlord's pages print in a unit's verification chip. A Pending unit on a Pending listing is not
     * waiting for a second review: the admin decides both together, so the chip says so (one wait, not two).
     */
    public function verificationLabel(?string $propertyVerification): string
    {
        return $this->verification_status === 'Pending' && $propertyVerification === 'Pending'
            ? 'Waiting with the listing'
            : $this->verification_status;
    }

    // ─── Scopes ──────────────────────────────────────────────
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'Approved');
    }

    /**
     * Units the admin has to decide on their own: Pending, on a property that is already Approved (a unit added or
     * edited after approval). A Pending unit on a Pending property is decided with the property (ListingController::approve),
     * so it is not counted here. Every unit queue and count goes through this one definition.
     */
    public function scopeAwaitingOwnReview($query)
    {
        return $query->where('property_units.verification_status', 'Pending')
            ->whereHas('property', fn ($p) => $p->where('verification_status', 'Approved'));
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

    /**
     * The label/value rows the unit detail views (landlord modal and public panel)
     * list under the price. Only rows that have a value, in display order.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function specRows(): array
    {
        $bathroom = $this->bathrooms === null
            ? null
            : $this->bathrooms.' '.Str::plural('bath', $this->bathrooms);

        return collect([
            ['Capacity', $this->occupancy_limit ? $this->occupancy_limit.' '.($this->occupancy_limit == 1 ? 'person' : 'people') : null],
            ['Security deposit', $this->security_deposit !== null ? '₱'.number_format($this->security_deposit, 0) : 'No deposit'],
            ['Floor area', $this->floor_area_label],
            ['Furnishing', $this->furnishing_status],
            ['Bedrooms', $this->bedrooms === null ? null : ($this->bedrooms == 0 ? 'Studio' : (string) $this->bedrooms)],
            ['Bathroom', $bathroom],
        ])->filter(fn ($row) => filled($row[1]))->values()->all();
    }

    /**
     * Everything the unit detail modal shows, as one array the page hands to Alpine. One place, so the Units page
     * and a property's own Units tab can never describe the same unit differently.
     *
     * Reads only what is already loaded (media, amenities, and reservations with their tenant for the tenant
     * name) so it never lazy-loads: lazy loading is switched off outside production. The thumbnail falls back to
     * the property's own photo.
     *
     * @param  string  $returnTo  where the Edit link brings the landlord back to: 'units' (the Units page) or 'property'
     */
    public function detailPayload(?Property $property = null, string $returnTo = 'units'): array
    {
        $property ??= $this->property;

        $thumb = $this->media->firstWhere('media_type', 'Image')
            ?? $property->media->firstWhere('media_type', 'Image');

        $activeReservation = $this->relationLoaded('reservations') && in_array($this->availability_status, ['Reserved', 'Occupied'], true)
            ? $this->reservations->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)->sortByDesc('reservation_id')->first()
            : null;
        $tenantName = $activeReservation?->tenant
            ? trim($activeReservation->tenant->first_name.' '.$activeReservation->tenant->last_name)
            : null;

        $styles = match ($this->availability_status) {
            'Available' => ['tile' => 'border-[#22C55E]/25 bg-[#22C55E]/[0.07]', 'text' => 'text-[#15803D]', 'dot' => 'bg-[#22C55E]'],
            'Reserved'  => ['tile' => 'border-[#FBBF24]/35 bg-[#FBBF24]/[0.10]', 'text' => 'text-[#B45309]', 'dot' => 'bg-[#FBBF24]'],
            'Occupied'  => ['tile' => 'border-[#EF4444]/25 bg-[#EF4444]/[0.07]', 'text' => 'text-[#DC2626]', 'dot' => 'bg-[#EF4444]'],
            default     => ['tile' => 'border-[#E2E4EC] bg-[#F7F8FC]', 'text' => 'text-[#5B6A8E]', 'dot' => 'bg-[#94A3B8]'],
        };

        return [
            'label'        => $this->unit_label,
            'property'     => $property->title,
            'status'       => $this->availability_status,
            'styles'       => $styles,
            'photo'        => $thumb?->media_url,
            'rent'         => (float) $this->rental_fee,
            'deposit'      => $this->security_deposit !== null ? (float) $this->security_deposit : null,
            'capacity'     => $this->occupancy_limit,
            'floorArea'    => $this->floor_area_label,
            'floor'        => $this->floor,
            'furnishing'   => $this->furnishing_status,
            // Only rows that have a value: the modal renders exactly this list, so no row is ever hidden.
            'facts'        => array_merge($tenantName ? [['Tenant', $tenantName]] : [], $this->specRows()),
            'description'  => $this->description,
            'photos'       => $this->media->where('media_type', 'Image')->pluck('media_url')->take(5)->values(),
            'tenant'       => $tenantName,
            'amenities'    => $this->amenitiesBeyondSpecs()->map(fn ($a) => [
                'name' => $a->amenity_name,
                'icon' => \App\Support\AmenityIcons::path($a->amenity_name),
            ])->values(),
            'property_url' => route('landlord.properties.show', $property),
            'edit_url'     => route('landlord.properties.units.edit', [$property, $this, 'return_to' => $returnTo]),
        ];
    }

    /**
     * Amenities minus the ones that only restate a spec row above
     * ("Shared Bathroom" next to a "Bathroom: 1 shared bath" row).
     */
    public function amenitiesBeyondSpecs(): Collection
    {
        $skip = $this->bathrooms !== null ? ['Private Bathroom', 'Shared Bathroom'] : [];

        return $this->amenities->reject(fn ($a) => in_array($a->amenity_name, $skip, true))->values();
    }
}
