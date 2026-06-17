<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
         /** @var User $user */
        $user = Auth::user();

        // Latest 5 reservations for the dashboard preview
        $reservations = $user->reservations()
            ->with('property')
            ->latest('created_at')
            ->take(5)
            ->get();

        // Stat counters
        $upcomingCount  = $user->reservations()
            ->where('reservation_status', 'Approved')
            ->count();

        $messagesCount  = \App\Models\Conversation::where('tenant_id', $user->user_id)
            ->orWhere('landlord_id', $user->user_id)
            ->withCount(['messages as unread_count' => function ($q) use ($user) {
                $q->where('sender_id', '!=', $user->user_id)
                  ->whereNull('read_at'); // add read_at to messages table later
            }])
            ->get()
            ->sum('unread_count');

        $savedCount     = $user->favorites()->count();

        $reportsCount   = $user->reports()
            ->where('report_status', 'Pending')
            ->count();

        // Recent activity from notifications
        $recentActivity = $user->notifications()
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('dashboard', compact(
            'reservations',
            'upcomingCount',
            'messagesCount',
            'savedCount',
            'reportsCount',
            'recentActivity',
        ));
    }
}