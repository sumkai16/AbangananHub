<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where a landlord is paid: the GCash number and the name on the account. One row per user, kept off
 * `users` so it is never part of a nested user payload. Written through User::savePayoutAccount().
 */
class UserPayoutAccount extends Model
{
    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'gcash_number',
        'gcash_account_name',
        'accepts_online',
        'payouts_hold_until',
        'paused_at',
        'paused_by',
        'payout_approved_at',
        'payout_approved_by',
    ];

    protected function casts(): array
    {
        return [
            'accepts_online' => 'boolean',
            'payouts_hold_until' => 'datetime',
            'paused_at' => 'datetime',
            'payout_approved_at' => 'datetime',
        ];
    }

    /**
     * Whether a tenant may be offered online payment for this landlord: GCash details on file (the money
     * has to be able to reach them) and no opt-out. Memoised on the request per landlord, so a list of
     * reservations costs one query per distinct landlord instead of one per row.
     */
    public static function acceptsOnline(int $landlordId): bool
    {
        $attributes = request()->attributes;
        $key = 'accepts_online:'.$landlordId;

        if (! $attributes->has($key)) {
            $account = static::find($landlordId);

            $attributes->set($key, $account !== null
                && filled($account->gcash_number)
                && filled($account->gcash_account_name)
                && $account->accepts_online);
        }

        return $attributes->get($key);
    }

    /** Drop the memo after the account changes, so the same request sees the new answer. */
    public static function forgetAcceptsOnline(int $landlordId): void
    {
        request()->attributes->remove('accepts_online:'.$landlordId);
    }

    /** An admin has looked at this GCash account and agreed money may be sent to it. */
    public function isApproved(): bool
    {
        return $this->payout_approved_at !== null;
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }

    /** Inside the 48 hours after the GCash details changed. */
    public function isOnHold(): bool
    {
        return $this->payouts_hold_until !== null && $this->payouts_hold_until->isFuture();
    }

    public function pausedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paused_by', 'user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
