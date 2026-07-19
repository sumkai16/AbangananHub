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
└── ...

resources/views/
├── layouts/
│   └── app.blade.php        # Base layout (glassmorphism, Poppins/Inter, mesh gradient)
├── components/              # Blade components (x-stat-card, etc.)
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
| face-api.js for liveness detection | Runs client-side (no server GPU needed), works on Chrome/Firefox, sufficient for capstone | Mid 2026 |
| Live camera capture required for unit photos | Anti-fraud — proves the unit is real and current; ≥3 live captures, uploads as extras | July 2026 |
| Two-column unit create/edit (form + sticky-preview rail) | Fills wasted horizontal space; live preview + amenities in the rail | July 2026 |
| Occupancy history via nightly snapshots + observer-based activity log | True daily trend needs stored history; observer catches every status-change path without touching each controller | July 2026 |

## 7. Known Tradeoffs
- **No queue worker** — `ShouldBroadcastNow` is synchronous. Acceptable for capstone load; would need a queue for production scale.
- **No caching layer** — No Redis/Memcached. DB queries are fast enough at capstone data volumes.
- **No rate limiting on API** — Sanctum API layer is scaffolded but not rate-limited. Non-issue until mobile app ships.
- **Single admin** — No admin role hierarchy or permission granularity. One admin account handles everything.
- **No automated testing** — Manual testing only. Time constraint; automated tests are a post-defense improvement.
- **PowerShell dev environment** — Compound artisan/tinker commands with `$` variables are unreliable; workarounds required.
- **Escrow is simulated** — PayMongo sandbox handles payment capture, but the escrow hold-and-release logic is application-layer simulation, not a real escrow service.
