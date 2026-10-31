<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Property;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::id();
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $propertyId = $request->query('property_id');

        $party = fn ($q) => $q->where('tenant_id', $userId)->orWhere('landlord_id', $userId);

        // Two inboxes: the deal chats ("inquiries") and the post-move-in chats ("tenancy").
        $box = $request->query('box') === 'tenancy' ? Conversation::TYPE_TENANCY : Conversation::TYPE_INQUIRY;
        $hasTenancy = Conversation::where($party)->tenancies()->exists();
        $boxUnread = [
            Conversation::TYPE_INQUIRY => $this->unreadIn(Conversation::TYPE_INQUIRY, $userId),
            Conversation::TYPE_TENANCY => $this->unreadIn(Conversation::TYPE_TENANCY, $userId),
        ];

        $base = Conversation::where($party)->where('type', $box);

        if ($propertyId) {
            $base->where('property_id', $propertyId);
        }

        $activeCount = (clone $base)->whereNotIn('status', ['Cancelled', 'Resolved'])->count();
        $resolvedCount = (clone $base)->where('status', 'Resolved')->count();
        $cancelledCount = (clone $base)->where('status', 'Cancelled')->count();
        $unreadCount = (clone $base)->where('status', '!=', 'Resolved')
            ->where('status', '!=', 'Cancelled')
            ->whereHas('latestMessage', function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)
                    ->where('is_read', false);
            })->count();

        $query = (clone $base)
            ->with(['tenant', 'landlord', 'property', 'unit', 'latestMessage', 'activeReservation.payments'])
            ->withCount(['concerns as open_concerns_count' => fn ($q) => $q->unresolved()]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('property', fn ($pq) => $pq->where('title', 'like', "%{$search}%"))
                    ->orWhereHas('tenant', fn ($uq) => $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"))
                    ->orWhereHas('landlord', fn ($uq) => $uq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if ($status === 'resolved') {
            $query->where('status', 'Resolved');
        } elseif ($status === 'cancelled') {
            $query->where('status', 'Cancelled');
        } elseif ($status === 'unread') {
            $query->where('status', '!=', 'Resolved')
                ->where('status', '!=', 'Cancelled')
                ->whereHas('latestMessage', function ($q) use ($userId) {
                    $q->where('sender_id', '!=', $userId)
                        ->where('is_read', false);
                });
        } else {
            // Default "active" tab — exclude terminal states
            $query->whereNotIn('status', ['Cancelled', 'Resolved']);
        }

        $conversations = $query->orderByDesc('updated_at')->get();

        // Landlord property filter list
        $user = Auth::user();
        $isLandlord = $user->hasRole('Landlord');
        $landlordProperties = $isLandlord
            ? Property::where('landlord_id', $userId)->orderBy('title')->get(['property_id', 'title'])
            : collect();

        return view('conversations.index', compact(
            'conversations', 'status', 'activeCount', 'unreadCount', 'resolvedCount', 'cancelledCount',
            'isLandlord', 'landlordProperties', 'propertyId', 'box', 'hasTenancy', 'boxUnread'
        ));
    }

    /** Conversations of one type with an unread message from the other person, still open. */
    private function unreadIn(string $type, int $userId): int
    {
        return Conversation::where(fn ($q) => $q->where('tenant_id', $userId)->orWhere('landlord_id', $userId))
            ->where('type', $type)
            ->whereNotIn('status', ['Cancelled', 'Resolved'])
            ->whereHas('latestMessage', fn ($q) => $q->where('sender_id', '!=', $userId)->where('is_read', false))
            ->count();
    }

    /**
     * Entry point from a tenancy page: open (creating if needed) the chat for this
     * tenancy and land in it. Both the tenant and the landlord come through here.
     */
    public function tenancyChat(Request $request, Reservation $reservation)
    {
        Gate::authorize('viewTenancyChat', $reservation);

        $thread = $reservation->tenancyThread ?? $reservation->openTenancyThread();

        // Walk-in tenants have no account to chat from.
        abort_unless($thread, 404, 'This tenant has no chat.');

        return redirect()->route('conversations.index', array_filter([
            'box' => 'tenancy',
            'active' => $thread->conversation_id,
            'concern' => $request->query('concern') === 'new' ? 'new' : null,
        ]));
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
            'type' => Conversation::TYPE_INQUIRY,
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

        // One load() with every path: a second load('messages.…') would re-fetch the
        // messages and drop the sender eager load (Model::preventLazyLoading then throws).
        $relations = ['tenant', 'landlord', 'property.media', 'unit.media', 'messages.sender', 'messages.paymentQrShare.sharedBy', 'activeReservation.payments', 'activeReservation.paymentClaims', 'activeReservation.unit', 'activeReservation.property'];
        if ($conversation->isTenancy()) {
            array_push($relations, 'reservation', 'concerns', 'messages.concern.tenant', 'reservation.payments', 'reservation.depositCharges', 'reservation.unit');
        }
        $conversation->load($relations);

        $otherParty = Auth::id() === $conversation->tenant_id
            ? $conversation->landlord
            : $conversation->tenant;

        // AJAX request → return chat partial only
        if (request()->ajax() || request()->wantsJson()) {
            return view(
                $conversation->isTenancy() ? 'conversations.partials.tenancy-panel' : 'conversations.partials.chat-panel',
                compact('conversation', 'otherParty')
            );
        }

        // A full page load of this route only forwards to the inbox. Flash data lasts one request, so keep
        // it for the inbox or "Conversation marked as resolved." is gone before anyone sees it.
        session()->reflash();

        return redirect()->route('conversations.index', array_filter([
            'active' => $conversation->conversation_id,
            'box' => $conversation->isTenancy() ? 'tenancy' : null,
        ]));
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
