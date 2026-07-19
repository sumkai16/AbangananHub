<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\LandlordVerification;
use App\Models\RentalBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class VerificationController extends Controller
{
    private const STATUSES = ['Pending', 'Approved', 'Rejected', 'All'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'Pending');

        if (! in_array($status, self::STATUSES, true)) {
            $status = 'Pending';
        }

        $query = LandlordVerification::with('user')
            ->when($status !== 'All', fn ($q) => $q->where('verification_status', $status));

        $verifications = in_array($status, ['Approved', 'Rejected'], true)
            ? $query->latest('reviewed_at')->paginate(15)->withQueryString()
            : $query->oldest('submitted_at')->paginate(15)->withQueryString();

        $counts = [
            'Pending' => LandlordVerification::where('verification_status', 'Pending')->count(),
            'Approved' => LandlordVerification::where('verification_status', 'Approved')->count(),
            'Rejected' => LandlordVerification::where('verification_status', 'Rejected')->count(),
        ];
        $counts['All'] = array_sum($counts);

        return view('admin.verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
            'counts' => $counts,
        ]);
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

        RentalBusiness::firstOrCreate(
            ['landlord_id' => $verification->user_id],
            [
                'business_name' => $verification->business_name,
                'description' => $verification->description,
                'logo_url' => $verification->logo_url,
                'contact_number' => $verification->contact_number,
                'business_address' => $verification->business_address,
            ]
        );

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