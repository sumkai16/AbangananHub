<?php

namespace App\Http\Controllers\Api\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Resources\PropertyResource;
use App\Http\Resources\ReviewResource;
use App\Models\PropertyUnit;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    /**
     * The requesting landlord's own profile — same payload as show(), just
     * without needing to know your own user_id. Mirrors web's `me()`, which
     * is a thin call onto the same shared showProfile() the public view uses.
     */
    public function me(Request $request): JsonResponse
    {
        return $this->show($request->user());
    }

    /**
     * Update personal fields, gcash payout details, and the rental business
     * (created on first save). Mirrors web Landlord\ProfileController@update
     * field-for-field, including the same "only update business if any
     * business field was sent" behavior.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'contact_number'        => ['nullable', 'string', 'max:20'],
            'gcash_number'          => ['nullable', 'string', 'max:20'],
            'gcash_account_name'    => ['nullable', 'string', 'max:255'],
            'bio'                   => ['nullable', 'string', 'max:1000'],
            'profile_picture'       => ['nullable', 'image', 'max:2048'],
            'business_name'         => ['nullable', 'string', 'max:255'],
            'business_description'  => ['nullable', 'string', 'max:1000'],
            'business_contact'      => ['nullable', 'string', 'max:20'],
            'business_address'      => ['nullable', 'string', 'max:500'],
            'logo'                  => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $result = cloudinary()->uploadApi()->upload(
                $request->file('profile_picture')->getRealPath(),
                [
                    'folder'         => 'abanganganhub/profile-pictures',
                    'transformation' => ['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face'],
                ]
            );
            $validated['profile_picture'] = $result['secure_url'];
        }

        $user->update(collect($validated)->only([
            'first_name', 'last_name', 'contact_number', 'gcash_number', 'gcash_account_name', 'bio', 'profile_picture',
        ])->toArray());

        $businessData = array_filter([
            'business_name'    => $validated['business_name'] ?? null,
            'description'      => $validated['business_description'] ?? null,
            'contact_number'   => $validated['business_contact'] ?? null,
            'business_address' => $validated['business_address'] ?? null,
        ], fn ($v) => $v !== null);

        if ($request->hasFile('logo')) {
            $business = $user->rentalBusiness;
            if ($business && $business->logo_public_id) {
                cloudinary()->uploadApi()->destroy($business->logo_public_id);
            }

            $logoResult = cloudinary()->uploadApi()->upload(
                $request->file('logo')->getRealPath(),
                [
                    'folder'         => 'abanganganhub/business-logos',
                    'transformation' => ['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face'],
                ]
            );

            $businessData['logo_url'] = $logoResult['secure_url'];
            $businessData['logo_public_id'] = $logoResult['public_id'];
        }

        if (! empty($businessData)) {
            $user->rentalBusiness()->updateOrCreate(['landlord_id' => $user->user_id], $businessData);
        }

        return $this->show($user->fresh());
    }

    /**
     * Public landlord profile, gated by the user's profile_visibility
     * setting — same rules as the web Landlord\ProfileController@show.
     * The route is public; a Sanctum token (if sent) identifies the viewer.
     */
    public function show(User $user): JsonResponse
    {
        if (! $user->hasRole('Landlord')) {
            abort(404);
        }

        $viewer = auth('sanctum')->user();
        $isOwner = $viewer && $viewer->user_id === $user->user_id;

        if (! $isOwner) {
            $visibility = $user->profile_visibility ?? 'private';

            if ($visibility === 'private') {
                abort(404);
            }

            if ($visibility === 'landlords_only' && (! $viewer || ! $viewer->hasRole('Landlord'))) {
                abort(404);
            }
        }

        $properties = $user->properties()
            ->where('verification_status', 'Approved')
            ->with('media')
            ->latest('created_at')
            ->get();

        $propertyIds = $properties->pluck('property_id');

        $units = PropertyUnit::whereIn('property_id', $propertyIds)->get();

        $reviews = Review::whereIn('property_id', $propertyIds)
            ->with(['tenant:user_id,first_name,last_name,profile_picture', 'property:property_id,title'])
            ->latest('created_at')
            ->take(10)
            ->get();

        $averageRating = Review::whereIn('property_id', $propertyIds)->avg('rating');

        return response()->json([
            'data' => [
                'user' => $user->only([
                    'user_id', 'first_name', 'last_name', 'profile_picture', 'bio', 'created_at',
                ]),
                'is_owner'        => $isOwner,
                'business'        => $user->rentalBusiness,
                'is_verified'     => (bool) $user->rentalBusiness,
                'properties'      => PropertyResource::collection($properties),
                'total_units'     => $units->count(),
                'occupied_units'  => $units->where('availability_status', 'Occupied')->count(),
                'reviews'         => ReviewResource::collection($reviews),
                'average_rating'  => $averageRating ? round($averageRating, 1) : null,
            ],
        ]);
    }
}
