# AI search: describe the place, get the best (or closest) matches

> Design: canvas https://claude.ai/artifact/ERzErWXZSSUVuc7ymGgTft, row **A+** (A+1 to A+6).
> Assumed from Axcee's last message: design = A+, landmarks = config list (option 1). Change here if wrong.

## Context

Search today is three exact SQL filters (Where `LIKE`, Type, Budget) plus a Filters modal.
A tenant who types "room near USC Talamban, Wi-Fi, under 5k, I have a cat" gets nothing useful,
and when filters are too strict the page is simply empty. Goal:

- One "Describe your ideal place" box replaces the Where/Type/Budget pill.
- Works in English, Tagalog, Bisaya (and Taglish/mixed); the AI replies in the same language.
- Shows exact matches, or the **closest** ones with an honest note on what's off ("₱500 over budget").
- Free model (Gemini free tier) for testing, Anthropic later by changing one env value.
- Everyone can use it (guests too), rate-limited, with a keyword fallback when the AI is down.
- Web now; one shared service so the mobile app gets an API endpoint later with no rework.

Decisions already made (with Axcee):

| Decision | Choice |
|---|---|
| UI placement | Replace the pill with one box; Filters + category chips stay |
| Matching | AI extracts filters → DB finds candidates (relaxing if needed) → AI ranks + writes summary |
| Reply language | Same as the search |
| Provider | Gemini free tier now, Anthropic later (driver switch) |
| Access | Everyone, rate-limited |
| Mobile | Web first; API endpoint planned (Phase 6) |
| Result display | AI summary on top + per-card ✓/gap chips |
| "Near X" | Landmark list with coordinates, distance in km |

## How a search works (2 AI calls)

```
query ─► [1 Interpret]  AI → JSON intent ─► IntentValidator (whitelists) ─► chips shown
                                                     │
                                                     ▼
                          CandidateFinder: exact query; if < 3 results, relax step by step
                                                     │  (each candidate gets ✓ hits / − misses, computed in PHP)
                                                     ▼
          [2 Rank + summarize]  AI gets ≤ 20 candidates' public facts → ordered ids + summary
                                                     │  (summary in user's language AND English)
                                                     ▼
                                   results page (A+3 exact / A+4 closest)
```

Key rule: **the AI never touches the database and never invents facts.**
- Call 1 output is validated against whitelists before any query (unknown values dropped).
- ✓/gap chips on cards are computed by PHP from real columns, not by the AI.
- Call 2 may only reorder ids it was given and write the summary; ids not in the candidate set are dropped.

### Intent schema (call 1 output)

`location` {kind: lgu|barangay|landmark, value}, `radius_km` (landmark only, default 2),
`property_type` (enum from properties.property_type), `price_min`, `price_max`,
`amenities` (names from `amenities` table), `rules` (keys of `BrowseFilters::RULES`),
`living` (`PropertyPolicies::LIVING_ARRANGEMENTS`), `for` (`BrowseFilters::SUITABLE_FOR`),
`furnishing` (`BrowseFilters::FURNISHING`), `occupants` (int), `language` (en|tl|ceb|mixed),
`unmapped` (phrases it understood but we can't filter on, e.g. "quiet street" — mentioned in the summary, never queried).
"5k", "4k", "₱5,000", "limang libo", "lima ka libo" → 5000 (prompt examples + PHP sanity clamp 500–200,000).

### Relaxation ladder (when exact < 3 results)

Drop/widen one step at a time, stop once ≥ 3 candidates, max 20:
1. furnishing, living, for
2. amenities beyond the first two the user named
3. house rules (pets/smoking/overnight/curfew)
4. price_max +15%, then +30%
5. location: landmark radius 2 → 4 km; barangay → its city; city → neighbouring LGUs (config map)
6. property_type

Each candidate keeps a score: 100 minus weighted misses (budget overage proportional). Ties: rating, then price.

## Files

New (all under `app/`, mirroring the `Services`/`Support` split already in the repo):

| File | Job |
|---|---|
| `config/ai_search.php` | driver, keys/models from env, timeouts (8 s), min results (3), max candidates (20), rate limits, daily cap |
| `config/landmarks.php` | ~30 Cebu landmarks: key, name, aliases ("usc tc", "usc talamban"), lat, lng, city. Drafted by Claude, checked by Axcee |
| `app/Services/AiSearch/AiSearchDriver.php` | interface: `interpret(string $q, array $vocab): array`, `rank(array $intent, array $candidates, string $lang): array` |
| `app/Services/AiSearch/GeminiDriver.php` | Laravel `Http` client (same style as `OcrService`), `generateContent` with `responseMimeType: application/json` + `responseJsonSchema` |
| `app/Services/AiSearch/AnthropicDriver.php` | Phase 5. Official SDK `anthropic-ai/sdk`, `outputConfig: ['format' => ['type' => 'json_schema', ...]]` |
| `app/Services/AiSearch/NullDriver.php` | used in tests and when no key is set; always "fails" so fallback runs |
| `app/Services/AiSearch/Prompts.php` | the two system prompts + vocabulary block (types, amenities, rules, LGUs, landmarks) — stable text first so it caches |
| `app/Services/AiSearch/IntentValidator.php` | whitelist every field; reuse `BrowseFilters`, `PropertyPolicies`, `config('cebu.lgus')`, `Property::searchLocations()`, `Amenity` names |
| `app/Services/AiSearch/CandidateFinder.php` | builds on `Property::browsable()->browseFilters()`; adds landmark distance (Haversine `selectRaw` on latitude/longitude) and the relaxation ladder |
| `app/Services/AiSearch/MatchExplainer.php` | per property: hits/misses list from real columns (price vs budget, amenities incl. unit-level, house_rules, distance) |
| `app/Services/AiSearch/KeywordFallback.php` | no-AI parse: matches LGU/barangay/landmark names, type words (EN/TL/CEB), `\d+k`/`₱\d+` budget |
| `app/Services/AiSearch/AiSearchService.php` | orchestrates: cache → rate/daily cap → driver → validate → find → rank → result DTO; any exception → fallback |
| `app/Http/Controllers/AiSearchController.php` | `POST /search/interpret` (JSON chips), `GET /search/results` (HTML partial) |
| `resources/views/components/ai-search-box.blade.php` | A+1 box (textarea, EN/TL/CEB marks, Try asking chips, Recent from localStorage) |
| `resources/views/properties/partials/ai-results.blade.php` | A+3/A+4/A+5: summary card, chips (amber failing chip, + Add opens existing Filters modal), cards |
| `resources/js/ai-search.js` | Alpine component: submit → interpret (chips fill, A+2) → fetch results HTML; Cancel via AbortController; recent searches |
| `database/migrations/…_create_ai_search_logs_table.php` | query, language, intent JSON, result count, relax steps, driver, latency ms, tokens, fell_back; `user_id` nullable; pruned after 30 days (`Prunable`) |
| `app/Console/Commands/AiSearchEval.php` | `php artisan ai-search:eval` runs `tests/fixtures/ai-search-cases.json` (30 queries EN/TL/CEB + expected intent) against the live driver, prints pass/fail per field |

Changed:

| File | Change |
|---|---|
| `resources/views/properties/index.blade.php`, `layouts/app.blade.php` | `<x-search-pill>` → `<x-ai-search-box>` (hero + header band). Keep `search-pill` file until verified, then delete |
| `resources/views/components/property-card.blade.php` | optional `$fit` prop: rank badge ("#1 best fit"/"Closest match") + ✓/gap chips (max 3, "+N") |
| `app/Http/Controllers/PropertyController.php` | `index()` accepts `?q=`: renders the page shell with the box pre-filled; results load via JS (shareable URL) |
| `routes/web.php` | two routes, `throttle:ai-search` |
| `app/Providers/AppServiceProvider.php` | `RateLimiter::for('ai-search')`: 20/hour per user id or IP; bind driver from config |
| `config/services.php` | `gemini` (key, model) and `anthropic` (key, model) entries |
| `.env.example` | `AI_SEARCH_DRIVER=gemini`, `GEMINI_API_KEY=`, `GEMINI_MODEL=`, `ANTHROPIC_API_KEY=`, `ANTHROPIC_SEARCH_MODEL=` |
| `resources/views/legal/privacy.blade.php` | add Google (Gemini) / Anthropic to the third-party list: search text is sent to them, no account data |
| `context/ARCHITECTURE.md` | decision row: AI search + Haversine only for landmark queries (refines the "Text search over Haversine" row) |
| `context/DESIGN.md`, `context/CHANGELOG.md`, `context/SCHEMA.md` | A+ rules, entry, `ai_search_logs` table |

## Limits, cost, safety

- **Gemini free tier (corrected Oct 9: it is ~20 requests/day per model, ~10 searches/day, and 3.x models often 503/time out; see CHANGELOG)**: model ID goes in `GEMINI_MODEL` — pick the current free Flash model in AI Studio (third-party sources disagree on IDs/limits; AI Studio's rate-limit page is the truth). (The original 1,000–1,500/day estimate was wrong.)
- **Daily cap**: `ai_search.daily_cap` counter in cache (default 8 searches on the free tier); over it → keyword fallback (A+5) for everyone until midnight PH time.
- **Cache**: interpret result 24 h by normalized query hash (lowercase, collapsed spaces). Rank+summary 10 min by hash(intent + candidate ids + updated_at max). Repeat searches cost 0 calls.
- **Free-tier data note**: Google may use free-tier prompts to improve its models. We send only the search text and public listing facts (title, type, area, price, amenities, rating) — no names, emails, IDs. Covered in the privacy page update.
- **Prompt injection**: the search text and listing titles are untrusted; prompts mark them as data. Output is whitelisted, so the worst case is a weird summary, never a weird query.
- **Timeouts**: 8 s per call; one retry on 429/5xx with backoff; then fallback.
- **Anthropic later (Phase 5)**: `composer require anthropic-ai/sdk`; default model `claude-opus-5-5` (the SDK guide's default; thinking can't be disabled, so use `effort: low`). Haiku 5.5 ($0.10/$0.50 per 1M) or Sonnet 5.5 are cheaper/faster choices — Axcee's call when switching. Include the server-side refusal fallback the SDK guide recommends.

## Phases

1. **Core, no AI**: config, landmarks list, IntentValidator, CandidateFinder (+ relaxation, distance), MatchExplainer, KeywordFallback, NullDriver, service, log table. Unit tests.
2. **Gemini**: driver, prompts, eval command + 30 cases. Tune prompts until eval ≥ 90% per field (location, type, price, amenities, rules).
3. **Web UI (A+)**: box, Alpine flow (A+1 → A+2 → A+3/4/5), card `$fit`, desktop A+6, 375 px first.
4. **Guardrails**: rate limiter, daily cap, caching, privacy page, docs in `context/`.
5. **Anthropic driver** (when Axcee has a key): driver + same eval run to compare.
6. **Mobile (plan only now)**: `POST /api/search/ai` (throttled, `auth:sanctum` optional) returning `{intent, chips, summary, summary_en, exact: bool, results: PropertyResource[] + fit}`; Expo screen mirrors A+ mobile. Same `AiSearchService`, no backend logic duplicated.

## Verification

Automated (`php artisan test`):
- IntentValidator drops unknown type/amenity/LGU, clamps price, maps "5k".
- CandidateFinder: exact hit; zero-hit query relaxes in ladder order; landmark radius uses distance.
- MatchExplainer: budget overage text, unit-level amenity counts as a hit.
- Service falls back to KeywordFallback on driver exception, timeout, invalid JSON, daily cap (Http::fake).
- Controller: throttle returns fallback, not an error page; guests allowed.

Manual (Axcee, in browser, per `test-before-commit`):
1. Set `GEMINI_API_KEY`, `GEMINI_MODEL`; run `php artisan ai-search:eval` — read the per-field scores.
2. 375 px: "Room near USC Talamban, Wi-Fi, under 5k, may pusa ko" → chips fill in, exact or closest results, ✓ chips match the listing page.
3. Bisaya: "Naa bay apartment sa Mandaue ubos sa 4k, naay parking?" → reply in Bisaya, "Show in English" works, amber chip on the failing filter, one-tap fix re-runs.
4. Remove a chip → results update without a new interpret call (check `ai_search_logs`).
5. Unset the key → A+5 fallback with Retry; hit 21 searches in an hour → fallback, no error.
6. Desktop 1280 px layout (A+6).
