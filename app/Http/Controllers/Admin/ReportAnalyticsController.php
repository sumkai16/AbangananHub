<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\PropertyUnit;
use App\Models\Reservation;
use App\Models\User;
use App\Models\UserRole;
use App\Models\LandlordVerification;
use App\Services\OccupancyRateCalculator;
use Illuminate\Http\Request;

class ReportAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $section = $request->input('section', 'properties');

        $data = match ($section) {
            'reservations' => $this->reservationsData($request),
            'users'        => $this->usersData($request),
            default        => $this->propertiesData($request),
        };

        $data['section'] = $section;

        return view('admin.report-analytics.index', $data);
    }

    // ─── Properties & Units ──────────────────────────────────

    private function propertiesData(Request $request): array
    {
        $typeFilter   = $request->input('type');
        $statusFilter = $request->input('status');

        // All properties for stat cards (unfiltered)
        $allProperties = Property::withCount([
            'units',
            'units as available_units_count' => fn ($q) => $q->where('availability_status', 'Available'),
            'units as reserved_units_count'  => fn ($q) => $q->where('availability_status', 'Reserved'),
            'units as occupied_units_count'  => fn ($q) => $q->where('availability_status', 'Occupied'),
        ])->get();

        $totalProperties = $allProperties->count();
        $totalUnits      = $allProperties->sum('units_count');
        $approvedUnits   = PropertyUnit::where('verification_status', 'Approved')->count();
        $occupiedUnits   = PropertyUnit::where('verification_status', 'Approved')
                            ->where('availability_status', 'Occupied')->count();
        $occupancyRate   = $approvedUnits > 0 ? round(($occupiedUnits / $approvedUnits) * 100, 1) : 0;

        // Donut data — unit statuses across all properties
        $allUnits      = PropertyUnit::count();
        $availableAll  = PropertyUnit::where('availability_status', 'Available')->count();
        $reservedAll   = PropertyUnit::where('availability_status', 'Reserved')->count();
        $occupiedAll   = PropertyUnit::where('availability_status', 'Occupied')->count();

        // Breakdown by property type
        $typeBreakdown = Property::selectRaw("property_type, COUNT(*) as property_count")
            ->groupBy('property_type')
            ->get()
            ->map(function ($row) {
                $units    = PropertyUnit::whereIn('property_id', Property::where('property_type', $row->property_type)->pluck('property_id'))
                    ->where('verification_status', 'Approved');
                $total    = (clone $units)->count();
                $occupied = (clone $units)->where('availability_status', 'Occupied')->count();

                return [
                    'type'           => $row->property_type,
                    'property_count' => $row->property_count,
                    'unit_count'     => $total,
                    'occupied_count' => $occupied,
                    'rate'           => $total > 0 ? round(($occupied / $total) * 100, 1) : 0,
                ];
            });

        // Filtered table
        $tableQuery = Property::withCount([
            'units',
            'units as available_units_count' => fn ($q) => $q->where('availability_status', 'Available'),
            'units as occupied_units_count'  => fn ($q) => $q->where('availability_status', 'Occupied'),
        ]);

        if ($typeFilter) {
            $tableQuery->where('property_type', $typeFilter);
        }
        if ($statusFilter) {
            $tableQuery->where('verification_status', $statusFilter);
        }

        $properties = $tableQuery->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return compact(
            'totalProperties', 'totalUnits', 'occupancyRate',
            'availableAll', 'reservedAll', 'occupiedAll',
            'typeBreakdown', 'properties',
            'typeFilter', 'statusFilter'
        );
    }

    // ─── Reservations ────────────────────────────────────────

    private function reservationsData(Request $request): array
    {
        $statusFilter = $request->input('status');
        $timeFilter   = $request->input('time');

        // All reservations for stat cards
        $allReservations = Reservation::count();
        $approvedCount   = Reservation::whereIn('rental_status', [
            'Under Negotiation', 'Pending Rental Agreement',
            'Rental Agreement Signed', 'Occupied',
        ])->count();
        $pendingCount    = Reservation::where('rental_status', 'Inquiry')->count();
        $rejectedCount   = Reservation::where('rental_status', 'Rejected')->count();
        $cancelledCount  = Reservation::where('rental_status', 'Cancelled')->count();
        $occupiedUnits   = PropertyUnit::where('availability_status', 'Occupied')
                            ->where('verification_status', 'Approved')->count();
        $approvalRate    = $allReservations > 0
            ? round(($approvedCount / $allReservations) * 100, 1)
            : 0;

        // Breakdown by property type
        $typeBreakdown = Property::selectRaw("property_type")
            ->distinct()
            ->pluck('property_type')
            ->map(function ($type) {
                $propertyIds  = Property::where('property_type', $type)->pluck('property_id');
                $total        = Reservation::whereIn('property_id', $propertyIds)->count();
                $approved     = Reservation::whereIn('property_id', $propertyIds)
                    ->whereIn('rental_status', [
                        'Under Negotiation', 'Pending Rental Agreement',
                        'Rental Agreement Signed', 'Occupied',
                    ])->count();

                return [
                    'type'     => $type,
                    'total'    => $total,
                    'approved' => $approved,
                    'pct'      => $total,
                ];
            });
        $totalForPct = $typeBreakdown->sum('total');
        $typeBreakdown = $typeBreakdown->map(function ($row) use ($totalForPct) {
            $row['pct'] = $totalForPct > 0 ? round(($row['total'] / $totalForPct) * 100, 1) : 0;
            return $row;
        });

        // Filtered table
        $tableQuery = Reservation::with(['tenant', 'property', 'unit']);

        if ($statusFilter) {
            $tableQuery->where('rental_status', $statusFilter);
        }
        if ($timeFilter) {
            $days = match ($timeFilter) {
                '7'  => 7,
                '30' => 30,
                '90' => 90,
                default => null,
            };
            if ($days) {
                $tableQuery->where('created_at', '>=', now()->subDays($days));
            }
        }

        $reservations = $tableQuery->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return compact(
            'allReservations', 'occupiedUnits', 'approvalRate',
            'approvedCount', 'pendingCount', 'rejectedCount', 'cancelledCount',
            'typeBreakdown', 'reservations',
            'statusFilter', 'timeFilter'
        );
    }

    // ─── Users ───────────────────────────────────────────────

    private function usersData(Request $request): array
    {
        $roleFilter   = $request->input('role');
        $statusFilter = $request->input('status');

        $totalUsers      = User::count();
        $adminCount      = UserRole::where('role', 'Admin')->count();
        $landlordCount   = UserRole::where('role', 'Landlord')->count();
        $tenantCount     = UserRole::where('role', 'Tenant')->count();
        $noRoleCount     = $totalUsers - ($adminCount + $landlordCount + $tenantCount);
        $suspendedCount  = User::where('account_status', 'Suspended')->count();

        // Verification pipeline
        $verifiedCount  = LandlordVerification::where('verification_status', 'Approved')->count();
        $pendingVerif   = LandlordVerification::where('verification_status', 'Pending')->count();
        $rejectedVerif  = LandlordVerification::where('verification_status', 'Rejected')->count();

        // Registration trend
        $thisWeek   = User::where('created_at', '>=', now()->startOfWeek())->count();
        $last30Days = User::where('created_at', '>=', now()->subDays(30))->count();

        // Filtered table
        $tableQuery = User::query()
            ->addSelect([
                'users.*',
                'role' => UserRole::selectRaw('role')
                    ->whereColumn('user_roles.user_id', 'users.user_id')
                    ->limit(1),
            ])
          ->with(['verificationApplication', 'roles']);

        if ($roleFilter) {
            $userIds = UserRole::where('role', $roleFilter)->pluck('user_id');
            $tableQuery->whereIn('user_id', $userIds);
        }
        if ($statusFilter) {
            $tableQuery->where('account_status', $statusFilter);
        }

        $users = $tableQuery->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        return compact(
            'totalUsers', 'adminCount', 'landlordCount', 'tenantCount', 'noRoleCount',
            'suspendedCount', 'verifiedCount', 'pendingVerif', 'rejectedVerif',
            'thisWeek', 'last30Days',
            'users', 'roleFilter', 'statusFilter'
        );
    }
}