<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ReportController as WebReportController;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobile equivalent of ReportController (web) and Tenant\ReportController
 * (web, index only) — same validation, same categories, same reason-string
 * assembly, not a second implementation of either.
 */
class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->user_id;

        $query = Report::where('reporter_id', $userId)->with(['property', 'reportedUser']);

        if ($status = $request->input('status')) {
            $query->where('report_status', $status);
        }

        return response()->json($query->latest()->paginate(12));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type'       => 'required|in:property,user',
            'property_id'       => 'required_if:target_type,property|nullable|exists:properties,property_id',
            'reported_user_id'  => 'required_if:target_type,user|nullable|exists:users,user_id',
            'category'          => 'required|in:'.implode(',', WebReportController::CATEGORIES),
            'details'           => 'nullable|string|max:1000',
        ]);

        if ($validated['target_type'] === 'user' && (int) $validated['reported_user_id'] === $request->user()->user_id) {
            abort(422, 'You cannot report yourself.');
        }

        $reason = $validated['category'];
        if (! empty($validated['details'])) {
            $reason .= ': '.$validated['details'];
        }

        $report = Report::create([
            'reporter_id'       => $request->user()->user_id,
            'property_id'       => $validated['target_type'] === 'property' ? $validated['property_id'] : null,
            'reported_user_id'  => $validated['target_type'] === 'user' ? $validated['reported_user_id'] : null,
            'report_reason'     => $reason,
            'report_status'     => 'Pending',
        ]);

        return response()->json(['data' => new ReportResource($report)], 201);
    }
}
