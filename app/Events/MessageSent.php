<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Message $message)
    {
    }

    public function broadcastOn(): array
    {
        $conversation = $this->message->conversation;

        $channels = [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
        ];

        // System messages have no sender, so there is no single "other
        // person" — both parties need it on their user channel.
        if ($this->message->sender_id === null) {
            $channels[] = new PrivateChannel('user.' . $conversation->tenant_id);
            $channels[] = new PrivateChannel('user.' . $conversation->landlord_id);

            return $channels;
        }

        $recipientId = $this->message->sender_id === $conversation->tenant_id
            ? $conversation->landlord_id
            : $conversation->tenant_id;

        $channels[] = new PrivateChannel('user.' . $recipientId);

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    public function broadcastWith(): array
    {
        $conversation = $this->message->conversation;
        $property = $conversation->property;
        $unit = $conversation->unit;

        $sender = $this->message->sender;

        return [
            'message_id'      => $this->message->message_id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            // Null on system messages — sender_id is null there, and reading
            // ->first_name off it would fatal.
            'sender_name'     => $sender ? $sender->first_name . ' ' . $sender->last_name : null,
            'sender_initial'  => $sender ? strtoupper(substr($sender->first_name, 0, 1)) : null,
            // Cloudinary URL — output as-is, never through Storage::url().
            'sender_avatar'   => $sender?->profile_picture,
            'is_system'       => (bool) $this->message->is_system,
            'message'         => $this->message->message,
            'sent_at'         => $this->message->sent_at->toIso8601String(),
            'property_title'  => $property?->title ?? '',
            'unit_label'      => $unit?->unit_label ?? '',
        ];
    }
}