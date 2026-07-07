<?php
namespace App\Http\Controllers;
use App\Events\MessageSent;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use Illuminate\Support\Facades\Auth;
class MessageController extends Controller
{
    public function store(SendMessageRequest $request, Conversation $conversation)
    {
        if ($conversation->isCancelled()) {
            return response()->json(['error' => 'This conversation has been cancelled.'], 403);
        }

        $message = $conversation->messages()->create([
            'sender_id' => Auth::id(),
            'message' => $request->validated('message'),
        ]);
        $message->load('sender');
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