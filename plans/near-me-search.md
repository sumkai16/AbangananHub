# "Near me" search, including "places by Tudtud Boarding Houses near me"

**Status:** Implemented Oct 19 2026 on branch `axci`; server side tested, page flow pending manual verification (checklist below). Follows `plans/business-first-identity-and-name-search.md` (the `host` intent key).

## What this does

A tenant types a sentence with "near me" in it, alone or with a business name:

- "boarding house near me under 4k"
- "places listed by tudtud boarding houses near me"

The browser asks for the tenant's location once. The results are ordered nearest first, every card says how far it is, and the page confirms the spot it used and lets the tenant correct it:

> **Using your location, near Tunghaan, Minglanilla.** Not here? **[Type your area]**

## What exists today (so we build on it, not around it)

| Fact | Where |
|---|---|
| Search already supports "near a point": a landmark has `lat`/`lng`, a radius, a bounding-box pre-filter plus a distance test, a "3.2 km from X" hit on each card, and a map origin circle | `CandidateFinder::query/withinBox`, `MatchExplainer`, `AiSearchController::mapOrigin` |
| The browser asks for the tenant's location in **one** place: the Directions button on a listing map, once, not saved, with a typed-address fallback. Landlords are asked only to pre-place a pin when listing | `resources/js/maps/property-map.js`, `location-picker.js` |
| Nothing is stored about a tenant's position, and search does not use it | — |
| `host` (a named business or listing) never relaxes and does not conflict with a location | `NameMatcher`, `CandidateFinder` |

"Near me" is therefore a landmark whose coordinates come from the browser instead of `config/landmarks.php`.

## Decisions (Oct 19)

1. **The spot is named from our own listings.** The server finds the closest area (barangay and city) that has live listings, so the tenant's coordinates never go to a third party. If they stand in an area with no listings it names the nearest neighbouring one, which is why the page lets them correct it. (Alternative rejected: reverse-geocoding with OpenStreetMap, which names the exact barangay but sends coordinates to Nominatim on every near-me search.)
2. **A named business shows all of its places, nearest first.** With a `host` there is no radius cut-off: every place they list appears, sorted by distance, each saying "X km from you". Without a `host`, near me behaves like "near IT Park": a radius that widens 2, 4, 8 km.
3. **Location lives for one search.** Not stored in the database, not logged, not remembered between visits, never sent to the AI provider.

## Design

### 1. Reading the sentence (`App\Services\AiSearch\NearMe`, new)
- Detects the phrase in English, Tagalog and Bisaya: *near me, nearby, around me, close to me, closest to me, malapit sa akin / sa ako / sa akoa, duol nako, dapit nako*. A small list with word boundaries, like `KeywordFallback`.
- `AiSearchService::interpret()` **removes the phrase before** the AI or keyword reading and sets `intent['near_me'] = true` afterwards. Reason: the model would otherwise file "me" as a text place, which finds nothing and now triggers the "doesn't match any place" notice. The cached AI intent stays free of it, the same as `host`.
- `IntentValidator` carries `near_me` (bool) through post-backs.

### 2. The point (`location.kind = 'point'`)
- New location kind `point`: `{kind: 'point', lat, lng, name, area}`.
- `IntentValidator::location()` accepts it only from the page: `lat`/`lng` numeric, **inside `config('cebu.bounds')`**, rounded to 3 decimals (about 110 m). Anything else is dropped. The `name` and `area` are **always recomputed on our side**, never taken from the request (same rule as `host.name`).
- `App\Services\AiSearch\NearestArea` (new): from live listings, a cached (10 min) list of `{barangay, city, avg lat, avg lng}`, nearest by `Landmarks::distanceKm`. Gives `{name: 'Tunghaan, Minglanilla', city, distance_km}`. A point outside the Cebu box is rejected with its own message (below).

### 3. Finding and ordering (`CandidateFinder`, `MatchExplainer`)
- **Without `host`:** the point is a landmark: `withinBox` plus the circle test with `radius_km` (default 2, ladder 4 and 8, capped at 10 as now). Order: exact first, then score, then **distance**, then rating, then price. (For landmarks the order stays as today; distance-first applies only to `point`, because "near me" means nearest.)
- **With `host`:** no box and no circle. All of the host's places, ordered by distance.
- `MatchExplainer`: hit "**3.2 km from you**"; beyond the radius and without a host, a miss "7.7 km away", same as landmarks today. `distance_km` feeds the map pins.
- Chip: "Near you · Tunghaan" (removable). Removing it keeps the rest of the search.
- Summary line, in en/tl/ceb like the others: "Places by Tudtud Boarding Houses, nearest to you first."

### 4. The page (`resources/js/ai-search.js`, `ai-results.blade.php`)
- After `interpret`, if `intent.near_me` and there is no point yet: phase "Finding your location…" → `navigator.geolocation.getCurrentPosition` (8 s timeout, normal accuracy is enough) → set `intent.location = {kind:'point', lat, lng}` → `fetchResults`. The coordinates live in the Alpine state only.
- If the AI reading replaces the intent mid-flight (the page already does this), the point is carried over.
- **Confirm line** at the top of the results (a new `location_notice` from the server): "Using your location, near **Tunghaan, Minglanilla**." with **Not here? Type your area**. Submitting the field rewrites the intent: `near_me` and the point off, `location = {kind: 'text', value: typed}`, and the existing place logic resolves it (barangay, city, landmark, free text). One-tap, no new query syntax.
- **Failures, none of them errors:** denied, unavailable or timed out → the search still runs without a place, with a notice "We couldn't get your location. Allow it in your browser, or type your area." plus the same input and a **Use my location** retry button. A location outside Cebu → "Your location looks to be outside Cebu. Type the area you want." These follow the existing rule that a search never returns an error page.
- Map: the origin marker reads "You" and the radius circle shows only when there is no `host`.

### 5. Privacy (this is the part that needs care)
- Coordinates are **never sent to the AI provider**: they are attached after `interpret`, on the `results` call only.
- `AiSearchLog` stores the intent as JSON. For `kind = 'point'` the log keeps the **area name only**, not `lat`/`lng` (stripped in `AiSearchService::log`).
- Not saved on the user, a cookie or localStorage. Gone when the tab closes.
- `legal/privacy.blade.php` gets one sentence: location is used once, for that search, and is not stored. RULES.md > AI search gets the matching rule ("coordinates are for one search; never logged, never sent to a provider").
- Browsers only offer location in a secure context: the production domain's SSL already covers it, and `127.0.0.1` counts as secure in development.

### Out of scope
- Remembering a home area, IP-based location, "near me" in the Where box or the classic filter page, and distance on classic browse cards. Each is a separate decision; none is needed for this.

## Tests
- **`NearMeTest` (new):** detection and stripping in en/tl/ceb, no false hit on "nearby" inside a name; the AI path never receives the phrase.
- **Validator:** a `point` inside Cebu survives and is rounded; outside the box, non-numeric and forged `name` / `area` are dropped or recomputed.
- **`NearestArea`:** nearest area for a spot, a spot between two areas, a spot with no listings nearby (names the neighbour), outside Cebu rejected.
- **Finder:** with `host`, a place 25 km away still shows, ordered nearest first; without `host`, the radius and ladder apply and order is by distance; the other types and other landlords' places stay out of a named search.
- **Explainer / chips / summary:** "3.2 km from you", the "Near you · Tunghaan" chip, the removed chip leaves `host` alone.
- **Privacy:** `ai_search_logs.intent` for a point has no `lat` or `lng`; the interpret call carries no coordinates (assert on the fake driver's input).
- **HTTP round trip:** interpret returns `near_me`, results with a point return the notice and distance-ordered cards.
- **Unchanged:** "room near IT Park under 5k", "boarding house ni juan dela cruz" and ordinary searches behave exactly as before.

## Manual test checklist (375px first, then desktop, light and dark)
1. "places listed by tudtud boarding houses near me": the browser asks, then Tudtud's places appear nearest first with "X km from you", and the confirm line names an area.
2. Chrome DevTools > Sensors: set the location to Minglanilla, then to Talisay, and watch the order and the area name change.
3. Tap **Not here?**, type "Mandaue": results move to Mandaue, the chip reads Mandaue.
4. Block location in the browser: a notice, no error, and the type-your-area field works. Retry with **Use my location** after unblocking.
5. Sensors set to Manila: the "outside Cebu" notice.
6. "boarding house near me under 4k" (no business): radius behaviour, nearest first.
7. Check the network tab: the `interpret` request carries the sentence only, no coordinates; the `results` request carries rounded coordinates.
8. Run "room near IT Park under 5k" and "property ni maria santos": same as before.

## Docs to update when it lands
`context/RULES.md` (the privacy rule), `context/ARCHITECTURE.md` decision log (point kind, area named locally, no radius for a named business), `context/CHANGELOG.md`, `plans/ai-search.md` (intent keys), the privacy page.

## Size
Medium. Server work (NearMe, NearestArea, point kind, finder/explainer/chip/summary, log strip) is one pass; the page flow (geolocation, confirm line, change-area, failure states) is the second and carries the risk, because it cannot be unit tested and must be checked by hand. One agent in sequence, since the pieces share the intent shape.
