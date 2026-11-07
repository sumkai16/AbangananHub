<?php

namespace App\Http\Controllers\Landlord;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as BaseReportController;
use App\Models\Report;
use App\Support\ReportTargets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $userId = Auth::user()->user_id;

        $query = Report::where('reporter_id', $userId)
            ->with(['property', 'reportedUser']);

        if ($status = $request->input('status')) {
            $query->where('report_status', $status);
        }

        $reports = $query->latest()->paginate(12)->withQueryString();

        $stats = [
            'total'    => Report::where('reporter_id', $userId)->count(),
            'pending'  => Report::where('reporter_id', $userId)->where('report_status', 'Pending')->count(),
            'resolved' => Report::where('reporter_id', $userId)->where('report_status', 'Resolved')->count(),
        ];

        return view('landlord.complaints.index', [
            'reports' => $reports,
            'stats' => $stats,
            // The "File a report" modal's pickers, same lists as the /reports page.
            'reportProperties' => ReportTargets::properties($userId),
            'reportUsers' => ReportTargets::users($userId),
            'reportCategories' => BaseReportController::CATEGORIES,
        ]);
    }
}
