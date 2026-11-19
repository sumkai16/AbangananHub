<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandlordVerification;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Services\AdminQueues;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Headline stats ────────────────────────────────────────
        $totalUsers          = User::count();
        $verifiedLandlords   = LandlordVerification::where('verification_status', 'Approved')->count();
        $totalProperties     = Property::count();
        $totalUnits          = PropertyUnit::count();
        $activeReservations  = Reservation::whereNotIn('rental_status', Reservation::TERMINAL_STATUSES)->count();

        // ── What is waiting on the admin ──────────────────────────
        $queue = AdminQueues::rows();
        $openItems = collect($queue)->sum('count');

        $moneyHeld = (float) Payment::where('status', 'Held')->sum('amount');
        $moneyOwed = (float) Payment::pendingPayout()->sum('amount');

        // ── Platform chart – last 7 days ──────────────────────────
        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $usersByDay = User::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $propertiesByDay = Property::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $reservationsByDay = Reservation::selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('day')->pluck('total', 'day');

        $chartLabels        = $days->map(fn ($d) => Carbon::parse($d)->format('M j'))->values();
        $chartUsers         = $days->map(fn ($d) => $usersByDay[$d] ?? 0)->values();
        $chartProperties    = $days->map(fn ($d) => $propertiesByDay[$d] ?? 0)->values();
        $chartReservations  = $days->map(fn ($d) => $reservationsByDay[$d] ?? 0)->values();

        // ── Recent reservations ───────────────────────────────────
        $recentReservations = Reservation::with(['tenant', 'property'])
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $reservationLedger = $this->reservationLedger();

        return view('admin.dashboard', compact(
            'totalUsers', 'verifiedLandlords', 'totalProperties', 'totalUnits', 'activeReservations',
            'queue', 'openItems', 'moneyHeld', 'moneyOwed', 'reservationLedger',
            'chartLabels', 'chartUsers', 'chartProperties', 'chartReservations',
            'recentReservations'
        ));
    }

    /**
     * Reservations by where they stand, for the ledger on the dashboard. Shares use the largest remainder method, so
     * the column always adds up to 100%.
     *
     * @return array{total: int, rows: list<array{label: string, count: int, share: int, href: string}>}
     */
    private function reservationLedger(): array
    {
        $byStatus = Reservation::selectRaw('rental_status, COUNT(*) as total')
            ->groupBy('rental_status')
            ->pluck('total', 'rental_status');

        $count = fn (array $statuses) => (int) collect($statuses)->sum(fn ($status) => $byStatus[$status] ?? 0);

        $rows = [
            ['label' => 'In progress', 'count' => $count(['Inquiry', 'Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Reserved']), 'href' => route('admin.reservations.index')],
            ['label' => 'Occupied', 'count' => $count(['Occupied']), 'href' => route('admin.reservations.index', ['status' => 'Occupied'])],
            ['label' => 'Completed', 'count' => $count(['Completed']), 'href' => route('admin.reservations.index', ['status' => 'Completed'])],
            ['label' => 'Cancelled', 'count' => $count(['Cancelled']), 'href' => route('admin.reservations.index', ['status' => 'Cancelled'])],
            ['label' => 'Rejected', 'count' => $count(['Rejected']), 'href' => route('admin.reservations.index', ['status' => 'Rejected'])],
        ];

        $total = array_sum(array_column($rows, 'count'));
        $exact = array_map(fn ($row) => $total > 0 ? $row['count'] / $total * 100 : 0, $rows);
        $shares = array_map(fn ($value) => (int) floor($value), $exact);

        if ($total > 0) {
            $remainders = array_map(fn ($value, $share) => $value - $share, $exact, $shares);
            arsort($remainders);
            foreach (array_slice(array_keys($remainders), 0, 100 - array_sum($shares)) as $index) {
                $shares[$index]++;
            }
        }

        foreach ($rows as $index => $row) {
            $rows[$index]['share'] = $shares[$index];
        }

        return ['total' => $total, 'rows' => $rows];
    }
}
