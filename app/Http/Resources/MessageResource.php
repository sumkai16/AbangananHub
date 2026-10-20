<?php

namespace App\Http\Resources;

class MessageResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'message_id'      => $this->attr('message_id'),
            'conversation_id' => $this->attr('conversation_id'),
            'sender_id'       => $this->attr('sender_id'),
            'message'         => $this->attr('message'),
            'is_read'         => $this->attr('is_read'),
            // `kind` is the stored field; the two flags are derived from it and kept so the mobile app
            // keeps working. concern_id lets a client tell a concern card from a plain message.
            'kind'            => $this->attr('kind'),
            'is_system'       => $this->resource->is_system,
            'is_inquiry_summary' => $this->resource->is_inquiry_summary,
            'concern_id'      => $this->attr('concern_id'),
            'sent_at'         => $this->attr('sent_at'),

            'sender'          => new UserResource($this->whenLoaded('sender')),
        ];
    }
}
