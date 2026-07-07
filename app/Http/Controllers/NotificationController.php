<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Review;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'all');

        $query = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->with(['notifiable' => function (MorphTo $morphTo) {
                $morphTo->morphWith([
                    Review::class => ['tenant', 'property.media', 'landlord'],
                ]);
            }])
            ->latest();

        if ($tab === 'unread') {
            $query->where('is_read', false);
        } elseif ($tab === 'review') {
            $query->where('notifiable_type', 'App\Models\Review');
        } elseif ($tab === 'reservation') {
            $query->where('notifiable_type', 'App\Models\Reservation');
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->count();

        // Side panel: load selected notification detail
        $selected = null;
        if ($request->filled('selected')) {
            $selected = Notification::with(['notifiable' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Review::class => ['tenant', 'property.media', 'landlord'],
                    ]);
                }])
                ->where('notification_id', $request->selected)
                ->where('user_id', auth()->id())
                ->first();

            if ($selected && !$selected->is_read) {
                $selected->markAsRead();
            }
        }

        return view('notifications.index', compact('notifications', 'unreadCount', 'tab', 'selected'));
    }

    public function recent()
    {
        $notifications = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->latest()
            ->take(8)
            ->get();

        $unreadCount = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->count();

        return view('notifications.partials.dropdown', compact('notifications', 'unreadCount'));
    }

    public function markRead(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);
        $notification->markAsRead();
        return response()->noContent();
    }

    public function markAllRead()
    {
        Notification::where('user_id', auth()->id())
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if (request()->expectsJson() || request()->ajax()) {
            return response()->noContent();
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}