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
    'is_furnished',
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
            'is_furnished'      => 'boolean',
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
    /**
     * The label/value rows the unit detail views (landlord modal and public panel)
     * list under the price. Only rows that have a value, in display order.
     * Bathroom count and type are one row ("1 shared bath") and the bedroom
     * count its own, so nothing is stated twice.
     *
     * @return list<array{0: string, 1: string}>
     */
    public function specRows(): array
    {
        $bathroom = null;
        if ($this->bathrooms !== null) {
            $kind = $this->bathroom_type ? Str::lower(Str::before($this->bathroom_type, ' ')).' ' : '';
            $bathroom = $this->bathrooms.' '.$kind.Str::plural('bath', $this->bathrooms);
        } elseif ($this->bathroom_type) {
            $bathroom = $this->bathroom_type;
        }

        return collect([
            ['Capacity', $this->occupancy_limit ? $this->occupancy_limit.' '.($this->occupancy_limit == 1 ? 'person' : 'people') : null],
            ['Security deposit', $this->security_deposit !== null ? '₱'.number_format($this->security_deposit, 0) : 'No deposit'],
            ['Floor area', $this->floor_area_label],
            ['Furnishing', $this->furnishing_status],
            ['Bedrooms', $this->bedrooms === null ? null : ($this->bedrooms == 0 ? 'Studio' : (string) $this->bedrooms)],
            ['Bathroom', $bathroom],
            ['Kitchen', $this->kitchen_type],
        ])->filter(fn ($row) => filled($row[1]))->values()->all();
    }

    /**
     * Amenities minus the ones that only restate a spec row above
     * ("Shared Bathroom" next to a "Bathroom: 1 shared bath" row).
     */
    public function amenitiesBeyondSpecs(): Collection
    {
        $skip = array_merge(
            $this->bathroom_type || $this->bathrooms !== null ? ['Private Bathroom', 'Shared Bathroom'] : [],
            $this->kitchen_type ? ['Private Kitchen', 'Shared Kitchen'] : [],
        );

        return $this->amenities->reject(fn ($a) => in_array($a->amenity_name, $skip, true))->values();
    }
}
