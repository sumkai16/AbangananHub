# Stakeholder test — performance pass (Sept 22, 2026)

Verifies the work committed in `2c53a2a` (`plans/performance-optimization.md`).
Nothing here has been checked in a browser yet — this is the first manual pass.

**Goal:** pages navigate quickly on the school network, and nothing looks or behaves differently.

---

## 0. Before you leave home (at your PC)

Follow `docs/demo-setup.md` in full. The short version:

```bash
git pull                       # make sure the school machine has commit 2c53a2a
npm install                    # new deps: chart.js, @fontsource/*
npm run build                  # never demo from `npm run dev`
php artisan migrate            # adds the performance indexes
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

- [ ] `.env` has `DEBUGBAR_ENABLED=false` and `REVERB_HOST=127.0.0.1` (`.env` is not in git — set it by hand)
- [ ] `ls public/hot` says *no such file*
- [ ] Start: `php artisan serve` + `php artisan reverb:start` (or the Apache vhost, if set up)
- [ ] Browse via `http://127.0.0.1:8000`, **not** `localhost`
- [ ] Open every page you plan to show once (warms OPcache and the browser cache)

> Do **not** run `php artisan test` — it wipes the dev database (`RefreshDatabase` on MySQL).

---

## 1. Capture the numbers (the actual proof)

DevTools → Network → tick **Disable cache** → hard reload (Ctrl+Shift+R). Screenshot the bottom bar
("N requests, X MB transferred, Finish Y s") for each page, on the **school wifi**.

| Page | Requests | MB transferred | Finish (s) | Notes |
|---|---:|---:|---:|---|
| `/` (landing) | | | | before: ~4.7 MB, 26 photos at 1200px |
| `/properties` (browse) | | | | |
| `/properties/{id}` | | | | |
| `/landlord/dashboard` | | | | |
| `/landlord/analytics` | | | | |
| `/admin/dashboard` | | | | |
| `/tenant/reservations` | | | | |

Targets: landing **< 500 KB** on first load. No request to `fonts.googleapis.com`,
`cdn.jsdelivr.net`, or any `images.unsplash.com/...w=1200`.

Then untick "Disable cache" and click between pages — page-to-page redirects should feel instant.

Optional: throttle to **Fast 3G** at home first to rehearse.

---

## 2. Functional checklist

### Public / tenant (test at 375px mobile width too)
- [ ] `/` — cards render, carousel arrows work, photos appear as you scroll, no layout jump
- [ ] `/` — "Browse by area" tiles all show a photo; the "View all" tile collage shows
- [ ] `/?location=Cebu` — hero/areas/popular sections hidden, grid works
- [ ] `/areas` — every tile has a photo
- [ ] `/properties/{id}` — gallery, unit list, contact card, nearby places
- [ ] Logged in as a tenant with a live reservation on a unit — that unit shows as already reserved (`hasActive`)
- [ ] **Browse map:** nothing loads until you click "Show map" (desktop) or the "Map" tab (mobile); then tiles, markers and clustering work, and it fits the container
- [ ] Fonts look identical (Inter body, DM Serif Display headings, Plus Jakarta Sans labels); still right with wifi off
- [ ] Login / register modal and the `/login` page background still show
- [ ] Favorites, notifications, conversations — header badge counts correct
- [ ] Real-time chat still connects (Reverb)

### Landlord
- [ ] Dashboard, properties, reservations (tab counts add up: All = sum of the tabs), tenants, payments
- [ ] `/landlord/analytics` — all four charts render; change the date range; **Export CSV** revenue matches the page
- [ ] Header notification dot and unread-messages badge match reality

### Admin
- [ ] Dashboard, ratings, report-analytics, users pages — charts render (Chart.js is now bundled, not CDN)
- [ ] `/admin/users` → edit a user's **roles** → save → the new roles apply immediately (role cache invalidation)
- [ ] Admin sees "Admin Actions"; landlord sees "Landlord Dashboard"; tenant sees neither

---

## 3. If something is wrong

| Symptom | Likely cause |
|---|---|
| Unstyled / wrong fonts | Forgot `npm run build`, or stale `public/hot` |
| Chart is blank | `npm run build` not run after `git pull` (charts bundle missing) |
| Map never appears | Same — `browse-map` chunk missing; check the console |
| `.env` change ignored | `config:cache` is on → `php artisan optimize:clear` |
| Still slow | Network tab: any `w=1200` image, Google Fonts, or jsdelivr request means a view regressed |
| Role/badge wrong | Report which page + user — the two things that changed there are `hasRole()` and the badge composer |

Triage query counts: `php artisan test --filter=PerformanceBaseline` (read-only, safe).
Expected: `/` 19, `/areas` 2, `/landlord/analytics` 18, `/tenant/reservations` 6.

---

## 4. After it passes

1. Tell Claude the manual pass is done → it deletes `plans/performance-optimization.md` (CLAUDE.md rule).
2. Remaining ideas, in order of value:
   - Serve the demo through **XAMPP Apache with gzip** (HTML is ~320 KB uncompressed; `artisan serve` is single-threaded)
   - `SESSION_DRIVER` / `CACHE_STORE` → `file` (removes a MySQL round-trip per request)
   - Make `phpunit.xml` safe (enable the sqlite override so tests can't wipe dev data)
   - Explicit column lists instead of `SELECT *` (only worth it once tables grow)
