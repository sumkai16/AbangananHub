<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\Message;
use App\Models\Favorite;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user   = Auth::user();
        $userId = $user->user_id; // Custom PK — $user->id returns null

        $upcomingCount = Reservation::where('tenant_id', $userId)
            ->whereNotIn('rental_status', ['Cancelled', 'Rejected'])
            ->count();

        $messagesCount = Message::where('sender_id', '!=', $userId)
            ->count();

        $savedCount = Favorite::where('tenant_id', $userId)->count();

        // Variable renamed to $reportsCount to match the dashboard view
        $reportsCount = Report::where('reporter_id', $userId)
            ->where('report_status', 'Pending')
            ->count();

        $reservations = Reservation::with('property')
            ->where('tenant_id', $userId)
            ->orderBy('reservation_date', 'asc')
            ->limit(10)
            ->get();

        // Placeholder until an Activity/Notification model is implemented
        $recentActivity = collect();

        return view('dashboard', compact(
            'upcomingCount',
            'messagesCount',
            'savedCount',
            'reportsCount',
            'reservations',
            'recentActivity'
        ));
    }
}
