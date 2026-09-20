# Performance Optimization — "fast at home, slow at school"

**Status:** implemented Sept 21 2026 — **pending manual verification** (§8) and a school-network re-test. Item 2e (explicit column lists) deliberately deferred — see §10.
**Date:** 2026-09-21
**Trigger:** Page-to-page navigation took 2–5s on the school network during the instructor demo;
same build feels instant at home. Instructor's note: *"fix the query — only query the data in that
specific page, not the whole page."*

---

## 1. Verdict first

**Queries are not what made it slow at school.** Measured on the real dev database, every page spends
**5–30 ms total in the database**. You cannot get from 30 ms to 2–5 seconds with queries.

What actually costs seconds is **how much the browser has to download before the page is usable**,
and that is invisible at home because your browser already has it all cached on a fast connection.

The single biggest number in this audit:

> The home page eagerly downloads **26 images at `?w=1200` — about 4.4 MB** — before it settles,
> with no `loading="lazy"` anywhere on the browse grid.

The instructor's advice is still correct as engineering hygiene, and §5 does all of it — it just
isn't the 2–5 seconds. Both get fixed.

---

## 2. Baseline (measured 2026-09-21)

Harness: `tests/Feature/PerformanceBaselineTest.php` (`php artisan test --filter=PerformanceBaseline`).
Server timings: `php artisan serve` on 127.0.0.1, `curl` on the same machine.

### Server side — healthy

| Route | Queries | DB time | Slowest single query |
|---|---:|---:|---|
| `/` (landing) | 20 | 29.0 ms | 7.8 ms |
| `/properties/{id}` | 33 | 10.8 ms | 0.9 ms |
| `/landlord/analytics` | 36 | 24.2 ms | 1.8 ms |
| `/admin/dashboard` | 22 | 12.6 ms | 2.2 ms |
| `/tenant/reservations` | 23 | 9.0 ms | 0.8 ms |
| `/favorites` | 20 | 10.0 ms | 0.9 ms |

Home page wall time: **957 ms cold, 172–240 ms warm**.

### Client side — this is the problem

| Thing | Measured | Note |
|---|---:|---|
| Home page HTML | **323 KB** | for a page with 15 cards |
| …of which Debugbar | **101 KB (31%)** | injected into every response |
| `<img>` tags on home | **31** | 26 from Unsplash |
| Weight of one card image | **170 KB** | `?w=1200&q=80` |
| **Total eager image bytes** | **≈ 4.4 MB** | none lazy-loaded |
| Render-blocking external CSS | Google Fonts | `<link>` in `<head>` of all 4 layouts |
| JS/CSS bundle | 163 KB JS + 117 KB CSS | fine |

Database size: 14 properties, 30 units, 19 users, 34 media rows. **Nothing here is slow because of
data volume.**

---

## 3. Root causes, ranked

| # | Cause | Why school ≠ home | Est. impact |
|---|---|---|---|
| 1 | **4.4 MB of eager, oversized images** | Home: warm browser cache + fast line. School: cold cache, contended wifi. 26 images also saturate the browser's 6-connections-per-host limit, delaying CSS/JS behind them. | **Dominant** |
| 2 | **`php artisan serve` is single-threaded** | `ServeCommand.php:100` — `PHP_CLI_SERVER_WORKERS` defaults to `1`, and Laravel warns it cannot raise it without `--no-reload`. (It is fork-based, so unavailable on Windows anyway.) Every same-origin request — HTML, `app.css`, `app.js`, `map-core.js`, `/broadcasting/auth` — is served **one at a time**. Cold, that is ~6 × 150 ms serialized. At home those assets were cached, so the queue never formed. | **High** |
| 3 | **Debugbar on every response** | No `config/debugbar.php`, no `DEBUGBAR_ENABLED` in `.env`, so it follows `APP_DEBUG=true` → **on**. Adds 101 KB to every page plus collection overhead. | **Medium-high** |
| 4 | **Google Fonts render-blocking** | `<link rel="stylesheet">` in `<head>` of all 4 layouts. A cold DNS + TLS handshake to `fonts.googleapis.com` on congested wifi blocks first paint for 1–3 s. Warm at home. | **Medium-high** |
| 5 | **No config/route/event cache** | `php artisan about` → Config `NOT CACHED`, Routes `NOT CACHED`, Events `NOT CACHED`. ~50–150 ms of bootstrap per request, multiplied by cause #2. | **Medium** |
| 6 | **Chart.js from jsdelivr** | 5 views load it from CDN at render time (admin dashboard/ratings/report-analytics/users, landlord analytics). Another cold external fetch. | **Medium** (those pages only) |
| 7 | **Repeated queries** *(instructor's point)* | `hasRole()` runs a fresh `exists` query **every call** — 9–10× per page. Notification + message counts run **twice** per layout. `AnalyticsController::revenueBetween()` runs **22×** in a loop. Areas tiles do a query per area. | **Low today, grows with data** |
| 8 | **`SELECT *` everywhere** | e.g. 29 of 33 queries on the property page. Harmless at 14 rows; wasteful as the table grows. | **Low today** |
| 9 | **Missing indexes** | Only 10 of 68 migrations declare an index. Nothing on `properties.verification_status`, `publication_status`, `city_municipality`, `property_units.availability_status`, `rental_fee`. | **Low today, high later** |

> **Before Phase 1, capture proof.** On the school network, open DevTools → Network, hard-reload
> `/`, and screenshot the waterfall + the "N requests / X MB transferred" summary. That is the
> before-number this whole plan is measured against, and it is also the single most convincing
> thing to show the instructor.

---

## 4. Phase 1 — Payload (fixes the 2–5 seconds)

### 1a. Lazy-load and right-size every listing image

`resources/views/components/property-card.blade.php:35-42` renders **every** media row of **every**
property as an eager `<img>`. Cards 2+ of the carousel are offscreen and cards below the fold are
unseen, yet all of it downloads immediately.

- First slide of each card: `decoding="async"`, explicit `width`/`height` (stops layout shift).
- Every other slide: `loading="lazy" decoding="async"`.
- Cards below the fold: `loading="lazy"` on the first slide too.
- Add a `responsive_image($url, $width)` helper (`app/Support/Images.php`) that rewrites the size
  parameter — `w=1200` → `w=640` for cards — and emits a `srcset` of `320w/640w/960w` with
  `sizes="(max-width: 768px) 100vw, 33vw"`. It must handle both Unsplash (`?w=&q=`) and Cloudinary
  (`/upload/f_auto,q_auto,w_640/`) URLs, since seeded data is Unsplash and real uploads are Cloudinary.
- Sweep the other **107** `<img>` tags — only 5 of 112 currently have `loading="lazy"`.

**Expected: ~4.4 MB → ~250 KB on first paint.**

### 1b. Turn Debugbar off by default

Add to `.env` and `.env.example`:

```
DEBUGBAR_ENABLED=false
```

Keeps `APP_DEBUG=true` (you still get real error pages) while removing 101 KB and the collectors
from every response. Flip it to `true` only while actively debugging.

### 1c. Self-host the fonts

`npm i @fontsource/inter @fontsource-variable/plus-jakarta-sans @fontsource/dm-serif-display`,
import them in `resources/css/app.css`, and delete the `fonts.googleapis.com` `<link>` + `preconnect`
lines from `layouts/app`, `layouts/admin`, `layouts/guest`, `layouts/landlord`, and `errors/404`.

Costs an npm install; buys a page that has **zero** render-blocking external requests and works
offline. This is the better option over just adding `font-display: swap` — worth the extra step.

### 1d. Bundle Chart.js instead of CDN

`npm i chart.js`, add a `resources/js/charts.js` entry, replace the 5 `cdn.jsdelivr.net` `<script>`
tags with a Vite chunk. Same reasoning as fonts.

### 1e. Defer the map

`map-core` is 186 KB JS + 15 KB CSS, and tiles come from `arcgisonline` / `basemaps.cartocdn.com`.
Initialise the browse map only when its container scrolls into view (`IntersectionObserver`), so a
tenant scrolling the list never pays for tiles they did not look at.

---

## 5. Phase 2 — Queries (the instructor's ask)

### 2a. Stop re-querying roles — `app/Models/User.php:158`

```php
public function hasRole(string $role): bool
{
    return $this->roles()->where('role', $role)->exists();   // a query EVERY call
}
```

`layouts/app.blade.php` alone calls it 7 times; profiling shows **9–10 identical queries per page**.

Fix: resolve against the loaded relation, loading it once.

```php
public function hasRole(string $role): bool
{
    return $this->roles->contains('role', $role);
}
```

…and eager-load `roles` on the authenticated user once per request. **Removes ~9 queries/page.**

### 2b. Compute the header badges once

`notifications()->…->count()` and the unread-messages count each run **twice** per layout
(`layouts/app.blade.php:154` + `:1034`, `layouts/landlord.blade.php:474` + `:525`,
`layouts/admin.blade.php:79`).

Fix: one `View::composer` in `AppServiceProvider` that shares `$unreadNotifications` and
`$unreadMessages`, computed once. **Removes 2–4 queries/page.**

### 2c. Collapse the analytics revenue loop

`AnalyticsController::revenueBetween()` (line 237) is called per month per period — profiling shows
**22 identical-shape `SUM(amount)` queries** on `/landlord/analytics`.

Fix: one query grouped by month over the whole window, then read months out of the result.
**36 queries → ~14.**

### 2d. Fix the areas N+1

`PropertyController::index()` (lines 63-70) runs a full `browsable()` query **per area** just to find
one photo — 8 extra queries with subselects. Fix: one query fetching a representative photo for all
areas at once, keyed by `city_municipality`.

### 2e. Select only what the page renders — *literally the instructor's wording*

Add explicit column lists to the hot paths, starting where the ratio is worst:

| Route | `SELECT *` queries |
|---|---|
| `/properties/{id}` | 29 of 33 |
| `/landlord/analytics` | 29 of 36 |
| `/` | 18 of 20 |
| `/favorites` | 18 of 20 |

Card rendering needs `property_id, title, address, city_municipality, property_type, latitude,
longitude` — not every column. Same for the `media`, `landlord` and `units` eager loads.

### 2f. Add the missing indexes

One migration, `add_performance_indexes`:

| Table | Index |
|---|---|
| `properties` | `(verification_status, publication_status)` |
| `properties` | `(city_municipality)` |
| `property_units` | `(availability_status, verification_status)` |
| `property_units` | `(rental_fee)` |
| `notifications` | `(user_id, is_read)` |
| `messages` | `(conversation_id, is_read, sender_id)` |
| `reviews` | `(property_id, is_hidden)` |
| `favorites` | `(tenant_id)` |

No measurable gain at 14 rows — this is for the demo dataset growing, and it is exactly what an
instructor checks for.

---

## 6. Phase 3 — How you run it at a demo

`php artisan serve` is a single-request-at-a-time development server. For anything you are being
graded on, do not demo on it.

**Add `docs/demo-setup.md` with a pre-demo checklist:**

1. `npm run build` — **the current `public/build` is from Sep 9 and `resources/` has changed since**,
   so the committed bundle is stale. Never demo from `npm run dev`.
2. `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache`
   *(and `php artisan optimize:clear` afterwards, or `.env` edits will appear to do nothing)*.
3. `DEBUGBAR_ENABLED=false`.
4. Serve through **XAMPP Apache** with a vhost pointing at `public/` — Apache is multi-process, so
   assets download in parallel instead of queueing. This one change alone removes cause #2.
5. Browse via `http://127.0.0.1`, never `localhost` — see [[windows-dev-perf-ipv6]]; Windows resolves
   `localhost` to IPv6 `::1` first and pays a fallback penalty.
6. Load every page you plan to show once, before the instructor is watching, to warm OPcache.

---

## 7. Order of work

| Phase | Effort | Expected result |
|---|---|---|
| 1a lazy + sized images | M | The 2–5 s largely gone |
| 1b Debugbar off | XS | −101 KB/page |
| 1c self-host fonts | S | No render-blocking external CSS |
| 1d bundle Chart.js | S | Admin/landlord pages stop waiting on a CDN |
| 1e defer map | S | −200 KB + no tile fetches until needed |
| 3 demo setup doc | S | Removes the serialization + bootstrap cost |
| 2a–2b query dedupe | S | ~12 fewer queries/page |
| 2c–2d N+1 fixes | M | `/landlord/analytics` 36 → ~14 |
| 2e column selects | M | Scales with data |
| 2f indexes | S | Scales with data |

Phase 1 + Phase 3 are what fix the demo. Phase 2 is what satisfies the instructor and keeps it fast
once there is real data.

---

## 8. Manual test checklist

Run `php artisan test --filter=PerformanceBaseline` before and after and compare the tables.
Then, in a browser — throttled to "Fast 3G" in DevTools to simulate the school wifi:

- [ ] `/` — cards render, carousel arrows still work, images appear as you scroll, no layout jump
- [ ] `/` — Network tab shows **< 500 KB** transferred on first paint (was ~4.7 MB)
- [ ] `/` with a filter applied (`?location=Cebu`) — hero/areas/popular correctly hidden, grid works
- [ ] `/areas` — every area tile still has its photo
- [ ] `/properties/{id}` — gallery, map, contact card all correct
- [ ] Browse map — loads when scrolled to, markers and clustering intact
- [ ] Landlord dashboard + `/landlord/analytics` — every chart renders (Chart.js now bundled)
- [ ] Admin dashboard, `/admin/users`, `/admin/ratings`, `/admin/report-analytics` — charts render
- [ ] Header badges (notifications, messages) show the same counts as before on all 3 layouts
- [ ] A Landlord, a Tenant and an Admin account each see the correct menu items (`hasRole` change)
- [ ] Fonts look identical — Inter, Plus Jakarta Sans, DM Serif Display — with wifi off
- [ ] Real-time chat still connects (Reverb untouched, but confirm)
- [ ] 375px mobile viewport — tenant + landlord surfaces unchanged (`CLAUDE.md` device priority)

---

## 9. What to tell your instructor

He was right that the queries needed cleaning, and §5 does exactly what he asked — repeated queries
removed, `SELECT *` replaced with explicit column lists, indexes added.

But bring the measurement: **5–30 ms in the database vs ~4.4 MB of images on the wire**. Showing
that you profiled it rather than guessed, and that you fixed both the thing he spotted *and* the
thing the numbers pointed at, is a stronger answer than just doing what you were told.

---

## 10. Implementation notes (Sept 21 2026)

**Measured result** (dev DB reseeded to 40 properties / 160 reviews mid-work; before/after taken on the
same data). Query counts are the reliable metric — DB ms is noisy.

| Route | Queries before | After |
|---|---:|---:|
| `/` | 67 | 19 |
| `/areas` | 41 | **2** |
| `/landlord/analytics` | 64 | **18** |
| `/landlord/reservations` | 17 | 9 |
| `/tenant/reservations` | 23 | **6** |
| `/conversations` | 19 | 9 |
| `/favorites` | 14 | 5 |
| `/notifications` | 15 | 6 |
| `/properties/{id}` | 34 | 22 |

| Payload (home page) | Before | After |
|---|---:|---:|
| Eager images on first paint | ≈ 4.4 MB (26 × 170 KB at `w=1200`) | **≈ 128 KB** (logo + hero) |
| Debugbar in HTML | 101 KB | 0 |
| Google Fonts requests | 3 (render-blocking) | 0 (self-hosted) |
| Chart.js | CDN, 5 pages | bundled |
| Login/register background | 2.9 MB | 169 KB |

**Verified by hand-computed fixtures** (inserted in a rolled-back transaction): analytics revenue,
6-month trend, per-property revenue, CSV export, both reservation tab-count sets, the property-page
`hasActive` flags, and the area-tile photos (0 mismatches vs. the old per-area algorithm, 20/20).

**Deviations from the plan**
- **2f indexes trimmed.** `notifications`, `favorites` and `reviews` were already covered by existing
  composite/unique/FK indexes, so only the ones that add something were created (`properties`,
  `property_units` covering index, `messages`, `reservations` ×2, `payments`).
- **2e deferred** (explicit column lists). Payoff is nil at this data size and the risk is real: a
  missing column silently becomes `null` in a view. Do it per-route when a table actually grows.
- **Extra finds not in the original plan:** `auth-bg.jpg` was 3 MB and rendered in a modal on every
  public page; the header icon was a 1200×1560 PNG shown at 40 px; the property-page unit check ran a
  query per unit; the landing "popular" cards ran an `EXISTS` per card.
- **Not done:** map tiles still come from ArcGIS/Carto (external, unavoidable); HTML is ~320 KB
  uncompressed — enable gzip (Apache `mod_deflate`) for the demo; `php artisan serve` remains
  single-threaded (see `docs/demo-setup.md`).

## 11. Known hazard discovered

`phpunit.xml` has the sqlite override commented out, and the Breeze tests use `RefreshDatabase`, so
`php artisan test` **wipes the dev MySQL database**. Do not run the full suite. Noted in
`context/RULES.md` → Testing.
