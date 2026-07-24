<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Tenant views their own profile.
     */
    public function show()
    {
        $user = Auth::user();

        $reviews = $user->reviews()
            ->with(['property:property_id,title'])
            ->latest('created_at')
            ->get();

        $activeReservations = $user->reservations()
            ->whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)
            ->with('property.media')
            ->latest('created_at')
            ->get();

        return view('tenant.profile.show', [
            'user' => $user,
            'favoritesCount' => $user->favorites()->count(),
            'reviews' => $reviews,
            'activeReservations' => $activeReservations,
        ]);
    }

    public function edit()
    {
        return view('tenant.profile.edit', [
            'user' => Auth::user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'profile_picture' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $result = cloudinary()->uploadApi()->upload(
                $request->file('profile_picture')->getRealPath(),
                [
                    'folder' => 'abanganganhub/profile-pictures',
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                    ],
                ]
            );
            $validated['profile_picture'] = $result['secure_url'];
        }

        $user->update($validated);

        return redirect()->route('tenant.profile.show')->with('success', 'Profile updated successfully.');
    }
}
