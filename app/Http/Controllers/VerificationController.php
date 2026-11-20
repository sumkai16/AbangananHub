<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVerificationRequest;
use App\Mail\VerificationLinkMail;
use App\Models\LandlordVerification;
use App\Models\User;
use App\Services\FaceMatch;
use App\Services\IdDocumentCheck;
use App\Services\IdTypeDetector;
use App\Services\OcrService;
use App\Support\IdTemplates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VerificationController extends Controller
{
    public function create(bool $signupFlow = false)
    {
        $verification = auth()->user()->verificationApplication;

        if ($verification?->isApproved()) {
            return redirect()->route('landlord.properties.index');
        }

        // Pending applicants have nothing to redo — show what they submitted
        // instead of a blank wizard whose store() would overwrite it.
        if ($verification?->isPending()) {
            // From the sign-up, show the status as step 4 on the same address instead of leaving it.
            return $signupFlow ? $this->show(signupFlow: true) : redirect()->route('landlord.verification.show');
        }

        return view('landlord.verification.create', compact('verification', 'signupFlow'));
    }

    /**
     * Entry point from signed email link.
     */
    public function createFromEmail(Request $request, User $user)
    {
        if (auth()->id() !== $user->user_id) {
            abort(403, 'This verification link belongs to a different account.');
        }

        $verification = $user->verificationApplication;

        if ($verification?->isApproved()) {
            return redirect()->route('landlord.properties.index');
        }

        // Pending applicants have nothing to redo — show what they submitted
        // instead of a blank wizard whose store() would overwrite it.
        if ($verification?->isPending()) {
            return redirect()->route('landlord.verification.show');
        }

        return view('landlord.verification.create', compact('verification'));
    }

    /**
     * AJAX — send or resend the verification email link.
     */
    public function sendEmailLink(Request $request)
    {
        $user = $request->user();

        $verification = $user->verificationApplication;

        if ($verification?->isApproved()) {
            return response()->json(['message' => 'Already verified.'], 422);
        }

        Mail::to($user->email)->send(new VerificationLinkMail($user));

        return response()->json(['message' => 'Verification link sent to ' . $user->email]);
    }

    /**
     * AJAX — ID photo preview for the ID step of the wizard.
     * Receives the base64 front (and back) of the ID, reads each photo once with Google Vision, and returns the name
     * match, the ID-type match and whether the photos look like an ID (App\Services\IdDocumentCheck). A failed look
     * costs one try per request (front and back together). After ATTEMPTS_BEFORE_CONTINUE failed tries the applicant
     * may carry on anyway; the admin sees that they did.
     */
    public function ocrCheck(Request $request, OcrService $ocr, IdDocumentCheck $idCheck, IdTypeDetector $types)
    {
        $request->validate([
            'id_image' => ['required', 'string', 'max:8000000'],
            'id_back'  => ['nullable', 'string', 'max:8000000'],
            'id_type'  => ['required', 'string', Rule::in(IdTemplates::names())],
        ]);

        $front = $this->decodeBase64Image($request->input('id_image'));
        $back = $request->filled('id_back') ? $this->decodeBase64Image($request->input('id_back')) : null;

        if (! $front || ($request->filled('id_back') && ! $back)) {
            return response()->json([
                'name'       => null,
                'confidence' => 0,
                'status'     => 'fail',
                'message'    => 'Invalid image data.',
                'id_check'   => $this->idCheckPayload(IdDocumentCheck::UNAVAILABLE, null, false, (int) session('id_check_failures', 0), false),
            ]);
        }

        $user = $request->user();
        $idType = $request->input('id_type');

        $frontReading = $ocr->read($front);
        $backReading = $back ? $ocr->read($back) : null;

        $frontCheck = $idCheck->assessFront($frontReading, $idType, $user->first_name, $user->last_name);
        $backStatus = $backReading ? $idCheck->assessBack($backReading) : null;
        $typeMismatch = $frontCheck['type_match'] === IdTypeDetector::MISMATCH;
        $failures = $this->recordIdCheckResult($frontCheck['status'], $backStatus, $typeMismatch);
        $nameResult = $ocr->matchName($frontReading->text, $user->first_name, $user->last_name);

        return response()->json([
            'name'           => $nameResult['name'],
            'confidence'     => $nameResult['confidence'],
            'status'         => $nameResult['status'],
            'id_number'      => $types->idNumber($frontReading->text, $idType),
            'extracted'      => $frontReading->text,
            'back_extracted' => $backReading?->text,
            'user_name'      => $user->first_name . ' ' . $user->last_name,
            'type_match'     => $frontCheck['type_match'],
            'detected_type'  => $frontCheck['detected_type'],
            'back_detected_type' => $backReading ? $types->detectBack($backReading->text) : null,
            'id_check'       => $this->idCheckPayload(
                $frontCheck['status'],
                $backStatus,
                $idCheck->blocks($frontCheck['status'], $backStatus, $failures, $typeMismatch),
                $failures,
                $typeMismatch,
            ),
        ]);
    }

    public function store(StoreVerificationRequest $request, OcrService $ocr, IdDocumentCheck $idCheck, IdTypeDetector $types, FaceMatch $faceMatch)
    {
        $user = $request->user();

        // ── Decode base64 images ──────────────────────────────
        $idImageData = $this->decodeBase64Image($request->input('id_image'));
        $selfieData = $this->decodeBase64Image($request->input('selfie'));

        $idBackData = null;
        if ($request->input('id_type') !== 'Passport' && $request->filled('id_back')) {
            $idBackData = $this->decodeBase64Image($request->input('id_back'));
        }

        if (! $idImageData || ! $selfieData) {
            return back()->withErrors(['id_image' => 'Invalid image data. Please re-capture your photos.']);
        }

        if ($request->input('id_type') !== 'Passport' && ! $idBackData) {
            return back()->withErrors(['id_back' => 'Invalid back ID image. Please re-capture.']);
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

        // ── Read the ID photos (free when the wizard's preview just read them) ──
        $idType = $request->input('id_type');
        $frontReading = $ocr->read($idImageData);
        $backReading = $idBackData ? $ocr->read($idBackData) : null;

        // ── Do they look like an ID? The same rule as the preview, but it never turns the application away here ──
        // The wizard already held the applicant back for the allowed tries. A fail that gets this far (they carried on
        // after the tries, the answer never reached the phone, or the preview never read the photo) is saved and flagged
        // red for the admin, who looks at every application. Bouncing now would throw away the finished wizard: the
        // photos and the selfie.
        $frontCheck = $idCheck->assessFront($frontReading, $idType, $user->first_name, $user->last_name);
        $backStatus = $backReading ? $idCheck->assessBack($backReading) : null;

        $nameResult = $ocr->matchName($frontReading->text, $user->first_name, $user->last_name);
        $idNumber = $types->idNumber($frontReading->text, $idType);

        // ── ID number dedup ───────────────────────────────────
        if ($idNumber) {
            $idNumberDupe = LandlordVerification::where('id_number', $idNumber)
                ->where('user_id', '!=', $user->user_id)
                ->exists();

            if ($idNumberDupe) {
                return back()->withErrors([
                    'id_image' => 'We couldn\'t process your verification. Please contact support if you believe this is an error.',
                ]);
            }
        }

        // ── Face on the ID against the selfie (a signal for the admin, never a reject) ──
        $face = $faceMatch->compare($idImageData, $selfieData);

        // A failed side makes the whole check "fail", and the admin is told the application was sent anyway. So is a
        // front that shows another ID type than the one picked: the wizard held it for the allowed tries too.
        $idCheckStatus = $backStatus === IdDocumentCheck::FAIL ? IdDocumentCheck::FAIL : $frontCheck['status'];
        $typeMismatch = $frontCheck['type_match'] === IdTypeDetector::MISMATCH;

        // ── Store images to local disk (the refusals above, a duplicate photo or ID number, all happen before any file changes) ──
        $folder = "verifications/{$user->user_id}";
        $idPath = "{$folder}/id_photo.jpg";
        $idBackPath = $idBackData ? "{$folder}/id_back.jpg" : null;
        $selfiePath = "{$folder}/selfie.jpg";

        // Delete old files if re-submitting
        $oldVerification = $user->verificationApplication;
        if ($oldVerification) {
            Storage::disk('local')->delete($oldVerification->government_id);
            Storage::disk('local')->delete($oldVerification->id_back ?? '');
            Storage::disk('local')->delete($oldVerification->selfie ?? '');
            if ($oldVerification->logo_url) {
                Storage::disk('public')->delete($oldVerification->logo_url);
            }
        }

        Storage::disk('local')->put($idPath, $idImageData);
        if ($idBackData && $idBackPath) {
            Storage::disk('local')->put($idBackPath, $idBackData);
        }
        Storage::disk('local')->put($selfiePath, $selfieData);

        // ── Handle logo upload ────────────────────────────────
        $logoPath = $request->hasFile('logo')
            ? $request->file('logo')->store('business_logos', 'public')
            : null;

        // ── Save record ───────────────────────────────────────
        $user->verificationApplication()->updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'government_id'       => $idPath,
                'id_back'             => $idBackPath,
                'id_type'             => $request->input('id_type'),
                'selfie'              => $selfiePath,
                'liveness_passed'     => $request->boolean('liveness_passed'),
                'id_image_hash'       => $idImageHash,
                'id_number'           => $idNumber,
                'ocr_name'            => $nameResult['name'],
                'ocr_confidence'      => $nameResult['confidence'],
                'ocr_status'          => $nameResult['status'],
                'id_check_status'     => $idCheckStatus,
                'id_type_match'       => $frontCheck['type_match'],
                'id_type_detected'    => $frontCheck['detected_type'],
                'id_check_overridden' => $idCheckStatus === IdDocumentCheck::FAIL || $typeMismatch,
                'face_match_status'   => $face['status'],
                'face_match_score'    => $face['similarity'],
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

        session()->forget('id_check_failures');

        // From the landlord sign-up the answer (step 4, admin review) stays on the same address.
        return redirect()->route($request->boolean('signup_flow') ? 'register.landlord' : 'landlord.verification.show')
            ->with('status', "Application submitted. We'll review it shortly.");
    }

    public function show(bool $signupFlow = false)
    {
        $verification = auth()->user()->verificationApplication;

        if (! $verification) {
            return redirect()->route($signupFlow ? 'register.landlord' : 'landlord.verification.create');
        }

        return view('landlord.verification.show', compact('verification', 'signupFlow'));
    }

    public function download(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        return Storage::disk('local')->download($verification->government_id);
    }

    public function downloadIdBack(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        if (! $verification->id_back) {
            abort(404);
        }

        return Storage::disk('local')->download($verification->id_back);
    }

    public function downloadSelfie(LandlordVerification $verification)
    {
        Gate::authorize('view', $verification);

        return Storage::disk('local')->download($verification->selfie);
    }

    /**
     * Serve a verification image inline (for display, not download).
     */
    public function preview(LandlordVerification $verification, string $type)
    {
        Gate::authorize('view', $verification);

        $path = match ($type) {
            'front'  => $verification->government_id,
            'back'   => $verification->id_back,
            'selfie' => $verification->selfie,
            default  => abort(404),
        };

        if (! $path) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($path),
            ['Content-Type' => 'image/jpeg']
        );
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * One failed look at the photos costs one try, whichever side failed, or a front that shows another ID type than the
     * one picked. A look that is none of these (a pass, unsure, or Vision unavailable) costs nothing. Returns the tries
     * used so far.
     */
    private function recordIdCheckResult(string $frontStatus, ?string $backStatus, bool $typeMismatch): int
    {
        $failures = (int) session('id_check_failures', 0);

        if ($frontStatus === IdDocumentCheck::FAIL || $backStatus === IdDocumentCheck::FAIL || $typeMismatch) {
            session(['id_check_failures' => ++$failures]);
        }

        return $failures;
    }

    /** @return array{status: string, back_status: ?string, blocked: bool, attempts_left: int, type_mismatch: bool} */
    private function idCheckPayload(string $status, ?string $backStatus, bool $blocked, int $failures, bool $typeMismatch): array
    {
        return [
            'status'        => $status,
            'back_status'   => $backStatus,
            'blocked'       => $blocked,
            'attempts_left' => max(0, IdDocumentCheck::ATTEMPTS_BEFORE_CONTINUE - $failures),
            'type_mismatch' => $typeMismatch,
        ];
    }

    protected function decodeBase64Image(string $base64): ?string
    {
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64, 2)[1];
        }

        $decoded = base64_decode($base64, true);

        // Must be a real picture, not just any bytes: the file is stored as the applicant's ID.
        if ($decoded === false || strlen($decoded) < 100 || @getimagesizefromstring($decoded) === false) {
            return null;
        }

        // The camera sends text, so Laravel's file rules never see it. Check the bytes really are a photo before
        // they are saved, shown to an admin or sent to the OCR service: a JPEG, PNG or WebP of a sane size.
        $info = @getimagesizefromstring($decoded);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 10000 || $info[1] > 10000) {
            return null;
        }

        return $decoded;
    }
}
