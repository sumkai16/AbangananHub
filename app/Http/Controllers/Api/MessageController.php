<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    /**
     * Send a message in a conversation. Reuses the web
     * SendMessageRequest (validates + authorizes via ConversationPolicy)
     * and broadcasts the same MessageSent event.
     */
    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        if ($conversation->isCancelled()) {
            return response()->json(['message' => 'This conversation has been cancelled.'], 403);
        }

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->user_id,
            'message'   => $request->validated('message'),
        ]);

        $message->load('sender:user_id,first_name,last_name,profile_picture');

        broadcast(new MessageSent($message))->toOthers();

        return response()->json([
            'data' => [
                'message_id'  => $message->message_id,
                'sender_id'   => $message->sender_id,
                'sender_name' => $message->sender->first_name . ' ' . $message->sender->last_name,
                'message'     => $message->message,
                'sent_at'     => $message->sent_at->toIso8601String(),
            ],
        ], 201);
    }
}
