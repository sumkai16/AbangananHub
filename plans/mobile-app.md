# Mobile App (React Native + Expo) — Tenant & Landlord

**Date:** 2026-07-27
**Status:** Phases 0–3 (all server-side work) shipped and verified same day —
66 API routes total, up from 34. Phase 4 (the Expo client itself) and Phase 5
(doc sync — already partly done inline as each phase landed) remain.
**Scope decided:** Tenant + Landlord roles. Admin stays web-only.

## Context

`routes/api.php` ships 34 Sanctum endpoints across 15 controllers, written ahead of
the mobile app and — per `ARCHITECTURE.md` §2 — never consumed by anything. The web
app has 178 routes. That ratio is the plan: the API covers browsing, chat,
notifications, profile, tenant inquiries, reviews, and landlord *read* views, and
covers **none** of the money, escrow, ledger, verification, or write paths built
since it was scaffolded.

`PRD.md` §4 currently lists "Native mobile app" as explicitly out of scope,
deferred post-deployment. Building this reverses that decision and PRD.md §4 and
§3 must be updated to match, or the two documents will contradict each other the
way SCHEMA.md and the unit-columns migration did.

### Three blockers found while surveying (all pre-existing)

1. **`/broadcasting/auth` runs on `web` middleware only** — confirmed via
   `route:list`. It is registered by `withRouting(channels: ...)` in
   `bootstrap/app.php`, which defaults to the session guard. A Sanctum Bearer
   token cannot authorize `conversation.{id}` or `user.{id}`, so on mobile:
   real-time chat, the payment stepper, `ReservationStatusUpdated`, and
   `HandoverScheduleUpdated` would all fail silently. This is the single largest
   thing standing between the current API and a working app, and it is invisible
   until a device tries to subscribe.
2. **`EnsureAccountActive` is appended to the `web` stack only**
   (`bootstrap/app.php:16`). A suspended user's existing Sanctum token keeps
   working indefinitely. Harmless while nothing consumes the API; a real hole the
   day a binary ships.
3. **No rate limiting on any API route.** `ARCHITECTURE.md` §7 already files this
   as "non-issue until mobile app ships." This is that day. `/api/auth/login` and
   `/api/auth/{provider}/token` are unthrottled against a public binary.

---

## Design decisions

### 1. Version the API at `/api/v1` before anything consumes it

Move the existing routes under a `v1` prefix now, while the cost is one line in
`bootstrap/app.php` and zero clients break. Once an app-store binary is in users'
hands, the server can no longer be the only thing that ships — an unversioned API
means every breaking change is a forced-update prompt.

### 2. Introduce `app/Http/Resources` — the one new layer this plan adds

Today every API controller returns raw Eloquent models (`response()->json($properties)`),
so the wire format is whatever the columns happen to be. `Property::$fillable`
changes, and a shipped binary breaks.

`ARCHITECTURE.md` §3 says "no repository or service layer — the codebase isn't
large enough to justify the indirection," and that judgment stands for services.
Resources are a different argument: the indirection buys a **contract with a
release cycle you don't control**, which no amount of codebase size makes
unnecessary. The two existing service classes (`RentLedger`,
`OccupancyRateCalculator`) got in on the same footing — they earned their keep.

Scope it tightly: one Resource per model actually serialized (~10), no
`Resource::collection` wrappers around things that don't need them, and no
Resource for an endpoint returning a purpose-built array (dashboards, ledgers).

### 3. Payments: hosted checkout in a WebView, intercepted on return

Chosen over a native PayMongo SDK. The webhook + `PaymentObserver` + escrow logic
is the most consequential code in the app, has no automated test coverage, and a
second payment integration would need to stay in lockstep with it forever.

Flow:
1. App calls `POST /api/v1/tenant/reservations/{r}/pay` (or `.../tenancy/{r}/pay-rent`),
   which does exactly what the web controller does but **returns the
   `checkout_url` as JSON instead of `redirect()`ing** to it.
2. App opens that URL in an Expo WebView. GCash and QRPh both render as today —
   nothing about PayMongo's hosted page changes.
3. The app watches for navigation to `success_url` and **intercepts it rather than
   loading it**, closes the WebView, and calls a new reconcile endpoint.

The reconcile endpoint matters more than it looks. In production the webhook
settles the payment before the redirect. But PayMongo cannot reach `localhost`,
so **in local dev the webhook never fires and `success()`'s poll-PayMongo fallback
is the only path that works** — the exact gap that left `PaymentObserver`
un-dispatched before July 2026. Extract that reconcile logic out of
`Tenant\PaymentController::success()`/`rentSuccess()` into a shared private
method, and have both the web handler and the new API endpoint call it. One
implementation, two entry points — same rule the "Mark keys turned over" button
followed.

Deliberately **not** doing deep links (`abangananhub://`) for the return: it needs
a server-side change to `success_url`, universal-link association files, and it
fails differently on iOS and Android. WebView interception is entirely client-side.

### 4. Landlord verification stays in a WebView for v1

The verification wizard is face-api.js / TensorFlow.js loaded from a CDN, doing
4-step liveness against a `getUserMedia` stream, with a per-user calibrated pitch
baseline. Porting that to React Native means finding a native equivalent for the
tiny 68-point landmark model and re-tuning every threshold — a large, subtle job
on the anti-fraud path, for a flow each landlord completes **exactly once**.

Serve the existing web wizard in an authenticated WebView instead. Requires a
one-time token-to-session bridge endpoint so the WebView arrives logged in.

Accept and document the consequence: the wizard's layout already collapses to the
mobile step-bar below `lg` (`ARCHITECTURE.md` §6), so it renders fine — but it is
web UI inside a native shell and will look like it.

### 5. Maps are a straight swap, not a port

Leaflet is DOM-bound. `react-native-maps` with OSM tiles covers browse + property
detail. The OSRM/Overpass/Nominatim calls are plain HTTP and move over unchanged.
The tap-to-pin location picker (landlord property create) is the fiddly one —
`react-native-maps` has native drag handling, so it is a rewrite of
`location-picker.js`'s interaction, not its logic.

### 6. Unit photos: the live-capture rule gets *stronger*, not weaker

Web enforces "≥3 live camera captures" by injecting canvas blobs into a file input
via `DataTransfer` and tagging `photo_sources[]` — a client-side assertion the
server takes on faith (`PropertyUnitController:70`). Anyone with devtools can post
`photo_sources[]=camera` for an uploaded file.

Expo's camera returns images the app never round-trips through a picker, so mobile
is *less* forgeable than web. But it is still a client assertion, and the server
must keep validating the count as it does now. Do not add a server-side
"verification" that only appears to close the gap.

### 7. Push notifications hang off `Notification::notify()`

The notification pipeline already funnels every creation site through one factory
that writes the row **and** dispatches `NotificationCreated` — built precisely so
the broadcast cannot be forgotten. Expo push is a third side effect on the same
hook, so it inherits that guarantee. Needs an `expo_push_token` column on `users`
(nullable, one per user — same reasoning as `provider`/`provider_id` not getting a
pivot table).

---

## Phases

### Phase 0 — Unblock ✅ DONE (July 27 2026)

All five shipped and probe-verified. Broadcasting auth: own conversation 200,
another user's channel 403, bad token 401, suspended 403. Register returns a
token; login refuses a suspended account (422). One trap found on the way —
`EnsureAccountActive` had to be applied per-route rather than appended to the
`api` group, because group middleware runs before `auth:sanctum` resolves the
guard and would have passed everything through while looking correct.

<details><summary>Original Phase 0 checklist</summary>

1. Register a Sanctum-guarded broadcasting auth route at `/api/v1/broadcasting/auth`
   (`Broadcast::routes(['middleware' => ['auth:sanctum']])`). Leave the web one
   untouched. **Verify with a real device subscribing to `conversation.{id}`
   before building anything on top of it** — this is the assumption the whole
   real-time surface rests on.
2. Add `EnsureAccountActive` to the `api` middleware group in `bootstrap/app.php`.
3. Throttle: `throttle:6,1` on login/register/social-token, `throttle:60,1` on the
   authenticated group.
4. Move existing routes under `/api/v1`.
5. `Api\AuthController::register` returns a token alongside `{user, roles}`,
   matching `login`'s shape so the client has one code path.

</details>

**Still owed from Phase 0:** the broadcasting probe ran in-process, which proves
the guard and the channel rules. It does **not** prove a physical device can
reach Reverb over TLS — see Open Questions. Do that before Phase 4.

### Phase 1 — API Resources ✅ DONE (July 27 2026)

10 Resources built on an `ApiResource` base whose `attr()` helper preserves the
sparse-`select()` behavior of the raw `toArray()` it replaces (column not
selected → key absent, not `null`) — the one place a naive Resource retrofit
would have quietly changed the wire shape for every screen relying on a lean
payload. All 15 controllers + `SocialiteController::verifyToken` retrofitted.

Verified with an in-process probe that drives every touched endpoint through
the **real HTTP kernel** (`Kernel::handle()`), not by calling controller
methods directly — this distinction mattered: a bug below reproduced through
the kernel and did *not* reproduce via a direct `tinker` call to the same
model accessor.

**Found and fixed one real pre-existing bug on the way:** `Api\PropertyController::index()`
never eager-loaded `units`, so `Property::getMinRentalFeeAttribute()`'s
unconditional `$this->units` access lazy-loaded and `preventLazyLoading` threw
a 500 on every request — the single most important tenant endpoint (property
browse) was dead on arrival. The parallel **web** controller already
eager-loads `units`; the API one never had, because nothing had ever sent it a
real request before this probe. One-line fix, documented in `ARCHITECTURE.md`.

### Phase 2 — Tenant money & escrow endpoints ✅ DONE (July 27 2026)

All endpoints shipped: `Api\Tenant\PaymentController` (pay/reconcile ×2),
`Api\Tenant\AgreementController` (show/sign/confirmMoveIn/disputeMoveIn),
`Api\HandoverController` (propose/confirm, outside both role middlewares like
the web routes), `Api\Tenant\TenancyController` (rent ledger). The reconcile
logic was extracted from the web controller into
`Concerns\ReconcilesPaymongoCheckout` first, so the web return-handler and the
new API endpoints share one implementation rather than two.

Verified against the app's own `escrow:scenarios` fixtures (8 real escrow
states with a real tenant/landlord login) driven through the real HTTP
kernel: confirmed move-in released a Held payment to Occupied, a fresh
dispute succeeded and a second dispute on an already-disputed reservation
correctly 422'd, handover propose→confirm set `handover_confirmed_at`, the
rent ledger returned real derived periods for an Occupied tenancy, and two
invalid-state attempts (signing twice, paying before signing) returned 422
rather than 500. Fixtures cleaned up after (`escrow:scenarios --clean`).

<details><summary>Original Phase 2 checklist</summary>

The whole reason a tenant needs the app. Each mirrors an existing web controller
action; none should reimplement its logic.

- `POST /tenant/reservations/{r}/pay` → returns `checkout_url`
- `POST /tenant/reservations/{r}/payment/reconcile` (shared reconcile method)
- `GET  /tenant/reservations/{r}/agreement` + `POST .../agreement/sign`
- `POST /tenant/reservations/{r}/confirm-move-in`
- `POST /tenant/reservations/{r}/dispute-move-in`
- `POST /reservations/{r}/handover/propose` + `/confirm` (both roles)
- `GET  /tenant/tenancy/{r}` (rent ledger — serialize `RentLedger` output)
- `POST /tenant/tenancy/{r}/pay-rent` + reconcile

Both clocks and the handover state need to serialize into the ledger/reservation
payload — the mobile equivalent of `_move-in-clock.blade.php` renders from that,
and like the Blade partial it must pick the live clock off `isTurnoverClock()`
rather than deciding for itself which one is running.

</details>

**Note for Phase 4 (client):** the raw `move_in_deadline_at`/`handover_at`/etc.
timestamps are in the `ReservationResource` payload as of Phase 1, but nothing
server-side computes "which clock is currently running" — `isTurnoverClock()`
is a `Reservation` model method the Blade partial calls directly. The client
either needs that same decision made server-side (a small addition to
`ReservationResource`) or has to port the logic. Flagged, not yet decided.

### Phase 3 — Landlord write surface ✅ DONE (July 27 2026)

All shipped: property write CRUD (`PropertyWriteController`, separate from
the read-only `Api\Landlord\PropertyController` since the web split is the
same — the unnamespaced web `PropertyController` owns writes,
`Landlord\PropertyController` is index/show only), unit write CRUD with the
live-capture rule preserved unchanged (`UnitWriteController`), `markTurnedOver`
added to the existing `Landlord\ReservationController`, walk-in tenants
(reusing `StoreWalkInTenantRequest` verbatim — it's framework validation, not
view-coupled), tenancy show/end/remind, record-payment + collections index,
payouts (read-only), occupancy, analytics, reviews + reply.

**Scope cut, deliberately:** CSV export and the printable payment receipt are
desktop-document concerns not ported to the API — a receipt is something to
print or attach, not a phone screen. Occupancy and Analytics were ported in
full rather than trimmed, once inspection showed both controllers already
build plain-array chart/table data server-side (the web view just feeds it to
Chart.js) — low risk to port completely, better fidelity than a cut.

Verified against the app's `walkin:scenarios` fixtures (6 real walk-in/ledger
states) plus a real create→delete property cycle with an uploaded photo:
recording a payment against a behind-on-rent tenancy worked, ending an
already-Completed tenancy correctly 403'd at the Gate (matching web — the
policy denies before the controller body runs on both channels), reminding an
active platform tenant sent a notification, adding a walk-in onto a unit with
a live reservation correctly 409'd, and payouts/occupancy/analytics all
returned real portfolio data. Fixtures cleaned up after.

### Phase 3 — original checklist

Property + unit CRUD (incl. multipart photo upload with `photo_sources[]`),
reservation pipeline actions (the 4 PATCHes already exist — verify they cover
`turned-over`, which does not), walk-in tenants, record-payment, end-tenancy,
tenancy ledger, payouts read view, occupancy, analytics, reviews + reply.

### Phase 4 — Expo client

Navigation shell, auth (incl. native Google/Facebook SDK → the **already-built**
`POST /api/auth/{provider}/token`, which was written for exactly this and has
never been called), browse + map, property detail, favorites, chat with Echo over
Reverb, notifications + push, the two WebView flows (payments, verification),
landlord dashboard and forms.

### Phase 5 — Docs

`PRD.md` §3/§4 (mobile moves out of "Explicitly Out of Scope"),
`ARCHITECTURE.md` §2/§6/§7 (the API layer is consumed now; the rate-limiting and
broadcasting-auth tradeoffs are resolved; add the Resources-layer decision to the
Key Decisions Log with its reasoning).

---

## Open questions to settle before Phase 2

- **Does the September 2026 defense need this?** `PRD.md` §6 measures success as
  "all 13+ modules functional and demonstrable in panel defense," and every one of
  them is already demonstrable on web. Phases 0–1 are worth doing regardless
  (they fix real holes). Phases 2–4 are a second client for a product whose first
  client is finished — that is a scheduling decision, not a technical one.
- **Landlord verification WebView needs a token→session bridge.** No such endpoint
  exists. It hands a session cookie to a WebView holding a Bearer token, and
  wants a short expiry and single use.
- **Reverb over TLS from a device.** Works today on `127.0.0.1` for web dev; a
  physical device on the LAN cannot reach that host. Needs the VPS Reverb endpoint
  and its cert verified from a device early, not in Phase 4.

## Verification

Same posture as the rest of this codebase: manual, with fixtures. `escrow:scenarios`
and `walkin:scenarios` already build the states that are otherwise unreachable
(7–14 days wide, backdated) and print login credentials — those accounts are what
the mobile client should be pointed at, unchanged. `escrow:verify`'s 33 assertions
continue to cover the money paths server-side; the API endpoints in Phase 2 route
through the same methods it already asserts, so they inherit that coverage.
