<?php

namespace App\Http\Resources;

class UnitMediaResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'media_id'   => $this->attr('media_id'),
            'unit_id'    => $this->attr('unit_id'),
            'media_type' => $this->attr('media_type'),
            'media_url'  => $this->attr('media_url'),
            'source'     => $this->attr('source'),
            'caption'    => $this->attr('caption'),
        ];
    }
}
