<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to the recipient on the private user.{id} channel the inbox already
 * subscribes to, so the bell updates without a page load.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Notification $notification)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->notification->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return [
            'notification_id' => $this->notification->notification_id,
            'type'            => $this->notification->type,
            'title'           => $this->notification->title,
            'message'         => $this->notification->message,
            'link'            => $this->notification->link,
            'created_at'      => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
