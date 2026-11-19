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

        // Breakdown by property type: approved-unit totals for every type in one grouped query.
        $unitsByType = PropertyUnit::join('properties', 'properties.property_id', '=', 'property_units.property_id')
            ->where('property_units.verification_status', 'Approved')
            ->groupBy('properties.property_type')
            ->selectRaw("properties.property_type, COUNT(*) as total, SUM(property_units.availability_status = 'Occupied') as occupied")
            ->get()
            ->keyBy('property_type');

        $typeBreakdown = Property::selectRaw("property_type, COUNT(*) as property_count")
            ->groupBy('property_type')
            ->get()
            ->map(function ($row) use ($unitsByType) {
                $total    = (int) ($unitsByType[$row->property_type]->total ?? 0);
                $occupied = (int) ($unitsByType[$row->property_type]->occupied ?? 0);

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

        // Breakdown by property type: reservation totals for every type in one grouped query.
        $reservationsByType = Reservation::join('properties', 'properties.property_id', '=', 'reservations.property_id')
            ->groupBy('properties.property_type')
            ->selectRaw("properties.property_type, COUNT(*) as total, SUM(reservations.rental_status IN ('Under Negotiation', 'Pending Rental Agreement', 'Rental Agreement Signed', 'Occupied')) as approved")
            ->get()
            ->keyBy('property_type');

        $typeBreakdown = Property::selectRaw("property_type")
            ->distinct()
            ->pluck('property_type')
            ->map(function ($type) use ($reservationsByType) {
                $total    = (int) ($reservationsByType[$type]->total ?? 0);
                $approved = (int) ($reservationsByType[$type]->approved ?? 0);

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
        $suspendedCount  = User::where('account_status', 'suspended')->count();

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
    // ─── CSV Export ──────────────────────────────────────────

    public function export(Request $request)
    {
        $section = $request->input('section', 'properties');

        $filename = "abangananhub-{$section}-" . now()->format('Y-m-d') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = match ($section) {
            'reservations' => $this->exportReservations($request),
            'users'        => $this->exportUsers($request),
            default        => $this->exportProperties($request),
        };

        return response()->stream($callback, 200, $headers);
    }

    private function exportProperties(Request $request): \Closure
    {
        $typeFilter   = $request->input('type');
        $statusFilter = $request->input('status');

        return function () use ($typeFilter, $statusFilter) {
            $handle = fopen('php://output', 'w');

            \App\Support\Csv::put($handle, [
                'Property ID', 'Title', 'Type', 'Address',
                'Verification Status', 'Total Units',
                'Available', 'Reserved', 'Occupied',
                'Rental Fee', 'Created At',
            ]);

            $query = Property::withCount([
                'units',
                'units as available_units_count' => fn ($q) => $q->where('availability_status', 'Available'),
                'units as reserved_units_count'  => fn ($q) => $q->where('availability_status', 'Reserved'),
                'units as occupied_units_count'  => fn ($q) => $q->where('availability_status', 'Occupied'),
            ]);

            if ($typeFilter)   $query->where('property_type', $typeFilter);
            if ($statusFilter) $query->where('verification_status', $statusFilter);

            $query->orderBy('created_at', 'desc')
                ->chunk(200, function ($properties) use ($handle) {
                    foreach ($properties as $p) {
                        \App\Support\Csv::put($handle, [
                            $p->property_id,
                            $p->title,
                            $p->property_type,
                            $p->address,
                            $p->verification_status,
                            $p->units_count,
                            $p->available_units_count,
                            $p->reserved_units_count,
                            $p->occupied_units_count,
                            $p->rental_fee,
                            $p->created_at?->format('Y-m-d'),
                        ]);
                    }
                });

            fclose($handle);
        };
    }

    private function exportReservations(Request $request): \Closure
    {
        $statusFilter = $request->input('status');
        $timeFilter   = $request->input('time');

        return function () use ($statusFilter, $timeFilter) {
            $handle = fopen('php://output', 'w');

            \App\Support\Csv::put($handle, [
                'Reservation ID', 'Tenant', 'Tenant Email',
                'Property', 'Unit', 'Status',
                'Created At',
            ]);

            $query = Reservation::with(['tenant', 'property', 'unit']);

            if ($statusFilter) $query->where('rental_status', $statusFilter);
            if ($timeFilter) {
                $days = match ($timeFilter) {
                    '7' => 7, '30' => 30, '90' => 90, default => null,
                };
                if ($days) $query->where('created_at', '>=', now()->subDays($days));
            }

            $query->orderBy('created_at', 'desc')
                ->chunk(200, function ($reservations) use ($handle) {
                    foreach ($reservations as $r) {
                        \App\Support\Csv::put($handle, [
                            $r->reservation_id,
                            $r->tenant ? $r->tenant->first_name . ' ' . $r->tenant->last_name : '',
                            $r->tenant?->email ?? '',
                            $r->property?->title ?? '',
                            $r->unit?->unit_name ?? '',
                            $r->rental_status,
                            $r->created_at?->format('Y-m-d H:i'),
                        ]);
                    }
                });

            fclose($handle);
        };
    }

    private function exportUsers(Request $request): \Closure
    {
        $roleFilter   = $request->input('role');
        $statusFilter = $request->input('status');

        return function () use ($roleFilter, $statusFilter) {
            $handle = fopen('php://output', 'w');

            \App\Support\Csv::put($handle, [
                'User ID', 'First Name', 'Last Name', 'Email',
                'Role', 'Account Status', 'Verification Status',
                'Registered At',
            ]);

            $query = User::with(['roles', 'verificationApplication']);

            if ($roleFilter) {
                $userIds = UserRole::where('role', $roleFilter)->pluck('user_id');
                $query->whereIn('user_id', $userIds);
            }
            if ($statusFilter) $query->where('account_status', $statusFilter);

            $query->orderBy('created_at', 'desc')
                ->chunk(200, function ($users) use ($handle) {
                    foreach ($users as $u) {
                        $role  = $u->roles->pluck('role')->join(', ') ?: '';
                        $verif = $u->verificationApplication?->verification_status ?? '';

                        \App\Support\Csv::put($handle, [
                            $u->user_id,
                            $u->first_name,
                            $u->last_name,
                            $u->email,
                            $role,
                            $u->account_status,
                            $verif,
                            $u->created_at?->format('Y-m-d'),
                        ]);
                    }
                });

            fclose($handle);
        };
    }
}