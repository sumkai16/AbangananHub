<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVerificationRequest;
use App\Models\LandlordVerification;
use App\Services\OcrService;
use Illuminate\Http\Request;
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

    /**
     * AJAX — OCR preview for step 5 of the wizard.
     * Receives base64 ID image, runs OCR, returns name match result.
     */
    public function ocrCheck(Request $request, OcrService $ocr)
    {
        $request->validate([
            'id_image' => ['required', 'string'],
            'id_type'  => ['required', 'string'],
        ]);

        // Decode base64 and write to a temp file for Tesseract
        $imageData = $this->decodeBase64Image($request->input('id_image'));

        if (! $imageData) {
            return response()->json([
                'name'       => null,
                'confidence' => 0,
                'status'     => 'fail',
                'message'    => 'Invalid image data.',
            ]);
        }

        $tempPath = storage_path('app/private/temp_ocr_' . auth()->id() . '.jpg');
        file_put_contents($tempPath, $imageData);

        try {
            $extractedText = $ocr->extractText($tempPath);
            $user = auth()->user();
            $nameResult = $ocr->matchName($extractedText, $user->first_name, $user->last_name);
            $idNumber = $ocr->extractIdNumber($extractedText, $request->input('id_type'));

            return response()->json([
                'name'        => $nameResult['name'],
                'confidence'  => $nameResult['confidence'],
                'status'      => $nameResult['status'],
                'id_number'   => $idNumber,
                'extracted'   => $extractedText,
                'user_name'   => $user->first_name . ' ' . $user->last_name,
            ]);
        } finally {
            @unlink($tempPath);
        }
    }

    public function store(StoreVerificationRequest $request, OcrService $ocr)
    {
        $user = $request->user();

        // ── Decode base64 images ──────────────────────────────
        $idImageData = $this->decodeBase64Image($request->input('id_image'));
        $selfieData = $this->decodeBase64Image($request->input('selfie'));

        if (! $idImageData || ! $selfieData) {
            return back()->withErrors(['id_image' => 'Invalid image data. Please re-capture your photos.']);
        }

        // ── Duplicate hash check ──────────────────────────────
        $idImageHash = hash('sha256', $idImageData);

        $duplicate = LandlordVerification::where('id_image_hash', $idImageHash)
            ->where('user_id', '!=', $user->user_id)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'id_image' => 'We couldn\'t process your verification. Please contact support if you believe this is an error.',
            ]);
        }

        // ── Store images to local disk ────────────────────────
        $folder = "verifications/{$user->user_id}";
        $idPath = "{$folder}/id_photo.jpg";
        $selfiePath = "{$folder}/selfie.jpg";

        // Delete old files if re-submitting
        $oldVerification = $user->verificationApplication;
        if ($oldVerification) {
            Storage::disk('local')->delete($oldVerification->government_id);
            Storage::disk('local')->delete($oldVerification->selfie ?? '');
            if ($oldVerification->logo_url) {
                Storage::disk('public')->delete($oldVerification->logo_url);
            }
        }

        Storage::disk('local')->put($idPath, $idImageData);
        Storage::disk('local')->put($selfiePath, $selfieData);

        // ── Run OCR ───────────────────────────────────────────
        $fullIdPath = Storage::disk('local')->path($idPath);
        $extractedText = $ocr->extractText($fullIdPath);
        $nameResult = $ocr->matchName($extractedText, $user->first_name, $user->last_name);
        $idNumber = $ocr->extractIdNumber($extractedText, $request->input('id_type'));

        // ── ID number dedup ───────────────────────────────────
        if ($idNumber) {
            $idNumberDupe = LandlordVerification::where('id_number', $idNumber)
                ->where('user_id', '!=', $user->user_id)
                ->exists();

            if ($idNumberDupe) {
                // Clean up stored files
                Storage::disk('local')->delete($idPath);
                Storage::disk('local')->delete($selfiePath);

                return back()->withErrors([
                    'id_image' => 'We couldn\'t process your verification. Please contact support if you believe this is an error.',
                ]);
            }
        }

        // ── Handle logo upload ────────────────────────────────
        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('business_logos', 'public')
            : null;

        // ── Save record ───────────────────────────────────────
        $user->verificationApplication()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'government_id'       => $idPath,
                'id_type'             => $request->input('id_type'),
                'selfie'              => $selfiePath,
                'id_image_hash'       => $idImageHash,
                'id_number'           => $idNumber,
                'ocr_name'            => $nameResult['name'],
                'ocr_confidence'      => $nameResult['confidence'],
                'ocr_status'          => $nameResult['status'],
                'business_name'       => $request->validated('business_name'),
                'description'         => $request->validated('description'),
                'logo_url'            => $logoPath,
                'contact_number'      => $request->validated('contact_number'),
                'business_address'    => $request->validated('business_address'),
                'verification_status' => 'Pending',
                'admin_notes'         => null,
                'reviewed_by'         => null,
                'reviewed_at'         => null,
                'submitted_at'        => now(),
            ]
        );

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

    public function downloadSelfie(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        return Storage::disk('local')->download($verification->selfie);
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Decode a base64 image string (with or without data URI prefix).
     */
    protected function decodeBase64Image(string $base64): ?string
    {
        // Strip data URI prefix if present
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $decoded = base64_decode($base64, true);

        if ($decoded === false || strlen($decoded) < 100) {
            return null;
        }

        return $decoded;
    }
}