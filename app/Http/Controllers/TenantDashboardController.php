<?php
namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Message;
use App\Models\Favorite;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $upcomingCount = Reservation::where('tenant_id', $user->id)
            ->where('reservation_status', 'Approved')
            ->count();

        $messagesCount = Message::where('sender_id', '!=', $user->id)
            ->count();

        $savedCount = Favorite::where('tenant_id', $user->id)->count();

        $supportCount = Report::where('reporter_id', $user->id)
            ->where('report_status', 'Pending')
            ->count();

        $reservations = Reservation::with('property')
            ->where('tenant_id', $user->id)
            ->orderBy('reservation_date', 'asc')
            ->limit(10)
            ->get();

        return view('dashboard', compact('upcomingCount', 'messagesCount', 'savedCount', 'supportCount', 'reservations'));
    }
}
