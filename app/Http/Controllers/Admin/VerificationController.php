<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\AuditLog;
use App\Models\LandlordVerification;
use App\Models\Notification;
use App\Models\RentalBusiness;
use Carbon\Carbon;
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

        $since = now()->subDays(30);
        $summary = [
            'oldest'   => LandlordVerification::where('verification_status', 'Pending')->min('submitted_at'),
            'approved' => LandlordVerification::where('verification_status', 'Approved')->where('reviewed_at', '>=', $since)->count(),
            'rejected' => LandlordVerification::where('verification_status', 'Rejected')->where('reviewed_at', '>=', $since)->count(),
            // Pending applications where the face match did not clear: these need a careful look.
            'flagged'  => LandlordVerification::where('verification_status', 'Pending')
                ->whereIn('face_match_status', ['mismatch', 'review', 'no_face'])->count(),
        ];
        $summary['oldest'] = $summary['oldest'] ? Carbon::parse($summary['oldest']) : null;

        $recent = LandlordVerification::with(['user', 'reviewer'])
            ->whereIn('verification_status', ['Approved', 'Rejected'])
            ->latest('reviewed_at')->limit(5)->get();

        return view('admin.verifications.index', [
            'verifications' => $verifications,
            'status' => $status,
            'counts' => $counts,
            'summary' => $summary,
            'recent' => $recent,
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

            AuditLog::record(
                'verification.approve',
                "Approved landlord verification for {$locked->user->email}.",
                $locked,
                null,
                ['landlord_id' => $locked->user_id, 'business_name' => $locked->business_name],
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

            AuditLog::record(
                'verification.reject',
                "Rejected landlord verification for {$locked->user->email}.",
                $locked,
                $request->validated('admin_notes'),
                ['landlord_id' => $locked->user_id],
            );
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
