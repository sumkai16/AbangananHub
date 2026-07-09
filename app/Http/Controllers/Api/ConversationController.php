<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Notification;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConversationController extends Controller
{
    /**
     * The requesting user's conversations (as tenant or landlord),
     * newest activity first. Optional ?status=all|unread|resolved|cancelled
     * and ?property_id= filters, matching the web inbox.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;
        $status = $request->query('status', 'all');

        $base = Conversation::where(function ($q) use ($userId) {
            $q->where('tenant_id', $userId)
                ->orWhere('landlord_id', $userId);
        });

        if ($propertyId = $request->query('property_id')) {
            $base->where('property_id', $propertyId);
        }

        $counts = [
            'active'    => (clone $base)->whereNotIn('status', ['Cancelled', 'Resolved'])->count(),
            'resolved'  => (clone $base)->where('status', 'Resolved')->count(),
            'cancelled' => (clone $base)->where('status', 'Cancelled')->count(),
            'unread'    => (clone $base)->whereNotIn('status', ['Cancelled', 'Resolved'])
                ->whereHas('latestMessage', function ($q) use ($userId) {
                    $q->where('sender_id', '!=', $userId)->where('is_read', false);
                })->count(),
        ];

        $query = (clone $base)->with([
            'tenant:user_id,first_name,last_name,profile_picture',
            'landlord:user_id,first_name,last_name,profile_picture',
            'property:property_id,title,address',
            'property.media',
            'unit:unit_id,unit_label,rental_fee',
            'latestMessage',
        ]);

        if ($status === 'resolved') {
            $query->where('status', 'Resolved');
        } elseif ($status === 'cancelled') {
            $query->where('status', 'Cancelled');
        } elseif ($status === 'unread') {
            $query->whereNotIn('status', ['Cancelled', 'Resolved'])
                ->whereHas('latestMessage', function ($q) use ($userId) {
                    $q->where('sender_id', '!=', $userId)->where('is_read', false);
                });
        } else {
            $query->whereNotIn('status', ['Cancelled', 'Resolved']);
        }

        $conversations = $query->orderByDesc('updated_at')->get()
            ->map(function (Conversation $conversation) use ($userId) {
                $isTenant = $conversation->tenant_id === $userId;

                return array_merge($conversation->toArray(), [
                    'other_party' => $isTenant ? $conversation->landlord : $conversation->tenant,
                    'has_unread'  => $conversation->latestMessage
                        && $conversation->latestMessage->sender_id !== $userId
                        && ! $conversation->latestMessage->is_read,
                ]);
            });

        return response()->json([
            'data'   => $conversations,
            'counts' => $counts,
        ]);
    }

    /**
     * Start (or reuse) a conversation about a property.
     * Tenants pass property_id; a landlord starting a thread on their
     * own property must pass tenant_id.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:properties,property_id'],
            'unit_id'     => ['nullable', 'exists:property_units,unit_id'],
            'tenant_id'   => ['nullable', 'exists:users,user_id'],
        ]);

        $property = Property::findOrFail($validated['property_id']);
        $userId = $request->user()->user_id;

        if ((int) $userId === (int) $property->landlord_id) {
            // Landlord starting the thread — a tenant must be specified.
            if (empty($validated['tenant_id'])) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['tenant_id is required when starting a conversation on your own property.'],
                ]);
            }
            if ((int) $validated['tenant_id'] === (int) $userId) {
                abort(403, 'You cannot start a conversation with yourself.');
            }
            $tenantId = (int) $validated['tenant_id'];
        } else {
            $tenantId = $userId;
        }

        $conversation = Conversation::firstOrCreate([
            'tenant_id'   => $tenantId,
            'landlord_id' => $property->landlord_id,
            'property_id' => $property->property_id,
            'unit_id'     => $validated['unit_id'] ?? null,
        ]);

        $conversation->load([
            'tenant:user_id,first_name,last_name,profile_picture',
            'landlord:user_id,first_name,last_name,profile_picture',
            'property:property_id,title,address',
        ]);

        return response()->json(
            ['data' => $conversation],
            $conversation->wasRecentlyCreated ? 201 : 200
        );
    }

    /**
     * A conversation's messages. Marks incoming messages and related
     * message notifications as read, same as the web view.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $userId = $request->user()->user_id;

        Notification::where('user_id', $userId)
            ->where('type', 'message')
            ->where('conversation_id', $conversation->conversation_id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $conversation->messages()
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $conversation->load([
            'tenant:user_id,first_name,last_name,profile_picture',
            'landlord:user_id,first_name,last_name,profile_picture',
            'property:property_id,title,address',
            'property.media',
            'unit:unit_id,unit_label,rental_fee',
            'messages.sender:user_id,first_name,last_name,profile_picture',
            'activeReservation',
        ]);

        $otherParty = $userId === $conversation->tenant_id
            ? $conversation->landlord
            : $conversation->tenant;

        return response()->json([
            'data' => array_merge($conversation->toArray(), [
                'other_party' => $otherParty,
            ]),
        ]);
    }
}
