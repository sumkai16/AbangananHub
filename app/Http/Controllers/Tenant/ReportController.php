<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Report;
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

        return view('tenant.reports.index', compact('reports', 'stats'));
    }
}
