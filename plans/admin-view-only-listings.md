# Admin sees listings as a tenant would, but cannot inquire

Status: planned, not started.

## Goal

An admin opening a public listing (`/properties/{property}`) sees the page exactly as a tenant sees it
(photos, units, prices, map, reviews) so they can check how a listing looks. They cannot contact the
landlord or act like a tenant on it.

## What is true today

| Action on the listing page | Route | Admin today |
|---|---|---|
| Send Inquiry (desktop card + mobile "Contact" sheet) | `POST /tenant/reservations` (`reservations.store`) | Button shows. Server already refuses: the route sits under `tenant` middleware and the seeded admin has only the `Admin` role, so the click ends on a bare 403 page. |
| Start an inquiry chat | `POST /conversations` (`conversations.store`) | **Allowed.** No role check: an admin can open an inquiry thread with any landlord. This is the real hole. |
| Terms consent checkbox | (UI only) | Shows. |
| Report this listing | `POST /reports` | Shows. An admin reporting to themselves makes no sense. |
| Favorite heart | `POST /favorites/{id}/toggle` | Not role-gated. |
| Leave a review | `POST /tenant/reviews` | Already hidden (`$canReview` needs a finished tenancy). |

## Changes

### 1. One rule, in one place
`app/Models/User.php`: add `canActAsTenant(): bool` = `! $this->hasRole('Admin')`. Every check below uses
it, so "who may inquire" can't drift between the page and the server.

### 2. Server (the part that actually protects)
| File | Change |
|---|---|
| `app/Http/Controllers/ConversationController.php` `store()` | `abort_unless($request->user()->canActAsTenant(), 403, 'Admins can view listings but not contact landlords.')` before `firstOrCreate`. |
| `app/Http/Controllers/Tenant/ReservationController.php` `store()` | Same check, first line. Belt and braces: the `tenant` middleware covers it today, but an admin who also holds `Tenant` would slip through. |
| `app/Http/Controllers/FavoriteController.php` `toggle()` | Same check (403 JSON when the request expects JSON). |
| `app/Http/Controllers/ReportController.php` `store()` | Same check. |

### 3. Listing page (`resources/views/properties/show.blade.php`)
Add at the top: `$adminView = auth()->check() && ! auth()->user()->canActAsTenant();` and treat it like
`$isOwner` wherever contact UI is drawn:

| Spot (current line) | Admin sees instead |
|---|---|
| Desktop action card buttons (~630) | A grey panel: "Admin view · inquiries are off" plus a link **Open in admin** → `admin.catalogue.properties.show`. |
| Consent checkbox (~654) | Hidden. |
| Report this listing (~676) and report modal (~1375) | Hidden. |
| Inquiry modal (~1461) | Not rendered. |
| Mobile sticky bar (~1614) | Bar stays (price + unit picker still useful), "Contact" becomes **Open in admin**. Mobile sheet (~1651) not rendered. |
| Favorite heart | Hidden (here and on the cards in `properties/index.blade.php`). |

A slim strip under the header, "You are viewing this listing as a tenant would. Admins can't inquire.",
so the missing buttons read as intentional, not broken.

### 4. Tests (`tests/Feature/AdminViewOnlyListingTest.php`)
1. Admin `GET properties.show` → 200, sees "Admin view", does not see "Send Inquiry".
2. Admin `POST conversations.store` → 403, no `conversations` row.
3. Admin `POST reservations.store` → 403, no `reservations` row.
4. Admin `POST favorites.toggle` and `reports.store` → 403.
5. Tenant: same page still shows "Send Inquiry"; `reservations.store` and `conversations.store` still work
   (guards against over-blocking).

### 5. Docs
`context/CHANGELOG.md` entry; one line in `context/PRD.md` under admin role ("views listings read-only").

## Manual check (before commit)
1. Log in as admin, open a live listing on desktop: no Send Inquiry, no checkbox, no report link, "Open in admin" works.
2. Same on a phone width (375px): sticky bar shows "Open in admin", no Contact sheet.
3. Log in as a tenant: inquiry flow unchanged end to end.

## Open question
Should the admin keep the **Report this listing** link? This plan hides it, since admins act on listings
directly (suspend) from the admin side. Say if you want it kept.
