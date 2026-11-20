<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Models\UserActivity;
use App\Support\ActivityCatalog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Writes one user_activities row for every state-changing request a signed-in person makes (and for downloads
 * and exports: see ActivityCatalog), on every route, web and API, with nothing to remember per route.
 *
 * It runs in terminate(), after the response has gone out: the status is known (a refused or failed attempt is
 * logged as such) and the log can never slow a page down or stand in the way of it. A failed write is logged and
 * swallowed, like AuthEventRecorder.
 *
 * It stores the route, the record acted on, the outcome and the device. It never stores the request body or the
 * real URL: both can hold passwords, ID numbers and one-time tokens.
 */
class LogUserActivity
{
    private const ROLE_ORDER = ['Admin', 'Landlord', 'Tenant'];

    public function handle(Request $request, Closure $next): Response
    {
        // Who it was has to be read now: after a sign-out or an account deletion the session no longer knows.
        // API requests have no session here; their guard resolves later and is read in terminate().
        if ($request->hasSession()) {
            $request->attributes->set('activity.actor', $request->user());
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $route = $request->route();
            $method = $request->getMethod();

            if (! $route || ! ActivityCatalog::shouldLog($route, $method)) {
                return;
            }

            // Nobody signed in (a failed login, a public form) is not "a user's activity"; auth_events covers it.
            $actor = $request->attributes->get('activity.actor') ?? $request->user();
            if (! $actor instanceof User) {
                return;
            }

            [$targetType, $targetId] = $this->target($route->parameters());
            $status = $response->getStatusCode();
            $ua = $request->userAgent();

            UserActivity::create([
                'user_id'     => $actor->user_id,
                'actor_name'  => mb_substr(trim($actor->first_name . ' ' . $actor->last_name), 0, 255),
                'actor_email' => $actor->email,
                'actor_role'  => $this->role($actor),
                'action'      => mb_substr(ActivityCatalog::actionKey($route, $method), 0, 160),
                'summary'     => ActivityCatalog::summary($route, $method),
                'method'      => $method,
                'path'        => mb_substr('/' . ltrim($route->uri(), '/'), 0, 255),
                'channel'     => $request->is('api/*') ? 'api' : 'web',
                'status'      => $status,
                'outcome'     => $this->outcome($request, $status),
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'ip_address'  => $request->ip(),
                'user_agent'  => $ua ? mb_substr($ua, 0, 255) : null,
                'device_id'   => $request->cookies->get(config('security.device_cookie', 'ah_device')),
            ]);
        } catch (Throwable $e) {
            Log::warning('Could not record user activity', ['error' => $e->getMessage()]);
        }
    }

    /** The highest role the person holds, so a row says "Admin" even for an admin who is also a landlord. */
    private function role(User $actor): ?string
    {
        foreach (self::ROLE_ORDER as $role) {
            if ($actor->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    /**
     * The record the request was about: the first bound model in the route, or a numeric {thing_id} segment
     * (the admin listing routes use {property_id}).
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function target(array $parameters): array
    {
        foreach ($parameters as $name => $value) {
            if ($value instanceof Model) {
                return [class_basename($value), is_numeric($value->getKey()) ? (int) $value->getKey() : null];
            }
        }

        foreach ($parameters as $name => $value) {
            if (is_string($name) && preg_match('/^([a-z]+(?:_[a-z]+)*)_id$/', $name, $m) && ctype_digit((string) $value)) {
                return [str_replace(' ', '', ucwords(str_replace('_', ' ', $m[1]))), (int) $value];
            }
        }

        return [null, null];
    }

    /**
     * ok: it went through. denied: the person was not allowed. failed: anything else that did not go through,
     * including a redirect back to a form with validation errors or an error message (those are 302s).
     */
    private function outcome(Request $request, int $status): string
    {
        if ($status === 401 || $status === 403) {
            return 'denied';
        }

        if ($status >= 400) {
            return 'failed';
        }

        if ($request->hasSession()) {
            $session = $request->session();
            $errors = $session->get('errors');

            if (($errors && $errors->any()) || $session->has('error')) {
                return 'failed';
            }
        }

        return 'ok';
    }
}
