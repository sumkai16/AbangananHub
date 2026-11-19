# Approve a property together with its units

**Status: built Nov 6 2026, suite 1733 passed (15 new tests in `ApprovePropertyWithUnitsTest`), pending Axcee's manual pass.** Delete this plan after that pass (see CLAUDE.md, Planning).

**Manual pass progress (Nov 6 2026).** Claude ran the admin half in Chrome as the seeded admin against a throwaway listing (since deleted): steps **4, 5, 6, 7, 8 (activity and audit read from the database), 9, 10 and 13 passed**. **Still for Axcee:** steps **1 to 3** (landlord wizard submit with real photo uploads, the "Waiting with the listing" chip, the admin notification), **11** (a unit added later appears alone in the queue), **12** (reject a property, resubmit, approve) and **14** (chip fits at 375px). Those are covered by feature tests only. Do not delete this plan until they are done.

## Problem

A property and each of its units have their own `verification_status`. The landlord wizard creates both at once, so one submission needs 1 + N admin decisions (a 4-unit house = 5). The dashboard also counts the same submission in two queues ("Property verifications" and "Unit approvals").

## Decision

**Keep both statuses, remove the double work at first submission.**

Why both stay: adding Room 5 to an approved building must not send the building back to review, and editing a building's description must not send every unit back to review. Different edits, different re-reviews.

## Flow after this change

**Landlord submits** (`PropertyWizardController::submit`): Draft becomes Published, property and units stay Pending. Admins get a notification (new).

**Admin approves the property** (`ListingController::approve`), in one transaction:
1. Property goes Approved.
2. Every unit of it that is still `Pending` goes Approved. Units already `Rejected` stay Rejected.
3. Landlord gets one notification: "Your listing 'X' was approved, with 3 units, and is now visible to tenants."

**Admin rejects the property**: units are not touched (hidden anyway by `isLive()`). The landlord edits and resubmits; the next approval cascades again.

**Admin wants to hold one unit back**: reject that unit first on its own page, then approve the property.

**After approval** (unchanged): a unit added later, or a unit's rent, occupancy limit or photos edited, makes only that unit Pending. It appears in the unit queue and is decided alone. Editing the property's details or photos still sends the property back to Pending (see "Not in this plan").

## Changes

| # | Change | File |
|---|---|---|
| 1 | In the approve transaction, load the property's Pending units with `lockForUpdate`, update each one **by model, not mass update** (so `PropertyUnitObserver` still writes "Unit X approved" to the property activity log), and write one `AuditLog::record('unit.approve', ..., meta: ['via' => 'listing.approve'])` per unit | `app/Http/Controllers/Admin/ListingController.php` |
| 2 | Landlord notification text includes the unit count; no per-unit notifications on the cascade | same |
| 3 | New scope `PropertyUnit::scopeAwaitingOwnReview()`: `verification_status = Pending` AND its property is `Approved`. One definition, used by every queue count below | `app/Models/PropertyUnit.php` |
| 4 | Unit queue Pending tab, its tab count, the summary (oldest, no photo) use the scope. `counts['All']` becomes the real total (`PropertyUnit::count()`), so the All tab and its count agree | `app/Http/Controllers/Admin/PropertyUnitController.php` |
| 5 | Dashboard "Unit approvals" queue cell uses the scope | `app/Http/Controllers/Admin/DashboardController.php` (line ~122) |
| 6 | Admin property page approve text: "Approving this listing also approves its N pending units" (N from the loaded units; omitted when 0). Confirm the Units tab shows each unit's verification chip | `resources/views/admin/catalogue/properties/show.blade.php` |
| 7 | Notify every admin when a landlord submits a listing. Same pattern as `Payment.php` (`User::whereHas('roles', ... 'Admin')` then `Notification::notify`), linking to `admin.listings.approval` | `app/Http/Controllers/Landlord/PropertyWizardController.php` (`submit`) |
| 8 | Landlord unit chip: while the property itself is Pending, a Pending unit reads **Waiting with the listing** (`PropertyUnit::verificationLabel($propertyStatus)`) instead of "Pending". Built on `landlord/properties/show` (cards and table) and `landlord/units/all`. **Not** on `properties/show` (the public page 404s for a Pending property, so the old "Pending review — hidden from tenants" tag can only appear on an Approved one, where it is still right) and not on `landlord/units/edit` (no property in scope; would need a lazy load) | `app/Models/PropertyUnit.php`, `resources/views/landlord/properties/show.blade.php`, `resources/views/landlord/units/all.blade.php` |

The admin sidebar has no number on "Unit Approvals" today, so there is nothing to change there.

## Not in this plan

- No migration, no mobile API change (approval is admin web only), no change to `isLive()`, browse scopes or the re-queue rules for edits.
- **Editing an approved property's details sends the whole listing back to Pending**, hiding it from tenants over a typo fix. Worth its own plan about what counts as a material edit.

## Risks

- **Cascade approves a unit the admin never opened.** Mitigated by the Units tab on the property page, the "also approves N units" text, and reject-the-unit-first. Every unit needs 3+ photos to be created.
- **Observer skipped by accident.** If the cascade uses `->update()` on a query builder, the property activity log loses the "Unit X approved" lines. A test covers this.
- **Race with a concurrent unit approve.** The unit endpoint already locks the row and returns 409 if not Pending; the cascade locks the same rows, so one of them wins.
- **Hidden Pending units** (property Pending, unit Pending) vanish from `/admin/units` Pending tab. They are still on the All tab and on the property page.

## Tests (`tests/Feature/ApprovePropertyWithUnitsTest.php`)

1. Approving a property approves its Pending units.
2. A Rejected unit stays Rejected after the property is approved.
3. Rejecting a property leaves its units Pending.
4. The property activity log has a "Unit X approved" line per cascaded unit, and an audit row per unit.
5. The landlord gets exactly one notification, and it names the unit count.
6. A Pending unit on a Pending property is not in the unit queue Pending tab, the tab count or the dashboard count.
7. A Pending unit added later to an Approved property is in all three.
8. The All tab count equals the real unit total.
9. Submitting from the wizard notifies each admin once.
10. A second approve of the same property returns 409 and does not re-run the cascade.

Run the full suite afterwards; `AdminDesignPagesTest` and the unit approval tests may assert the old counts.

## Docs to update when built

`context/CHANGELOG.md` (dated entry), `context/ARCHITECTURE.md` Key Decisions Log (why both statuses stay and the cascade rule), `context/PRD.md` (Property/Unit Approval line).

## Manual test guide

Sign in as a landlord for steps 1 to 3, as an admin for 4 to 11. Use a throwaway property.

1. **Submit:** run the wizard to the end with 3 units and submit. On `/landlord/properties/{id}` and `/landlord/units`, each unit chip reads "Waiting with the listing" (not "Pending").
2. **Tenant view:** open the property as a tenant or signed out. It is not visible.
3. **Admin ping:** an admin has a new notification "listing submitted" that opens `/admin/listings`.
4. **Queue counts:** `/admin/units` Pending tab and the dashboard "Unit approvals" do **not** count the 3 new units. The All tab does.
5. **Property page:** `/admin/catalogue/properties/{id}` shows "Approving this listing also approves its 3 pending units" and the Units tab lists all three.
6. **Hold one back:** open one unit, reject it with a reason. Return to the property page: the text now says 2 pending units.
7. **Approve the property.** The 2 units are Approved, the rejected one is still Rejected.
8. **Activity log:** the property's Recent activity shows "Unit X approved" for the 2 units and the listing approval.
9. **Landlord:** exactly one new notification, naming 2 units. The rejected unit still shows its reason.
10. **Tenant view:** the property is browsable and shows the 2 approved units only.
11. **Later unit:** as the landlord, add a fourth unit. It is Pending and appears in the admin Pending tab and the dashboard count, and approving it alone does not touch the property.
12. **Reject path:** submit a second throwaway property, reject it with a reason. Its units stay Pending and are still absent from the Pending tab. Landlord edits and resubmits, admin approves, and the units cascade.
13. **Double click:** approve a property in two tabs. The second shows "already been reviewed" and nothing changes.
14. **Phone width:** the "Waiting with the listing" chips on `/landlord/properties/{id}` and `/landlord/units` fit at 375px (the unit name may cut off, the chip must not overflow).
