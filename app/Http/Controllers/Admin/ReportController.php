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

        return view('admin.reports.index', [
            'reports' => $reports,
            'status' => $status,
        ]);
    }

    public function show(Report $report)
    {
        $report->load(['reporter', 'property', 'reportedUser']);

        return view('admin.reports.show', compact('report'));
    }

    public function resolve(Report $report)
    {
        abort_if($report->report_status !== 'Pending', 409, 'This report has already been resolved.');

        $report->resolve();

        return back()->with('status', 'Report marked as resolved.');
    }
}
