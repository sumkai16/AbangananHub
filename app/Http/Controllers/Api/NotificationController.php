<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Paginated notifications (message-type ones live in conversations,
     * so they are excluded here — same as the web notifications page).
     * Optional ?tab=all|unread|review|reservation.
     */
    public function index(Request $request): JsonResponse
    {
        $tab = $request->input('tab', 'all');

        $query = $request->user()->notifications()
            ->where('type', '!=', 'message')
            ->with(['notifiable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Review::class => ['tenant:user_id,first_name,last_name', 'property:property_id,title'],
                ]);
            }])
            ->latest();

        if ($tab === 'unread') {
            $query->where('is_read', false);
        } elseif ($tab === 'review') {
            $query->where('notifiable_type', Review::class);
        } elseif ($tab === 'reservation') {
            $query->where('notifiable_type', \App\Models\Reservation::class);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = $request->user()->notifications()
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->count();

        return response()->json(array_merge($notifications->toArray(), [
            'unread_count' => $unreadCount,
        ]));
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_if($notification->user_id !== $request->user()->user_id, 403);

        $notification->markAsRead();

        return response()->json(['data' => $notification]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->user_id)
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }
}
