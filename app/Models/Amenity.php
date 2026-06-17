<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class Amenity extends Model
{
    protected $fillable = [
        'amenity_name',
    ];
protected $primaryKey = 'amenity_id';
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