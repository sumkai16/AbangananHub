<?php

namespace App\Services\Security;

use App\Models\AuthEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * The only writer of auth_events. Logging must never block or break a login, so a failed write is logged and
 * swallowed. Never pass a password, token, 2FA code or session id in $metadata.
 */
class AuthEventRecorder
{
    public function record(string $event, ?User $user = null, ?string $email = null, array $metadata = [], ?string $channel = null): ?AuthEvent
    {
        try {
            $request = request();
            $ip = $request->ip();

            // A bot hammering one IP must not fill the table. Only warning events are capped; real sign-ins never are.
            if (in_array($event, AuthEvent::WARNING_EVENTS, true) && $ip) {
                $key = 'auth-events:'.$ip;
                if (RateLimiter::tooManyAttempts($key, (int) config('security.auth_events_ip_cap_per_minute', 30))) {
                    return null;
                }
                RateLimiter::hit($key, 60);
            }

            $row = AuthEvent::create([
                'user_id'    => $user?->user_id,
                'email'      => $email ?? $user?->email,
                'event'      => $event,
                'channel'    => $channel ?? 'web',
                'ip_address' => $ip,
                'user_agent' => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
                'device_id'  => $request->cookies->get(config('security.device_cookie', 'ah_device')),
                'metadata'   => $metadata ?: null,
            ]);

            if (in_array($event, AuthEvent::WARNING_EVENTS, true)) {
                $this->inspect($row);
            }

            return $row;
        } catch (Throwable $e) {
            Log::warning('Could not record auth event', ['event' => $event, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** Alerting is a bonus: whatever goes wrong in there must never break (or hide) the event that was just written. */
    private function inspect(AuthEvent $row): void
    {
        try {
            app(SuspiciousActivityMonitor::class)->inspect($row);
        } catch (Throwable $e) {
            Log::warning('Suspicious-activity check failed', ['event' => $row->event, 'error' => $e->getMessage()]);
        }
    }
}
