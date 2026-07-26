<?php

namespace App\Http\Resources;

class PropertyMediaResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'media_id'   => $this->attr('media_id'),
            'property_id' => $this->attr('property_id'),
            'unit_id'    => $this->attr('unit_id'),
            'media_type' => $this->attr('media_type'),
            'media_url'  => $this->attr('media_url'),
            'cloudinary_public_id' => $this->attr('cloudinary_public_id'),
        ];
    }
}
