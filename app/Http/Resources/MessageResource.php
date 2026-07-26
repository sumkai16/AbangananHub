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
            'is_system'       => $this->attr('is_system'),
            'sent_at'         => $this->attr('sent_at'),

            'sender'          => new UserResource($this->whenLoaded('sender')),
        ];
    }
}
