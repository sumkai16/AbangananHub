<?php

namespace App\Http\Resources;

class PropertyResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'property_id'          => $this->attr('property_id'),
            'landlord_id'          => $this->attr('landlord_id'),
            'title'                => $this->attr('title'),
            'description'          => $this->attr('description'),
            'house_rules'          => $this->attr('house_rules'),
            'property_type'        => $this->attr('property_type'),
            'address'              => $this->attr('address'),
            'city_municipality'    => $this->attr('city_municipality'),
            'barangay'             => $this->attr('barangay'),
            'latitude'             => $this->attr('latitude'),
            'longitude'            => $this->attr('longitude'),
            'verification_status'  => $this->attr('verification_status'),
            'created_at'           => $this->attr('created_at'),
            'updated_at'           => $this->attr('updated_at'),

            // Query-time computed columns (withMin/withAvg/withCount in
            // Property::scopeBrowsable(), or manually set via setAttribute
            // as PropertyController::index does for is_favorited) — these
            // are real attributes when present, absent otherwise, same as
            // every other column here.
            'min_rental_fee'       => $this->attr('min_rental_fee'),
            'avg_rating'           => $this->attr('avg_rating'),
            'review_count'         => $this->attr('review_count'),
            'is_favorited'         => $this->attr('is_favorited'),

            'landlord'             => new UserResource($this->whenLoaded('landlord')),
            'media'                => PropertyMediaResource::collection($this->whenLoaded('media')),
            'amenities'            => $this->whenLoaded('amenities'),
            'units'                => PropertyUnitResource::collection($this->whenLoaded('units')),
        ];
    }
}
