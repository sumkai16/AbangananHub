<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandlordVerification;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserRole;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Headline stats ────────────────────────────────────────
        $totalUsers          = User::count();
        $verifiedLandlords   = LandlordVerification::where('verification_status', 'Approved')->count();
        $totalProperties     = Property::count();
        $totalUnits          = PropertyUnit::count();
        $activeReservations  = Reservation::whereNotIn('rental_status', ['Cancelled', 'Rejected'])->count();

        $pendingVerifications = LandlordVerification::where('verification_status', 'Pending')->count();
        $pendingListings      = Property::where('verification_status', 'Pending')->count();
        $pendingUnits         = PropertyUnit::where('verification_status', 'Pending')->count();
        $pendingItems         = $pendingVerifications + $pendingListings + $pendingUnits;

        // ── Reservation breakdown ─────────────────────────────────
        $reservationStats = Reservation::selectRaw('rental_status, COUNT(*) as total')
            ->groupBy('rental_status')
            ->pluck('total', 'rental_status');

        // ── User distribution ─────────────────────────────────────
        $totalLandlords        = UserRole::where('role', 'Landlord')->count();
        $totalTenants          = UserRole::where('role', 'Tenant')->count();
        $totalAdmins           = UserRole::where('role', 'Admin')->count();
        $unverifiedLandlords   = max(0, $totalLandlords - $verifiedLandlords);

        // ── Platform chart – last 7 days ──────────────────────────
        $days = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));

        $usersByDay = User::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $propertiesByDay = Property::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $reservationsByDay = Reservation::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $chartLabels        = $days->map(fn($d) => Carbon::parse($d)->format('M j'))->values();
        $chartUsers         = $days->map(fn($d) => $usersByDay[$d] ?? 0)->values();
        $chartProperties    = $days->map(fn($d) => $propertiesByDay[$d] ?? 0)->values();
        $chartReservations  = $days->map(fn($d) => $reservationsByDay[$d] ?? 0)->values();

        // ── Pending items for the table ───────────────────────────
        $pendingVerificationList = LandlordVerification::where('verification_status', 'Pending')
            ->with('user')
            ->orderByDesc('submitted_at')
            ->limit(8)
            ->get();

        $pendingListingList = Property::where('verification_status', 'Pending')
            ->with('landlord')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $pendingUnitList = PropertyUnit::where('verification_status', 'Pending')
            ->with('property.landlord')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        // ── Recent reservations ───────────────────────────────────
        $recentReservations = Reservation::with(['tenant', 'property'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalUsers', 'verifiedLandlords', 'totalProperties', 'totalUnits',
            'activeReservations', 'pendingItems',
            'pendingVerifications', 'pendingListings', 'pendingUnits',
            'reservationStats',
            'totalLandlords', 'totalTenants', 'totalAdmins', 'unverifiedLandlords',
            'chartLabels', 'chartUsers', 'chartProperties', 'chartReservations',
            'pendingVerificationList', 'pendingListingList', 'pendingUnitList',
            'recentReservations'
        ));
    }
}
