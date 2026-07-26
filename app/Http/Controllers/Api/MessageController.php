<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Http\Resources\MessageResource;
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
            'data' => array_merge((new MessageResource($message))->resolve(), [
                // Kept for existing clients that read this flattened shape
                // rather than sender.first_name/last_name.
                'sender_name' => $message->sender->first_name . ' ' . $message->sender->last_name,
            ]),
        ], 201);
    }
}
