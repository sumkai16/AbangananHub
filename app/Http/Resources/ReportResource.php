<?php

namespace App\Http\Resources;

class ReportResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'report_id'        => $this->attr('report_id'),
            'reporter_id'      => $this->attr('reporter_id'),
            'property_id'      => $this->attr('property_id'),
            'reported_user_id' => $this->attr('reported_user_id'),
            'report_reason'    => $this->attr('report_reason'),
            'report_status'    => $this->attr('report_status'),
            'created_at'       => $this->attr('created_at'),
            'updated_at'       => $this->attr('updated_at'),

            'property'      => new PropertyResource($this->whenLoaded('property')),
            'reported_user' => new UserResource($this->whenLoaded('reportedUser')),
        ];
    }
}
