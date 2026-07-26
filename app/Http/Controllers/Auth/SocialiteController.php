<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
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

        return response()->json([
            'user'  => $user,
            'token' => $user->createToken($request->string('device_name'))->plainTextToken,
            'roles' => $user->roles()->pluck('role'),
        ]);
    }

    /**
     * Find-or-create the account behind a verified social identity.
     * Existing accounts (password-based or a different provider) are linked
     * by verified email rather than duplicated; brand-new accounts get the
     * Tenant role immediately, matching RegisteredUserController's web
     * self-registration and Api\AuthController's mobile registration.
     */
    private function resolveSocialUser(string $provider, SocialiteUser $socialUser): User
    {
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            $user->forceFill([
                'provider'        => $provider,
                'provider_id'     => $socialUser->getId(),
                'profile_picture' => $user->profile_picture ?: $socialUser->getAvatar(),
            ])->save();

            return $user;
        }

        [$firstName, $lastName] = $this->splitName($socialUser->getName(), $socialUser->getEmail());

        $user = User::create([
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $socialUser->getEmail(),
            'password'          => Hash::make(Str::random(40)),
            'provider'          => $provider,
            'provider_id'       => $socialUser->getId(),
            'profile_picture'   => $socialUser->getAvatar(),
            'email_verified_at' => now(),
        ]);

        $user->assignRole('Tenant');

        event(new Registered($user));

        return $user;
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
