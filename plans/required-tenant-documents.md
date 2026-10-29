# Required tenant documents (upload before signing)

Status: phases 1 to 4 built Oct 29 2026, pending manual verification. Phase 5 partly done (privacy page, docs); walk-in upload, API read and photo-metadata stripping not built. Decided with Axcee on Oct 29 2026, from the reference app's "Compliance Management"
(required documents per property type) and its Move-In Checklist. Builds on `plans/lease-templates-page.md`.

## Decisions (Oct 29 2026)
| Question | Answer |
|---|---|
| When are documents due | **Before signing.** The Sign button stays locked until every required document is uploaded. |
| Review | **Landlord approves, or rejects with a reason.** The tenant re-uploads a rejected one. |
| Retention | **Delete the file 30 days after the tenancy ends.** Only the record stays ("Approved on Oct 3"). |
| Reuse | **No.** A document belongs to one tenancy and is visible only to that property's landlord. |

Defaults I chose (change if you disagree, see "To confirm"):
- A landlord may **waive** a requirement for one tenant.
- **Signing needs the document uploaded, not approved.** Approval never blocks the tenant's money or move-in.
- **Admins cannot open tenant documents** (they can see that a requirement was met).
- If the tenancy ends **before the agreement was signed**, files are deleted straight away, not after 30 days.

## What the landlord sets: required documents on a lease template
A **Documents tenants must upload** section on the template page (`landlord/leases/edit`), under the rules panel:
toggles for a fixed list, plus up to 5 custom ones the landlord names.

| Key | Label |
|---|---|
| `valid_id` | Valid government ID (photo of the card) |
| `barangay_clearance` | Barangay clearance |
| `police_clearance` | Police or NBI clearance |
| `proof_of_income` | Proof of income or employment |
| `school_enrollment` | School ID or enrollment proof |
| `other` | Any label the landlord types (max 100 chars) |

- `lease_templates.required_documents` (json, null): `[{"key":"valid_id"},{"key":"other","label":"Letter from employer"}]`.
- **Frozen at send** into `lease_snapshot.required_documents` as `[{key,label}]`, the same way rent rules are, so
  editing the template later never changes what a tenant was asked for. A lease with none (or the standard
  lease) asks for nothing, exactly as today.
- The existing emergency contact and ID number (`tenant_details`) stay. "Valid ID" is the picture of that ID.

## Data
New table **`tenancy_documents`** (one row per requirement per reservation):

| Column | Notes |
|---|---|
| `tenancy_document_id` | PK |
| `reservation_id` | FK reservations, cascade. UNIQUE with `doc_key` |
| `doc_key` | varchar(40): a key above, or `other_1` to `other_5` |
| `label` | varchar(100), copied from the snapshot |
| `status` | enum `Requested` (no file yet), `Pending` (uploaded, waiting for the landlord), `Approved`, `Rejected`, `Waived` |
| `file_path`, `file_name`, `mime`, `size` | null until uploaded, and again after the purge. Private disk |
| `rejection_reason` | text null |
| `uploaded_at`, `reviewed_by` (FK users, set null), `reviewed_at` | |
| `purge_after` | date null. Set by the observer when the reservation ends |
| `purged_at` | timestamp null. File deleted; row kept as the record |
| timestamps | |

- Rows are created **when the agreement is sent** (`Reservation::advanceToPendingAgreement`, same transaction as the
  snapshot), status `Requested`. No requirements, no rows.
- Storage: `local` (private) disk, `tenancy-documents/{reservation_id}/{random}.{ext}`. Never the original filename on disk.

## Rules (one place: `App\Services\TenancyDocuments`)
- `requestFor(Reservation)`: create rows from the snapshot.
- `upload(Reservation, key, UploadedFile)`: tenant only. Allowed while status is `Requested`, `Pending` or `Rejected`
  (replaces the old file and deletes it); **not** once `Approved` or `Waived`. Sets `Pending`.
- `approve`, `reject(reason required)`, `waive`, `reopen`: landlord of the property only. Locked row, re-checks state.
- `missing(Reservation)`: requirements that are `Requested` or `Rejected`. This is what blocks signing.
- **The signing gate lives in `Reservation::signAgreement()`**, not in the controller, so the web form
  (`Tenant\AgreementController::sign`) and the mobile API (`Api\Tenant\AgreementController::sign`) cannot disagree. The
  view reads the same `missing()` to disable the button and say why.
- Approval and rejection never touch payments, escrow or move-in. Money paths read none of this.
- Validation: `pdf, jpg, jpeg, png, webp`, 10 MB each (same as property documents), content type checked, up to 8 per tenancy.

## Tenant side
- **Agreement page** (`agreements/show`): a "Documents to upload" card above the signing form. One row per requirement: label, status pill, **Upload** (camera or file on phones), the landlord's reason if rejected, and a line under the card: "Only <landlord first name> can see these. They are deleted 30 days after your tenancy ends." The Sign button is disabled with "Upload N more documents to sign".
- **Chat:** the "Get ready to sign faster" card also lists the documents the landlord will ask for (from the unit's template), and the move-in checklist gets a **Documents uploaded** step.
- Notifications: landlord gets "<tenant> uploaded <document>"; tenant gets "approved" / "rejected: <reason>".

## Landlord side
- **Tenancy page and the chat's side card:** a Documents list with **View** (opens the private file inline in a new tab), **Approve**, **Reject** (reason box), **Waive**. A "2 to review" badge.
- **Sent & signed tab:** a "Docs 2/3" column.
- Every View is written to `AuditLog` (who, which document, when).
- Walk-ins (phase 5): the landlord uploads on the tenant's behalf from the walk-in form and the tenancy page; those rows are saved as `Approved` (landlord-asserted, labelled "Recorded by you").

## Privacy and security
- Files are served only by policy-gated routes (`tenancy-documents/{id}/file`): the tenant who owns it, or the landlord
  of the reservation's property. Same pattern as `PropertyDocumentPolicy` and the signed lease file. 403 for everyone else, including admin.
- Responses: `Content-Disposition: inline`, `X-Content-Type-Options: nosniff`, no caching.
- **Purge:** `reservations:purge-tenant-documents`, daily 03:30 in `routes/console.php`. Deletes files where
  `purge_after <= today`, sets `purged_at`, nulls `file_path`. `ReservationObserver` sets `purge_after` when the status
  becomes terminal (`Reservation::TERMINAL_STATUSES`): today if `agreed_at` is null, otherwise +30 days.
- The privacy page (`legal/privacy`) gets a paragraph on these documents and the 30-day rule.
- Consent line at upload (above). No OCR, no automatic reading of ID contents.

## Phases
1. **Data and rules:** migration, `RequiredDocument` list, `TenancyDocuments` service, policy, signing gate in
   `signAgreement()`, purge command and observer hook. Tests: gate (web and API), state transitions, purge timing,
   frozen requirements, another landlord/tenant refused.
2. **Template editor:** the Documents section; frozen into the snapshot; rows created on send.
3. **Tenant upload:** agreement-page card, file route, chat nudge, notifications.
4. **Landlord review:** tenancy page, chat side card, checklist step, Sent & signed column, audit log.
5. **Walk-ins, API read, privacy page, docs, manual test guide.**

About 4 to 5 days. Phases 1 to 4 are the useful release.

## To confirm
1. **Waive:** OK for the landlord to excuse a document for one tenant? (Planned yes.)
2. **Approval does not block move-in.** Only signing is gated, because tenant money may already be in escrow and a landlord who never reviews must not trap it. OK?
3. **Admins cannot open tenant documents.** If a dispute needs them, the landlord or tenant shows them. OK, or should admin see them with a logged "reason" step?
4. **Image metadata:** phone photos carry GPS and device data. Stripping it needs the PHP GD (or Imagick) extension, which this dev machine lacks, so it is **not built**. Add `ext-gd` to the VPS requirements first, then re-encode on upload.

## Risks
- **Sensitive data held by the platform.** Mitigated by private storage, short retention, no reuse, no admin access and the audit log; not by encryption at rest. Encrypting each file (`Crypt`) costs memory on 10 MB files and a slower View; deferred unless asked.
- **Tenants may refuse to upload** and abandon the inquiry. The requirement is per lease, so a landlord can keep one lease with none (for example for walk-ins or low-risk units).
- **Landlord never reviews.** Not blocking, by design; the badge, notification and checklist are the nudge.
- **Two ID concepts** (typed ID number in `tenant_details`, uploaded picture here) can drift. Both are labelled and shown together; no sync.
- **Retention is only as good as the scheduler.** The command must run on the VPS (already required for the other nightly jobs); the runbook gets a line.

## Manual test guide (phases 1 to 4)
1. Leases > edit a lease > **Documents tenants must upload**: tick Valid ID, add "Pay slip" with "Add another document", save. The card on the Leases page says "2 documents to upload".
2. As the tenant in an Inquiry chat on that lease: "Have these ready to upload" lists them.
3. As the landlord, send the agreement. As the tenant, open it: a **Documents to upload** card above the signing form, status "To upload", and the Sign button is disabled with "Upload 2 more documents above to sign."
4. Upload the ID (a phone photo or PDF). Status becomes "Waiting for review". Sign is still locked until Pay slip is uploaded; then it unlocks.
5. As the landlord, the chat's side card has **Documents** with "1 to review": View opens the file in a new tab; **Approve**, **Send back** (a reason is required) and **Not needed** (confirm).
6. Send one back: the tenant gets a notification, the card shows the reason, and the Sign button locks again until re-uploaded.
7. The tenant cannot replace an approved file; the landlord can **Reopen** it.
8. Another landlord, another tenant and an admin each get a 403 on the file link.
9. Cancel the reservation before signing: the files are gone (the row says deleted). After a signed tenancy ends, files stay 30 days; run `php artisan reservations:purge-tenant-documents` after setting `purge_after` to yesterday to see them go while the row stays Approved.
10. Admin > Audit logs shows "Tenant document opened" each time the landlord views a file.
11. Check the tenant card and the side card at 375px.
