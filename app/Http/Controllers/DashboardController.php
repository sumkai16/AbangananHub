<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $upcomingCount = $user->reservations()
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->count();

        $messagesCount = Notification::where('user_id', $user->user_id)
            ->where('type', 'message')
            ->where('is_read', false)
            ->count();

        $savedCount = $user->favorites()->count();

        $reportsCount = $user->reports()
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