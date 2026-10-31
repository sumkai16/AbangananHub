<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * One tenant on one landlord's list, with that landlord's Tenant ID (TN-0001). A tenant can be on the list
 * before any contract exists, which is what makes "Pending" possible.
 *
 * The status (Pending / Active / Former) is never stored: it is read from the tenant's reservations on this
 * landlord's properties, so it cannot drift from them. "Deleting" a tenant who has history only archives the row.
 */
class LandlordTenant extends Model
{
    protected $primaryKey = 'landlord_tenant_id';

    protected $fillable = [
        'landlord_id',
        'tenant_id',
        'tenant_number',
        'archived_at',
    ];

    /** Reservation statuses that make a tenant Active: a signed lease, a held unit, or a unit they live in. */
    public const ACTIVE_STATUSES = ['Rental Agreement Signed', 'Reserved', 'Occupied'];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }

    /** The Tenant ID landlords see, e.g. TN-0007. */
    public function code(): string
    {
        return 'TN-'.str_pad((string) $this->tenant_number, 4, '0', STR_PAD_LEFT);
    }

    /** This tenant's reservations on this landlord's properties (every status). */
    public function reservations(): Builder
    {
        return Reservation::where('tenant_id', $this->tenant_id)
            ->whereHas('property', fn ($q) => $q->where('landlord_id', $this->landlord_id));
    }

    /**
     * Pending, Active or Former, read from the statuses of this tenant's reservations with the landlord.
     *
     * @param  iterable<string>  $statuses
     */
    public static function statusFrom(iterable $statuses): string
    {
        $statuses = collect($statuses);

        if ($statuses->intersect(self::ACTIVE_STATUSES)->isNotEmpty()) {
            return 'Active';
        }

        return $statuses->contains('Completed') ? 'Former' : 'Pending';
    }

    /**
     * Puts a tenant on a landlord's list (or back on it, if they were archived) and returns the row.
     *
     * The number is the landlord's highest plus one. Two requests can read the same highest number; the unique
     * (landlord_id, tenant_number) index refuses the second insert and it simply tries again.
     */
    public static function ensureFor(int $landlordId, int $tenantId): self
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($landlordId, $tenantId) {
                    $existing = self::where('landlord_id', $landlordId)
                        ->where('tenant_id', $tenantId)
                        ->lockForUpdate()
                        ->first();

                    if ($existing) {
                        if ($existing->archived_at !== null) {
                            $existing->update(['archived_at' => null]);
                        }

                        return $existing;
                    }

                    $next = (int) self::where('landlord_id', $landlordId)->lockForUpdate()->max('tenant_number') + 1;

                    return self::create([
                        'landlord_id' => $landlordId,
                        'tenant_id' => $tenantId,
                        'tenant_number' => $next,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }
}
