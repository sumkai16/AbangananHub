<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function show()
    {
        $user = Auth::user();

        $reviews = $user->reviews()
            ->with(['property:property_id,title', 'property.media'])
            ->latest('created_at')
            ->take(5)
            ->get();

        $activeReservations = $user->reservations()
            ->with(['property:property_id,title,address,rental_fee', 'property.media'])
            ->whereIn('reservation_status', ['Pending', 'Approved', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed'])
            ->latest('created_at')
            ->get();

        $favoritesCount = $user->favorites()->count();

        return view('tenant.profile.show', compact('user', 'reviews', 'activeReservations', 'favoritesCount'));
    }

    public function edit()
    {
        $user = Auth::user();
        return view('tenant.profile.edit', compact('user'));
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

        unset($validated['profile_picture_input']);
        $user->update($validated);

        return redirect()->route('tenant.profile.show')->with('success', 'Profile updated successfully.');
    }
}