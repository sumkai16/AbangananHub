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
        $status = $request->query('status', 'all');
        $propertyId = $request->query('property_id');

        $base = Conversation::where(function ($q) use ($userId) {
            $q->where('tenant_id', $userId)
                ->orWhere('landlord_id', $userId);
        });

        if ($propertyId) {
            $base->where('property_id', $propertyId);
        }

        $allCount = (clone $base)->count();
        $resolvedCount = (clone $base)->where('status', 'Resolved')->count();
        $unreadCount = (clone $base)->where('status', '!=', 'Resolved')
            ->whereHas('latestMessage', function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)
                    ->where('is_read', false);
            })->count();

        $query = (clone $base)->with(['tenant', 'landlord', 'property', 'unit', 'latestMessage']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('property', fn($pq) => $pq->where('title', 'like', "%{$search}%"))
                  ->orWhereHas('tenant', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"))
                  ->orWhereHas('landlord', fn($uq) => $uq->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($status === 'resolved') {
            $query->where('status', 'Resolved');
        } elseif ($status === 'unread') {
            $query->where('status', '!=', 'Resolved')
                ->whereHas('latestMessage', function ($q) use ($userId) {
                    $q->where('sender_id', '!=', $userId)
                        ->where('is_read', false);
                });
        }

        $conversations = $query->orderByDesc('updated_at')->get();

        // Landlord property filter list
        $user = Auth::user();
        $isLandlord = $user->hasRole('Landlord');
        $landlordProperties = $isLandlord
            ? Property::where('landlord_id', $userId)->orderBy('title')->get(['property_id', 'title'])
            : collect();

        return view('conversations.index', compact(
            'conversations', 'status', 'allCount', 'unreadCount', 'resolvedCount',
            'isLandlord', 'landlordProperties', 'propertyId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,property_id'],
            'unit_id' => ['nullable', 'exists:property_units,unit_id'],
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
            'unit_id' => $validated['unit_id'] ?? null,
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

        $conversation->messages()
            ->where('sender_id', '!=', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $conversation->load(['tenant', 'landlord', 'property.media', 'unit', 'messages.sender', 'activeReservation']);

        $otherParty = Auth::id() === $conversation->tenant_id
            ? $conversation->landlord
            : $conversation->tenant;

        // AJAX request → return chat partial only
        if (request()->ajax() || request()->wantsJson()) {
            return view('conversations.partials.chat-panel', compact('conversation', 'otherParty'));
        }

       return redirect()->route('conversations.index', ['active' => $conversation->conversation_id]);
    }

    public function resolve(Conversation $conversation)
    {
        Gate::authorize('resolve', $conversation);
        $conversation->update(['status' => 'Resolved']);

        if (request()->ajax()) {
            return response()->json(['status' => 'Resolved']);
        }

        return redirect()->route('conversations.show', $conversation)
            ->with('status', 'Conversation marked as resolved.');
    }
    public function recentMessages()
{
    $userId = Auth::id();

    $conversations = Conversation::where(function ($q) use ($userId) {
            $q->where('tenant_id', $userId)
              ->orWhere('landlord_id', $userId);
        })
        ->with(['tenant', 'landlord', 'property', 'unit', 'latestMessage'])
        ->orderByDesc('updated_at')
        ->take(5)
        ->get();

    $unreadCount = Conversation::where(function ($q) use ($userId) {
            $q->where('tenant_id', $userId)
              ->orWhere('landlord_id', $userId);
        })
        ->whereHas('latestMessage', function ($q) use ($userId) {
            $q->where('sender_id', '!=', $userId)
              ->where('is_read', false);
        })
        ->count();

    return view('conversations.partials.bubble-panel', compact('conversations', 'unreadCount'));
}
}