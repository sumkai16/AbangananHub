<?php

namespace App\Services\Security;

use App\Models\AuthEvent;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Turns a burst of warning events into one in-app alert for every admin. Called by AuthEventRecorder right after a
 * warning event is written. Each rule alerts at most once an hour per subject (Cache::add), so an attack does not
 * flood the bell.
 */
class SuspiciousActivityMonitor
{
    private const DEDUPE_MINUTES = 60;

    public function inspect(AuthEvent $event): void
    {
        match ($event->event) {
            'login_failed'      => $this->failedSignIns($event),
            'lockout'           => $this->adminLockout($event),
            'access_denied'     => $this->repeatedByUser($event, 'access_denied', 3, 10, 'blocked attempts to open admin pages'),
            'two_factor_failed' => $this->repeatedByUser($event, 'two_factor_failed', 3, 10, 'wrong two-factor codes'),
            default             => null,
        };
    }

    private function failedSignIns(AuthEvent $event): void
    {
        $email = $event->email;
        if (! $email) {
            return;
        }

        $count = AuthEvent::where('event', 'login_failed')
            ->where('email', $email)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($count >= 5) {
            $this->alert('failed-login', strtolower($email), "{$count} failed sign-ins for {$email} in 15 minutes.", $event, $email);
        }
    }

    private function adminLockout(AuthEvent $event): void
    {
        $user = $event->user_id
            ? User::find($event->user_id)
            : ($event->email ? User::where('email', $event->email)->first() : null);

        if (! $user || ! $user->hasRole('Admin')) {
            return;
        }

        $this->alert('admin-lockout', (string) $user->user_id, "Admin account {$user->email} was locked out after repeated failed sign-ins.", $event, $user->email);
    }

    private function repeatedByUser(AuthEvent $event, string $rule, int $threshold, int $minutes, string $what): void
    {
        if (! $event->user_id) {
            return;
        }

        $count = AuthEvent::where('event', $event->event)
            ->where('user_id', $event->user_id)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();

        if ($count < $threshold) {
            return;
        }

        $who = $event->email ?: User::whereKey($event->user_id)->value('email') ?: "user #{$event->user_id}";
        $this->alert($rule, (string) $event->user_id, "{$count} {$what} by {$who} in {$minutes} minutes.", $event, $who);
    }

    private function alert(string $rule, string $subject, string $sentence, AuthEvent $event, ?string $search): void
    {
        // add() is false while a recent alert for this rule and subject is still on cooldown.
        if (! Cache::add("sec-alert:{$rule}:{$subject}", 1, now()->addMinutes(self::DEDUPE_MINUTES))) {
            return;
        }

        $link = route('admin.security-events.index', array_filter(['search' => $search, 'event' => $event->event]));

        $adminIds = User::whereHas('roles', fn ($q) => $q->where('role', 'Admin'))->pluck('user_id');
        foreach ($adminIds as $adminId) {
            Notification::notify((int) $adminId, 'account', 'Security alert', $sentence, $link);
        }
    }
}
