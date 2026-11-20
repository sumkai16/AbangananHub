<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a signed-in person did: every state-changing request (and every download or export), whoever sent it.
 * Append-only. Written only by App\Http\Middleware\LogUserActivity, so a new route is logged without anyone
 * remembering to. It records the route and the record acted on, never the request body (passwords, ID numbers).
 *
 * Kept apart from audit_logs on purpose: that table is the tamper-evident trail of consequential admin actions
 * (hash chain, locked on every write); this one is high-volume and answers "what did anyone do".
 */
class UserActivity extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;
    protected $primaryKey = 'activity_id';

    public const OUTCOMES = ['ok' => 'Done', 'failed' => 'Did not go through', 'denied' => 'Not allowed'];

    protected $fillable = [
        'user_id', 'actor_name', 'actor_email', 'actor_role', 'action', 'summary', 'method', 'path', 'channel',
        'status', 'outcome', 'target_type', 'target_id', 'ip_address', 'user_agent', 'device_id',
    ];

    protected $casts = [
        'status'     => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /** "Property #12", or null when the request had no target. */
    public function targetLabel(): ?string
    {
        return $this->target_type ? $this->target_type . ($this->target_id ? ' #' . $this->target_id : '') : null;
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('security.user_activity_retention_days', 365)));
    }
}
