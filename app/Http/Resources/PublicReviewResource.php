<?php

namespace App\Http\Resources;

/**
 * A review on a public surface (property detail, a landlord's business page). Same fields as ReviewResource,
 * but the reviewer is "Juan D." with no photo or contact details. The landlord's own review screens keep the
 * full ReviewResource.
 */
class PublicReviewResource extends ReviewResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        $data['tenant'] = $this->whenLoaded('tenant', fn () => [
            'user_id'    => $this->tenant->user_id,
            'short_name' => $this->tenant->shortName(),
        ]);

        return $data;
    }
}
