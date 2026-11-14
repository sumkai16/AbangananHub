<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Security\AuthEventRecorder;
use App\Services\Security\TwoFactor;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * An admin's own two-factor settings: turn it on (QR + first code), see the recovery codes once, make new ones,
 * turn it off. Turning it off is refused while `security.admin_two_factor` is `required`.
 */
class TwoFactorSetupController extends Controller
{
    private const PENDING_KEY = 'two_factor.pending_secret';

    public function __construct(private TwoFactor $twoFactor, private AuthEventRecorder $events)
    {
    }

    public function show(Request $request): View
    {
        $user = $request->user();
        $required = $this->isRequired();

        if ($user->hasTwoFactor()) {
            return view('admin.security.two-factor', [
                'enabled'       => true,
                'required'      => $required,
                'remaining'     => $this->twoFactor->remainingRecoveryCodes($user),
                'recoveryCodes' => session('two_factor_codes'),
                'qrSvg'         => null,
                'secret'        => null,
            ]);
        }

        $secret = $this->pendingSecret($request) ?? $this->newPendingSecret($request);

        return view('admin.security.two-factor', [
            'enabled'       => false,
            'required'      => $required,
            'remaining'     => 0,
            'recoveryCodes' => null,
            'qrSvg'         => $this->twoFactor->qrSvg($user, $secret),
            'secret'        => $secret,
        ]);
    }

    /** Confirms the first code from the authenticator app and turns two-factor on. */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactor()) {
            return redirect()->route('admin.security.two-factor');
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        $secret = $this->pendingSecret($request);
        if (! $secret) {
            return redirect()->route('admin.security.two-factor')
                ->with('error', 'Your setup session expired. Scan the new QR code and try again.');
        }

        $plain = null;
        $enabled = DB::transaction(function () use ($user, $secret, $data, &$plain) {
            if (! $this->twoFactor->enable($user, $secret, $data['code'], $plain)) {
                return false;
            }
            AuditLog::record('security.two_factor_enabled', 'Turned on two-factor sign-in', $user);

            return true;
        });

        if (! $enabled) {
            return back()->withErrors(['code' => "That code didn't match. Check the 6-digit code in your app and try again."]);
        }

        $this->events->record('two_factor_enabled', $user);
        $request->session()->forget(self::PENDING_KEY);
        $request->session()->put('auth.two_factor_passed', $user->user_id);

        return redirect()->route('admin.security.two-factor')
            ->with('two_factor_codes', $plain)
            ->with('success', 'Two-factor sign-in is on.');
    }

    public function regenerateCodes(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactor()) {
            return redirect()->route('admin.security.two-factor');
        }

        $plain = DB::transaction(function () use ($user) {
            $plain = $this->twoFactor->generateRecoveryCodes($user);
            AuditLog::record('security.two_factor_codes', 'Made new two-factor recovery codes', $user);

            return $plain;
        });

        return redirect()->route('admin.security.two-factor')
            ->with('two_factor_codes', $plain);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->isRequired()) {
            return redirect()->route('admin.security.two-factor')
                ->with('error', 'Admins must keep two-factor on, so it cannot be turned off.');
        }

        if (! $user->hasTwoFactor()) {
            return redirect()->route('admin.security.two-factor');
        }

        DB::transaction(function () use ($user) {
            $this->twoFactor->disable($user);
            AuditLog::record('security.two_factor_disabled', 'Turned off two-factor sign-in', $user);
        });

        $this->events->record('two_factor_disabled', $user);
        $request->session()->forget('auth.two_factor_passed');

        return redirect()->route('admin.security.two-factor')
            ->with('success', 'Two-factor sign-in is off.');
    }

    private function isRequired(): bool
    {
        return config('security.admin_two_factor') === 'required';
    }

    private function pendingSecret(Request $request): ?string
    {
        $stored = $request->session()->get(self::PENDING_KEY);
        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (DecryptException) {
            return null;
        }
    }

    private function newPendingSecret(Request $request): string
    {
        $secret = $this->twoFactor->newSecret();
        $request->session()->put(self::PENDING_KEY, Crypt::encryptString($secret));

        return $secret;
    }
}
