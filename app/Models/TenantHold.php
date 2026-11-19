<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A landlord's report that a tenant has not paid, and what the platform did about it. The tenant keeps their account
 * (account_status is untouched) but cannot start new things while the hold is active. Changed only through
 * App\Services\Holds\HoldService, which also writes the audit rows.
 */
class TenantHold extends Model
{
    protected $primaryKey = 'hold_id';

    public const PENDING = 'pending_admin';
    public const ACTIVE = 'active';
    public const RELEASED = 'released';
    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING  => 'Pending review',
        self::ACTIVE   => 'On hold',
        self::RELEASED => 'Released',
        self::REJECTED => 'Rejected',
    ];

    /** Why a hold ended: rent paid, landlord or admin released it, the debt was dropped, or the source hold ended. */
    public const RELEASE_REASONS = [
        'paid'            => 'Rent paid',
        'landlord'        => 'Released by the landlord',
        'admin'           => 'Released by an admin',
        'source_released' => 'The original hold ended',
        'debt_dropped'    => 'Landlord stopped collecting or the tenancy ended',
    ];

    protected $fillable = [
        'tenant_id', 'landlord_id', 'reservation_id', 'source_hold_id', 'amount_owed', 'reason', 'status',
        'tenant_note', 'flagged_at', 'confirmed_by', 'confirmed_at', 'rejected_note', 'released_by',
        'released_at', 'release_reason',
    ];

    protected $casts = [
        'amount_owed'  => 'decimal:2',
        'flagged_at'   => 'datetime',
        'confirmed_at' => 'datetime',
        'released_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id', 'user_id');
    }

    public function landlord(): BelongsTo
    {
        return $this->belongsTo(User::class, 'landlord_id', 'user_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class, 'reservation_id', 'reservation_id');
    }

    public function sourceHold(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_hold_id', 'hold_id');
    }

    public function copies(): HasMany
    {
        return $this->hasMany(self::class, 'source_hold_id', 'hold_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(HoldMatchAlert::class, 'hold_id', 'hold_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }

    /** Waiting for an admin, or in force. These are the holds that still matter. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::PENDING, self::ACTIVE]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::PENDING, self::ACTIVE], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
