<?php

namespace App\Http\Resources;

class PropertyUnitResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'unit_id'              => $this->attr('unit_id'),
            'property_id'          => $this->attr('property_id'),
            'unit_label'           => $this->attr('unit_label'),
            // unit_type/floor/security_deposit are $fillable but no migration
            // defines the columns yet (ARCHITECTURE.md "KNOWN BUG", July 2026)
            // — attr() correctly returns MISSING for them until that lands.
            'unit_type'            => $this->attr('unit_type'),
            'floor'                => $this->attr('floor'),
            'description'          => $this->attr('description'),
            'rental_fee'           => $this->attr('rental_fee'),
            'security_deposit'     => $this->attr('security_deposit'),
            'occupancy_limit'      => $this->attr('occupancy_limit'),
            'availability_status'  => $this->attr('availability_status'),
            'verification_status'  => $this->attr('verification_status'),
            'rejection_reason'     => $this->attr('rejection_reason'),
            'vacated_at'           => $this->attr('vacated_at'),
            'created_at'           => $this->attr('created_at'),
            'updated_at'           => $this->attr('updated_at'),

            'property'             => new PropertyResource($this->whenLoaded('property')),
            'media'                => UnitMediaResource::collection($this->whenLoaded('media')),
        ];
    }
}
