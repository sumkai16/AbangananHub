<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(15);
        return view('notifications.index', compact('notifications'));
    }

    public function recent()
    {
        $notifications = auth()->user()->notifications()->latest()->take(8)->get();
        $unreadCount = Notification::unreadCount(auth()->id());
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
        Notification::markAllAsRead(auth()->id());

        if (request()->expectsJson() || request()->ajax()) {
            return response()->noContent();
        }

        return back()->with('status', 'All notifications marked as read.');
    }
}