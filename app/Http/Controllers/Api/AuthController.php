<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new tenant account.
     * Same validation as web registration; the mobile app is
     * tenant-facing so the Tenant role is assigned immediately.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'contact_number' => ['required', 'string', 'max:20'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
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
            'data' => [
                'user'  => $user,
                'roles' => $user->roles()->pluck('role'),
            ],
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

        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'user'  => $user,
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

}
