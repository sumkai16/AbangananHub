# Move-in safety: handover photo + reminder emails

**Date:** 2026-11-10
**Status:** Part B built Nov 10 (photo required, Axcee chose required). Pending Axcee's manual check; delete this plan once verified.
**Update Nov 10:** the app will only run through ngrok during the defense, never deployed, so there is no real
mail service. **Part A (emails) is dropped for now**; only **Part B (handover photo)** is planned. A stays below
as a future option if the app is ever deployed.
**Applies to:** online (PayMongo) payments only. Direct payments have no escrow, so no clocks.

## Why

Today, if the landlord taps "Mark keys turned over" and the tenant never taps "Confirm move-in", the
payment is released to the landlord automatically after 7 days (`ProcessMoveInDeadlines::releaseExpiredConfirmations`).
Silence counts as consent. Two weak spots:

1. The tenant is only told in-app (notification + push). Someone who does not open the app misses the countdown.
2. The landlord's "keys handed over" claim leaves no evidence, so an admin reviewing a dispute has nothing to look at.

## Decision (recommended by Claude, agreed by Axcee on Nov 10)

Keep auto-release (otherwise a ghosting tenant traps the landlord's money forever). Add:

- **A. Email** the tenant when the countdown starts, and again with 1 day and 0 days left.
- **B. A required handover photo** when the landlord marks keys turned over, shown to the tenant and the admin.

Not doing: escalate-to-admin on silence (every forgotten click becomes admin work), longer window (7 days stays).

## A. Reminder emails (DEFERRED: needs a real mail service, not planned for the defense)

| When | Email | Today |
|---|---|---|
| Landlord marks keys turned over | "Your landlord says the keys were handed over. Confirm within 7 days or your payment is released." | in-app + push only |
| 1 day left | same idea, "1 day left" | in-app + push only |
| 0 days left (last day) | "Last day" | in-app + push only |
| 4 days left | not emailed (in-app only, as now) | unchanged |

- New `App\Mail\MoveInConfirmationMail` + `resources/views/emails/move-in-confirmation.blade.php` (same shape as `GcashChangedMail`).
- New `App\Services\MoveInNotices` with `countdownStarted(Reservation)` and `reminder(Reservation, int $daysLeft)`.
  The system message + notification + email for "countdown started" is copy-pasted today in
  `Landlord\ReservationController::markTurnedOver` and `Api\Landlord\ReservationController::markTurnedOver`;
  both move into `countdownStarted()` so email is added once, not twice.
- A mail failure is caught and logged (like the push in `Notification::notify`). It must never roll back the
  transaction or stop the nightly job.
- Sent after the DB commit, not inside the lock.
- **Prerequisite:** `.env` mail is Mailtrap (captures, never delivers). Real delivery needs a real SMTP/Resend/etc.
  provider in production. Check the VPS plan (`plans/hostinger-vps-deployment.md`) before relying on this.

## B. Handover photo

- **Migration:** `reservations` gets `keys_handover_photo_url` (string 500, nullable) and
  `keys_handover_photo_public_id` (string 255, nullable). Nullable so reservations already counting down are unaffected.
- **Model:** `Reservation::markKeysTurnedOver(string $photoUrl, string $publicId)` refuses without a photo, so the
  web, API and tests cannot disagree (same reason the other rules live in the model).
- **Controllers:** web `Landlord\ReservationController::markTurnedOver` and API `Api\Landlord\ReservationController::markTurnedOver`
  validate `handover_photo` (required, image, jpg/png/webp, max 5 MB), upload through `ImageUploader` to
  `abanganan/handover`, then call the model. Upload happens before the lock so a slow upload does not hold it.
- **Landlord UI:** the "Mark keys turned over" form in `landlord/contracts/partials/_details.blade.php`
  becomes `multipart/form-data` with a file input, hint "A photo of the keys or the handover. The tenant and
  admin can see it."
- **Tenant UI:** `tenant/agreements/show` (where Confirm move-in is) shows the photo with the date, so a false claim is visible to the person it affects.
- **Admin UI:** `admin/conversations/show.blade.php` ("Keys marked") links the photo.
- **`EscrowVerify` command** calls `markKeysTurnedOver()` three times: update to pass a dummy photo.

**Honest limit:** a photo does not prove the keys changed hands (it could be any photo). It is friction against a
casual false claim and evidence for an admin, not proof. Say so in the terms, not "verified".

## Files (expected)

Created: migration, `MoveInConfirmationMail`, `emails/move-in-confirmation.blade.php`, `MoveInNotices`.
Modified: `Reservation`, both `markTurnedOver` controllers, `ProcessMoveInDeadlines`, `_details.blade.php`,
tenant agreement view, admin conversation view, `EscrowVerify`, `tests/Feature/Payments/MoveInDeadlinesTest.php`
(+ a test for the photo rule), `context/CHANGELOG.md`, `ARCHITECTURE.md` (decision row), `SCHEMA.md`, `terms.blade.php` (one sentence).

## Manual test checklist (Axcee)

1. Landlord tries "Mark keys turned over" with no photo: refused, nothing changes.
2. With a photo: tenant gets in-app + email (Mailtrap), and sees the photo on the agreement page; admin sees it too.
3. Run `php artisan reservations:process-move-in-deadlines` with the deadline set to 1 day and 0 days out: one email each, none at 4 days, none twice on a same-day re-run.
4. Break the mail host on purpose: the command still finishes and the in-app reminder still arrives.
5. Tenant taps Confirm move-in: payment releases as before. Tenant taps "Keys never arrived": frozen for admin as before.
6. A reservation already counting down before this change (no photo) still releases normally.

## Open questions

- ~~Production email?~~ Answered: no, ngrok only. A is deferred.
- Required photo, or optional? Recommended: **required**.

## Defense notes (ngrok)

- The nightly job (`reservations:process-move-in-deadlines`) only runs if the scheduler runs. For the demo, run the
  command by hand to show the reminder, escalation and auto-release; do not rely on `schedule:work`.
- The photo upload uses Cloudinary like other uploads, so the Cloudinary keys must work on the demo machine.
