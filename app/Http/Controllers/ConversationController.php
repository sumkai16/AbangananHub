<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use App\Models\Notification;
class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->query('search');

        $query = Conversation::with(['tenant', 'landlord', 'property', 'latestMessage'])
            ->where(function ($q) use ($userId) {
                $q->where('tenant_id', $userId)
                    ->orWhere('landlord_id', $userId);
            });

        if ($search) {
            $query->where(function ($q) use ($search, $userId) {
                $q->whereHas('property', fn($pq) => $pq->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('tenant', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('landlord', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        $conversations = $query->orderByDesc('updated_at')->get();

        return view('conversations.index', compact('conversations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,property_id'],
        ]);

        $property = Property::findOrFail($validated['property_id']);
        $tenantId = Auth::id();

        if ((int) $tenantId === (int) $property->landlord_id) {
            abort(403, 'You cannot start a conversation with yourself.');
        }

        $conversation = Conversation::firstOrCreate([
            'tenant_id' => $tenantId,
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
        ]);

        return redirect()->route('conversations.show', $conversation);
    }

public function show(Conversation $conversation)
{
    Gate::authorize('view', $conversation);

    Notification::where('user_id', Auth::id())
        ->where('type', 'message')
        ->where('conversation_id', $conversation->conversation_id)
        ->where('is_read', false)
        ->update(['is_read' => true]);

    $conversation->load(['tenant', 'landlord', 'property', 'messages.sender']);

    return view('conversations.show', compact('conversation'));
}
}