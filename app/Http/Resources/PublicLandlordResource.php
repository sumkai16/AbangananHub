<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A landlord as the public sees them: the rental business, never the person. Used wherever a landlord is
 * nested in a public payload (browse, property detail, favourites). The full UserResource, with email, phone
 * and photo, stays on the surfaces between the two parties of a reservation or conversation.
 *
 * The business phone number is only sent with a Sanctum token, mirroring the web page, where it sits behind
 * a login.
 */
class PublicLandlordResource extends JsonResource
{
    public function toArray($request): array
    {
        $landlord = $this->resource;
        // Never lazy-load inside a collection: a caller that forgot `landlord.rentalBusiness` gets the
        // person-safe fallback name instead of an N+1 (or a thrown LazyLoadingViolationException).
        $hasBusiness = $landlord->relationLoaded('rentalBusiness');
        $business = $hasBusiness ? $landlord->rentalBusiness : null;

        return [
            'user_id'                 => $landlord->user_id,
            'business_name'           => $hasBusiness ? $landlord->publicName() : $landlord->shortName(),
            'logo_url'                => $business?->logo_url,
            'is_verified'             => $business !== null,
            'member_since'            => $landlord->created_at,
            'business_contact_number' => $this->when(auth('sanctum')->check(), fn () => $business?->contact_number),
        ];
    }
}
