<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVerificationRequest;
use App\Models\LandlordVerification;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class VerificationController extends Controller
{
    public function create()
    {
        $verification = auth()->user()->verificationApplication;

        if ($verification?->isApproved()) {
            return redirect()->route('landlord.listings.index');
        }

        return view('landlord.verification.create', compact('verification'));
    }

    public function store(StoreVerificationRequest $request)
    {
        $user = $request->user();
        $oldPath = $user->verificationApplication?->government_id;
        $oldLogoPath = $user->verificationApplication?->logo_url;

        $path = $request->file('government_id')->store('government_ids', 'local');

        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('business_logos', 'public')
            : null;

        $user->verificationApplication()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'government_id' => $path,
                'business_name' => $request->validated('business_name'),
                'description' => $request->validated('description'),
                'logo_url' => $logoPath,
                'contact_number' => $request->validated('contact_number'),
                'business_address' => $request->validated('business_address'),
                'verification_status' => 'Pending',
                'admin_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'submitted_at' => now(),
            ]
        );

        if ($oldPath) {
            Storage::disk('local')->delete($oldPath);
        }

        if ($oldLogoPath) {
            Storage::disk('public')->delete($oldLogoPath);
        }

        return redirect()->route('landlord.verification.show')
            ->with('status', "Application submitted. We'll review it shortly.");
    }

    public function show()
    {
        $verification = auth()->user()->verificationApplication;

        if (! $verification) {
            return redirect()->route('landlord.verification.create');
        }

        return view('landlord.verification.show', compact('verification'));
    }

    public function download(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        return Storage::disk('local')->download($verification->government_id);
    }
}