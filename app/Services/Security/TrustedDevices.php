<?php

namespace App\Services\Security;

use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * "Don't ask for the code again on this browser". The cookie holds a random token; only its sha256 is stored, so a
 * database leak gives no usable cookie. Trust is per user, expires, and is wiped by revokeAll() whenever the
 * credentials change (password, 2FA on/off, recovery codes, sign out everywhere).
 */
class TrustedDevices
{
    public function cookieName(): string
    {
        return config('security.trusted_device_cookie', 'ah_2fa_trusted');
    }

    /** Remembers this browser and queues the cookie. */
    public function trust(User $user, Request $request): void
    {
        $days = (int) config('security.trusted_device_days', 30);
        $token = Str::random(48);

        TrustedDevice::create([
            'user_id'      => $user->user_id,
            'token_hash'   => hash('sha256', $token),
            'user_agent'   => $request->userAgent() ? mb_substr($request->userAgent(), 0, 255) : null,
            'ip_address'   => $request->ip(),
            'last_used_at' => now(),
            'expires_at'   => now()->addDays($days),
        ]);

        Cookie::queue(Cookie::make(
            $this->cookieName(), $token, $days * 24 * 60, '/', null, $request->isSecure(), true, false, SymfonyCookie::SAMESITE_LAX,
        ));
    }

    /** The row for this request's cookie, if it belongs to the user and has not expired. */
    public function current(User $user, Request $request): ?TrustedDevice
    {
        $token = $request->cookie($this->cookieName());
        if (! is_string($token) || $token === '') {
            return null;
        }

        return TrustedDevice::where('user_id', $user->user_id)
            ->where('token_hash', hash('sha256', $token))
            ->where('expires_at', '>', now())
            ->first();
    }

    /** True when the cookie is valid; marks this session as having passed two-factor and audits it. */
    public function passSession(User $user, Request $request): bool
    {
        $device = $this->current($user, $request);
        if (! $device) {
            return false;
        }

        $request->session()->put('auth.two_factor_passed', $user->user_id);

        if (! $device->last_used_at || $device->last_used_at->lt(now()->subHour())) {
            $device->forceFill(['last_used_at' => now()])->save();
        }

        AuditLog::record('auth.two_factor_passed', 'Signed in with a remembered browser (two-factor skipped)', $user, actor: $user);

        return true;
    }

    /** @return Collection<int, TrustedDevice> newest first, unexpired only */
    public function forUser(User $user): Collection
    {
        return TrustedDevice::where('user_id', $user->user_id)->where('expires_at', '>', now())->latest('id')->get();
    }

    public function revoke(User $user, int $id): bool
    {
        return TrustedDevice::where('user_id', $user->user_id)->where('id', $id)->delete() > 0;
    }

    public function revokeAll(User $user): void
    {
        TrustedDevice::where('user_id', $user->user_id)->delete();
    }
}
