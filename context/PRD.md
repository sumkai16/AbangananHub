# PRD.md — Product Requirements Document

## 1. Problem Statement
In Cebu, Philippines (Talisay, Minglanilla, Naga City), tenants and landlords rely on informal rental channels — Facebook groups, word of mouth, personal referrals — that offer no identity verification, no listing authenticity checks, and no accountability. This leads to scams, fake listings, wasted time, and zero recourse. AbangananHub replaces that with a controlled, admin-verified rental accommodation marketplace where landlords are identity-verified, listings are admin-approved, and communication happens through structured, traceable channels.

## 2. Target User
- **Tenants:** Students, young professionals, and workers in Cebu looking for verified bedspaces, rooms, apartments, or houses for rent. They need to browse, filter, compare, and reserve accommodations with confidence that the listing and landlord are legitimate.
- **Landlords:** Property owners in the Cebu coverage area who want to list rental units, manage reservations, and communicate with prospective tenants through a structured platform instead of scattered social media threads.
- **Admin:** A single platform administrator who verifies landlord identities, approves property listings, handles reports/complaints, and monitors platform activity.

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

## 4. Explicitly Out of Scope
- Legal dispute handling between landlords and tenants (the move-in dispute flow only freezes the deposit and queues it for an admin — it renders no judgment)
- **Refunds.** Nothing in the app writes `payments.status = 'Refunded'`; PayMongo's programmatic refund support is unverified. A landlord who never turns over the keys is escalated to admin review, and that queue drains only by releasing to the landlord. Closing this is Phase 2 of the move-in spec and requires confirming PayMongo first
- Offline mode (map and real-time features require stable internet)
- Native mobile app (web only — React Native/Expo deferred to post-deployment)
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
  - PayMongo (sandbox/test, GCash — escrow simulated in app layer)
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
