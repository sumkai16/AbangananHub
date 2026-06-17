<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id', 'property_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public static function toggle(int $tenantId, int $propertyId): bool
    {
        $existing = static::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->first();

        if ($existing) {
            $existing->delete();
            return false;
        }

        static::create([
            'tenant_id'  => $tenantId,
            'property_id' => $propertyId,
        ]);

        return true;
    }

    public static function isFavoritedBy(int $tenantId, int $propertyId): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->exists();
    }
}