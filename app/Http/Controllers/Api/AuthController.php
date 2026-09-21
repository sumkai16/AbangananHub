<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new tenant account, returning a Sanctum token.
     * Same validation as web registration; the mobile app is
     * tenant-facing so the Tenant role is assigned immediately.
     *
     * Returns the same {user, token, roles} shape as login() so the client
     * has one code path for "I am now signed in" — previously this returned
     * no token and the app would have had to immediately re-POST to /login.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'contact_number' => ['required', 'string', 'max:20'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name'    => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'email'          => $request->email,
            'contact_number' => $request->contact_number,
            'password'       => Hash::make($request->password),
        ]);

        $user->assignRole('Tenant');

        event(new Registered($user));

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $user->createToken($request->device_name)->plainTextToken,
            'roles' => $user->roles()->pluck('role'),
        ], 201);
    }

    /**
     * Login with email + password, returning a Sanctum token
     * scoped to the given device.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'       => ['required', 'string', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Checked here as well as in the `active` middleware: that one revokes an
        // existing token, this one refuses to mint a new one. Without it a
        // suspended user could keep issuing fresh tokens indefinitely.
        if ($user->account_status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended.'],
            ]);
        }

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user'  => new UserResource($user),
            'token' => $token,
            'roles' => $user->roles()->pluck('role'),
        ]);
    }

    /**
     * Revoke the token used for the current request only.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * A single-use, short-lived ticket that bridges this Bearer-token
     * session into a *session-guard* login for a WebView. The landlord KYC
     * wizard (face-api.js liveness, OCR) stays web UI in a native shell
     * rather than being ported — see plans/mobile-app.md — and that web UI
     * has no idea what a Sanctum token is.
     *
     * The ticket itself is the credential: an unguessable 40-char random
     * string, cached for 2 minutes, deleted the instant it's redeemed
     * (`Api\AuthController`'s counterpart, `AuthController::webviewLogin`
     * (web) on the other end). Single use is enforced by that deletion, not
     * by the short TTL alone — an unexpired ticket is still replayable
     * inside its window if it isn't also destroyed on first use.
     */
    public function webviewTicket(Request $request): JsonResponse
    {
        $ticket = Str::random(40);

        Cache::put("webview_ticket:{$ticket}", $request->user()->user_id, now()->addMinutes(2));

        return response()->json([
            'url' => route('auth.webviewLogin', ['ticket' => $ticket]),
        ]);
    }
}
