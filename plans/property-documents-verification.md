# Property verification documents

## Context

Item 3 of `plans/property-flow-verification-gaps.md`, and the last structural piece of the
analyst doc's verification story. Today an admin approving a property listing is taking the
landlord's word for it: `landlord_verifications` proves the *person* is who they say they are, but
nothing anywhere proves they have the right to rent out *this property*. The analyst doc's Step 5
covers exactly that gap — proof of ownership, tax declaration, permits — with the hard constraint
that those documents stay admin-only and are never exposed to renters.

Two things found while planning that shape the work:

1. **Every file upload in this app currently goes to Cloudinary, which serves public URLs.**
   Property photos, unit photos, business logos — all public, correctly so. Verification documents
   must not follow that path. `LandlordVerification` already solved this: government IDs and
   selfies go to `Storage::disk('local')` (rooted at `storage/app/private`, not web-reachable) and
   are served through policy-gated controller routes (`VerificationController::preview/download`,
   `LandlordVerificationPolicy@view`). Property documents follow that precedent exactly, not the
   Cloudinary one. Getting this backwards would publish land titles and tax declarations to
   anyone with the URL.
2. **The public "Verified Property" badge is currently unconditional decoration.**
   `resources/views/properties/show.blade.php:230` renders it on every approved listing that has
   photos, backed by nothing beyond the listing being approved. Decision taken: it becomes
   conditional on the property actually having a currently-valid verified document.

Decisions taken: ship the core (upload, private serving, verify/reject-with-reason) **plus the
admin "request document" action**; landlord manages documents on a **dedicated page per property**;
badge **tied to verified documents**.

## Design decisions

- **`Expired` is derived from `expiry_date`, not a stored enum member.** The doc lists four
  statuses (Pending/Verified/Rejected/Expired), but a document whose `expiry_date` has passed *is*
  expired — storing that as state means a nightly job has to maintain it and can drift. This is
  precisely the lesson `context/ARCHITECTURE.md:266` records for `"Paid (held)"`. So the column is
  `ENUM('Pending','Verified','Rejected')`, `expiry_date` is nullable, and expiry is computed on
  read. No scheduled command, nothing to fall out of sync.
- **A "requested" document is a row with `file_path IS NULL`,** not a fifth status. When an admin
  requests a Tax Declaration, that inserts `(property_id, document_type, status Pending,
  file_path null)`. `file_path IS NULL` = awaiting the landlord's upload; `file_path NOT NULL` +
  Pending = uploaded, awaiting review. Two facts, two columns, no new enum member.
- **Documents do not gate property approval.** The doc is explicit: "The system should not blindly
  require every document from every landlord … required documents can depend on the type of
  landlord, property, and applicable legal requirements." The admin sees the documents while
  deciding and uses judgment; the request action is the lever for a specific missing one. A
  mandatory-document checklist is the separate, still-unscoped submission-checklist item.
- **No new admin surface.** Document review lands on the existing
  `admin.catalogue.properties.show` page (which already shows info, photos, and units and is
  currently read-only), turning it into the "Property Verification page" the doc describes.
  Property-level approve/reject stays where it is in `Admin\ListingController`.
- **This is the app's first PDF upload.** Nothing currently accepts `application/pdf` anywhere;
  preview has to branch on type (inline `<iframe>`/object for PDF, `<img>` for scans).

## Schema

**Migration `create_property_documents_table`**

| Column | Type | Notes |
|---|---|---|
| document_id | BIGINT UNSIGNED PK | matches the `*_id` PK convention on every table here |
| property_id | FK → properties.property_id, cascade | |
| document_type | VARCHAR(100) | validated against `PropertyDocument::TYPES` |
| file_path | VARCHAR(255) NULLABLE | **nullable**: null = admin-requested, not yet uploaded |
| file_name | VARCHAR(255) NULLABLE | landlord's original filename, for the admin's benefit |
| document_number | VARCHAR(100) NULLABLE | TCT no., tax dec no., permit no. |
| status | ENUM('Pending','Verified','Rejected') DEFAULT 'Pending' | Expired is derived, see above |
| rejection_reason | TEXT NULLABLE | required when rejecting |
| expiry_date | DATE NULLABLE | permits expire; titles don't |
| verified_by | FK → users.user_id NULLABLE, set null | |
| verified_at | TIMESTAMP NULLABLE | |
| requested_by | FK → users.user_id NULLABLE, set null | set when an admin requests the document |
| timestamps | | |

Index on `(property_id, status)`.

**`App\Models\PropertyDocument`**
- `const TYPES = ['Proof of Ownership', 'Tax Declaration', 'Authorization / Special Power of
  Attorney', 'Business or Mayor\'s Permit', 'Occupancy Permit', 'Fire Safety Certificate',
  'Other']` — one source of truth for the upload form, the request form, and validation, in the
  spirit of `Reservation::TERMINAL_STATUSES`.
- `isExpired()`: `status === 'Verified' && expiry_date !== null && expiry_date->isPast()`.
- `getDisplayStatusAttribute()`: `'Expired'` when `isExpired()`, else `status`. Every view reads
  this, never the raw column.
- `scopeCurrentlyValid()`: `status = 'Verified' AND (expiry_date IS NULL OR expiry_date >= today)`
  — the one query behind the public badge, so "verified" can't mean two things.
- `Property::documents()` hasMany; `Property::hasVerifiedDocuments()` wrapping
  `documents()->currentlyValid()->exists()`.

## Landlord side

**`Landlord\PropertyDocumentController`**, routes nested under the existing landlord group in
`routes/web.php:71` alongside `properties.units`:

- `GET /properties/{property}/documents` — index: uploaded documents with status, admin-requested
  ones awaiting upload called out first, rejection reasons shown in full, upload form.
- `POST /properties/{property}/documents` — store (new upload).
- `POST /properties/{property}/documents/{document}/replace` — resubmit against an existing row
  (fills a requested row, or replaces a rejected one). Resets `status` to `Pending`, clears
  `rejection_reason`/`verified_by`/`verified_at`, and deletes the superseded file from disk.
- `DELETE /properties/{property}/documents/{document}` — remove; refuse when the document is
  currently Verified (that is an admin's finding to reverse, not the landlord's).
- `GET .../{document}/preview` and `.../{document}/download` — `Gate::authorize('view', $document)`
  then `response()->file(...)` / `Storage::disk('local')->download(...)`, mirroring
  `VerificationController::preview/download`.

Every write asserts ownership the way the sibling controllers do
(`$property->landlord_id !== Auth::user()->user_id → 403`).

**`StorePropertyDocumentRequest`** — `document_type` in `PropertyDocument::TYPES`; `file` required,
`mimes:pdf,jpg,jpeg,png,webp`, `max:10240`; `document_number` nullable string max 100;
`expiry_date` nullable date, `after:today`.

**Storage:** `Storage::disk('local')->putFile("property_documents/{$property->property_id}", $file)`.
Private disk, never `public`, never Cloudinary.

**`App\Policies\PropertyDocumentPolicy@view`** — `$user->user_id === $document->property->landlord_id
|| $user->hasRole('Admin')`, registered the same way `LandlordVerificationPolicy` is.

Add a "Documents" entry to the property's More menu / units-page nav so the page is reachable, and
surface a count of rejected-or-requested documents on the landlord property show page as a nudge.

## Admin side

**`Admin\PropertyDocumentController`** under the existing admin route group:

- `POST /admin/properties/{property}/documents/{document}/verify` — sets Verified, `verified_by`,
  `verified_at`, clears `rejection_reason`.
- `POST /admin/properties/{property}/documents/{document}/reject` — `rejection_reason` **required**
  (`RejectPropertyDocumentRequest`, mirroring the existing `RejectVerificationRequest`).
- `POST /admin/properties/{property}/documents/request` — `document_type` required; inserts the
  placeholder row with `requested_by`.

All three follow the house pattern for consequential admin transitions
(`context/RULES.md:37-49`, as implemented in `Admin\VerificationController::approve/reject`):
`DB::transaction` + `lockForUpdate()` + `abort_if` on the current state + `AuditLog::record()` +
`Notification::notify()` to the landlord. Verify/reject notify with the document type and, on
rejection, the reason; request notifies asking for the named document.

**UI** on `resources/views/admin/catalogue/properties/show.blade.php`: a Documents section listing
each document with type, number, expiry, status badge, an inline preview (PDF vs image branch), and
verify/reject controls; plus a "Request a document" type picker. `PropertyCatalogueController::show`
(`:127`) adds `documents.verifier` to its eager loads.

## Public side

`properties/show.blade.php:230` — the "Verified Property" badge becomes
`@if($property->hasVerifiedDocuments())`. `PropertyController::show()` eager-loads `documents`
(status only — the file paths are never sent to a renter's browser, and no document route is
reachable without passing the policy). Nothing else about documents is exposed publicly.

## Files

**New:** migration; `app/Models/PropertyDocument.php`; `app/Policies/PropertyDocumentPolicy.php`;
`app/Http/Controllers/Landlord/PropertyDocumentController.php`;
`app/Http/Controllers/Admin/PropertyDocumentController.php`;
`app/Http/Requests/Landlord/StorePropertyDocumentRequest.php`;
`app/Http/Requests/Admin/RejectPropertyDocumentRequest.php`;
`resources/views/landlord/documents/index.blade.php`;
`resources/views/components/document-status-badge.blade.php`.

**Modified:** `app/Models/Property.php` (documents relation + `hasVerifiedDocuments()`),
`app/Providers/AppServiceProvider.php` (policy registration, if policies are registered explicitly),
`routes/web.php`, `app/Http/Controllers/Admin/PropertyCatalogueController.php`,
`app/Http/Controllers/PropertyController.php` (eager load for the badge),
`resources/views/admin/catalogue/properties/show.blade.php`,
`resources/views/properties/show.blade.php`,
`resources/views/landlord/properties/show.blade.php` (nav entry + nudge).

**Docs to update as part of the work** (per `CLAUDE.md`): `context/SCHEMA.md` — the new table, the
migration-log entry, and a note that this is the second private-disk upload path;
`context/ARCHITECTURE.md` — one entry covering both non-obvious calls (private local disk rather
than the Cloudinary path every other upload uses, and Expired derived rather than stored);
`plans/property-flow-verification-gaps.md` — mark item 3 done.

## Verification

Run the app (`npm run build`, `php artisan serve`). Accounts as before, password `password`.

**Privacy — the point of the whole feature.** Upload a document as the landlord, copy its
`file_path` from the DB, and confirm it is **not** reachable at any public URL
(`/storage/...`), only through the policy-gated preview route. Log in as a *different* landlord and
hit that preview route directly → 403. Log out entirely → redirected to login, never the file.
Confirm nothing in the rendered public property page HTML contains a document path.

**Landlord flow:** upload a PDF and an image → both listed as Pending with correct filenames and
previews. Admin rejects one with a reason → landlord sees the reason on the documents page and gets
a notification. Landlord replaces it → back to Pending, reason cleared, old file gone from
`storage/app/private`. Try deleting a Verified document → refused.

**Admin request flow:** request a Tax Declaration → it appears on the landlord's page as awaiting
upload (not as a rejected document), landlord notified. Landlord uploads against it → same row
fills, status Pending, admin sees it in the review list.

**Badge:** an approved property with no verified documents shows **no** "Verified Property" badge
(this is a visible change to existing listings). Verify one document → badge appears. Set that
document's `expiry_date` to yesterday in the DB → badge disappears again and the admin list shows
it as Expired, with no scheduled command having run — confirming derived expiry works.

**Regression:** landlord verification (the existing `LandlordVerification` flow) still uploads,
previews, and downloads correctly — both features now share the private disk.

**Automated:** the suite has 10 pre-existing failures on this branch (stock Breeze auth scaffolding,
stale routes); confirm that count is unchanged. `ProfileTest` uses `RefreshDatabase` against the
real dev database, so **re-seed after any full test run** (`php artisan migrate:fresh --seed`).
