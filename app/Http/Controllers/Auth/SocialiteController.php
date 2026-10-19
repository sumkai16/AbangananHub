<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    private const LINK_REFUSED = 'An account with this email already exists. Log in with your email and password (and verify your email) instead.';

    /**
     * Send the browser to the provider's consent screen.
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the browser-redirect callback (web login/register).
     */
    public function callback(string $provider): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException|\Exception $e) {
            Log::error("Socialite {$provider} callback failed", ['exception' => $e]);

            return redirect()->route('login')
                ->with('error', 'We could not complete sign-in with '.ucfirst($provider).'. Please try again.');
        }

        if (empty($socialUser->getEmail())) {
            return redirect()->route('login')
                ->with('error', ucfirst($provider).' did not share an email address, so we could not sign you in. Please use email and password instead.');
        }

        $user = $this->resolveSocialUser($provider, $socialUser);

        if (! $user) {
            return redirect()->route('login')->with('error', self::LINK_REFUSED);
        }

        Auth::login($user, remember: true);
        request()->session()->regenerate();

        return redirect($user->homeRoute());
    }

    /**
     * Mobile-ready endpoint: verify a token a native Google/Facebook SDK
     * already produced on-device, then issue this app's own Sanctum token.
     * Nothing calls this yet (no mobile app exists), but it shares the same
     * account linking/creation logic as the web callback so mobile can plug
     * in later without touching that logic again. Mirrors Api\AuthController
     * login's device_name + {user, token, roles} shape so mobile clients see
     * one consistent auth response regardless of which endpoint they hit.
     */
    public function verifyToken(Request $request, string $provider): JsonResponse
    {
        $request->validate([
            'token'       => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->userFromToken($request->string('token'));
        } catch (\Exception $e) {
            Log::error("Socialite {$provider} token verification failed", ['exception' => $e]);

            return response()->json([
                'message' => 'That '.ucfirst($provider).' token could not be verified.',
            ], 401);
        }

        if (empty($socialUser->getEmail())) {
            return response()->json([
                'message' => ucfirst($provider).' did not share an email address.',
            ], 422);
        }

        $user = $this->resolveSocialUser($provider, $socialUser);

        if (! $user) {
            return response()->json(['message' => self::LINK_REFUSED], 409);
        }

        return response()->json([
            'user' => new UserResource($user->load('payoutAccount')),
            'token' => $user->createToken($request->string('device_name'))->plainTextToken,
            'roles' => $user->roles()->pluck('role'),
        ]);
    }

    /**
     * Find-or-create the account behind a social identity. A provider identity already stored in
     * social_accounts always resolves to its account. An existing account with the same email is linked
     * ONLY when its own email_verified_at is set, or when the provider itself vouches for the address (then
     * the account is claimed, see claimUnverifiedAccount), and it is not a walk-in/inactive placeholder;
     * otherwise this returns null and the caller refuses. Brand-new emails get an account with the Tenant role,
     * matching RegisteredUserController's web self-registration and Api\AuthController's mobile one.
     */
    private function resolveSocialUser(string $provider, SocialiteUser $socialUser): ?User
    {
        // A provider identity we have seen before always resolves to the same account.
        $linked = SocialAccount::where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($linked) {
            return $linked->user;
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            if ($user->is_walk_in || $user->account_status === 'inactive') {
                return null;
            }

            if ($user->email_verified_at === null) {
                // Nobody has proved they own this address yet. Only a provider that vouches for the email may
                // claim it; otherwise whoever registered first (Facebook, or a pre-registered password) would
                // lock the real owner out of social login.
                if (! $this->providerVerifiedEmail($provider, $socialUser)) {
                    return null;
                }

                $this->claimUnverifiedAccount($user);
            }

            // Adds a row; an existing link (this or another provider) is never overwritten.
            SocialAccount::firstOrCreate(
                ['user_id' => $user->user_id, 'provider' => $provider],
                ['provider_id' => $socialUser->getId()],
            );

            if (! $user->profile_picture && $socialUser->getAvatar()) {
                $user->forceFill(['profile_picture' => $socialUser->getAvatar()])->save();
            }

            return $user;
        }

        [$firstName, $lastName] = $this->splitName($socialUser->getName(), $socialUser->getEmail());

        $user = User::create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $socialUser->getEmail(),
            'password'          => Hash::make(Str::random(40)),
            'profile_picture'   => $socialUser->getAvatar(),
        ]);

        // email_verified_at is not mass-assignable, so passing it to create() silently left every social account
        // unverified. Set it explicitly, and only when the provider itself says the address is confirmed.
        if ($this->providerVerifiedEmail($provider, $socialUser)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $user->socialAccounts()->create([
            'provider'    => $provider,
            'provider_id' => $socialUser->getId(),
        ]);

        $user->assignRole('Tenant');

        event(new Registered($user));

        return $user;
    }

    /** Google reports whether the address is confirmed; other providers do not reliably, so they count as unverified. */
    /**
     * The provider just proved ownership of an address whose account was never verified. Whatever else was
     * attached to that account was set up by someone who had not proved it, so drop their other social links,
     * scramble the password (the owner can reset it by email) and mark the address verified.
     */
    private function claimUnverifiedAccount(User $user): void
    {
        $user->socialAccounts()->delete();
        $user->forceFill([
            'password'          => Hash::make(Str::random(40)),
            'email_verified_at' => now(),
        ])->save();
        $user->tokens()->delete();
    }

    private function providerVerifiedEmail(string $provider, SocialiteUser $socialUser): bool
    {
        if ($provider !== 'google' || ! $socialUser instanceof AbstractUser) {
            return false;
        }

        $raw = $socialUser->getRaw();

        return ($raw['email_verified'] ?? $raw['verified_email'] ?? false) === true;
    }

    private function splitName(?string $name, string $email): array
    {
        $name = trim((string) $name);

        if ($name === '') {
            return [Str::before($email, '@'), ''];
        }

        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
