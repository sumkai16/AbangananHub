# Defense script: data and demo flow

Everything the panel may be shown, with the account to log in as and the page to open. The data comes from
`DefenseSeeder` (admin queues in `DefenseAdminSeeder`, landlord and tenant states in `DefenseRentalsSeeder`, search results in `DefenseSearchSeeder`) on top of the
base seed. **The tables below are generated from those seeders' own `SCENARIOS` lists**, so they match the data.

## 1. Build the data (once, the day before; again to reset after a rehearsal)

```bash
git pull
composer install && npm install
npm run build                      # never demo from `npm run dev`
php artisan migrate:fresh --seed   # WIPES the database, then builds the whole defense world (about 45 seconds)
php artisan optimize:clear
```

Then, for the demo itself:

```bash
php artisan serve                  # or the Apache vhost
php artisan reverb:start           # live chat and live pages; without it pages still refresh every 5 seconds
```

- Browse at `http://127.0.0.1:8000`, **not** `localhost`. To run two accounts side by side, use `http://127.0.0.1:8000`
  for one and `http://localhost:8000` for the other (separate logins).
- **No internet is needed for the data.** The 32 listing photos are in `public/images/seed/` and the ID and selfie images
  come from `database/seeders/assets/`. Map tiles, fonts and the payment test environment still use the internet.
- Re-run only the defense layer on a database that already has the base seed: `php artisan db:seed --class=DefenseSeeder`
  (safe to repeat; it adds nothing the second time). It refuses to run in production.
- Dates are relative to the day you seed: "overdue by two months" is true on seeding day and drifts as days pass, so
  reseed shortly before the defense.

## 2. Logins

All seeded accounts use the password `password`.

| Role | Email | Used for |
|---|---|---|
| Administrator | `admin@abangananhub.com` | Every admin queue (two-factor is ON, see below) |
| Landlord | `landlord@abangananhub.com` (Maria Santos) | The landlord demo: every sidebar number has something |
| Tenant | `axcee@abangananhub.com` (Axcee Cabusas) | The presenter's tenant: an overdue rental, a signed agreement waiting for payment, unread chat |
| New tenant | `tenant.new@abangananhub.com` | Starts from nothing: browse, search, inquire, sign up |

### Administrator two-factor (kept ON)

- **Print the current code on the laptop:** `php artisan defense:code` (add `--secret` to also show the secret and the recovery codes).
- **Or add it to a phone authenticator app** as a time-based key with this secret: `ABANGANANHUBDEFENSEKEYSEVEN23456`
  (issuer AbangananHub, account `admin@abangananhub.com`).
- **Recovery codes** (single use): `DEFN-AB23`, `DEFN-CD34`, `DEFN-EF45`, `DEFN-GH56`, `DEFN-JK67`, `DEFN-LM72`, `DEFN-NP23`, `DEFN-QR34`.
- Tick "Don't ask again on this browser for 30 days" after the first sign-in so the panel is not asked again during the demo.
  Risky admin actions still ask for the password (step-up), by design.

## 3. What to show, by account

### Administrator

| # | Log in as | Open | What the panel sees |
|---|---|---|---|
| 1 | `admin@abangananhub.com` | /admin/verifications?status=Pending | Three landlord applications waiting, two with viewable ID and selfie, name-read and face-match results (match, and a "review by eye" case). |
| 2 | `admin@abangananhub.com` | /admin/verifications?status=Rejected | A rejected application with the admin note about the blurry ID, for the resubmission story. |
| 3 | `admin@abangananhub.com` | /admin/listings/approval?status=Pending | Listings waiting for approval: one complete (photos and three units), one thin (no photos, one unit). |
| 4 | `admin@abangananhub.com` | /admin/documents?status=Pending | Three uploaded property documents (tax declaration, business permit, proof of ownership) to verify or reject. |
| 5 | `admin@abangananhub.com` | /admin/documents?status=All | One verified proof of ownership (the listing earns the Verified badge), one rejected expired certificate, one document still requested. |
| 6 | `admin@abangananhub.com` | /admin/catalogue/units?needs=approval | Two new units added to already-approved listings, each needing its own approval. |
| 7 | `admin@abangananhub.com` | /admin/reports?status=Pending | Nine pending reports against listings and users, with real reasons. |
| 8 | `admin@abangananhub.com` | /admin/reports?status=Resolved | Resolved reports with notes and the action taken (warn, delist, suspend, no action). |
| 9 | `admin@abangananhub.com` | /admin/refund-requests?type=disputes | A move-in dispute on rent and deposit held online: the tenant, the reason, and the Refund and Release buttons (Refund calls PayMongo, so it only works with real test keys; Release works offline). |
| 10 | `admin@abangananhub.com` | /admin/refund-requests?type=escalations | A "Refund not returned" escalation: a landlord kept ₱3,500 paid directly, the tenant sent it to admin, and the report says AbangananHub cannot refund it. |
| 11 | `admin@abangananhub.com` | /admin/payouts | A landlord owed money (released deposit and rent paid online) with a pay-out action. |
| 12 | `admin@abangananhub.com` | /admin/payouts | Two GCash payout accounts waiting for approval, each with the name check against the landlord's verified ID. |
| 13 | `admin@abangananhub.com` | /admin/payouts | A failed, unresolved payout (GCash declined the transfer) with Retry, plus an earlier payout that was sent. |
| 14 | `admin@abangananhub.com` | /admin/tenant-holds | A landlord's unpaid-rent report waiting for confirmation, an active hold, a released one, and a possible-duplicate-account alert. |
| 15 | `admin@abangananhub.com` | /admin/deposit-charges | Two active deposit charges (cleaning, damage) and one voided, on a completed tenancy. |
| 16 | `admin@abangananhub.com` | /admin/payments?status=All | Payments in every state: Held, Released, Paid, Pending, Voided and Refunded. |
| 17 | `admin@abangananhub.com` | /admin/audit-logs | An activity log across several days and action types (approvals, releases, refunds, holds, sign-ins), hash-chained. |
| 18 | `admin@abangananhub.com` | /admin/users?status=Suspended | A suspended account in the users list. |
| 19 | `admin@abangananhub.com` | /admin/ratings | Ratings and reports analytics with enough reviews to have shape (from ReviewSeeder). |
| 20 | `admin@abangananhub.com` | /notifications | Unread admin notifications: payout accounts to approve, a refund escalation, a dispute, applications waiting. |

### Landlord and tenant

| # | Log in as | Open | What the panel sees |
|---|---|---|---|
| 1 | `landlord@abangananhub.com` | any landlord page (sidebar) | Maria's sidebar has a number on Reservations, Properties, Rentals, Reviews, Concerns and Payments. |
| 2 | `axcee@abangananhub.com` | /tenant/tenancy (Unit 1, 1-Bedroom Apartment in Poblacion, Consolacion) | A rental two months overdue (₱19,000): the pay picker, the pay sheet on a phone, and the Deposit tab with a deposit charge. |
| 3 | `landlord@abangananhub.com` | /landlord/payments | The same tenancy as Overdue, with Remind overdue and the ledger. |
| 4 | `axcee@abangananhub.com` | /tenant/reservations, then the agreement (Studio Apartments, Lahug) | A signed agreement waiting for the move-in payment, direct payments, no QR shared yet, so "Ask Maria to share a payment QR" shows. |
| 5 | `axcee@abangananhub.com` | /conversations (Maria, Studio Apartments, Lahug) | A thread with two unread messages from the landlord. |
| 6 | `axcee@abangananhub.com` | /tenant/reservations (Casa Verde Rooms, Mandaue) | A cancelled reservation with "Your refund is on its way". |
| 7 | `axcee@abangananhub.com` | /favorites | Three saved listings. |
| 8 | `landlord@abangananhub.com` | /landlord/concerns | An open electrical concern from Axcee, in the tenancy chat. |
| 9 | `landlord@abangananhub.com` | /conversations (Kyle Bautista, Studio Apartments, Lahug) and the bell | Kyle asked for a payment QR: a notification, a system line, and the Share payment QR button marked Asked. |
| 10 | `landlord@abangananhub.com` | /landlord/payments and the chat with Noemi Gutierrez | A GCash payment report waiting to be confirmed or rejected, with the QR Maria shared in that chat. |
| 11 | `landlord@abangananhub.com` | /landlord/contracts | Liam Dizon's rental ends in 30 days (renewal window), so Rentals has a number. |
| 12 | `landlord@abangananhub.com` | /landlord/contracts?status=all (Ana Rivera) | An ended rental with a deposit charge and a partly returned deposit. |
| 13 | `landlord@abangananhub.com` | /landlord/reservations and its Viewings tab | A new inquiry from Bianca Uy and a viewing request to answer. |
| 14 | `landlord@abangananhub.com` | /landlord/reviews | Three reviews without a reply. |
| 15 | `landlord@abangananhub.com` | /landlord/properties | A listing the admin rejected, with the reason, to fix and resubmit. |
| 16 | `landlord@abangananhub.com` | /landlord/properties | A suspended listing, hidden from tenants. |
| 17 | `landlord@abangananhub.com` | /landlord/lease-policies | A custom policy on a property and a different one on a single unit (Unit 3). |
| 18 | `landlord@abangananhub.com` | /landlord/tenants | A walk-in tenant with no account who pays cash. |
| 19 | `landlord@abangananhub.com` | /landlord/payouts | Maria's GCash payout account is approved, so online payouts work. |
| 20 | `landlord@abangananhub.com` | /landlord/payments and /landlord/contracts | Three more payment stories: a part payer (Rico Salazar), one who pays months ahead (Selena Tan) and one who always hands cash (Jun Pascual). |
| 21 | `tenant.new@abangananhub.com` | the whole site | A tenant with no activity: browse, search, inquire and sign up from nothing. |

### AI search (`DefenseSearchSeeder`)

Type these in the search box on the landing page or Browse Rentals (no login needed). Each returns **three or more exact matches** with the hit chips and a summary in the language typed; the first three are the suggestion chips on the landing page. About 27 listings were added across the seven business landlords, so "places by this business" and "near me" have data too.

| # | Type | Language | What the panel sees |
|---|---|---|---|
| 1 | `Bedspace near Lahug, aircon, under 3k` | English | Bedspaces within 2 km of Lahug with aircon under ₱3,000, distance on each card (Lahug is a place with coordinates, not text). |
| 2 | `Bedspace para sa babae, may aircon` | Tagalog | Women-only bedspaces with aircon, summary in Tagalog with an English toggle. |
| 3 | `Kwarto duol sa CIT-U, ubos sa 3k` | Bisaya | Rooms near CIT-U under ₱3,000, summary in Bisaya. |
| 4 | `Dorm near IT Park, max ₱18k` | English | Four dormitories near Cebu IT Park. |
| 5 | `Apartment sa Mandaue ubos 4k` | Taglish | Three Mandaue apartments under ₱4,000. |
| 6 | `Room near USC Talamban, Wi-Fi, under ₱5,000. I have a cat.` | English | Rooms that allow pets (a house-rule filter), with Wi-Fi. |
| 7 | `Bedspace for men near Colon, under 2k` | English | Men-only bedspaces downtown. |
| 8 | `Boarding house sa Lapu-Lapu, walang curfew` | Tagalog | Boarding houses in Lapu-Lapu with no curfew rule. |
| 9 | `House sa Talisay na may parking, 10k pababa` | Tagalog | Houses in Talisay with parking under ₱10,000. |
| 10 | `maria santos` or `properties ni Santos Rentals Cebu` | English | Searches by business name: only that business's listings, with a "By Santos Rentals Cebu" chip. |
| 11 | `near me` added to any search | English | The browser asks for the location, then the nearest places show first. |
| 12 | `Dorm near the airport` (nothing fits) | English | Never an empty page: "No exact match, but these could work" with the closest places and what differs on each card. |
| 23 | `cheapest 2 bedroom apartment sa Banilad, walay deposit` | Taglish + Bisaya | Chips for 2+ bedrooms, No deposit and Cheapest first; the two Banilad flats come first, lowest rent first. |
| 24 | `family of 6 apartment near Robinsons Galleria` | English | A three-bedroom flat in Mabolo that fits 6 ("Good for 6" now filters). |
| 25 | `bedspace 10 minutes walk to IT Park` | English | Each card says "about N min walk" (real minutes once an `ORS_API_KEY` is set). |
| 26 | `bedspace far from Colon, under 3k` | English | A dashed "Away from Colon" circle on the map, no result inside it. |
| 27 | `room between IT Park and USC Talamban, under 6k` | English | Two "Close to" chips, a distance to each place on every card and a collapsed "How I searched". |
| 28 | `room near Skyrise, under 6k` | English | A place that is not in our list, found on OpenStreetMap (needs internet). |
| 29 | After any search: tap **Cheaper**, then type `may aircon` | any | The search changes in place (Refine bar), **Back** returns to the last one. |
| 30 | Pan the results map, tap **Search this area** | any | Results for exactly what the map shows. |
| 31 | Signed in: **Screenshot** button, upload a rental post | any | The search is filled in from the image and run. |
| 32 | Admin: **Search insights**, and a listing under Property Verifications | admin | Searches and cost per day, supply gaps; a "Photo check (AI, advisory)" block (run `php artisan listing-photos:check 1` first). |
| 13 | `Need ko ng room near IT Park, may wifi and aircon, max 6k` | Taglish | Rooms near IT Park with Wi-Fi and aircon under ₱6,000; the summary is in Tagalog. |
| 14 | `Female lang na bedspace malapit sa CIT-U, with aircon` | Taglish | Women-only aircon bedspaces near CIT-U. |
| 15 | `May parking ba yung house sa Talisay? 10k lang budget ko` | Taglish | Talisay houses with parking under ₱10,000. |
| 16 | `Naa ba'y bedspace near IT Park with aircon? 3k max` | Bisaya + English | Bedspaces near IT Park with aircon, summary in Bisaya. |
| 17 | `Pet friendly nga house sa Talisay please, up to 12k` | Bisaya + English | Pet-friendly houses in Talisay. |
| 18 | `Dormitory for boys duol sa UC Main, below 3500` | Bisaya + English | Men-only dormitories near UC Main. |
| 19 | `Naghahanap ko ug kwarto malapit sa USC Talamban, ubos sa 5k` | Bisaya + Tagalog | Four rooms near USC Talamban under ₱5,000. |
| 20 | `Pwede ang iro ug pusa, boarding house sa Talisay, hindi molapas 4k` | Bisaya + Tagalog | Pet-friendly Talisay boarding houses; the summary is in English because the sentence mixes both languages evenly. |
| 21 | `Apartment sa Mandaue na naay parking, hanggang 7k` | Bisaya + Tagalog | Mandaue apartments with parking under ₱7,000. |
| 22 | `Gusto ko ng bedspace dapit sa Colon, 2k lang, babae ko` | Bisaya + Tagalog | Women-only bedspaces near Colon under ₱2,000. |

## 4. Suggested order (about 15 minutes)

1. **Tenant, new** (`tenant.new@`): browse and search in English, Tagalog or Cebuano, open a listing, show the map and the verified badge.
2. **Tenant, Axcee**: My Rentals (overdue rental, month picker, Deposit tab) and the signed agreement ("Ask Maria to share a payment QR").
3. **Landlord, Maria, second browser**: the QR request arrives (bell and chat, live), share the QR; the tenant's page updates by itself. Then the payment report to confirm: confirm it and watch the tenant's page move on.
4. **Landlord**: sidebar numbers, Reservations and Viewings, Policies and the policy reader, Payments (Overdue, Remind overdue), Concerns.
5. **Administrator**: sign in with two-factor, the dashboard queue, Landlord Verifications, Property Verifications, Refund requests (dispute and escalation), Payouts, Reports, Activity Logs (hash-chained).

## 5. Known limits

- **Refund** (Admin > Refund requests > dispute) calls PayMongo and only works with real PayMongo test keys; **Release** works offline. Show Release, or the Refund form up to the confirm step.
- Online payment checkout needs the internet and PayMongo test keys; direct payments (QR and cash) and everything above work without them.
- A part-payer's month (Rico Salazar) reads "partial" until the 28th of the month and "overdue" after.
- Chat and live pages use Reverb on `127.0.0.1:8080`, so they work in a browser on the same computer. Through an ngrok https link they fall back to refreshing every 5 seconds (see the changelog, Nov 17).
- Do not run `php artisan test` against the demo database: tests use `abanganan_hub_test`, a separate database.
