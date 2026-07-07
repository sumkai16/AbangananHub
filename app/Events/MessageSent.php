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

        // Determine recipient (the other person in the conversation)
        $recipientId = $this->message->sender_id === $conversation->tenant_id
            ? $conversation->landlord_id
            : $conversation->tenant_id;

        return [
            new PrivateChannel('conversation.' . $this->message->conversation_id),
            new PrivateChannel('user.' . $recipientId),
        ];
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

        return [
            'message_id'      => $this->message->message_id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $this->message->sender->first_name . ' ' . $this->message->sender->last_name,
            'sender_initial'  => strtoupper(substr($this->message->sender->first_name, 0, 1)),
            'message'         => $this->message->message,
            'sent_at'         => $this->message->sent_at->toIso8601String(),
            'property_title'  => $property?->title ?? '',
            'unit_label'      => $unit?->unit_label ?? '',
        ];
    }
}