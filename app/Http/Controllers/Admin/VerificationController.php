<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\RentalBusiness;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($verification) {
            // Row-lock so a double-click / concurrent request can't both pass
            // this check and race each other into assignRole + RentalBusiness::firstOrCreate.
            $locked = LandlordVerification::whereKey($verification->getKey())->lockForUpdate()->firstOrFail();

            abort_if($locked->verification_status !== 'Pending', 409, 'This application has already been reviewed.');

            $locked->update([
                'verification_status' => 'Approved',
                'admin_notes' => null,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            $locked->user->assignRole('Landlord');

            RentalBusiness::firstOrCreate(
                ['landlord_id' => $locked->user_id],
                [
                    'business_name' => $locked->business_name,
                    'description' => $locked->description,
                    'logo_url' => $locked->logo_url,
                    'contact_number' => $locked->contact_number,
                    'business_address' => $locked->business_address,
                ]
            );
        });

        Notification::notify(
            $verification->user_id,
            'verification',
            'You are now a verified landlord',
            'Your landlord application was approved. You can now list properties and receive inquiries.',
            route('landlord.dashboard'),
        );

        return back()->with('status', 'Landlord application approved.');
    }

    public function reject(RejectVerificationRequest $request, LandlordVerification $verification)
    {
        DB::transaction(function () use ($request, $verification) {
            $locked = LandlordVerification::whereKey($verification->getKey())->lockForUpdate()->firstOrFail();

            abort_if($locked->verification_status !== 'Pending', 409, 'This application has already been reviewed.');

            $locked->update([
                'verification_status' => 'Rejected',
                'admin_notes' => $request->validated('admin_notes'),
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);
        });

        $reason = $request->validated('admin_notes');

        Notification::notify(
            $verification->user_id,
            'verification',
            'Landlord application not approved',
            'Your landlord application was not approved.' . ($reason ? ' Reason: ' . $reason : ''),
            route('landlord.verification.show'),
        );

        return back()->with('status', 'Application rejected.');
    }
}