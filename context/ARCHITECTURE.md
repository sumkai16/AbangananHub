# ARCHITECTURE.md — System Architecture

## 1. Stack Summary
- Frontend: Blade templates, Tailwind CSS v3, Alpine.js, vanilla JS fetch()
- Backend: PHP 8.2, Laravel 12.6, Eloquent ORM
- Database: MySQL
- Auth: Laravel Breeze (session-based web), Laravel Sanctum v4.3.2 (API token auth for mobile layer)
- Real-time: Laravel Reverb (WebSockets)
- Build: Vite
- Hosting: Hostinger VPS, Ubuntu 22.04, Nginx, Supervisor (Reverb daemon), SSL

## 2. High-Level Overview
AbangananHub is a server-rendered Laravel MVC application. Blade templates render HTML server-side; Alpine.js handles client-side interactivity (dropdowns, modals, toggles, form wizards). Real-time chat uses Laravel Reverb over WebSockets with Laravel Echo on the client. PayMongo handles payment processing via server-side checkout session creation. Cloudinary (v3 SDK) stores public media; local disk stores sensitive identity documents. A scaffolded Sanctum API layer (16 controllers, 30+ endpoints) exists for future React Native mobile clients but is not yet consumed.

## 3. Design Pattern
**MVC (Laravel default)** with the following refinements:
- **Form Request classes** for validation (e.g. `StoreVerificationRequest`, `RejectVerificationRequest`) — keeps controllers thin.
- **Policies** for authorization (e.g. `LandlordVerificationPolicy`) — owner-or-admin checks live here, not in controllers.
- **Middleware** for role gating (`EnsureTenant`, `EnsureLandlord`, `EnsureAdmin`) — registered as aliases in `bootstrap/app.php`.
- **No repository or service layer** — the codebase isn't large enough to justify the indirection. Business logic lives in controllers; if a controller gets fat, it gets a Form Request or a policy extracted, not a service class.
- **Blade components** for reusable UI (`x-stat-card`, `x-section-header`, `x-empty-state`, `x-reservation-card`, `x-verification-status-badge`).
- **Model observers** for cross-cutting side effects: `PropertyUnitObserver` (registered in `AppServiceProvider::boot`) writes an `occupancy_activities` row whenever a unit's `availability_status` changes — one hook covers every transition path (manual edit, reservation approve/cancel, move-in).
- **Scheduled command**: `occupancy:snapshot` (in `app/Console/Commands`, scheduled in `routes/console.php` daily 23:55) records per-landlord occupancy history. Needs cron/Supervisor on the VPS; locally run `php artisan schedule:work`. The project had no scheduler before this — it's the first scheduled task.

## 4. Folder/Module Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/           # Admin-namespaced controllers
│   │   │   ├── VerificationController.php
│   │   │   ├── PropertyController.php
│   │   │   ├── ComplaintController.php
│   │   │   ├── ReportController.php
│   │   │   └── ...
│   │   ├── Landlord/        # Landlord-namespaced controllers
│   │   │   ├── PropertyController.php
│   │   │   ├── PropertyUnitController.php
│   │   │   ├── OccupancyController.php
│   │   │   └── ...
│   │   ├── Api/             # Sanctum API controllers (16 total)
│   │   │   └── ...
│   │   ├── VerificationController.php  # Tenant-facing (no namespace — chicken-and-egg)
│   │   ├── PropertyController.php      # Public browse/show
│   │   ├── FavoriteController.php
│   │   ├── ReservationController.php
│   │   ├── MessageController.php
│   │   ├── ReviewController.php
│   │   ├── NotificationController.php
│   │   └── ...
│   ├── Middleware/
│   │   ├── EnsureTenant.php
│   │   ├── EnsureLandlord.php
│   │   └── EnsureAdmin.php
│   └── Requests/            # Form Request validation
├── Models/                  # 13 Eloquent models, all with explicit $primaryKey
├── Policies/
├── Events/
│   └── MessageSent.php      # ShouldBroadcastNow, broadcastAs() required
├── Mail/                    # Branded Mailables (VerificationLinkMail, PasswordResetMail)
└── ...

resources/views/
├── layouts/
│   ├── app.blade.php        # Base layout (flat #F7FCFC bg, Poppins/Inter, sticky white header)
│   ├── admin.blade.php      # Admin shell (sidebar)
│   ├── landlord.blade.php   # Landlord shell (sidebar)
│   └── guest.blade.php      # Split-panel auth shell — reached via <x-guest-layout>
├── components/              # Blade components (x-stat-card, x-confirm-modal, etc.)
├── partials/
│   └── flash-modal.blade.php  # Single source of truth for flash → modal
├── emails/                  # Branded HTML email templates (table-based, inline styles)
├── tenant/
├── landlord/
├── admin/
├── properties/
├── auth/
└── ...

public/js/                   # Extracted reusable JS (not inlined in Blade)
```

## 5. Data Flow
### Typical Web Request
Request → Nginx → Laravel Router → Middleware (auth + role) → Controller → Eloquent Model → MySQL → Controller (prepares data) → Blade View → HTML Response

### Real-time Chat
Client JS (fetch POST) → MessageController@store → DB insert → `MessageSent` event (ShouldBroadcastNow) → Laravel Reverb → WebSocket → Laravel Echo (client) → DOM update
- `X-Socket-ID` header sent on fetch to enable `toOthers()` exclusion
- Echo listener uses `.listen('.EventName')` (leading dot) for bare event names

### Payment Flow
Tenant initiates → Controller creates PayMongo Checkout Session (server-side) → Redirect to PayMongo → Webhook callback → Payment record created → Reservation status updated

### Post-Auth Destination
Every auth entry point — login, registration, both `VerifyEmailController` paths, the verification prompt, verification resend, and password confirm — resolves its redirect through `User::homeRoute()`: Admin → `admin.dashboard`, Landlord → `landlord.dashboard`, everyone else → `properties.index`. New accounts carry no role until Tenant is granted on first browse, so they fall through to the listings as well.
**There is no tenant dashboard and no bare `dashboard` route** — only `admin.dashboard` and `landlord.dashboard` exist. Adding a role means adding its home to `homeRoute()`; every auth entry point then follows automatically.

### Password Reset
Login modal "Forgot Password?" → in-modal email form → AJAX POST to `password.email` → `PasswordResetLinkController@store` (returns JSON when `wantsJson()`, otherwise redirects with a flash) → modal swaps to a "check your email" view → Laravel writes the token to `password_reset_tokens` → `User::sendPasswordResetNotification()` is overridden to send `PasswordResetMail` (a branded Mailable) instead of the framework's default notification → emailed link opens the full-page `auth/reset-password` view (a fresh page load from an email, so it can't be a modal) → `password.store` → redirect to login with a success flash.
Throttling is the framework's, not custom: `config/auth.php` → `passwords.users.throttle` = 60s between requests per email, `expire` = 60 minutes.

### File Upload (Cloudinary)
Form submit (multipart) → Controller → `cloudinary()->uploadApi()->upload($file)` (v3 SDK) → Cloudinary CDN URL stored in DB (`media_url` column)

### Unit Photos — Live Capture
Unit create requires photos: **≥3 must be live camera captures** (anti-fraud — proves the unit is real/current), uploads are extras (≤10 total). Client-side `getUserMedia` draws frames to a canvas → `toBlob` → injected into an aggregated `photos[]` file input via `DataTransfer`, kept index-aligned with `photo_sources[]` (camera|upload) and `photo_captions[]` hidden inputs. Controller validates the alignment and the live-count, then persists `source` + `caption` per `unit_media` row. Requires HTTPS or localhost (browser constraint).

## 6. Key Decisions Log

| Decision | Reason | Date |
|---|---|---|
| Session-based auth (Breeze) over Sanctum SPA | Simpler for server-rendered Blade; Sanctum reserved for API layer | Early 2026 |
| Reverb over Pusher | Free, self-hosted, no third-party dependency for capstone | Early 2026 |
| Text search over Haversine radius | Cebu-scoped platform doesn't need geo-radius — address text match is sufficient | Mid 2026 |
| No service/repository layer | Codebase not large enough to justify indirection; Form Requests + Policies keep controllers manageable | Mid 2026 |
| Conversations table kept (not sender/receiver direct) | Already built and tested; rewrite would regress real-time chat | Mid 2026 |
| Cloudinary over S3 | Better free tier, simpler SDK for image transforms, appropriate for capstone scale | Mid 2026 |
| Local disk for gov't IDs, Cloudinary for public media | Identity docs must not be publicly accessible; policy-gated download route handles access | Mid 2026 |
| Property → Units (multi-unit model adopted) | PM requirement for bedspace/room granularity; partial adoption without Business entity layer | Mid 2026 |
| PayMongo sandbox over direct GCash API | Broader payment method coverage through single integration; easier sandbox signup for students | Mid 2026 |
| Ocean Teal palette replacing Gold Black | Stronger brand identity, better WCAG compliance, cohesive with glassmorphism spec | July 2026 |
| Glassmorphism UI spec | Distinctive visual identity for defense; bg-white/70 backdrop-blur-xl consistent across ~55 views | July 2026 |
| **Glassmorphism retired app-wide for flat cards** | The analyst prototype's flat, structured look read as clearer and more trustworthy than frosted panels; translucency was fighting the soft shadows and muddying dense data views. One `<x-card>` component now owns the card spec. Teal mesh gradient dropped for a flat `#F7FCFC` background. Palette unchanged — prototype's blue/purple accents map onto the locked Ocean Teal set, color reserved for status | July 2026 |
| face-api.js for liveness detection | Runs client-side (no server GPU needed), works on Chrome/Firefox, sufficient for capstone | Mid 2026 |
| Live camera capture required for unit photos | Anti-fraud — proves the unit is real and current; ≥3 live captures, uploads as extras | July 2026 |
| Two-column unit create/edit (form + sticky-preview rail) | Fills wasted horizontal space; live preview + amenities in the rail | July 2026 |
| Occupancy history via nightly snapshots + observer-based activity log | True daily trend needs stored history; observer catches every status-change path without touching each controller | July 2026 |
| CSV export on landlord list pages (Units, Reservations, Tenants) | Reuses the existing `response()->streamDownload()` + `fputcsv` + `chunk(200)` pattern from `OccupancyController::export()` — streamed so a large portfolio never builds the file in memory. Each controller extracts its filter logic into a private `filteredQuery(Request)` shared by `index()` and `export()`, so a CSV always matches what the page is showing and the two can't drift apart. Buttons pass `request()->only(...)` to carry the active filters | July 2026 |
| Landlord list pages: analyst-prototype data tables + grid/table toggle | Reservations rebuilt as filterable table (search/property/date-range in controller); Units/Properties/Reservations each got a client-side view toggle persisted in localStorage; both views render server-side and swap via x-show, per-record derived data precomputed once per page | July 2026 |
| Public property page restyled to analyst prototype (flat cards) | Three-column layout, flat white cards — was an exception at the time, became the app-wide reference once glassmorphism was retired (DESIGN.md §6e); Inquiry/Reserve toggle is presentational (single reservations.store flow) | July 2026 |
| Mobile inquiry: sticky bar + two-step teleported modal | Desktop sidebar is hidden <lg, so phones had no inquiry path; modal shares Alpine selectedUnit state with sidebar, posts to same reservations.store | July 2026 |
| Inbox chat panel: pinned inquiry summary card | Unit photo/price + Inquiry Details (move in/out, message) pinned atop thread for both parties; landlord header shows tenant email/phone + New Inquiry badge | July 2026 |
| Password reset request lives in the auth modal; the reset step is a full page | Matches the existing AJAX login/register modal flow; the reset form arrives from an email link (fresh page load) so it can't be a modal | July 2026 |
| Branded Mailable over Laravel's default reset notification | Framework default is unstyled and off-brand; reuses the `VerificationLinkMail` + `resources/views/emails/` pattern already in the codebase | July 2026 |
| All post-redirect flash messages render through one global modal | The three app layouts each auto-fired a modal **and** 11 page views rendered their own inline banner, so a single flash could show twice; `partials/flash-modal.blade.php` is now the only place session flashes are read | July 2026 |
| Controllers flash sentences, not status slugs | Breeze's `'password-updated'` / `'profile-updated'` slugs leaked as literal text once flashes were routed through a generic modal | July 2026 |
| WCAG 2.2 AA accessibility pass | Accessibility was in the RULES.md checklist but never enforced; 76 inputs had a visible label with no `for`/`id` pairing and were nameless to screen readers | July 2026 |

| Tenant dashboard removed — tenants land on `/properties` | It was already orphaned (no link to it in any live layout's nav), every widget duplicated a dedicated page that was in the header anyway, and its `$recentActivity` section was a permanent empty placeholder. Tenants browse; they don't operate a control panel. Landlords and admins keep their dashboards | July 2026 |
| Notification modal raised to `z-[9998]` | At `z-50` it rendered *below* the public layout's `z-[100]` sticky header — the header stayed bright and clickable over the dimmed backdrop — and tied with the admin/landlord sidebar, winning only on DOM order. A blocking dialog must outrank passive UI (header, slideout, lightbox, toasts); only the auth modal (`z-[9999]`) sits above it | July 2026 |
| Notifications go through one factory, `Notification::notify()` | The pipeline was effectively dead: `Notification::create` existed in exactly **three** places, all `ReviewController`, so a new review was the only event in the entire app that produced a notification — an active landlord with 14 properties still had an empty bell. `ConversationController::show` was already marking `type = 'message'` notifications read, proving message notifications were designed and never built. Every creation site now calls `Notification::notify()`, which writes the row **and** dispatches `NotificationCreated`, so the broadcast can't be forgotten — the same failure that left `postSystemMessage()` writing rows nobody saw. Rental-lifecycle notifications hang off `ReservationObserver` (created + status transitions), so one hook covers both parties across all seven transition methods | July 2026 |
| Landlord "Reports" split into **Analytics** (Insights) and **My Complaints** (Account) | One word meant two unrelated things. `landlord.reports.index` was a *complaint tracker* — reports the landlord filed — while the prototype's Reports/Analytics nav means rental-business performance. Now: `landlord.analytics.*` is the performance page, `landlord.complaints.*` is the filed-report history (route + view directory renamed). Reports filed *against* a user remain admin-only; `Landlord\ReportController` already scoped to `reporter_id`, so there was never a leak there. Revenue is sourced from `payments` (`Held` + `Released`, dated by `paid_at`) joined through reservations — previously surfaced nowhere for landlords. Charts reuse the established Chart.js 4.4.0 CDN pattern (5 other views) rather than adding a dependency | July 2026 |
| "Paid (held)" is a **derived** stepper stage, not a `rental_status` value | The fact already lives in `payments.status = 'Held'`. Mirroring it into `rental_status` would give one fact two homes that can disagree — if the PayMongo webhook writes the payment row but a reservation write fails, you'd have a paid reservation that renders as unpaid, on the money path. `rental_status` is also enumerated across 20 files (count arrays, `validStatuses`, filter tabs, analytics buckets, `PropertyUnit`), and a missed one silently drops reservations out of a tab. The stepper renders 6 nodes from `rental_status + payment state` instead: `Rental Agreement Signed` maps to stage 4 ("Paid") when a Held/Released payment exists, else stage 3. **The tradeoff accepted:** landlords can't filter their reservation list by "awaiting move-in" without a payments join | July 2026 |
| Tenant move-in confirmation releases the escrow; admin release kept as override | `confirmMoveIn()` flipped the reservation to Occupied and left the payment on `Held` **forever** — money only reached the landlord if an admin noticed and clicked Release on the admin Payments screen. The release now happens in the same locked transaction as the status flip, with `released_by = null` marking a platform release vs an admin id for a manual one. `Admin\PaymentController::release` stays for disputes and stuck webhooks | July 2026 |
| Agreement page: print-to-PDF via Tailwind `print:` variants, not a `<style>` block | A rental agreement the tenant cannot keep a copy of is a gap for a document they're asked to sign. RULES.md bans inline `<style>`, so the print sheet is built from `print:hidden` on chrome/actions and `print:border-none print:shadow-none` on the card, plus a footer carrying the agreement ref that only appears in print. Also added an `AGR-YYYY-NNNNN` reference and a signature block surfacing `agreed_at` / `agreed_ip` / `landlord_tc_accepted_at` — all three were already captured in the DB and never displayed | July 2026 |
| `PaymentStatusUpdated` broadcast so the agreement page's "Payment Processing" state is honest | The page told the tenant "This page will update once the payment is verified" while nothing polled or listened — the PayMongo webhook lands out-of-band, so the spinner sat there forever. The webhook now dispatches on `user.{tenant}`/`user.{landlord}` and the page reloads. A full reload is deliberate: the page has five mutually exclusive server-rendered states and re-rendering one in JS would duplicate the Blade | July 2026 |
| Rental-status changes broadcast a *signal*, not markup; each client refetches its own render | The chat panel renders differently per role (landlord sees "Accept & negotiate", tenant sees "Waiting for landlord"), so there is no single HTML payload correct for both parties — and reimplementing the role×status matrix in JS would have duplicated ~150 lines of Blade branching that would immediately drift. `ReservationStatusUpdated` carries only ids + status; the client re-fetches `GET /conversations/{id}` (which already returned the per-viewer partial for the inbox's AJAX loading) and swaps it in, preserving the draft message and scroll position | July 2026 |
| `ReservationObserver` broadcasts the transition, not the seven transition methods | `Reservation` has seven methods that change `rental_status` (advanceToNegotiation, advanceToPendingAgreement, signAgreement, markOccupied, confirmMoveIn, reject, cancel), all ending in `save()`. Hooking `updated` + `wasChanged('rental_status')` means no transition — present or future — can change status without notifying the other party. Same pattern as `PropertyUnitObserver` | July 2026 |
| Inquiry form validation split: field errors inline, state rejections to the flash modal | `properties/show` had **no `$errors` block at all**, so all five of `Tenant\ReservationController::store`'s `withErrors()` calls redirected back and rendered nothing — the form silently did nothing. Date rules moved to `StoreReservationRequest`; `target_move_in_date` is now `required` (it was `nullable`, which made `after:target_move_in_date` on move-out skip entirely — Laravel drops the rule when the referenced field is null, which is how a move-out *before* move-in shipped). Operational rejections (unit taken, duplicate inquiry, own listing) now flash `error`/`warning` per RULES.md; only date/message errors render inline | July 2026 |
| `User::homeRoute()` is the single post-auth destination | Login resolved the role inline while registration and email-verification hardcoded `route('dashboard')`, so the same tenant got a different home depending on whether they registered or logged in; all 8 redirect sites now call one method | July 2026 |

**Bugs this surfaced (all pre-existing, all fixed July 2026):** `<x-guest-layout>` was referenced by four auth views but the component never existed (the layout lived at `layouts/guest.blade.php` with no alias), so `auth/login`, `auth/register`, `auth/forgot-password` and `auth/reset-password` all 500'd when visited directly. The auth modal carried `transition-all scale-100 opacity-100 duration-300` as static classes that never changed, so its animation never fired. `components/login-modal`, `register-modal` and `success-modal` were never rendered anywhere, and three "Login" buttons on `properties/show` dispatched `open-modal` at that dead component — clicking them did nothing. The tenant-dashboard removal turned up more of the same: `layouts/navigation.blade.php` (Breeze scaffolding) was included by nothing while holding three links, its `x-nav-link`/`x-responsive-nav-link` components were used only by it, a second unrouted `App\Http\Controllers\DashboardController` sat alongside `Tenant\DashboardController` rendering the same view, and `landlord/verification/show` linked a pre-role applicant at the tenant dashboard. All deleted or repointed.

**Another orphan, same class (found and deleted July 2026):** `resources/views/conversations/show.blade.php` — 459 lines, never rendered. `ConversationController::show()` returns the `chat-panel` partial for AJAX and *redirects to `conversations.index`* otherwise, so the view was unreachable while the `conversations.show` **route** stayed heavily linked (5+ views) and working. It was a full second implementation of the chat panel — its own header, its own vertical stepper, its own copy of the role×status action matrix, its own inline Echo wiring — quietly diverging from the live partial. Note the tell: a live *route* is not evidence of a live *view*.

**Recurring lesson:** grep for *every* reference before deleting a route or view, and re-grep after editing. Several of these were only found by baselining references first, and one `replace_all` edit silently missed a second occurrence that differed only by indentation.

## 7. Known Tradeoffs
- **No queue worker** — `ShouldBroadcastNow` is synchronous. Acceptable for capstone load; would need a queue for production scale.
- **No caching layer** — No Redis/Memcached. DB queries are fast enough at capstone data volumes.
- **No rate limiting on API** — Sanctum API layer is scaffolded but not rate-limited. Non-issue until mobile app ships.
- **Single admin** — No admin role hierarchy or permission granularity. One admin account handles everything.
- **No automated testing** — Manual testing only. Time constraint; automated tests are a post-defense improvement.
- **PowerShell dev environment** — Compound artisan/tinker commands with `$` variables are unreliable; workarounds required.
- **Escrow is simulated** — PayMongo sandbox handles payment capture, but the escrow hold-and-release logic is application-layer simulation, not a real escrow service.
