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