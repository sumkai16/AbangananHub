# PRD.md — Product Requirements Document

## 1. Problem Statement
In Cebu, Philippines (Talisay, Minglanilla, Naga City), tenants and landlords rely on informal rental channels — Facebook groups, word of mouth, personal referrals — that offer no identity verification, no listing authenticity checks, and no accountability. This leads to scams, fake listings, wasted time, and zero recourse. AbangananHub replaces that with a controlled, admin-verified rental accommodation marketplace where landlords are identity-verified, listings are admin-approved, and communication happens through structured, traceable channels.

## 2. Target User
- **Tenants:** Students, young professionals, and workers in Cebu looking for verified bedspaces, rooms, apartments, or houses for rent. They need to browse, filter, compare, and reserve accommodations with confidence that the listing and landlord are legitimate. **Primarily on a phone's mobile browser**, not a desktop — see `context/DESIGN.md` §0b.
- **Landlords:** Property owners in the Cebu coverage area who want to list rental units, manage reservations, and communicate with prospective tenants through a structured platform instead of scattered social media threads. **Also primarily mobile** — checking listings/tenants/rent on the go, not sitting at a desk.
- **Admin:** A single platform administrator who verifies landlord identities, approves property listings, handles reports/complaints, and monitors platform activity. Desktop-oriented — the one role this app is not designed mobile-first for.

## 3. MVP Scope (Defensible 13-Module Set)
- [x] Auth (registration, login, password reset via emailed token, session-based via Laravel Breeze)
- [x] Role System (Tenant, Landlord, Admin — role assigned on verification approval)
- [x] Landlord Identity Verification (live camera capture of government ID + selfie — no upload path, OCR via Google Cloud Vision, 4-step liveness detection via face-api.js, admin review pipeline)
- [x] Property Listing CRUD (landlord creates/edits properties and units, Cloudinary media upload)
- [x] Property/Unit Approval (admin reviews and approves/rejects listings)
- [x] Search & Filters (text search, property type, price range, verified-landlord filter, paginated browse)
- [x] Interactive Map (Leaflet + OSM tiles, OSRM directions, Overpass landmarks, Nominatim geocoding)
- [x] Favorites (toggle, index, immediate DOM removal on unfavorite)
- [x] Real-time Chat (Laravel Reverb WebSockets, conversation-based messaging)
- [x] Reservation System (state machine: Pending → Approved/Rejected/Cancelled, AUX rental process flow with PayMongo sandbox payment)
- [x] Move-In Confirmation Window (two escrow deadlines expiring toward opposite parties — landlord turnover clock, tenant confirmation clock; tenant dispute freezes both into an admin review queue; nightly `reservations:process-move-in-deadlines` reminds, escalates and auto-releases)
- [x] Reviews (tenant reviews units post-stay, rating + comment)
- [x] Notifications (in-app notifications, mark-read endpoints)
- [x] Admin Dashboard (user management, analytics, report handling)
- [x] Complaints (report properties or users, admin resolution pipeline)
- [x] Occupancy Monitoring (landlord tracks unit occupancy status)
- [x] Tenant Ratings (landlord rates tenants)
- [x] Report Analytics (admin CSV export, data visualization)
- [x] Landlord CSV export (Units, Reservations, Tenants — filter-aware, alongside the existing Occupancy export)
- [x] Walk-in Tenants (landlord records an offline-arranged tenancy directly to Occupied — a lightweight non-login tenant account, no escrow; badged Walk-in everywhere as landlord-asserted, not platform-verified)
- [x] Rent Ledger (derived monthly billing periods with Paid/Partial/Overdue/Due status for any occupied tenancy, walk-in or platform; landlord records offline payments — Cash/GCash/Bank/Maya/Check — with printable receipts and a portfolio-wide collections view)
- [x] Tenant Online Rent Payment (platform tenants pay their single earliest unsettled billing period via PayMongo GCash/QRPh checkout, settling straight to Paid — no escrow, since the initial-payment escrow only ever protected the pre-move-in handover; realtime via the existing Reverb `PaymentStatusUpdated` broadcast, so the landlord's ledger and the tenant's own read-only ledger page both update live without a refresh. Walk-in tenants can't log in, so they stay offline-only)
- [x] End of Tenancy (Completed terminal status returns the unit to the available pool; before this an Occupied reservation had no exit)
- [x] Rent Reminders (nightly `reservations:process-rent-reminders` notifies the landlord about upcoming/overdue rent for every tenancy, and the platform tenant too; walk-in tenants can't log in so the landlord reminder is their only channel — idempotent, catch-up-safe)
- [x] Overall Ratings (aggregation of existing reviews + tenant_ratings: admin `/admin/ratings` platform overview with per-relationship averages, distributions, leaderboards and a 6-month trend; role-separated received-rating badges on tenant/landlord profiles and admin user detail. Property-grain; tenant→unit deferred — reviews carry no `unit_id`)
- [x] Landlord Payouts (money released/paid to a landlord lands in the platform's own PayMongo balance, not theirs — `payments.payout_status` tracks whether an admin has since sent it on manually via GCash and recorded a reference; admin payouts queue grouped by landlord, landlord-facing pending/history view, `users.gcash_number`/`gcash_account_name` payout destination. No real disbursement API — manual transfer only, same spirit as the escrow simulation. See `docs/specs/2026-07-26-landlord-payout-design.md`)

## 4. Explicitly Out of Scope
- Legal dispute handling between landlords and tenants (the move-in dispute flow only freezes the deposit and queues it for an admin — it renders no judgment)
- **Refunds.** Nothing in the app writes `payments.status = 'Refunded'`; PayMongo's programmatic refund support is unverified. A landlord who never turns over the keys is escalated to admin review, and that queue drains only by releasing to the landlord. Closing this is Phase 2 of the move-in spec and requires confirming PayMongo first
- Offline mode (map and real-time features require stable internet)
- ~~Native mobile app~~ — **no longer out of scope as of July 27 2026.** A React Native + Expo client for Tenant + Landlord is planned in `plans/mobile-app.md`; the server-side unblocking (Phase 0) is done. Admin stays web-only. Note this is *additive* to the defense scope — every module below is already demonstrable on web, so mobile is upside, not a dependency
- Property types beyond bedspace, room, apartment, house
- Coverage area beyond Cebu, Philippines
- Live production PayMongo merchant account (sandbox only for capstone)
- Auto-generated legally binding rental contracts
- Multi-occupant identity sub-table tracking
- 3D/video keyframing for photo verification

## 5. Technical Requirements
- Stack: PHP 8.2, Laravel 12, Blade + Tailwind CSS v3 + Alpine.js, MySQL, Vite
- Hosting: Hostinger VPS, Ubuntu, Nginx, Supervisor (for Laravel Reverb), SSL
- Auth: Laravel Breeze (session-based), Laravel Sanctum (API layer for future mobile)
- Third-party integrations:
  - PayMongo (sandbox/test, GCash/QRPh — escrow simulated in app layer)
  - Cloudinary (v3 SDK — public media: property/unit photos, profile pictures, logos)
  - Google Cloud Vision API (OCR for landlord ID verification)
  - face-api.js / TensorFlow.js (liveness detection in verification wizard)
  - Laravel Reverb (WebSockets for real-time chat)
  - Mailtrap (development email sandbox — password reset and landlord verification emails)
  - Leaflet.js + OpenStreetMap + OSRM + Overpass + Nominatim (maps)

## 6. Success Metrics
- Metric: All 13+ modules functional and demonstrable in panel defense
- Target: Zero critical bugs during live demo (September 2026)
- Metric: Landlord verification pipeline end-to-end (submit → admin review → role grant)
- Target: Works in under 3 minutes during demo
- Metric: Real-time chat message delivery
- Target: Sub-2-second delivery via Reverb during demo

## 7. Constraints
- Two-person team (Axcee: full-stack lead; Joseph: UI/CSS)
- Academic capstone — September 2026 panel defense deadline
- No budget for paid APIs in production (sandbox/free tiers only)
- PayMongo sandbox only — no live transactions
- Windows local dev environment (PowerShell quirks with artisan commands)
- **A registered domain and real SSL certificate are hard functional requirements, not polish.**
  `getUserMedia` only runs in a secure context, so landlord ID verification and the ≥3 live unit
  captures do not work over plain HTTP, and Let's Encrypt will not issue for a bare IP. Deploying to
  an IP address alone would take two demoable modules offline. Domain: `abangananhub.com`.
- **Recurring infrastructure cost** — a VPS (~12-month term) plus domain registration. The "no
  budget for paid APIs" constraint above covers third-party services; hosting is the one unavoidable
  spend, and it is what makes the app reachable by the panel at all.

## 8. Pre-Defense Readiness
Tracked separately from module scope because these are **not code** — they are account access,
third-party console settings, and product decisions that cannot be scripted or delegated. Several
fail *at the provider*, meaning the app looks healthy and the feature is dead. Owner: Axcee.

| Item | Status | Note |
|---|---|---|
| VPS provisioned + domain DNS pointed | Not started | Blocks everything below. See `plans/hostinger-vps-deployment.md` |
| Production `.env` copied to server | Not started | Only Axcee holds the credentials |
| Google + Facebook OAuth callback URLs registered | Not started | Changing `APP_URL` updates the app's side only, never the provider's allow-list |
| OAuth apps published, **or** panel added as test users | **Decision open** | Both still in Testing/Development mode. Publishing needs provider review time; adding test users avoids that risk. Either is defensible — arriving at the defense without having chosen is not |
| PayMongo webhook repointed + one live sandbox payment run | Not started | **Highest-risk item.** PayMongo cannot reach `localhost`, so this webhook has *never fired* in development — the poll-fallback in `ReconcilesPaymongoCheckout` has carried the entire payment path. It goes live untested |
| Mail: keep Mailtrap sandbox, **or** wire a real sender | **Decision open** | Mailtrap captures mail and never delivers. Fine if the demo shows its console; not fine if a panel member must receive a reset link |
| Test review comment removed | Not started | SCHEMA.md flags one reading "hahaha suled" in `reviews` |
| Off-server backups (`mysqldump` + `storage/app/private`) | Not started | That directory holds the **only** copy of every government ID and property document — Cloudinary is not a fallback for it |

**Schedule risk:** several steps have provider-controlled waits that cannot be compressed — DNS
propagation (hours to a day), SSL issuance (needs DNS live first), and OAuth review if that route is
taken. A first deploy also reliably surfaces something. Deploy with weeks of slack, not days.
