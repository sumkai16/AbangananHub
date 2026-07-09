<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
<<<<<<< HEAD
=======
    /**
     * Tenant views their own profile.
     */
>>>>>>> 8beaf992cdc4334922c6727840ad6e43eb588d55
    public function show()
    {
        $user = Auth::user();

        $reviews = $user->reviews()
<<<<<<< HEAD
            ->with(['property:property_id,title', 'property.media'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $activeReservations = $user->reservations()
            ->with(['property:property_id,title,address', 'property.media'])
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected', 'Occupied'])
            ->latest('created_at')
            ->get();

        $favoritesCount = $user->favorites()->count();

        return view('tenant.profile.show', compact('user', 'reviews', 'activeReservations', 'favoritesCount'));
=======
            ->with(['property:property_id,title'])
            ->latest('created_at')
            ->get();

        $activeReservations = $user->reservations()
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->with('property.media')
            ->latest('created_at')
            ->get();

        return view('tenant.profile.show', [
            'user' => $user,
            'favoritesCount' => $user->favorites()->count(),
            'reviews' => $reviews,
            'activeReservations' => $activeReservations,
        ]);
>>>>>>> 8beaf992cdc4334922c6727840ad6e43eb588d55
    }

    public function edit()
    {
<<<<<<< HEAD
        $user = Auth::user();
        return view('tenant.profile.edit', compact('user'));
=======
        return view('tenant.profile.edit', [
            'user' => Auth::user(),
        ]);
>>>>>>> 8beaf992cdc4334922c6727840ad6e43eb588d55
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

<<<<<<< HEAD
        $user->update(collect($validated)->only([
            'first_name', 'last_name', 'contact_number', 'bio', 'profile_picture',
        ])->toArray());
=======
        $user->update($validated);
>>>>>>> 8beaf992cdc4334922c6727840ad6e43eb588d55

        return redirect()->route('tenant.profile.show')->with('success', 'Profile updated successfully.');
    }
}
