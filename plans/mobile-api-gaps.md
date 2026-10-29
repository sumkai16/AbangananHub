# Server Gaps for the Mobile Client

**Date:** 2026-07-27
**Continues:** `plans/mobile-app.md` (Phases 0–3 shipped — 66 API routes).
**Client counterpart:** `../AbangananHubMobile/plans/mobile-scope.md`, which owns
the screen scope. This file owns only the **server-side** work that client needs.

## Context

The mobile client now exists as a sibling repo (`C:\Projects\AbangananHubMobile`,
Expo SDK 54 + expo-router + NativeWind) and already consumes `/api/v1` for auth,
browse, property detail, favorites and reservation inquiry. Scope for it is fixed
at **Tenant + Landlord, Admin web-only**, a demo-able companion app for the
September 2026 defense, with realtime chat, push, maps, and a KYC WebView all in.

That scope opens five gaps on this side. Each blocks a screen; none is optional.

---

## 1. Expo push notifications ✅ DONE (July 27 2026)

- Migration: `users.expo_push_token`, nullable string, one per user — same
  reasoning as `provider`/`provider_id` not getting a pivot table.
- `PATCH /api/v1/profile/push-token` on `Api\ProfileController`.
- `app/Services/ExpoPushNotifier.php` — HTTP POST to Expo's push endpoint,
  fire-and-forget, failures logged not thrown (a dead push must never fail the
  action that triggered it).
- Hook it as a **third side effect inside `Notification::notify()`**
  (`app/Models/Notification.php`), alongside the row write and the
  `NotificationCreated` dispatch. That factory exists precisely so a new
  notification channel can't be forgotten at one of the many creation sites;
  push inherits the guarantee by living there and nowhere else.

## 2. Token → session bridge for the KYC WebView ✅ DONE (July 27 2026)

The verification wizard is face-api.js/TensorFlow.js doing 4-step liveness
against `getUserMedia`, completed exactly once per landlord — it stays as web UI
in a WebView rather than being ported. The WebView holds a Bearer token and needs
a session.

- `POST /api/v1/auth/webview-ticket` → returns a single-use, ≤2 min signed URL.
- A web route redeems it, logs the user into the session guard, redirects to
  `landlord.verification.create`.
- Model on the existing signed-URL pattern in
  `VerificationController::viaemail`.
- **Single use is enforced server-side** (cache key deleted on redeem), not by
  URL expiry alone — an expiring URL is still replayable inside its window.

## 3. `active_clock` on `ReservationResource`

Phase 2 left this open. The raw `move_in_deadline_at` / `handover_at` /
`keys_turned_over_at` timestamps serialize, but the decision of *which clock is
running* lives in `Reservation::isTurnoverClock()`, a model method
`_move-in-clock.blade.php` calls directly. The client would have to re-derive it.

Serialize the answer instead:
`{ active_clock: 'turnover'|'confirmation'|null, deadline_at, days_remaining }`
so the Blade partial and the native screen read one source. Two clients silently
disagreeing about which escrow clock is live is exactly the drift
`ReconcilesPaymongoCheckout` was extracted to prevent.

## 4. Four missing endpoints ✅ DONE (July 27 2026)

Shipped: `POST /api/v1/reports`, `GET /api/v1/tenant/reports`,
`POST /api/v1/conversations/{c}/resolve`, `GET|PATCH /api/v1/landlord/profile/me`.
Verified through the real HTTP kernel with escrow-fixture accounts —
including the wrong-role 403s (tenant hitting `landlord/profile/me`,
self-report rejection). Fixing the landlord profile endpoint surfaced a real
pre-existing bug: `rental_businesses.contact_number`/`business_address`/
`business_name` were `NOT NULL` with no default while validated as
`nullable`, so a landlord's first profile save crashed whenever any field
was left blank — same shape as the `property_units` bug. Fixed by
`2026_07_27_000002_make_rental_businesses_columns_nullable`; see SCHEMA.md.

Original scope, for reference:

- `POST /api/v1/reports` + `GET /api/v1/tenant/reports` — complaints, a PRD
  module and demo-visible.
- `POST /api/v1/conversations/{conversation}/resolve`.
- `GET|PATCH /api/v1/landlord/profile/me` — only the public `show` exists today.

## 5. Reverb reachable from a physical device

Still owed from Phase 0: that probe ran in-process and proves the guard and the
channel rules, **not the transport**. A phone must hold a live WebSocket to the
Reverb host (LAN in dev, VPS + cert in production) before any chat screen is
built. If it fails, chat and the payment stepper degrade to polling and several
client screens change shape — which is why this is checked first, not last.

---

## Explicitly not added

CSV exports, the printable payment receipt, password reset / email verification
(web-only Breeze), `DELETE /profile`, and the entire Admin surface.

---

## Pre-existing blocker (breaks web today, not just mobile)

`context/ARCHITECTURE.md` documents it: `PropertyUnit::$fillable` declares
`unit_type`, `floor`, `security_deposit` and the controllers write them, but
migration `2026_07_18_022220_add_unit_type_floor_deposit_description_to_property_units`
is misnamed and only alters `availability_status` — nothing creates those
columns, so unit creation throws `SQLSTATE[42S22]`. Needs a **new** migration.
The mobile unit-create screen hits the same path via `UnitWriteController`, and
`../AbangananHubMobile/src/lib/properties.ts` already types those fields as
optional off `PropertyUnitResource`.

---

## Verification

Manual with fixtures, the posture the rest of this codebase uses.

- `escrow:scenarios` and `walkin:scenarios` build the otherwise unreachable
  backdated states and print login credentials — point the mobile client at
  those accounts unchanged. `escrow:verify`'s 33 assertions still cover the money
  paths server-side; the API routes through the same methods.
- Push: trigger any `Notification::notify()` site with a device token stored and
  confirm the push lands with the app killed; confirm a user with a null token
  and a failing Expo call both leave the originating action succeeding.
- Bridge: confirm a redeemed ticket 403s on second use, and that an expired one
  fails independently of that.
- `active_clock`: drive the `escrow:scenarios` states and confirm the JSON agrees
  with what `_move-in-clock.blade.php` renders for the same reservation.
- Reverb: subscribe to `conversation.{id}` from a physical device and see a
  web-sent message arrive live.

---

## Mobile catch-up backlog (noted Oct 20 2026, nothing done in the app yet)

Server changes the mobile client (`AbangananHubMobile`) has not picked up. None of them break a build, but several change what it receives.

| Area | Server change | What the app should do |
|---|---|---|
| Unit fields | `unit_type`, `bathroom_type`, `kitchen_type`, `pets_allowed`, `smoking_allowed`, `visitors_allowed`, `is_furnished` were dropped from `property_units` (Oct 18) | Remove the UI and payload keys for them |
| Push | Tokens live in `user_devices`; the server deletes a device on `DeviceNotRegistered` | Send `expo_push_token` on logout so a signed-out phone stops getting pushes |
| Chat | `messages.kind` (`user`, `system`, `inquiry_summary`, `concern`) and `concern_id` are in `MessageResource` | Use `kind` and `concern_id` to draw concern cards |
| Public identity | Listing and business endpoints return the business (`business_name`, `logo_url`) and `short_name` for reviewers, not personal names or contact | Move screens off the old `landlord.first_name`, `email`, `contact_number`, `profile_picture` keys |
| GCash | `gcash_*` is only in the owner's own profile response, never in nested users | Confirm nothing reads `user.gcash_*` from a nested user |
| Signing | Signing an agreement needs the five tenant-details fields (emergency contact and ID) or it returns 422 | Add the fields to the sign screen |
| Late fee (Oct 20) | Each ledger period carries `rent`, `late_fee`, `late_fee_waived`, and `expected` already includes the fee | Show "Rent + late fee" and "late fee waived" on the rent ledger and the payment screen |
| Late fee | Rent checkout line item is "Rent (Oct 2026) + late fee" and the amount includes the fee | Nothing to do, but the amount a tenant sees may be higher than the plain rent |
| Direct payments (phase 4, not built) | Will add a gate on API rent checkout when the landlord chose direct-only | Handle the refusal; claim endpoints come later |
