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
    private const FIRST_PAGE_SIZE = 6;
    private const MORE_PAGE_SIZE = 12;

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

        $this->ensureVisible($user);

        return $this->showProfile($user, false);
    }

    /**
     * Next page of a landlord's approved properties, as rendered rows, for the
     * profile's "Show more" button. Same visibility rules as the profile itself.
     */
    public function properties(User $user, Request $request)
    {
        if (!$user->hasRole('Landlord')) {
            abort(404);
        }

        if (!(Auth::check() && Auth::id() === $user->user_id)) {
            $this->ensureVisible($user);
        }

        $offset = max(0, (int) $request->query('offset', 0));
        $total = $this->approvedProperties($user)->count();

        $rows = $this->approvedProperties($user)
            ->with(['media', 'units'])
            ->latest('created_at')
            ->skip($offset)
            ->take(self::MORE_PAGE_SIZE)
            ->get();

        return response()->json([
            'html' => $rows->map(fn ($p) => view('landlord.profile._property-card', ['property' => $p])->render())->implode(''),
            'remaining' => max(0, $total - $offset - $rows->count()),
        ]);
    }

    private function ensureVisible(User $user): void
    {
        $visibility = $user->profile_visibility ?? 'private';

        if ($visibility === 'private') {
            abort(404);
        }

        if ($visibility === 'landlords_only' && (!Auth::check() || !Auth::user()->hasRole('Landlord'))) {
            abort(404);
        }
    }

    private function approvedProperties(User $user)
    {
        return $user->properties()->where('verification_status', 'Approved');
    }

    /**
     * Shared logic for both me() and show().
     */
    private function showProfile(User $user, bool $isOwner)
    {
        $business = $user->rentalBusiness;
        $verification = $user->verificationApplication;

        $propertyIds = $this->approvedProperties($user)->pluck('property_id');
        $propertyCount = $propertyIds->count();

        $properties = $this->approvedProperties($user)
            ->with(['media', 'units'])
            ->latest('created_at')
            ->take(self::FIRST_PAGE_SIZE)
            ->get();

        // Unit stats
        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();
        $totalUnits = $units->count();
        $occupiedUnits = $units->where('availability_status', 'Occupied')->count();
        $availableUnits = $units->where('availability_status', 'Available')->count();

        // At-a-glance listing facts for the Details tab. Rent range is over currently available units (what a tenant could actually take);
        // areas and types are grouped in SQL so a big portfolio stays cheap.
        $availableFees = $units->where('availability_status', 'Available')->pluck('rental_fee')->filter();
        $propertySummary = [
            'rentLow' => $availableFees->min(),
            'rentHigh' => $availableFees->max(),
            'areas' => $this->approvedProperties($user)->whereNotNull('city_municipality')->distinct()->orderBy('city_municipality')->pluck('city_municipality'),
            'types' => $this->approvedProperties($user)->selectRaw('property_type, COUNT(*) as c')->groupBy('property_type')->orderByDesc('c')->pluck('c', 'property_type'),
        ];

        // Reviews received on this landlord's properties
        $reviews = Review::whereIn('property_id', $propertyIds)
            ->with(['tenant:user_id,first_name,last_name,profile_picture', 'property:property_id,title'])
            ->latest('created_at')
            ->take(10)
            ->get();

        // Shared helper: excludes hidden reviews (the inline avg here used to
        // include them) and carries the count.
        $ratingSummary = $user->landlordRatingSummary();

        // Star-by-star breakdown for the reviews panel's distribution bars.
        $ratingDistribution = Review::whereIn('property_id', $propertyIds)
            ->where('is_hidden', false)
            ->selectRaw('rating, COUNT(*) as c')
            ->groupBy('rating')
            ->pluck('c', 'rating');

        return view('landlord.profile.show', [
            'user' => $user,
            'isOwner' => $isOwner,
            'business' => $business,
            'verification' => $verification,
            'properties' => $properties,
            'propertyCount' => $propertyCount,
            'propertySummary' => $propertySummary,
            'totalUnits' => $totalUnits,
            'occupiedUnits' => $occupiedUnits,
            'availableUnits' => $availableUnits,
            'reviews' => $reviews,
            'averageRating' => $ratingSummary['avg'],
            'ratingCount' => $ratingSummary['count'],
            'ratingDistribution' => $ratingDistribution,
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
        'gcash_number' => ['nullable', 'string', 'max:20'],
        'gcash_account_name' => ['nullable', 'string', 'max:255'],
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
        'first_name', 'last_name', 'contact_number', 'gcash_number', 'gcash_account_name', 'bio', 'profile_picture',
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