<?php

namespace App\Http\Resources;

class ConversationResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'conversation_id' => $this->attr('conversation_id'),
            'tenant_id'       => $this->attr('tenant_id'),
            'landlord_id'     => $this->attr('landlord_id'),
            'property_id'     => $this->attr('property_id'),
            'unit_id'         => $this->attr('unit_id'),
            'status'          => $this->attr('status'),
            'created_at'      => $this->attr('created_at'),
            'updated_at'      => $this->attr('updated_at'),

            // other_party/has_unread are NOT model attributes — both index()
            // and show() build them via array_merge on the controller side,
            // never via setAttribute — so they stay out of this Resource and
            // the controllers keep merging them onto the resolved array,
            // same as before.

            'tenant'          => new UserResource($this->whenLoaded('tenant')),
            'landlord'        => new UserResource($this->whenLoaded('landlord')),
            'property'        => new PropertyResource($this->whenLoaded('property')),
            'unit'            => new PropertyUnitResource($this->whenLoaded('unit')),
            'messages'        => MessageResource::collection($this->whenLoaded('messages')),
            'latestMessage'   => new MessageResource($this->whenLoaded('latestMessage')),
            'activeReservation' => new ReservationResource($this->whenLoaded('activeReservation')),
        ];
    }
}
