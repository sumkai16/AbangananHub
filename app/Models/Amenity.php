<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Amenity extends Model
{
    protected $fillable = [
        'amenity_name',
        'scope',
        'category',
    ];
protected $primaryKey = 'amenity_id';

    /** Unit amenities that contradict each other: a unit has one or the other, never both. */
    public const EXCLUSIVE_UNIT_AMENITIES = ['Private Bathroom', 'Shared Bathroom'];

    /** True when the selection (amenity ids) ticks more than one of the mutually exclusive amenities. */
    public static function hasConflict(array $ids): bool
    {
        if ($ids === []) {
            return false;
        }

        return static::whereIn('amenity_id', $ids)
            ->whereIn('amenity_name', self::EXCLUSIVE_UNIT_AMENITIES)
            ->count() > 1;
    }

    /** Validation rule for a unit's `amenities` array. */
    public static function exclusiveRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (self::hasConflict((array) $value)) {
                $fail('A unit has a private or a shared bathroom, not both.');
            }
        };
    }

    // ─── Query scopes ────────────────────────────────────────

    public function scopeForProperty($query)
    {
        return $query->whereIn('scope', ['property', 'both']);
    }

    public function scopeForUnit($query)
    {
        return $query->whereIn('scope', ['unit', 'both']);
    }

    // ─── Accessors ───────────────────────────────────────────

    // Convenience alias so views can use $amenity->name
    public function getNameAttribute(): ?string
    {
        return $this->amenity_name;
    }

    // ─── Relationships ───────────────────────────────────────

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(
            Property::class,
            'property_amenities',
            'amenity_id',    // FK on pivot pointing to THIS model
            'property_id'    // FK on pivot pointing to Property
        );
    }
}