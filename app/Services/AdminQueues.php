<?php

namespace App\Services;

use App\Models\LandlordVerification;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Property;
use App\Models\PropertyDocument;
use App\Models\PropertyUnit;
use App\Models\Report;
use App\Models\Reservation;
use App\Models\TenantHold;
use App\Models\HoldMatchAlert;
use App\Models\UserPayoutAccount;
use Carbon\Carbon;

/**
 * The queues only an admin can clear. The dashboard's "Needs your attention" card and the numbers on the admin
 * sidebar both read from here, so the two can never disagree about how much is waiting.
 */
class AdminQueues
{
    /**
     * One row per queue, in a fixed order so a row never moves between visits.
     *
     * @return list<array{key: string, label: string, count: int, oldest: ?Carbon, href: string}>
     */
    public static function rows(): array
    {
        $rows = [
            ['verifications', 'Landlord verifications', LandlordVerification::where('verification_status', 'Pending'), 'submitted_at', route('admin.verifications.index', ['status' => 'Pending'])],
            ['listings', 'Property verifications', Property::where('verification_status', 'Pending'), 'created_at', route('admin.listings.approval', ['status' => 'Pending'])],
            ['documents', 'Property documents', PropertyDocument::where('status', 'Pending')->whereNotNull('file_path'), 'created_at', route('admin.documents.index', ['status' => 'Pending'])],
            ['units', 'Unit approvals', PropertyUnit::awaitingOwnReview(), 'property_units.created_at', route('admin.catalogue.units.index', ['needs' => 'approval'])],
            ['reports', 'Reports', Report::where('report_status', 'Pending'), 'created_at', route('admin.reports.index', ['status' => 'Pending'])],
            ['disputes', 'Move-in disputes', self::moveInDisputes(), 'move_in_disputed_at', route('admin.refund-requests.index', ['type' => 'disputes'])],
            ['escalations', 'Refunds not returned', self::escalations(), 'reports.created_at', route('admin.refund-requests.index', ['type' => 'escalations'])],
        ];

        return array_map(function (array $row) {
            [$key, $label, $query, $column, $href] = $row;
            $summary = $query->toBase()->selectRaw("COUNT(*) as total, MIN({$column}) as oldest")->first();

            return [
                'key' => $key,
                'label' => $label,
                'count' => (int) $summary->total,
                'oldest' => $summary->oldest ? Carbon::parse($summary->oldest) : null,
                'href' => $href,
            ];
        }, $rows);
    }

    /**
     * Refund requests, source 1: a tenant reported a problem with the move-in while AbangananHub holds their money.
     * The admin refunds the tenant or releases the money to the landlord; either clears the dispute.
     */
    public static function moveInDisputes()
    {
        return Reservation::whereNotNull('move_in_disputed_at');
    }

    /**
     * Refund requests, source 2: the landlord kept money paid straight to them and the tenant sent it to us as a
     * "Refund not returned" report. AbangananHub never held it; the admin acts on the landlord. Open while the report is. Callers that load rows
     * add `select('reservations.*')` (the join would otherwise bring the report's columns in).
     */
    public static function escalations()
    {
        return Reservation::join('reports', 'reports.report_id', '=', 'reservations.refund_report_id')
            ->where('reports.report_status', 'Pending');
    }

    /**
     * The numbers on the admin sidebar, by sidebar item. Worked out once per request (the sidebar renders on every
     * admin page) and kept on the request, never in a static, so one admin's counts cannot leak into the next
     * request in a long-running process.
     *
     * @return array<string, int>
     */
    public static function navCounts(): array
    {
        $attributes = request()->attributes;
        $counts = $attributes->get('admin_nav_counts');

        if ($counts === null) {
            $counts = collect(self::rows())->pluck('count', 'key')->all();
            // One number for the Refund requests item: both of its lists (the page reads the same two queries).
            $counts['refunds'] = ($counts['disputes'] ?? 0) + ($counts['escalations'] ?? 0);
            $counts['payouts'] = self::payoutsToAct();
            $counts['holds'] = TenantHold::where('status', TenantHold::PENDING)->count() + HoldMatchAlert::open()->count();
            $attributes->set('admin_nav_counts', $counts);
        }

        return $counts;
    }

    /**
     * What the Payouts page asks an admin to do: landlords owed money, GCash accounts to approve, and failed payouts
     * nobody has dealt with. Same three lists the page shows.
     */
    public static function payoutsToAct(): int
    {
        $reservationIds = Payment::pendingPayout()->distinct()->pluck('reservation_id');
        $owedLandlords = $reservationIds->isEmpty() ? 0 : Property::whereIn(
            'property_id',
            Reservation::whereIn('reservation_id', $reservationIds)->select('property_id')
        )->distinct()->count('landlord_id');

        $toApprove = UserPayoutAccount::whereNull('payout_approved_at')
            ->whereNotNull('gcash_number')
            ->whereNotNull('gcash_account_name')
            ->count();

        $failed = Payout::where('status', 'Failed')->whereNull('resolved_at')->count();

        return $owedLandlords + $toApprove + $failed;
    }
}
