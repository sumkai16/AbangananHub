<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
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

        return view('notifications.partials.dropdown', compact('notifications'));
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

        return response()->noContent();
    }
}