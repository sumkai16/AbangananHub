<?php

namespace App\Http\Resources;

class ReviewResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'review_id'           => $this->attr('review_id'),
            'tenant_id'           => $this->attr('tenant_id'),
            'property_id'         => $this->attr('property_id'),
            'landlord_id'         => $this->attr('landlord_id'),
            'rating'              => $this->attr('rating'),
            'review_comment'      => $this->attr('review_comment'),
            'landlord_reply'      => $this->attr('landlord_reply'),
            'landlord_replied_at' => $this->attr('landlord_replied_at'),
            'is_hidden'           => $this->attr('is_hidden'),
            'created_at'          => $this->attr('created_at'),
            'updated_at'          => $this->attr('updated_at'),

            'tenant'              => new UserResource($this->whenLoaded('tenant')),
            'property'            => new PropertyResource($this->whenLoaded('property')),
        ];
    }
}
