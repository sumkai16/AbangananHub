<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "This new account looks like a tenant who is on hold." A prompt for an admin to look, never a verdict: nothing is
 * blocked because of it. Written by App\Services\Holds\HoldMatcher. Dismissed alerts are pruned after 90 days.
 */
class HoldMatchAlert extends Model
{
    use MassPrunable;

    protected $primaryKey = 'alert_id';

    public const OPEN = 'open';
    public const DISMISSED = 'dismissed';
    public const HELD = 'held';

    /** What each matching signal is called on screen. */
    public const SIGNALS = [
        'phone'  => 'Same phone number',
        'device' => 'Same browser or device',
        'name'   => 'Same name',
        'ip'     => 'Same network address',
    ];

    protected $fillable = ['hold_id', 'user_id', 'signals', 'status', 'reviewed_by', 'reviewed_at'];

    protected $casts = [
        'signals'     => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function hold(): BelongsTo
    {
        return $this->belongsTo(TenantHold::class, 'hold_id', 'hold_id');
    }

    /** The new account. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'user_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::OPEN);
    }

    public function prunable(): Builder
    {
        return static::where('status', self::DISMISSED)
            ->where('reviewed_at', '<', now()->subDays((int) config('security.hold_match.dismissed_retention_days', 90)));
    }
}
