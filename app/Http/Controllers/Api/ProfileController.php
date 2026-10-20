<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\UserDevice;
use App\Support\NameLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class ProfileController extends Controller
{
    /**
     * Current user profile with role info.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('payoutAccount');

        return response()->json([
            'data' => [
                'user'  => new UserResource($user),
                'roles' => $user->roles()->pluck('role'),
            ],
        ]);
    }

    /**
     * Update bio, contact number, and profile picture (Cloudinary).
     * Mirrors the web Tenant\ProfileController@update rules.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        NameLock::apply($request, $user);

        $validated = $request->validate([
            'first_name'      => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'       => ['sometimes', 'required', 'string', 'max:255'],
            'contact_number'  => ['nullable', 'string', 'max:20'],
            'bio'             => ['nullable', 'string', 'max:1000'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $result = cloudinary()->uploadApi()->upload(
                $request->file('profile_picture')->getRealPath(),
                [
                    'folder' => 'abanganganhub/profile-pictures',
                    'transformation' => [
                        'width'   => 400,
                        'height'  => 400,
                        'crop'    => 'fill',
                        'gravity' => 'face',
                    ],
                ]
            );
            $validated['profile_picture'] = $result['secure_url'];
        }

        $user->update($validated);

        return response()->json(['data' => new UserResource($user->fresh('payoutAccount'))]);
    }

    /**
     * Change password (requires current password).
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Registers this device's Expo push token, called on login/app start. A user can have
     * several devices; the token is unique, so one that changes hands (a shared phone, a new
     * login) moves to the current user rather than being duplicated.
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expo_push_token' => ['required', 'string', 'max:255'],
        ]);

        UserDevice::updateOrCreate(
            ['expo_push_token' => $validated['expo_push_token']],
            ['user_id' => $request->user()->user_id],
        );

        return response()->json(['message' => 'Push token registered.']);
    }
}
