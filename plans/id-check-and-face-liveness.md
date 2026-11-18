# ID Photo Check + Easier Face Check Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Status:** Built 2026-11-18 (all 7 tasks, uncommitted), then revised after a final review: see the LAST section, which overrides Task 3's `store()` blocking and Task 5's 35% size rule. Pending Axcee's manual verification (checklist below); delete this file once he has run it.

**Goal:** Stop a photo that is clearly not an ID from going through the landlord application (block twice, then let the applicant continue, flagged for the admin), and make the live face check easier to pass and to follow on a phone.

**Architecture:** One Google Vision request per photo now asks for text, faces and labels together. A new `IdDocumentCheck` service scores those signals into pass / unsure / fail / unavailable. The preview endpoint counts failed attempts in the session, and `store()` enforces the same rule, so the browser cannot skip it. Vision results are cached by image hash, so `store()` reuses the preview's reading instead of calling Vision a third time. The face check stays on face-api.js but drops "Look up", smooths the readings, uses the full landmark model served from our own domain, shows the instruction on the camera view, and only takes the selfie when the face is frontal again.

**Tech Stack:** Laravel 11 / PHP 8, Blade + Alpine.js, `public/js/verification-wizard.js`, face-api.js 0.22.2, Google Cloud Vision `images:annotate`, PHPUnit.

**Spec:** this conversation's decisions, recorded here (no separate design doc):
- Approach **B**: several signals from one Vision call (text, ID-type keywords, common ID words, the applicant's name, a face on the card, Vision labels).
- When the check **fails**: block 2 tries, then let them continue, flagged for the admin.
- Vision unavailable (down, quota, no key): never block, flag only.
- Remove "Look up". Keep face-api.js (not MediaPipe). Apply the 7 face-check fixes listed in Task 5–6.

## Global Constraints

- Mobile-first: every new UI is checked at 375px first (`context/DESIGN.md` §0b).
- Palette only: navy `#060D26`, coral `#FF8A66` (hover `#E96F4F`, text `#B35A3D`), muted `#5B6A8E`, line `#E2E4EC`, surface `#F7F8FC`, chip `#ECEEF6`, red `#EF4444`, amber `#FBBF24`, green `#22C55E`. Never `#FF8A65`.
- Allowed failures before continuing: **2** (constant `IdDocumentCheck::ATTEMPTS_BEFORE_CONTINUE = 2`).
- Copy says "landlord", never "property owner".
- No new Composer or npm dependencies.
- Do not commit until Axcee has run the manual checklist at the end in a browser (`test-before-commit`). Commit messages carry no AI co-author trailer.
- Tests: only the focused PHP tests named in each task. The browser behaviour is checked by Axcee with the manual checklist; no JS test suite.

## Review Focus

1. **A real but worn or faded ID scores "unsure", not "fail".** Unsure never blocks. Pinned by `test_a_faded_id_with_a_face_and_card_label_is_unsure_not_fail` (Task 2).
2. **Vision is down during the preview but back for `store()`.** The applicant must not be bounced after finishing the selfie (a bounce restarts the wizard and loses the photos). `store()` blocks only when the preview already read that exact photo (`OcrService::cached()` hit) and `blocks()` is true, which only happens if someone forced Continue past the browser block. A photo the preview never read is saved and flagged, never blocked. Pinned by `test_store_without_a_preview_flags_but_does_not_block` (Task 3).
3. **Front and back sent in one request count as ONE attempt,** not two. Pinned by `test_one_preview_with_front_and_back_counts_as_one_attempt` (Task 3).
4. **The selfie is taken while the head is still turned** after the last step. Capture must wait for a frontal, centred face. Manual checklist item F4.
5. **A single noisy frame restarts a step's hold.** Brief losses under 300 ms must not reset progress. Manual checklist item F3.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `app/Support/VisionReading.php` | Create | Value object: `text`, `faceCount`, `labels`, `available` |
| `app/Services/OcrService.php` | Modify | `read(string $bytes): VisionReading` (one call, three features, cached). Remove `extractText()`. Keep `matchName`, `extractIdNumber`, `matchIdType`. |
| `app/Services/IdDocumentCheck.php` | Create | Scores a front reading (and back reading) into a verdict; owns the attempt rule |
| `app/Http/Controllers/VerificationController.php` | Modify | `ocrCheck()` takes front + back in one request, counts attempts; `store()` enforces and saves the verdict |
| `database/migrations/2026_11_18_000100_add_id_check_to_landlord_verifications.php` | Create | 3 columns |
| `app/Models/LandlordVerification.php` | Modify | fillable + casts |
| `resources/views/admin/verifications/show.blade.php` | Modify | ID-check chips |
| `public/js/verification-wizard.js` | Modify | One preview call; Continue gating; liveness rework |
| `resources/views/landlord/verification/create.blade.php` | Modify | ID-step verdict card; liveness overlay |
| `public/models/face-api/*` | Create | Self-hosted, pinned model weights |
| `tests/Unit/IdDocumentCheckTest.php` | Create | Scoring |
| `tests/Feature/VerificationIdCheckTest.php` | Create | Attempts, enforcement, saved columns |
| `tests/Feature/UploadAttackTest.php` | Modify | `extractText` mocks → `read` |
| `context/SCHEMA.md`, `context/PRD.md`, `context/DESIGN.md`, `context/CHANGELOG.md` | Modify | Docs |

---

### Task 1: One Vision call returns text, faces and labels

**Files:**
- Create: `app/Support/VisionReading.php`
- Modify: `app/Services/OcrService.php:21-70` (replace `extractText`)
- Test: `tests/Feature/VisionReadTest.php`

**Interfaces:**
- Produces: `final class VisionReading { public function __construct(public readonly string $text, public readonly int $faceCount, public readonly array $labels /* lowercase description => float score */, public readonly bool $available) }` with `public static function unavailable(): self`.
- Produces: `OcrService::read(string $imageBytes): VisionReading` (reads, caches when available).
- Produces: `OcrService::cached(string $imageBytes): ?VisionReading` (cache lookup only, never calls Vision).

- [ ] **Step 1: Write the failing tests** in `tests/Feature/VisionReadTest.php` using `Http::fake` on `vision.googleapis.com/*`:
  - `test_one_request_asks_for_text_faces_and_labels`: assert exactly one request was sent, and its `requests.0.features` types are `DOCUMENT_TEXT_DETECTION`, `FACE_DETECTION`, `LABEL_DETECTION`.
  - `test_reading_maps_text_faces_and_labels`: fake response `fullTextAnnotation.text = "REPUBLIKA NG PILIPINAS"`, two `faceAnnotations`, `labelAnnotations` `[{description:"Identity document",score:0.91}]` → `text` matches, `faceCount === 2`, `labels === ['identity document' => 0.91]`, `available === true`.
  - `test_same_image_is_read_once`: call `read()` twice with the same bytes → `Http::assertSentCount(1)`; `cached()` then returns the reading. `cached()` on unseen bytes returns null and sends nothing.
  - `test_failed_request_is_unavailable_and_not_cached`: fake a 500, call twice → `available === false` both times, `Http::assertSentCount(2)`.
  - `test_missing_key_is_unavailable_without_a_request`: config key `''` → `available === false`, nothing sent.
- [ ] **Step 2: Run** `php artisan test --filter=VisionReadTest` → FAIL (class/method missing).
- [ ] **Step 3: Implement** `VisionReading` and `OcrService::read()`. Features: `DOCUMENT_TEXT_DETECTION`, `FACE_DETECTION` (`maxResults` 1), `LABEL_DETECTION` (`maxResults` 10). A `responses.0.error` entry counts as unavailable. Cache available readings only, key `vision:` . sha256 of the bytes, 30 minutes, via `Cache::remember`-style logic that skips storing unavailable results. Keep the existing 15 s timeout and log lines. `extractText(string $path)` stays for now as a one-line wrapper (`read(file_get_contents($path))->text`) so the controller keeps working; Task 3 removes it.
- [ ] **Step 4: Run** `php artisan test --filter='VisionReadTest|UploadAttackTest|OcrNameMatchTest'` → PASS.

### Task 2: Score a reading into a verdict

**Files:**
- Create: `app/Services/IdDocumentCheck.php`
- Test: `tests/Unit/IdDocumentCheckTest.php`

**Interfaces:**
- Consumes: `VisionReading`, `OcrService::matchName()`, `OcrService::matchIdType()`.
- Produces:
  - constants `PASS = 'pass'`, `UNSURE = 'unsure'`, `FAIL = 'fail'`, `UNAVAILABLE = 'unavailable'`, `ATTEMPTS_BEFORE_CONTINUE = 2`, `PASS_POINTS = 3`.
  - `assessFront(VisionReading $r, string $idType, string $firstName, string $lastName): array{status: string, type_match: string, signals: array<string,bool>}`.
  - `assessBack(VisionReading $r): string` (pass / fail / unavailable).
  - `blocks(string $frontStatus, ?string $backStatus, int $failures): bool` — true when either status is `fail` and `$failures < ATTEMPTS_BEFORE_CONTINUE`.

Scoring (front), the part the tests pin:

| Signal | Rule | Points |
|---|---|---|
| `has_text` | at least 20 non-space characters | required (no text → `fail`) |
| `type_keyword` | `matchIdType()` status `match` | 2 |
| `name` | `matchName()` status `pass` or `partial` | 2 |
| `id_words` | at least 2 distinct of: `republic of the philippines`, `republika ng pilipinas`, `date of birth`, `petsa ng kapanganakan`, `address`, `tirahan`, `sex`, `kasarian`, `nationality`, `signature`, `lagda`, `valid until`, `expiry`, `date of issue` (case-insensitive) | 1 |
| `face` | `faceCount >= 1` | 1 |
| `card_label` | any label containing `identity document`, `license`, `passport`, `id card` or equal to `card`, score ≥ 0.6 | 1 |

`pass` = has text and points ≥ 3. `unsure` = has text and points 1–2. `fail` = no text, or 0 points. `unavailable` = reading not available. Back: `pass` when it has text, else `fail`.

- [ ] **Step 1: Write the failing tests:**
  - `test_a_clear_philsys_front_passes` (PhilSys text with the name + face + label) → `pass`, `type_match = 'match'`.
  - `test_a_blank_photo_fails` (empty text, no face) → `fail`.
  - `test_a_selfie_without_text_fails` (no text, 1 face) → `fail`.
  - `test_a_receipt_fails` (40 chars of receipt text, no face, label `receipt`) → `fail`.
  - `test_a_faded_id_with_a_face_and_card_label_is_unsure_not_fail` (25 chars of partial text, 1 face, label `identity document` 0.8) → `unsure`.
  - `test_an_unavailable_reading_is_unavailable`.
  - `test_back_with_text_passes_and_blank_back_fails`.
  - `test_blocks_until_two_failures`: `blocks('fail', null, 0)` true, `blocks('fail', null, 1)` true, `blocks('fail', null, 2)` false, `blocks('unsure', 'pass', 0)` false, `blocks('pass', 'fail', 0)` true, `blocks('unavailable', null, 0)` false.
- [ ] **Step 2: Run** `php artisan test --filter=IdDocumentCheckTest` → FAIL.
- [ ] **Step 3: Implement** `IdDocumentCheck` (inject `OcrService` for the two match methods).
- [ ] **Step 4: Run** → PASS.

### Task 3: Preview counts attempts; `store()` enforces and saves

**Files:**
- Create: `database/migrations/2026_11_18_000100_add_id_check_to_landlord_verifications.php`
- Modify: `app/Models/LandlordVerification.php` (fillable, casts)
- Modify: `app/Http/Controllers/VerificationController.php` (`ocrCheck` ~82-123, `store` ~125-243)
- Test: `tests/Feature/VerificationIdCheckTest.php`

**Interfaces:**
- Consumes: `OcrService::read()`, `IdDocumentCheck`.
- Produces (JSON from `POST /landlord/apply/ocr-check`, consumed by Task 4). Request: `{ id_image, id_back?, id_type }`. Response keeps today's front keys (`name`, `confidence`, `status`, `id_number`, `extracted`, `user_name`, `type_match`, `type_keywords`) and adds:
  ```json
  { "back_extracted": "string|null",
    "id_check": { "status": "pass|unsure|fail|unavailable", "back_status": "pass|fail|unavailable|null",
                  "blocked": true, "attempts_left": 1 } }
  ```
- Session key: `id_check_failures` (int).
- Columns: `id_check_status` string(20) nullable after `ocr_status`; `id_type_match` string(20) nullable; `id_check_overridden` boolean default false.

- [ ] **Step 1: Write the failing tests** (Vision faked with `Http::fake`, a fixture applicant via `CreatesMarketplaceFixtures`):
  - `test_one_preview_with_front_and_back_counts_as_one_attempt`: blank front + blank back → `id_check.status = 'fail'`, `blocked = true`, `attempts_left = 1`, session `id_check_failures = 1`.
  - `test_second_failed_preview_unblocks`: two failing previews → second returns `blocked = false`, `attempts_left = 0`.
  - `test_passing_preview_does_not_count`: good front → failures stay 0.
  - `test_unavailable_preview_never_blocks`: Vision 500 → `status = 'unavailable'`, `blocked = false`, failures 0.
  - `test_store_rejects_a_previewed_failing_id_before_two_attempts`: one failing preview (failures 1), then store with the same images → redirect with error on `id_image`, no record saved, no files left in `verifications/{user_id}`.
  - `test_store_without_a_preview_flags_but_does_not_block`: no preview, store with a blank-text front → saved, `id_check_status = 'fail'`, `id_check_overridden = true`.
  - `test_store_after_two_failures_saves_and_flags`: failures 2 → saved with `id_check_status = 'fail'`, `id_check_overridden = true`; session counter cleared.
  - `test_store_saves_pass_and_type_match`: good front → `id_check_status = 'pass'`, `id_type_match = 'match'`, `id_check_overridden = false`.
- [ ] **Step 2: Run** `php artisan test --filter=VerificationIdCheckTest` → FAIL.
- [ ] **Step 3: Migration + model.** Run `php artisan migrate`.
- [ ] **Step 4: `ocrCheck()`**: validate `id_back` as `nullable|string|max:8000000`. Decode both with the existing `decodeBase64Image()`; read each with `OcrService::read()` from bytes (no temp file any more). Increment `id_check_failures` once if either verdict is `fail` (an `unsure`/`unavailable` never counts). Then compute `blocked` with the new count.
- [ ] **Step 5: `store()`**, before any file is written: note whether the front (and back) were previewed (`cached()` not null), then read them with `read()` (free when cached). If a previewed photo makes `blocks()` true for the current session count, return `back()->withErrors(['id_image' => "This photo doesn't look like your {$idType}. Retake it with the whole ID in the frame, lying flat, without glare."])` without touching the counter. Otherwise save `id_check_status`, `id_type_match`, `id_check_overridden` (= front or back is `fail`), and `session()->forget('id_check_failures')`. Name and ID-number extraction use `$reading->text`. Remove `OcrService::extractText()` and the temp-file code in `ocrCheck()`.
- [ ] **Step 6: Update** `UploadAttackTest` mocks from `shouldNotReceive('extractText')` to `shouldNotReceive('read')`.
- [ ] **Step 7: Run** `php artisan test --filter='VerificationIdCheckTest|VisionReadTest|IdDocumentCheckTest|UploadAttackTest|FaceMatchTest|VerificationStatusAccessTest|OcrNameMatchTest'` → PASS.

### Task 4: ID step UI and admin chips

**Files:**
- Modify: `public/js/verification-wizard.js` (`idCaptureComplete` :84, `runOcrCheck` :661, `checkDuplicateSides` :624, `retakePhoto` :590)
- Modify: `resources/views/landlord/verification/create.blade.php` (ID step result block ~380-445, Continue :564)
- Modify: `resources/views/admin/verifications/show.blade.php` (chips next to `$ocrChip`)

**Interfaces:**
- Consumes: the Task 3 JSON.

- [ ] **Step 1: JS.** `runOcrCheck()` sends one request with `id_image`, `id_back` (when present) and `id_type`. `checkDuplicateSides()` reads `ocrResult.back_extracted`. Add getter `idCheckBlocks` = `ocrResult?.id_check?.blocked === true`. `idCaptureComplete` also requires `!this.ocrLoading && !this.idCheckBlocks`. A network error (`ocrError`) never blocks.
- [ ] **Step 2: Verdict card**, above today's name/type rows, by `id_check.status`:
  - `fail` + blocked: red card. Title "This doesn't look like a {idType}". Body "Lay the ID flat, fill the frame, and avoid glare. {attempts_left} more {try/tries} before you can continue anyway." Coral **Retake photo** button (`retakePhoto('id')`).
  - `fail` + not blocked: amber card. "We still can't confirm this is an ID. You can continue, and our team will check it by hand."
  - `unsure`: amber, "Some details were hard to read. Retake for a clearer photo, or continue."
  - `unavailable`: neutral, "We couldn't check this photo right now. Our team will check it by hand."
  - `pass`: no extra card (today's green rows show).
  - Back `fail`: the same red/amber card, saying "the back of your ID".
- [ ] **Step 3: Admin chips** on `admin/verifications/show.blade.php`: "Looks like an ID" (green) / "Might not be an ID" (amber, unsure) / "Not an ID by the check" (red, fail) / "ID not checked" (neutral, null or unavailable); "ID type matches" / "ID type not confirmed"; and a red "Continued after 2 failed checks" when `id_check_overridden`. Same chip component and tones as the existing `$ocrChip`.
- [ ] **Step 4: Verify** `php artisan view:cache` succeeds. Then run the ID part of the manual checklist yourself only as far as rendering (Axcee does the camera test).

### Task 5: Face check engine: drop Look up, smooth, full model, frontal capture

**Files:**
- Create: `public/models/face-api/` (4 files)
- Modify: `public/js/verification-wizard.js` (`livenessSteps` :59, `_initFaceApi` :169-192, `_startLivenessLoop` :208, `_processDetection` :260, checks :330-405, `_completeLivenessStep` :412, `_startCountdown` :432)

**Interfaces:**
- Produces Alpine state for Task 6: `livenessSteps = ['Look straight', 'Turn left', 'Turn right']`; `livenessPhase` (`'steps' | 'frame'`); `livenessProgress` (0–1, how far into the current pose); `livenessHint` (string, '' when none); `livenessDirection` (`'left' | 'right' | null`).

- [ ] **Step 1: Self-host weights.** Download from `https://github.com/justadudewhohacks/face-api.js/tree/master/weights` at one fixed commit (record its SHA in a comment at `_initFaceApi`): `tiny_face_detector_model-weights_manifest.json`, `tiny_face_detector_model-shard1`, `face_landmark_68_model-weights_manifest.json`, `face_landmark_68_model-shard1`. Put them in `public/models/face-api/`. `MODEL_URL = '/models/face-api'`. Load `faceapi.nets.faceLandmark68Net` (full) in place of the tiny landmark net; call `.withFaceLandmarks()` with no argument. Detector options: `new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 })`.
- [ ] **Step 2: Remove Look up.** Delete `_getPitch`, `_checkLookUp`, `_median`, `_pitchSamples`, `_pitchBaseline` and the pitch sampling in `_checkLookStraight`.
- [ ] **Step 3: Smoothing + grace.** Keep the last 5 yaw readings; use their average. A pose counts as lost only after 300 ms out of pose (`_outOfPoseSince`); shorter dips keep `_holdStart`. A missing face follows the same 300 ms grace. Holds: Look straight 800 ms (|yaw| < 15), turns 500 ms (yaw < −20 / > 20). `livenessProgress` = hold fraction for Look straight; `min(1, |yaw| / 20)` for turns.
- [ ] **Step 4: Hints and vibration.** If a step hasn't completed 6 s after it started: no face → "Move into better light and fit your face in the ring"; a turn → "Turn a little more"; straight → "Look straight at the screen". Clear on completion. On each completion call `navigator.vibrate?.(40)` inside try/catch.
- [ ] **Step 5: Frontal capture.** After Turn right, set `livenessPhase = 'frame'`, instruction "Look at the camera". The 3-2-1 countdown runs only while |yaw| < 10 AND the face box width is ≥ 35% of `video.videoWidth` AND the box centre is within the middle 40% horizontally. Breaking any of these (past the 300 ms grace) resets the countdown to 3. At 0, `_autoCaptureSelfie()` as today.

### Task 6: Face check screen: instruction on the camera, arrow, progress

**Files:**
- Modify: `resources/views/landlord/verification/create.blade.php` (step 3, ~575-760)

**Interfaces:**
- Consumes: Task 5 state.

- [ ] **Step 1: Copy.** Intro: "Three quick movements prove you're here in person. Then look at the camera and we take the photo for you, to compare with the photo on your ID." The page heading stays "Show us it's really you" (no longer swaps to the live instruction).
- [ ] **Step 2: Overlay on the video** (inside the `aspect-[3/4]` box): at the top, the live instruction in 20px semibold white on a `bg-[#060D26]/70` pill, with a left or right arrow icon for the turn steps (`livenessDirection`). Under it, a 4px progress bar (`livenessProgress`, coral fill on white/30 track). Bottom: 3 step dots + a 4th camera dot for the frame phase (done = green, current = coral, next = white/50). The hint shows as a smaller amber pill above the dots when `livenessHint` is set. Replace the "Face detected" pill (the dots carry that); keep "No face detected".
- [ ] **Step 3: The side/below step list** stays for desktop (`xl`) only; hide it below `xl` (the overlay replaces it on phones).
- [ ] **Step 4: Reduced motion:** the progress bar and dot transitions are off under `motion-reduce`.
- [ ] **Step 5: Verify** `npm run build` and `php artisan view:cache` succeed.

### Task 7: Docs

**Files:** `context/SCHEMA.md` (landlord_verifications: 3 columns), `context/PRD.md:14` (3-step liveness + ID check sentence), `context/DESIGN.md` (new section: ID verdict card + liveness overlay), `context/CHANGELOG.md` (2026-11-18 entry).

- [ ] **Step 1:** Update the four docs. PRD keeps "Does not detect a fake ID".
- [ ] **Step 2:** Stop. Hand Axcee the manual checklist below. Commit only after he confirms.

---

## Manual checklist (Axcee, phone at 375px via ngrok, then desktop)

**ID check**
- I1. Photograph a real ID front + back → no red card, Continue works, the admin page shows "Looks like an ID" and "ID type matches".
- I2. Photograph a blank wall → red card, Continue disabled, "1 more try". Retake the wall → amber "continue anyway", Continue works. Submit → admin shows "Not an ID by the check" + "Continued after 2 failed checks".
- I3. Photograph a receipt or a book page → red card.
- I4. Photograph a real ID in poor light → amber "unsure" at worst, never red twice in a row for a clearly visible ID.
- I5. Turn the PC's network off for the preview (or blank `GOOGLE_VISION_API_KEY` + `php artisan config:clear`) → neutral card, Continue works, admin shows "ID not checked".

**Face check**
- F1. Steps shown: Look straight, Turn left, Turn right, then a camera dot. No Look up.
- F2. The instruction and arrow are readable on the video while turning your head. "Turn left" means your left.
- F3. Turn slowly, wobble a little mid-turn: progress doesn't restart from zero on a small wobble.
- F4. After Turn right, stay turned: no countdown. Face the camera: 3-2-1, and the saved selfie is frontal.
- F5. Stand still on a turn for 6 s → "Turn a little more". Cover the camera → light hint.
- F6. Android: a short buzz per step.
- F7. Admin face match on a real submission reads "match" with a frontal selfie.
- F8. DevTools Network: the model files load from `/models/face-api/`, not GitHub.

---

## Changes after the final review (2026-11-18)

A fresh reviewer found no Critical and five Important issues; all five were fixed, each starting from a failing check.

| # | Finding | Fix |
|---|---|---|
| 1 | A slow answer for photos the applicant had since retaken could overwrite the new state and unlock Continue (the Retake links stay visible while "Checking your ID..." spins) | `runOcrCheck` takes a sequence number and an `AbortController`; a retake, a new ID type or a newer check cancels the one in flight, and an older answer is ignored |
| 2 | The check request had no timeout, and Continue now waits for it | Aborts after 45 s and lands on "not checked" (`ocrError`), which never blocks |
| 3 | A lost or overtaken answer plus a previewed fail made `store()` bounce the applicant and lose the whole wizard | **`store()` no longer refuses for the ID check.** A fail that reaches it is saved and flagged red (`id_check_status = fail`, `id_check_overridden = true`). The wizard still holds Continue for the first 2 failed tries. Admin copy changed to "Sent with a failed ID check" |
| 4 | The face-size rule was measured against the uncropped video; on a landscape laptop webcam the visible 3:4 box shows about 42% of its width, so a face that fits the ring never counted as close enough (and the hint said "Move a little closer") | Size and centring are measured against the visible crop, `min(videoWidth, videoHeight * 3/4)`; the threshold is 0.30 of it (was 0.35 of the whole video) |
| 5 | The amber hint pill was near-white on amber in dark mode (the app's dark remap lightens navy text) | `html.dark .bg-[#FBBF24] { color: #060D26 }` in `resources/css/app.css` |

**Reversing finding 3:** if you want the server to hold the line again, put the `blocks()` check back in `VerificationController::store()` before the files are written. It then needs a "Check again" button for the lost-answer case, or the bounce returns.

**Deferred Minor findings** (none fixed; each is a small change):
1. Yaw smoothing is a mean of 5, so one wild frame can still push a hold past the 300 ms grace; a median of 5 ignores it.
2. Hints can flicker (the no-face hint fires inside the grace) and "Turn a little more" can show while the applicant is already in pose.
3. `OcrService::cached()` reads its payload keys without defaults.
4. A real ID with glare where Vision reads under 20 characters but finds a face and a card label scores `fail` (2 retakes, then red flag); consider `unsure` after real-ID trials.
5. Front and back are two sequential Vision calls; one combined `images:annotate` request would halve the wait.
6. The no-face hint wraps to two lines in a rounded-full pill on ~320 px boxes.
7. (Predates this work) the Vision key travels in the query string and a connection error can write it to `laravel.log`; the `x-goog-api-key` header avoids that.
