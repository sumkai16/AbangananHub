<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $primaryKey = 'review_id';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'landlord_id',
        'rating',
        'review_comment',
        'landlord_reply',
        'landlord_replied_at',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_hidden' => 'boolean',
            'landlord_replied_at' => 'datetime',
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

    public function landlord()
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * A tenant earns the right to review the moment they move in, and — this
     * used to be the bug — keeps it after the stay ends. The original check
     * required rental_status === 'Occupied' exactly, so the instant
     * Reservation::endTenancy() flipped a tenancy to 'Completed' the review
     * window silently closed for that reservation. A review about a stay
     * should be writable once the stay is over, not only while it's ongoing.
     */
    public static function canReview(int $tenantId, int $propertyId): bool
    {
        $hasLivedThere = Reservation::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->whereIn('rental_status', ['Occupied', 'Completed'])
            ->exists();

        $alreadyReviewed = static::where('tenant_id', $tenantId)
            ->where('property_id', $propertyId)
            ->exists();

        return $hasLivedThere && !$alreadyReviewed;
    }

    public static function averageRatingFor(int $propertyId): float
    {
        return static::where('property_id', $propertyId)
            ->where('is_hidden', false)
            ->avg('rating') ?? 0.0;
    }
}