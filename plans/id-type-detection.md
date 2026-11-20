# ID Type Detection (templates per ID)

**Status:** Built 2026-11-19 (uncommitted). Pending Axcee's manual pass (Verification §4, checklist T1-T5); delete this file once he has run it. Tests: detector 27, feature 7 new. Deviation from the plan: `IdSamplesCheck` also counts an unread file as a failure.

## Context

Today the ID step only checks the type the applicant **picked**: `OcrService::matchIdType()` (`app/Services/OcrService.php:327`) looks for that type's keywords and answers `match` / `mismatch`. It never says what the photo actually is, so a PhilSys applicant who uploads a Driver's License just gets "This doesn't look like a PhilSys".

The real samples (`storage/app/id-samples/`, gitignored) also exposed two bugs in the keyword list:

| Bug | Effect |
|---|---|
| Passport keywords include "REPUBLIC OF THE PHILIPPINES" | Every UMID, Driver's License and Postal ID "matches" Passport |
| Driver's License keywords include "PROFESSIONAL" | Every PRC card "matches" Driver's License |

And `extractIdNumber()` misses the current passport number format (`P1234567A`: letter, 7 digits, letter).

**Goal:** a template per ID type (header phrases, field labels, ID number format, back-side cues). Score the photo against every template, name the type it looks like, and when that differs from the pick, offer a one-tap switch.

**Decided with Axcee (2026-11-19):**
- Keep the ID type picker. It decides whether a back photo is needed (Passport doesn't need one) and what the camera step says.
- Mismatch = **one-tap switch**: "This looks like a Driver's License, not a PhilSys" with **[Use Driver's License]** (keeps the photo) and **[Retake photo]**. Continue is held until they act. A mismatch costs one try, the same as a failed check, so after 2 tries they can continue anyway and the admin is flagged. A wrong detection can't trap anyone.
- SSS is added later. Its template exists only to check an applicant who **picked** SSS. It is never suggested by detection until we have SSS samples.

## Approach

### 1. Templates: `app/Support/IdTemplates.php` (new)

One class holds every ID type. It becomes the single source of the type list, which today is copied in 3 places (`StoreVerificationRequest:24`, `VerificationController:91`, `verification-wizard.js:47`).

Per type: `anchors` (phrases unique to that ID), `labels` (field labels unique to it), `number` (regex) + `numberStrong` (bool), `back` (back-side phrases), `detectable` (false for SSS).

| Type | Anchors (3 pts each, max 6) | Labels (1 pt each, max 3) | Number (strong 2 / weak 1) | Back |
|---|---|---|---|---|
| PhilSys | pambansang pagkakakilanlan, philippine identification card, philsys, philid | mga pangalan, gitnang apelyido, tirahan | `\d{4}-\d{4}-\d{4}-\d{4}` strong | psa.gov.ph, nearest psa office, uri ng dugo, kalagayang sibil |
| Driver's License | land transportation office, driver's license, department of transportation, lto | license no, agency code, dl codes, eyes color, restrictions | `[A-Z]\d{2}-\d{2}-\d{6}` strong | dl codes, organ donation, in case of emergency notify, serial number |
| Passport | pasaporte, passport, department of foreign affairs, dfa | kodigo ng bansa, pasaporte blg, issuing authority, nasyonalidad | `[A-Z]\d{7}[A-Z]\|[A-Z]{2}\d{7}` strong; MRZ line `P<PHL` = +4 | (no back) |
| Professional ID Card | professional regulation commission, professional identification card | registration no, registration date | `\d{7}` weak | prc.gov.ph, certification, duly registered professional |
| UMID | unified multi-purpose id, umid | crn | `\d{4}-\d{7}-\d` strong | (none yet, no sample) |
| Postal ID | postal identity card, philippine postal corporation, phlpost | prn, issuing post office | 12 digits, weak | phlpost.gov.ph, nearest post office, proof of identity of the cardholder |
| SSS ID | social security system, sss | — | `\d{2}-\d{7}-\d` strong | — |

Words found on many IDs ("republic of the philippines", "republika ng pilipinas", "date of birth", "address", "sex", "apelyido", "surname") are on **no** template. They stay in `IdDocumentCheck::ID_WORDS`, which answers "is this an ID at all".

Matching reuses the existing whole-word rule from `matchIdType()` (`(?<![\p{L}\p{N}])…(?![\p{L}\p{N}])`, case-insensitive). The text is normalized first: curly apostrophes become `'`, and "drivers" also matches "driver's".

### 2. Detector: `app/Services/IdTypeDetector.php` (new)

- `scoreFront(string $text, string $type): int`
- `detectFront(string $text): ?string`: the top **detectable** type when its score is ≥ `DETECT_MIN = 4` and at least `DETECT_LEAD = 3` ahead of the runner-up. Otherwise null.
- `matchFront(string $text, string $chosen): array{status: 'match'|'mismatch'|'unknown', detected: ?string}`
  - `mismatch`: something else was detected
  - `match`: the pick was detected, or nothing was detected and the pick scores ≥ `MATCH_MIN = 3`
  - `unknown`: otherwise
- `detectBack(string $text): ?string`: the same rule using the `back` phrases.
- `idNumber(string $text, string $type): ?string`: moved out of `OcrService::extractIdNumber()` and keeps its O→0 / I→1 retry. For Passport, it takes the number from MRZ line 2 when its check digit (7-3-1 weights) is right, otherwise the printed number.

Remove `OcrService::matchIdType()` and `OcrService::extractIdNumber()`. Their callers move to the detector.

### 3. Check + controller

- `IdDocumentCheck::assessFront()` (`app/Services/IdDocumentCheck.php:54`) calls `matchFront()`. It returns `type_match` + `detected_type`. `type_keyword` (2 pts) still means `match`. A mismatch doesn't turn the check into a `fail`, because the photo is a real ID.
- `IdDocumentCheck::blocks()` gets a `bool $typeMismatch`: it holds when front fail, back fail **or** mismatch, and failures < 2.
- `VerificationController::recordIdCheckResult()` counts one try when any of those three is true (still one try per request).
- `ocrCheck()` response adds `detected_type`, `back_detected_type`, `id_check.type_mismatch`.
- `store()` saves the new column `id_type_detected`. `id_check_overridden` is also true when the saved type is `mismatch`, since it was sent past the check.
- Validation for `id_type` uses `Rule::in(IdTemplates::names())` in both places.

### 4. Migration

`2026_11_19_000100_add_id_type_detected_to_landlord_verifications.php`: `id_type_detected` varchar(40) nullable, after `id_type_match`. Add it to the `LandlordVerification` fillable.

### 5. Wizard (JS + Blade)

- `public/js/verification-wizard.js`
  - `idTypes` comes from `config.idTypes` (the Blade passes `IdTemplates::names()`).
  - `idCheckCard` gets a mismatch case, ordered after a front fail and before a back fail:
    - Blocked: red, title "This looks like a {detected}, not a {idType}", body "Use {detected} if that's the ID you photographed, or retake a photo of your {idType}.", buttons `switchTo: detected` + `retake: 'id'`.
    - Not blocked: amber, "This still looks like a {detected}", "You can continue, and our team will check it.", plus the switch button.
  - New `switchIdType(type)`: sets `_keepPhotos = true` so the `idType` watcher (`:212`) doesn't wipe the photos, then changes `idType`.
    - Switching to Passport drops the back photo.
    - Switching to a type that needs a back, when there is none, goes to `capture-back` and opens the camera.
    - Otherwise it runs `runOcrCheck()` again.
  - Back type differs from front type (both detected): an amber note "The back looks like it's from a different ID". It doesn't block or cost a try.
- `resources/views/landlord/verification/create.blade.php`
  - The verdict card (`~:360-379`) gets a coral **Use {detected}** primary button. Retake becomes the secondary (outline) button when both show.
  - The old amber "This doesn't look like a {idType}" row (`:453-468`) becomes "We couldn't confirm this is a {idType}" for `unknown` only. Mismatch now has the card.
  - Mobile-first: at 375px the buttons stack full width, min-h 44px.

### 6. Admin

`resources/views/admin/verifications/show.blade.php:42` `$typeChip`: `match` → green "ID type matches"; `mismatch` → red "Picked {id_type} · looks like a {id_type_detected}"; `unknown` → amber "ID type not confirmed".

### 7. Calibration command (local only, not in CI)

`app/Console/Commands/IdSamplesCheck.php` → `php artisan id-samples:check`

- Reads every image under `storage/app/id-samples/<folder>/` through `OcrService::read()` (cached, so a re-run costs no Vision calls). Folder → expected type: philsys, drivers-license, passport, prc, umid, postal, sss.
- Prints a table: file, expected type, detected type (front, or back for `*back*` files), every type's score, ID number found, ✅/❌.
- Writes Vision's raw text to `storage/app/id-samples/_text/*.txt` for tuning. This stays inside the gitignored folder because it contains personal data.
- Run it after building and whenever new samples land (UMID back, UMID-ATM, ePhilID, digital ID, SSS). Then tune the template and flip SSS to `detectable`.

### 8. Tests (no real ID data in the repo)

Fixtures are **made-up transcriptions** in the layout of each sample, with fake names and numbers (Juana Dela Cruz, P1234567A with a correct MRZ check digit), in `tests/Fixtures/id-text/*.txt`.

- `tests/Unit/IdTypeDetectorTest.php` (new):
  - Each of the 6 fixtures detects its own type.
  - UMID, DL and Postal text with Passport picked → `mismatch`, not `match` (bug 1).
  - PRC text with Driver's License picked → `mismatch` (bug 2).
  - Receipt text and blank text → detected null, `unknown`.
  - "LTO\nNON-PROFESSIONAL" with DL picked → `match`, detected null (one weak anchor is under `DETECT_MIN`).
  - SSS text is never detected, but SSS picked + "Social Security System" → `match`.
  - DL back and Postal back detect their type.
  - Passport number comes from the MRZ; with a broken check digit it falls back to the printed number; the PhilSys PCN is read.
- `tests/Unit/OcrNameMatchTest.php`: move the `matchIdType` / `extractIdNumber` cases (`:89-112`) into the detector test.
- `tests/Feature/VerificationIdCheckTest.php`:
  - DL text with PhilSys picked → `blocked`, `attempts_left = 1`, `detected_type = "Driver's License"`.
  - The same photo re-checked as Driver's License → `match`, not blocked, and no extra try counted.
  - Two mismatches → unblocked.
  - Store with a mismatch saves `id_type_detected` and `id_check_overridden = true`.
- `tests/Unit/IdDocumentCheckTest.php`: `blocks()` with `typeMismatch`.
- `tests/Feature/AdminVerificationIdCheckChipsTest.php`: the "Picked … · looks like a …" chip.

### 9. Docs

`context/SCHEMA.md` (`id_type_detected`; `id_type_match` text), `context/CHANGELOG.md` (2026-11-19), `context/DESIGN.md` (mismatch card + switch button), `context/ARCHITECTURE.md` (IdTemplates / IdTypeDetector next to IdDocumentCheck).

## Critical files

| File | Change |
|---|---|
| `app/Support/IdTemplates.php` | new |
| `app/Services/IdTypeDetector.php` | new |
| `app/Services/IdDocumentCheck.php` | use detector, `blocks()` + mismatch |
| `app/Services/OcrService.php` | remove `matchIdType`, `extractIdNumber` |
| `app/Http/Controllers/VerificationController.php` | response keys, try counting, save column, validation |
| `app/Http/Requests/StoreVerificationRequest.php` | `Rule::in(IdTemplates::names())` |
| `app/Console/Commands/IdSamplesCheck.php` | new |
| `database/migrations/2026_11_19_000100_…` | new column |
| `public/js/verification-wizard.js`, `resources/views/landlord/verification/create.blade.php` | mismatch card, switch |
| `resources/views/admin/verifications/show.blade.php` | chip |

One agent, in order: templates → detector → check/controller → wizard → admin → command → docs. The PHP pieces depend on each other, so splitting into parallel agents isn't worth it. Implement with Sonnet 5 per CLAUDE.md.

## Verification

1. `php artisan test --filter='IdTypeDetectorTest|IdDocumentCheckTest|VerificationIdCheckTest|AdminVerificationIdCheckChipsTest|OcrNameMatchTest|VisionReadTest|UploadAttackTest'` passes, and so does the full suite.
2. `php artisan migrate` and `php artisan view:cache` succeed, and so does `npm run build`.
3. `php artisan id-samples:check`: all 8 sample files ✅ (PhilSys, DL front/back, Passport, PRC, UMID front, Postal front/back). Any ❌ → tune the template, re-run.
4. Manual pass (Axcee, phone at 375px via ngrok), before any commit:
   - T1. Pick PhilSys, photograph your Driver's License → red "This looks like a Driver's License, not a PhilSys". Tap **Use Driver's License** → it asks for the back if needed, the card clears, Continue works, and the review step shows Driver's License.
   - T2. Pick Passport, photograph a PRC card → mismatch card. Retake twice with the same card → amber, Continue works. Submit → admin shows the red "Picked Passport · looks like a Professional ID Card" chip and "Sent with a failed ID check".
   - T3. Pick the right type for each real ID you have → green "This looks like a …", no card.
   - T4. Front of one ID, back of another → amber "different ID" note, Continue still works.
   - T5. Blank wall → the existing red "doesn't look like" card (unchanged behaviour).
