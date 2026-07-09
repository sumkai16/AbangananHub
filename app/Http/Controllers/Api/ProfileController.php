<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
        $user = $request->user();

        return response()->json([
            'data' => [
                'user'  => $user,
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

        return response()->json(['data' => $user->fresh()]);
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
}
