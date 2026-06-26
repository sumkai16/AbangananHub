<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\LandlordVerification;
use Illuminate\Support\Facades\Gate;

class VerificationController extends Controller
{
    public function index()
    {
        $verifications = LandlordVerification::with('user')
            ->where('verification_status', 'Pending')
            ->oldest('submitted_at')
            ->paginate(15);

        return view('admin.verifications.index', compact('verifications'));
    }

    public function show(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        $verification->load('user');

        return view('admin.verifications.show', compact('verification'));
    }

    public function approve(LandlordVerification $verification)
    {
        abort_if($verification->verification_status !== 'Pending', 409, 'This application has already been reviewed.');

        $verification->update([
            'verification_status' => 'Approved',
            'admin_notes' => null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        $verification->user->assignRole('Landlord');

        // TODO: notify the user once the notification pipeline covers this event type.

        return back()->with('status', 'Landlord application approved.');
    }

    public function reject(RejectVerificationRequest $request, LandlordVerification $verification)
    {
        abort_if($verification->verification_status !== 'Pending', 409, 'This application has already been reviewed.');

        $verification->update([
            'verification_status' => 'Rejected',
            'admin_notes' => $request->validated('admin_notes'),
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Application rejected.');
    }
}