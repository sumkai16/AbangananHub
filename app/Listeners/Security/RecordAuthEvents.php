<?php

namespace App\Listeners\Security;

use App\Mail\NewSignInMail;
use App\Services\Security\AuthEventRecorder;
use App\Services\Security\NewDeviceDetector;
use App\Support\UserAgentSummary;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Maps Laravel's auth events to auth_events rows. Registered in AppServiceProvider::boot().
 * Only the session ("web") guard is recorded; the API records its own logins with channel "api".
 */
class RecordAuthEvents
{
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(Login::class, [$this, 'onLogin']);
        $events->listen(Failed::class, [$this, 'onFailed']);
        $events->listen(Lockout::class, [$this, 'onLockout']);
        $events->listen(Logout::class, [$this, 'onLogout']);
        $events->listen(PasswordReset::class, [$this, 'onPasswordReset']);
    }

    public function onLogin(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $recorded = $this->recorder()->record('login', $event->user, channel: $this->channel());

        $this->alertOnNewDevice($event->user, $recorded);
    }

    /**
     * Email admins and landlords (tenants get nothing) when a sign-in comes from a browser they have not used in 90
     * days. Runs after the response via defer(), and a mail failure is logged and swallowed: it must never break a login.
     */
    private function alertOnNewDevice(\App\Models\User $user, ?\App\Models\AuthEvent $recorded): void
    {
        try {
            if (! $recorded || ! $user->email || ! ($user->hasRole('Admin') || $user->hasRole('Landlord'))) {
                return;
            }

            if (! app(NewDeviceDetector::class)->isNew($user, $recorded->device_id, $recorded->event_id)) {
                return;
            }

            $mail = new NewSignInMail(
                $recorded->created_at->copy()->timezone(config('rentals.timezone'))->format('M j, Y \a\t g:i A'),
                UserAgentSummary::from($recorded->user_agent),
                $recorded->ip_address,
            );
            $to = $user->email;

            defer(function () use ($to, $mail) {
                try {
                    Mail::to($to)->send($mail);
                } catch (Throwable $e) {
                    Log::warning('Could not send new sign-in email', ['error' => $e->getMessage()]);
                }
            });
        } catch (Throwable $e) {
            Log::warning('Could not check for a new device', ['error' => $e->getMessage()]);
        }
    }

    public function onFailed(Failed $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        // $event->credentials holds the typed password. Read the email key only, never pass the array on.
        $email = $event->credentials['email'] ?? null;

        $this->recorder()->record(
            'login_failed',
            $event->user,
            is_string($email) ? mb_substr($email, 0, 255) : null,
            channel: $this->channel(),
        );
    }

    public function onLockout(Lockout $event): void
    {
        $email = $event->request->input('email');

        $email = is_string($email) ? mb_substr($email, 0, 255) : null;

        // Link the account when the typed address matches one, so the lockout shows in that user's history.
        $this->recorder()->record(
            'lockout',
            $email ? \App\Models\User::where('email', $email)->first() : null,
            email: $email,
            channel: $this->channel(),
        );
    }

    public function onLogout(Logout $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        $this->recorder()->record('logout', $event->user, channel: $this->channel());
    }

    public function onPasswordReset(PasswordReset $event): void
    {
        $this->recorder()->record('password_reset', $event->user, channel: $this->channel());
    }

    private function recorder(): AuthEventRecorder
    {
        return app(AuthEventRecorder::class);
    }

    /** web, a social provider (google/facebook) or webview, from the route that is handling the request. */
    private function channel(): string
    {
        $route = request()->route();

        return match ($route?->getName()) {
            'social.callback'    => (string) $route->parameter('provider', 'web'),
            'auth.webviewLogin'  => 'webview',
            default              => 'web',
        };
    }
}
