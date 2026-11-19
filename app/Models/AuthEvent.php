<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account or security event (sign-in, failure, lockout, access denied...). Append-only. Kept apart from
 * audit_logs on purpose: anyone on the internet can generate failed logins, and that noise must never bury the
 * admin trail. Written only through App\Services\Security\AuthEventRecorder.
 */
class AuthEvent extends Model
{
    use MassPrunable;

    public const UPDATED_AT = null;
    protected $primaryKey = 'event_id';

    public const EVENTS = [
        'login'                   => 'Signed in',
        'login_failed'            => 'Failed sign-in',
        'lockout'                 => 'Locked out',
        'logout'                  => 'Signed out',
        'password_reset'          => 'Password reset',
        'password_changed'        => 'Password changed',
        'email_changed'           => 'Email changed',
        'social_linked'           => 'Social account linked',
        'social_unlinked'         => 'Social account unlinked',
        'two_factor_enabled'      => 'Two-factor turned on',
        'two_factor_disabled'     => 'Two-factor turned off',
        'two_factor_failed'       => 'Failed two-factor code',
        'recovery_code_used'      => 'Recovery code used',
        'access_denied'           => 'Access denied',
        'sessions_revoked'        => 'Other devices signed out',
        'password_confirm_failed' => 'Failed password confirmation',
    ];

    /** Events an admin should notice: they get the amber chip and feed the suspicious-activity alerts. */
    public const WARNING_EVENTS = ['login_failed', 'lockout', 'two_factor_failed', 'access_denied', 'password_confirm_failed'];

    protected $fillable = [
        'user_id', 'email', 'event', 'channel', 'ip_address', 'user_agent', 'device_id', 'metadata',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function label(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }

    public function isWarning(): bool
    {
        return in_array($this->event, self::WARNING_EVENTS, true);
    }

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays((int) config('security.auth_events_retention_days', 90)));
    }
}
