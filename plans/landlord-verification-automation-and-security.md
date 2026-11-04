# Landlord verification: auto-approval, document levels, and data security

Status: proposal, nothing implemented yet. Written 2026-11-04.
Related code: `app/Http/Controllers/VerificationController.php`, `app/Services/OcrService.php`,
`app/Models/LandlordVerification.php`, `app/Services/LandlordPayouts.php`, `app/Services/AccountFilePurger.php`.

## 1. Where verification stands today

| Check | Status | Weakness |
|---|---|---|
| OCR name on ID vs account name | Done | Proves the text matches, not that the ID is genuine |
| ID type keywords | Preview only | Easy to fake |
| Duplicate ID photo hash and ID number | Done | Catches reuse, not forgery |
| Liveness (`liveness_passed`) | Stored | Sent by the browser, so it can be faked |
| Selfie vs ID photo face match | Missing | A stolen ID would pass |
| ID authenticity and expiry | Missing | |
| Business name, address, phone | Missing | Nothing checks them |

## 2. Auto-approval

Full automation that guarantees the data is real is not possible with our own OCR. Use **tiered
auto-approval** backed by a KYC provider.

**Recommendation: a full KYC provider** (Sumsub, Veriff, Persona or similar). Landlords handle
tenants' money, so one bad auto-approval costs more than the per-check fee. Only a KYC provider can
tell a genuine ID from an edited one. Rekognition-only (face match and liveness) is the cheaper
fallback, but a forged ID with the forger's own face would still pass.

Steps:
1. Pick a provider that supports Philippine IDs (PhilSys, UMID, driver's license, passport). Test in
   the sandbox with real PH IDs first. Coverage is the one thing not yet confirmed.
2. The wizard hands the ID check, face match and liveness to the provider (replaces the fakeable flag).
3. Add a webhook endpoint. The result drives the tiers:

| Result | Action |
|---|---|
| Clean pass, name matches account, no duplicates, ID not expired | Auto-approve |
| Borderline | Pending for admin, reasons shown |
| Fake-ID flag, face mismatch, duplicate | Reject or flag |

4. Keep the existing duplicate hash and ID-number checks.
5. Safety rails: log every auto-approval in `AuditLog`, admin on/off switch, admin can revoke later,
   send a sample of auto-approvals to manual review for the first weeks, manual queue as fallback if
   the provider is down.
6. Verify the phone by SMS code. Business name and address cannot be proven automatically.

Risks: cost per check, images go to a third party (Data Privacy Act consent and processing
agreement needed), outside dependency.

## 3. Documents: most small Cebu landlords have no permits

Stakeholder feedback: most Filipino rental owners do not have a business permit, BIR registration or a
title in their own name. Do not require formal documents. Use levels instead.

Evidence an informal landlord usually has:

| Evidence | Supports | Strength |
|---|---|---|
| Government ID plus selfie | The person is real | Strong |
| Electric or water bill for the property | Links person to address | Medium |
| Real property tax receipt or tax declaration | Links to the property (check the name, often family-held) | Medium to strong |
| Barangay certification or clearance | Local official knows them | Medium |
| Deed of sale, donation, or extrajudicial settlement | Ownership without a title | Strong |
| Notarized authority letter from owner | Caretaker or family-managed rental | Medium |
| Existing lease contracts or rent receipts | A real rental operation | Medium |
| GCash or Maya account in the same name | Payout account belongs to them | Medium |
| Photos with house number, GPS photos taken on site | Unit exists, person is there now | Weak alone |

Formal documents (title, business permit, BIR 2303, DTI/SEC, fire safety certificate, occupancy
permit, sanitary permit, DOT accreditation for short stays) are **optional trust badges**, not
requirements. Confirm exact local rules with Cebu City BPLO or a lawyer before making any mandatory.

| Level | Landlord provides | Allowed |
|---|---|---|
| 1. Identity verified | ID, selfie, SMS-verified phone | A few units, shown as "ID verified" |
| 2. Property linked | Level 1 plus utility bill or tax receipt or barangay certification, plus on-site GPS photos | Full listing, "Property verified" |
| 3. Fully documented | Level 2 plus title, business permit or deed | "Fully verified" badge, higher limits, search priority |

Auto-approval applies cleanly at Level 1. SPA or sublease cases go to manual review (highest fraud risk).
Honest limit: no document proves a landlord is honest. Real protection is the combination of verified
identity, a location check, a payout account in the same name, and holding the first payout until the
tenant moves in. Check how `LandlordPayouts` holds payouts today.

Nothing can automatically check a title against the Registry of Deeds (no public API). Admin compares
against a certified copy only when a case looks suspicious.

## 4. Security: what to say when asked "how secure are you?"

Already in place (confirmed from code):
- ID and selfie on the private disk, not publicly reachable
- `Gate::authorize('view', ...)` on download and preview
- Uploads checked as real images with type and size limits (`decodeBase64Image`)
- Security headers middleware and a CSP report endpoint
- Upload attack tests (commit `1f7948f`)
- Duplicate ID detection
- `AccountFilePurger` exists (not yet reviewed for completeness)

Gaps, in priority order:
1. **Encrypt files at rest.** Biggest upgrade. Encrypt before writing, or use an encrypted volume or bucket.
2. **Data minimization and retention.** After approval, delete or archive the ID back and raw OCR text
   after a set period (for example 90 days to 1 year). Keep only the result and a hash of the ID number.
3. **Encrypt `id_number`.** Use the `encrypted` cast, plus a separate keyed hash column for the duplicate check.
4. **Audit every file access** with `AuditLog` (who viewed what, when).
5. **Limit admin access.** 2FA for admins, a separate document-viewing permission, masked data until opened on purpose.
6. **Short-lived file access.** Signed expiring URLs and `no-store` cache headers.
7. **Temp files.** `ocrCheck` writes `temp_ocr_{id}.jpg`. Use a random name and a scheduled sweep for crash leftovers.
8. **Rate limits** on the verification and OCR endpoints.
9. **Backups** encrypted, access-controlled, under the same retention rule. Test a restore.
10. **Third-party provider.** Data processing agreement, ISO 27001 or SOC 2, ask where data is stored.
11. **Infrastructure.** Secrets out of the repo and rotated, HTTPS with HSTS, patched dependencies,
    firewall, SSH keys only (see `plans/hostinger-vps-deployment.md`).
12. **Incident response plan** decided in advance.

Data Privacy Act of 2012 (RA 10173), to confirm with a lawyer or the National Privacy Commission:
- Appoint a Data Protection Officer.
- Privacy notice and consent in the wizard (what, why, how long).
- Register with the NPC if required.
- Report a serious breach to the NPC and affected people within 72 hours.
- Support user requests for access, correction and deletion.

Say "encrypted, access-logged, deleted after a fixed period" only once items 1 to 4 are built.

## 5. Suggested order

1. Security items 1 to 4, plus the privacy notice and consent text.
2. Level 1 and Level 2 document flow, with SMS phone verification and on-site GPS photos.
3. KYC provider in sandbox mode, tier logic and webhook, audit logging, admin switch.
4. Switch auto-approval on at launch, sample-review the first weeks.

## Open decisions

- KYC provider choice and its Philippine ID coverage and pricing
- Retention period for ID images
- Which documents count toward Level 2 and Level 3
- Whether short-term stays are in scope (DOT accreditation)
