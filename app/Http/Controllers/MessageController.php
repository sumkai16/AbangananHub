<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function store(SendMessageRequest $request, Conversation $conversation)
    {
        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'message' => $request->validated('message'),
        ]);

        $message->load('sender');

        $recipientId = $message->sender_id === $conversation->tenant_id
            ? $conversation->landlord_id
            : $conversation->tenant_id;

        Notification::create([
            'user_id' => $recipientId,
            'type' => 'message',
            'conversation_id' => $conversation->conversation_id,
            'title' => 'New message from ' . $message->sender->first_name,
            'message' => Str::limit($message->message, 100),
            'is_read' => false,
        ]);

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'message_id' => $message->message_id,
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender->first_name . ' ' . $message->sender->last_name,
            'message' => $message->message,
            'sent_at' => $message->sent_at->toIso8601String(),
        ]);
    }
}