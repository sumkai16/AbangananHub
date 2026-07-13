<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    /**
     * Landlord views their own profile.
     */
    public function me()
    {
        return $this->showProfile(Auth::user(), true);
    }

    /**
     * Public profile view (tenants, other landlords, guests).
     * Respects profile_visibility setting.
     */
    public function show(User $user)
    {
        // Verify this user is actually a landlord
        if (!$user->hasRole('Landlord')) {
            abort(404);
        }

        // If viewing own profile, redirect to the dedicated route
        if (Auth::check() && Auth::id() === $user->user_id) {
            return redirect()->route('landlord.profile.me');
        }

        // Enforce visibility
        $visibility = $user->profile_visibility ?? 'private';

        if ($visibility === 'private') {
            abort(404);
        }

        if ($visibility === 'landlords_only' && (!Auth::check() || !Auth::user()->hasRole('Landlord'))) {
            abort(404);
        }

        return $this->showProfile($user, false);
    }

    /**
     * Shared logic for both me() and show().
     */
    private function showProfile(User $user, bool $isOwner)
    {
        $business = $user->rentalBusiness;
        $verification = $user->verificationApplication;

        $properties = $user->properties()
            ->where('verification_status', 'Approved')
            ->with(['media', 'units'])
            ->latest('created_at')
            ->get();

        $propertyIds = $properties->pluck('property_id');

        // Unit stats
        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();
        $totalUnits = $units->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();

        // Reviews received on this landlord's properties
        $reviews = Review::whereIn('property_id', $propertyIds)
            ->with(['tenant:user_id,first_name,last_name,profile_picture', 'property:property_id,title'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $averageRating = Review::whereIn('property_id', $propertyIds)->avg('rating');

        return view('landlord.profile.show', [
            'user' => $user,
            'isOwner' => $isOwner,
            'business' => $business,
            'verification' => $verification,
            'properties' => $properties,
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'reviews' => $reviews,
            'averageRating' => $averageRating ? round($averageRating, 1) : null,
        ]);
    }

    public function edit()
    {
        $user = Auth::user();
        $business = $user->rentalBusiness;

        return view('landlord.profile.edit', compact('user', 'business'));
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
        'business_name' => ['nullable', 'string', 'max:255'],
        'business_description' => ['nullable', 'string', 'max:1000'],
        'business_contact' => ['nullable', 'string', 'max:20'],
        'business_address' => ['nullable', 'string', 'max:500'],
        'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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

    // Update user fields
    $user->update(collect($validated)->only([
        'first_name', 'last_name', 'contact_number', 'bio', 'profile_picture',
    ])->toArray());

    // Update or create rental business
    $businessData = array_filter([
        'business_name' => $validated['business_name'] ?? null,
        'description' => $validated['business_description'] ?? null,
        'contact_number' => $validated['business_contact'] ?? null,
        'business_address' => $validated['business_address'] ?? null,
    ], fn($v) => $v !== null);

    // Handle logo upload
    if ($request->hasFile('logo')) {
        $business = $user->rentalBusiness;

        if ($business && $business->logo_public_id) {
            cloudinary()->uploadApi()->destroy($business->logo_public_id);
        }

        $logoResult = cloudinary()->uploadApi()->upload(
            $request->file('logo')->getRealPath(),
            [
                'folder' => 'abanganganhub/business-logos',
                'transformation' => [
                    'width' => 400,
                    'height' => 400,
                    'crop' => 'fill',
                    'gravity' => 'face',
                ],
            ]
        );

        $businessData['logo_url'] = $logoResult['secure_url'];
        $businessData['logo_public_id'] = $logoResult['public_id'];
    }

    if (!empty($businessData)) {
        $user->rentalBusiness()
            ->updateOrCreate(['landlord_id' => $user->user_id], $businessData);
    }

    return redirect()->route('landlord.profile.me')->with('success', 'Profile updated successfully.');
}
}