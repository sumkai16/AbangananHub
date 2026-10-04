<?php

namespace App\Events;

use App\Models\ViewingRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A viewing was requested, rescheduled, confirmed, declined or cancelled.
 *
 * Same shape as HandoverScheduleUpdated: no markup, because the card renders
 * differently for each party (one sees "Waiting for …", the other sees the
 * buttons). Clients refetch their own panel.
 */
class ViewingScheduleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $conversationId, public int $viewingId)
    {
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'ViewingScheduleUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'viewing_id'      => $this->viewingId,
        ];
    }
}
