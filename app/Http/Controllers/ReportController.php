<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public const CATEGORIES = [
        'Scam or Fraud',
        'Inappropriate Content',
        'Harassment',
        'Fake Listing',
        'Other',
    ];

    public function create(Request $request)
    {
        $userId = Auth::id();

        $properties = Property::where('landlord_id', '!=', $userId)
            ->orderBy('title')
            ->get(['property_id', 'title']);

        $users = User::where('user_id', '!=', $userId)
            ->orderBy('first_name')
            ->get(['user_id', 'first_name', 'last_name', 'email']);

        $prefillPropertyId = $request->integer('property_id') ?: null;
        $prefillUserId = $request->integer('user_id') ?: null;

        return view('reports.create', [
            'categories' => self::CATEGORIES,
            'properties' => $properties,
            'users' => $users,
            'prefillPropertyId' => $prefillPropertyId,
            'prefillUserId' => $prefillUserId,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'target_type' => 'required|in:property,user',
            'property_id' => 'required_if:target_type,property|nullable|exists:properties,property_id',
            'reported_user_id' => 'required_if:target_type,user|nullable|exists:users,user_id',
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'details' => 'nullable|string|max:1000',
        ]);

        if ($validated['target_type'] === 'user' && (int) $validated['reported_user_id'] === Auth::id()) {
            return back()->withInput()->withErrors(['reported_user_id' => 'You cannot report yourself.']);
        }

        $reason = $validated['category'];
        if (!empty($validated['details'])) {
            $reason .= ': ' . $validated['details'];
        }

        Report::create([
            'reporter_id' => Auth::id(),
            'property_id' => $validated['target_type'] === 'property' ? $validated['property_id'] : null,
            'reported_user_id' => $validated['target_type'] === 'user' ? $validated['reported_user_id'] : null,
            'report_reason' => $reason,
            'report_status' => 'Pending',
        ]);

        return redirect()->route('reports.create')
            ->with('success', 'Your report has been submitted. Our team will review it shortly.');
    }
}
