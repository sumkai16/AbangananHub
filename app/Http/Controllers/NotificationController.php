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
            $query->whereIn('type', Notification::RESERVATION_TYPES);
        } elseif ($tab === 'needs_action') {
            $query->whereIn('type', Notification::NEEDS_ACTION_TYPES);
        }

        $notifications = $query->paginate(20)->withQueryString();

        $unreadCount = auth()->user()->notifications()
            ->where('type', '!=', 'message')
            ->where('is_read', false)
            ->count();

        // The "Needs action" tab's own number: rent to chase and payment reports to answer that are still open.
        $needsActionCount = auth()->user()->notifications()
            ->whereIn('type', Notification::NEEDS_ACTION_TYPES)
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

        return view('notifications.index', compact('notifications', 'unreadCount', 'needsActionCount', 'tab', 'selected'));
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

    /**
     * One tap from any list of notifications: mark it read and go where it points. Only the owner may open it, and only
     * an address on this site is followed, so a notification can never send someone to another host.
     */
    public function open(Notification $notification)
    {
        abort_if($notification->user_id !== auth()->id(), 403);

        $notification->markAsRead();

        $link = trim((string) $notification->link);

        if ($this->isInternal($link)) {
            return redirect($link);
        }

        return redirect($notification->conversation_id
            ? route('conversations.index', ['active' => $notification->conversation_id])
            : route('notifications.index'));
    }

    private function isInternal(string $link): bool
    {
        if ($link === '') {
            return false;
        }

        $parts = parse_url($link);

        if ($parts === false) {
            return false;
        }

        if (! isset($parts['host'])) {
            // A path on this site. "//host/path" has no scheme but names another host, so it is not one.
            return str_starts_with($link, '/') && ! str_starts_with($link, '//');
        }

        $siteHosts = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);

        return in_array($parts['host'], $siteHosts, true) && in_array($parts['scheme'] ?? 'http', ['http', 'https'], true);
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
