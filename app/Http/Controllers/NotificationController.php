<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->latest()
            ->paginate(15);

        return view('notifications.index', compact('notifications'));
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