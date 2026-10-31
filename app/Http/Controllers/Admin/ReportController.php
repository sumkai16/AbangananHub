<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Report;
use App\Services\PaymentClaims;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const STATUSES = ['Pending', 'Resolved', 'All'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'Pending');

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'Pending';
        }

        $query = Report::with(['reporter', 'property', 'reportedUser'])
            ->when($status !== 'All', fn ($q) => $q->where('report_status', $status));

        $reports = $query->latest('report_id')->paginate(15)->withQueryString();

        $statusTotals = Report::selectRaw('report_status, COUNT(*) as total')->groupBy('report_status')->pluck('total', 'report_status');
        $counts = [
            'Pending' => $statusTotals['Pending'] ?? 0,
            'Resolved' => $statusTotals['Resolved'] ?? 0,
        ];
        $counts['All'] = array_sum($counts);

        return view('admin.reports.index', [
            'reports' => $reports,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function show(Report $report)
    {
        $report->load(['reporter', 'property', 'reportedUser', 'paymentClaim.reservation.property']);

        // A payment dispute: the evidence the admin would otherwise hunt for, and the landlord's track record.
        $claim = $report->paymentClaim;
        $landlordId = $report->reported_user_id ?? $claim?->reservation?->property?->landlord_id;
        $landlordRecord = $claim && $landlordId ? PaymentClaims::landlordRecord($landlordId) : null;

        return view('admin.reports.show', compact('report', 'claim', 'landlordRecord'));
    }

    public function resolve(Request $request, Report $report)
    {
        abort_if($report->report_status !== 'Pending', 409, 'This report has already been resolved.');

        $validated = $request->validate([
            'admin_notes'  => 'required|string|max:1000',
            'action_taken' => 'required|in:none,warn_user,suspend_user,delist_property',
        ]);

        $actionLabel = 'No action taken';

        // Enforce action
        if ($validated['action_taken'] === 'warn_user') {
            $targetUser = $report->reported_user_id
                ? $report->reportedUser
                : ($report->property ? $report->property->landlord : null);

            if ($targetUser) {
                // A first offence: say what is expected and what repeat reports lead to. No reporter is named.
                Notification::notify(
                    $targetUser->user_id,
                    'report',
                    'Warning from AbangananHub',
                    'AbangananHub reviewed a complaint about how a payment was handled on one of your listings. Please confirm or record every payment you receive, and give a clear reason when you reject one. Repeated complaints can lead to your account being suspended.',
                    route('notifications.index'),
                );
                $actionLabel = "Warned user: {$targetUser->first_name} {$targetUser->last_name}";
            }
        } elseif ($validated['action_taken'] === 'suspend_user') {
            $targetUser = $report->reported_user_id
                ? $report->reportedUser
                : ($report->property ? $report->property->landlord : null);

            if ($targetUser) {
                $targetUser->update(['account_status' => 'suspended']);
                $actionLabel = "Suspended user: {$targetUser->first_name} {$targetUser->last_name}";
            }
        } elseif ($validated['action_taken'] === 'delist_property' && $report->property) {
            // Suspended, not Rejected — this is a moderation takedown, not a
            // verdict on whether the listing was ever legitimate. Rejected would
            // get silently cleared the next time the listing is edited and
            // re-approved (PropertyController::update() resets verification_status
            // to Pending on any material change); publication_status is untouched
            // by that reset, so a suspension survives it.
            $report->property->update(['publication_status' => 'Suspended']);
            $actionLabel = "Delisted property: {$report->property->title}";
        }

        $report->resolve(
            $validated['admin_notes'],
            $actionLabel,
            auth()->id()
        );

        AuditLog::record(
            'report.resolve',
            "Resolved report #{$report->getKey()}. {$actionLabel}.",
            $report,
            $validated['admin_notes'],
            ['action_taken' => $validated['action_taken']],
        );

        // Only the reporter is told. The reported party is deliberately not
        // notified that a report existed — that is admin-only moderation state.
        Notification::notify(
            $report->reporter_id,
            'report',
            'Your report was reviewed',
            $validated['admin_notes'],
            route('notifications.index'),
        );

        return back()->with('status', 'Report resolved.');
    }
}
