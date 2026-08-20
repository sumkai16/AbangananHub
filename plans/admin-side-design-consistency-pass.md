# Admin-side design consistency & robustness pass

## Context

This mirrors the tenant-side (`plans/tenant-side-design-consistency-pass.md`, commit 8c7bda9) and landlord-side (`plans/landlord-side-design-consistency-pass.md`, commit 97c15a0) passes, now scoped to the admin-facing views (dashboard, catalogue, listings/units approval, verifications, property documents, reservations, payments, payouts, reviews, reports, conversations, ratings, report-analytics, users, audit-logs, settings, profile). An audit against `context/DESIGN.md` and `context/RULES.md` — the same issue classes the prior two passes checked — turned up a substantial set of confirmed bugs and drift, all verified by direct file reads, plus a few candidates checked and ruled out.

`layouts/admin.blade.php` uses the same z-index scale as `layouts/landlord.blade.php`: mobile slim top bar `z-30` (line 513), mobile sidebar backdrop `z-40` (line 48), sidebar `z-50` (line 53) — confirmed by direct read.

### Confirmed bugs

1. **Six page-level dialogs sit at `z-50` instead of the documented `z-[200]`, and none are teleported to `body`.** DESIGN.md's z-index table is explicit: "Page-level modal (`x-modal`) | `z-[200]`." None of the following use `x-teleport="body"` either, which RULES.md's Modals & Overlays section calls out as a hazard regardless of the palette conversion ("Always `<template x-teleport="body">` modals that live inside cards"):
   - `admin/payouts/index.blade.php:127` — "Mark payout sent" modal, defined once at page root (not clipped, but still off-convention).
   - `admin/reviews/index.blade.php:179` — the hide/unhide confirm modal, defined **inside** each review's `@foreach` iteration (the review cards are among the hand-rolled-card files in finding 2 below), so N copies of the same modal markup exist on the page, one per review.
   - `admin/reservations/show.blade.php:352` (Force Cancel) and `:386` (Force Reject).
   - `admin/users/show.blade.php:70` — the delete-user confirm modal, nested inside the header's `x-data="{ deleteOpen: false }"` action row.
   - `admin/verifications/show.blade.php:391` — a `previewImage` document lightbox. This one is arguably miscategorized rather than just under-valued: DESIGN.md's table lists "Photo lightbox | `z-[999]`" as the correct layer for an image-preview-on-click pattern (matching `properties/show`'s lightbox), not the page-level-modal `z-[200]` tier.

   Because the admin sidebar is also `z-50`, these currently render above it only by DOM-order tie-breaking (each dialog's markup follows `<aside>` in source order) — the same "works by accident" situation DESIGN.md documents for the landlord `z-50` lightboxes before that pass fixed them, not a guarantee.

2. **Card-spec drift — the largest-volume finding, larger than either prior pass.** Only **3** admin files use `<x-card>` (`resources/views/components/card.blade.php`) at all; **23** admin files hand-roll the literal `bg-white border border-[#E2E8F0] rounded-2xl shadow-[0_1px_3px_rgba(15,23,42,0.06)]` string instead, confirmed via direct grep of the class string across `resources/views/admin`: `listings/approval`, `catalogue/properties/{index,show}`, `documents/{index,show}`, `payouts/index`, `dashboard`, `reports/{index,show}`, `verifications/{index,show}`, `payments/index`, `users/{index,create,edit,show}`, `units/index`, `reservations/{index,show}`, `catalogue/units/index`, `conversations/{index,show}`. DESIGN.md §7 is explicit that `<x-card>` should be used "rather than retyping the classes."

3. **Broken-thumbnail risk — same bug class the landlord pass found, five more instances on the admin side.** RULES.md's Storage section requires filtering `unit_media`/`property_media` to `media_type === 'Image'` before rendering into `<img>`, because both tables also store `Video` rows. Confirmed unfiltered `->media->first()` calls feeding directly into `<img>`:
   - `admin/reservations/show.blade.php:295` → rendered at `:298` (`<img src="{{ $photo->media_url }}" ...>`).
   - `admin/reservations/index.blade.php:164` → the `$photo` variable feeds the same-row thumbnail in the table.
   - `admin/catalogue/units/index.blade.php:200` → `$thumb` precomputed once per unit in a `@php` loop, then rendered in both the grid and table views.
   - `admin/catalogue/properties/index.blade.php:186` → same precompute-once-per-property pattern.
   - `admin/listings/approval.blade.php:76` → `optional($property->media->first())->media_url`.
   None of these use `firstWhere('media_type', 'Image')`, unlike the sibling convention already established elsewhere in the app (`tenancies/show.blade.php`, `walk-in/create.blade.php`, and now the landlord-pass fix to `landlord/tenants/index.blade.php`). Any property or unit whose first-uploaded media is a video shows a broken image on five separate admin surfaces.

4. **Off-palette raw Tailwind color utilities — confirms and localizes a gap DESIGN.md already names.** DESIGN.md §7 states: *"`agreements/show.blade.php` was converted from raw `amber-*`/`red-*`/`sky-*` Tailwind utilities to the token hexes... **Still pending:** `admin/reservations/index.blade.php` has not had the same pass yet."* Confirmed by direct read — four raw-utility instances, all in this one file, none elsewhere in `resources/views/admin` (verified via the exact §11b migration-audit grep, which is supposed to return zero and instead surfaced only this file):
   - `:129` — `text-gray-600` on the "Needs review" tab's inactive state.
   - `:132` — `bg-red-100`/`text-red-800` on the disputed-count pill.
   - `:219` — `text-red-700` on the dispute-reason line.
   - `:221` — `text-gray-500` on the disputed-at timestamp.
   The correct token equivalents are already established elsewhere in this exact file (`text-[#94A3B8]` for muted/inactive text, `text-[#156F8C]` for the active "Needs review" state per DESIGN.md §7's "Needs review tab" entry, `#EF4444`/`#DC2626` family for the red/error tier).

5. **Missing inline `@error` validation** (RULES.md: "validation errors stay inline"), confirmed in two places where the controller validates but the view shows nothing per-field:
   - `admin/units/show.blade.php:150` — the reject-unit `<textarea name="rejection_reason">` has **zero** `@error` blocks, even though `Admin\PropertyUnitController::reject()` (`app/Http/Controllers/Admin/PropertyUnitController.php:91`) validates it as `required|string|max:500`. The sibling document-rejection flow does this correctly: `admin/documents/show.blade.php:134` has `@error('rejection_reason')` right after its equivalent textarea, using `RejectPropertyDocumentRequest`'s identical `required` rule as the reference pattern.
   - `admin/reports/show.blade.php:78-98` — the resolve-report form has two required fields, `admin_notes` (`<textarea>` at `:81`) and `action_taken` (`<x-styled-select>` at `:98`), both validated as `required` by `Admin\ReportController::resolve()` (`app/Http/Controllers/Admin/ReportController.php:52-55`), with **no `@error` block for either field** anywhere in the form.

### Checked and ruled out (false positives / already correct)

- **Status tab bars** — every admin index page audited (`verifications/index`, `listings/approval`, `units/index`, `payments/index`, `reports/index`, `documents/index`, `reservations/index`) already uses the documented underline style (`border-b-2 border-[#2AA7A1]` active / `border-transparent text-[#94A3B8]` inactive) per ARCHITECTURE.md's July 26 2026 decision. No pill/segmented tabs found anywhere in `resources/views/admin`. Not a bug.
- **Search/filter-bar card borders** — checked all four admin pages with a `method="GET"` filter form (`catalogue/properties/index:90`, `catalogue/units/index:104`, `users/index:33`, `audit-logs/index:54`): all four already carry `bg-white rounded-2xl ... shadow-[...]` with **no** `border` class, matching the July 26 2026 "filter-bar cards dropped their outer border" decision. Not a regression.
- **The "Needs review" tab's suppression logic** (`admin/reservations/index.blade.php:111,115,122`) — `$disputedActive` correctly suppresses the active state on every status tab while the dispute filter is on, and the count pill is correctly guarded by `@if ($disputedCount > 0)` (an empty queue shows no badge, not a zero), matching DESIGN.md §7's "Needs review tab" rule exactly. Only the *color tokens* on this tab are wrong (finding 4) — the logic itself is not a bug.
- **Off-scale opacity modifiers** — ran DESIGN.md §11b's fourth grep (`\[#hex\]/N` opacity suffix) across all of `resources/views/admin`; the only match is `/20`, which is on Tailwind's default scale. The specific `bg-[#EF4444]/8` bug §11b documents as having existed "one bubble in `admin/conversations/show`" is confirmed already fixed — no `/8` or any other off-scale value remains anywhere in admin views.
- **Malformed classes / `1A1A2E` / `EEF2F5` / `backdrop-blur-xl` / `bg-white/70`** — ran all four remaining §11b audit greps against `resources/views/admin`; all returned zero matches. This surface is otherwise clean on those specific defect classes.
- **`admin.listings.approval`'s reject action collects no reason at all** (`Admin\ListingController::reject()`, `app/Http/Controllers/Admin/ListingController.php:88-115`) — noted but *not* filed as a "missing `@error`" bug, since there is no validation to be missing: the controller accepts no input and the landlord is notified with a generic "Review the details and resubmit" message. This is a feature-parity gap against the sibling Unit-rejection and Document-rejection flows (both of which do collect and surface a reason), not a rule violation — flagging for awareness, not fixing in this pass.

## Approach

1. **Fix the z-index/teleport bugs** — bump the five page-level dialogs (`payouts/index`, `reviews/index`, `reservations/show` ×2, `users/show`) to `z-[200]` and wrap each in `<template x-teleport="body">`, matching the landlord-pass fix. Reclassify `verifications/show`'s document-preview lightbox to `z-[999]` (the "Photo lightbox" tier) instead, matching `properties/show`'s existing lightbox — do not lump it in with the page-level-modal fix.

2. **Card-spec drift** — replace the hand-rolled card divs with `<x-card>` (using the `flush` prop where a table/row-list supplies its own padding) across the ~23 identified files, same mechanical approach used on both prior passes: keep any extra layout classes (padding overrides, flex/grid direction, margins, `overflow-hidden`) as additional attributes merged onto the component.

3. **Fix the five broken-thumbnail sites** — change each `->media->first()` / `->media()->first()` to `->media->firstWhere('media_type', 'Image')`, matching the established sibling convention (`tenancies/show.blade.php`, `walk-in/create.blade.php`, the already-fixed `landlord/tenants/index.blade.php`).

4. **Re-token `admin/reservations/index.blade.php`'s four raw-utility instances** — `text-gray-600` → `text-[#94A3B8]`, `bg-red-100`/`text-red-800` → the established `#EF4444`/`#DC2626` pill tokens used elsewhere in the app for red status pills, `text-red-700` → `text-[#DC2626]`, `text-gray-500` → `text-[#94A3B8]`. This closes the specific gap DESIGN.md §7 already names as outstanding.

5. **Add missing `@error` blocks** — `admin/units/show.blade.php` (`rejection_reason`) and `admin/reports/show.blade.php` (`admin_notes`, `action_taken`), following the exact pattern already used correctly in `admin/documents/show.blade.php:134-136`.

## Verification

- `php artisan view:cache` after each batch of Blade edits to catch syntax errors immediately, same approach used successfully on both prior passes.
- `php -l` on any touched PHP controller files (none should need changes for this pass — all findings are view-layer).
- Re-run the DESIGN.md §11b migration-audit greps against `resources/views/admin` after step 4 to confirm the raw-Tailwind-color grep returns zero, closing the gap the file itself names as pending.
- HTTP smoke tests via `curl` against the local loopback server (`127.0.0.1:8000`) for every touched route, confirming no 500s.
- Manually trigger each of the five fixed modals (payouts mark-paid-out, review hide/unhide, reservation force-cancel/force-reject, user delete) and confirm each renders above the sidebar and centered, not just by DOM-order luck.
- Submit the units-reject and reports-resolve forms with the required field blank and confirm the error now renders inline next to the field instead of only via a redirect with no visible feedback.
