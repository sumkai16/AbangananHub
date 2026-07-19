<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
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

        $counts = [
            'Pending' => Report::where('report_status', 'Pending')->count(),
            'Resolved' => Report::where('report_status', 'Resolved')->count(),
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
        $report->load(['reporter', 'property', 'reportedUser']);

        return view('admin.reports.show', compact('report'));
    }

 public function resolve(Request $request, Report $report)
{
    abort_if($report->report_status !== 'Pending', 409, 'This report has already been resolved.');

    $validated = $request->validate([
        'admin_notes'  => 'required|string|max:1000',
        'action_taken' => 'required|in:none,suspend_user,delist_property',
    ]);

    $actionLabel = 'No action taken';

    // Enforce action
    if ($validated['action_taken'] === 'suspend_user') {
        $targetUser = $report->reported_user_id
            ? $report->reportedUser
            : ($report->property ? $report->property->landlord : null);

        if ($targetUser) {
            $targetUser->update(['account_status' => 'Suspended']);
            $actionLabel = "Suspended user: {$targetUser->first_name} {$targetUser->last_name}";
        }
    } elseif ($validated['action_taken'] === 'delist_property' && $report->property) {
        $report->property->update(['verification_status' => 'Rejected']);
        $actionLabel = "Delisted property: {$report->property->title}";
    }

    $report->resolve(
        $validated['admin_notes'],
        $actionLabel,
        auth()->id()
    );

    return back()->with('status', 'Report resolved.');
}
}
