<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');
        $status = $request->input('status', 'all');

        $sort = $request->input('sort', 'newest');

        $searched = Conversation::query()->when($search, function ($q) use ($search) {
            $q->where(function ($inner) use ($search) {
                $inner->whereHas('tenant', fn ($t) => $t->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                )->orWhereHas('landlord', fn ($l) => $l->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                )->orWhereHas('property', fn ($p) => $p->where('title', 'like', "%{$search}%")
                );
            });
        });

        $conversations = (clone $searched)->with(['tenant', 'landlord', 'property', 'latestMessage'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status));

        $sort === 'oldest' ? $conversations->oldest() : $conversations->latest();

        $conversations = $conversations->paginate(15)->withQueryString();

        $counts = [
            'all'      => (clone $searched)->count(),
            'Open'     => (clone $searched)->where('status', 'Open')->count(),
            'Resolved' => (clone $searched)->where('status', 'Resolved')->count(),
        ];

        return view('admin.conversations.index', compact('conversations', 'counts', 'search', 'status', 'sort'));
    }

    public function show(Conversation $conversation)
    {
        // activeReservation + payments: without them the admin reviewing a
        // dispute could read the messages but not see where the rental
        // actually stands — no stage, no handover schedule, no escrow state,
        // while both participants see all three in their own thread.
        $conversation->load([
            'tenant', 'landlord', 'property', 'unit', 'messages.sender', 'messages.concern',
            'activeReservation.payments', 'activeReservation.unit',
        ]);

        return view('admin.conversations.show', [
            'conversation' => $conversation,
            'reservation'  => $conversation->activeReservation,
        ]);
    }
}
