<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'tenant_id',
        'property_id',
        'landlord_id',
        'rating',
        'review_comment',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    // ─── Relationships ───────────────────────────────────────

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public static function canReview(int $tenantId, int $propertyId): bool
    {
        $hasApprovedReservation = Reservation::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->where('reservation_status', 'Approved')
            ->exists();

        $alreadyReviewed = static::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->exists();

        return $hasApprovedReservation && !$alreadyReviewed;
    }

    public static function averageRatingFor(int $propertyId): float
    {
        return static::where('property_id', $propertyId)
            ->avg('rating') ?? 0.0;
    }
}