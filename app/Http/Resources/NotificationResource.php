<?php

namespace App\Http\Resources;

class NotificationResource extends ApiResource
{
    public function toArray($request): array
    {
        return [
            'notification_id' => $this->attr('notification_id'),
            'user_id'         => $this->attr('user_id'),
            'type'            => $this->attr('type'),
            'notifiable_type' => $this->attr('notifiable_type'),
            'notifiable_id'   => $this->attr('notifiable_id'),
            'conversation_id' => $this->attr('conversation_id'),
            'title'           => $this->attr('title'),
            'message'         => $this->attr('message'),
            'link'            => $this->attr('link'),
            'is_read'         => $this->attr('is_read'),
            'created_at'      => $this->attr('created_at'),

            // Polymorphic — NotificationController eager-loads it typed as
            // Review with specific nested selects; passed through as-is
            // rather than modeled here, same as today's raw serialization.
            'notifiable'      => $this->whenLoaded('notifiable'),
        ];
    }
}
