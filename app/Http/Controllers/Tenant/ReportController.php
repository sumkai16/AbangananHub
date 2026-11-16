<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as FileReportController;
use App\Models\Property;
use App\Models\Report;
use App\Models\User;
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

        // The report form is a modal on this page. A listing or profile can open it with the target already
        // chosen (?property_id= / ?user_id=); ?report=1 opens it with nothing chosen.
        $prefillProperty = $request->integer('property_id')
            ? Property::with('landlord')->find($request->integer('property_id'))
            : null;
        $prefillUser = ! $prefillProperty && $request->integer('user_id') && $request->integer('user_id') !== $userId
            ? User::find($request->integer('user_id'))
            : null;

        return view('tenant.reports.index', [
            'reports' => $reports,
            'stats' => $stats,
            'reportProperties' => ReportTargets::properties($userId),
            'reportUsers' => ReportTargets::users($userId),
            'reportCategories' => FileReportController::CATEGORIES,
            'prefillProperty' => $prefillProperty,
            'prefillUser' => $prefillUser,
            'openReport' => $request->boolean('report') || $prefillProperty || $prefillUser,
        ]);
    }
}
