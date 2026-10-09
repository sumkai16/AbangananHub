# CHANGELOG.md — what changed and why (Oct 2026)

Newest first. One entry per piece of work, with the files to read. Longer reasoning lives in ARCHITECTURE.md (Key Decisions Log), DESIGN.md §31, RULES.md and SCHEMA.md; this file is the index. Entries up to `94f2e15` came from branch `joseph`; the Oct 8 entries below are on `axci`.

## Oct 9 2026 — merged `origin/joseph` into `axci` (budget filter, icon-only toolbar, landlord CTA)
- Joseph's two commits (budget card in the Filters modal, icon-only browse toolbar, landlord CTA panel) are in. They sit well with AI search: budget now lives in the Filters modal, which is where "Refine with filters" sends people.
- **Conflicts resolved:** `properties/index.blade.php` (Filters modal form: took Joseph's version because the budget card needs it, which also brings back the wider `max-w-7xl` modal the Oct 8 revert had removed) and `context/DESIGN.md` (both sides numbered sections §32 and §33, so ours are now §35 logo + field errors and §36 AI search; Joseph's §32-§34 unchanged).
- **One test changed:** `BudgetFilterTest` asserted the old search pill carried a chosen price as hidden fields; the AI box submits only `q` (a sentence is a fresh search), so it now asserts that instead.
- `components/search-pill.blade.php` is unused on both sides now (Joseph removed its Budget field and the AI box replaced it); delete it after the browser pass.

## Oct 9 2026 — AI search: describe the place, get the best or closest matches (branch `axci`, uncommitted)
- The Where / Type / Budget pill is replaced by one "Describe your ideal place" box (`components/ai-search-box.blade.php`, hero and header variants). A plain GET form to `/properties?q=`; `PropertyController::index()` renders `properties/ai.blade.php` for it and `resources/js/ai-search.js` fills it in. English, Tagalog, Bisaya and mixed.
- **Two AI calls per search, and the AI never touches the database.** (1) `interpret`: sentence -> JSON intent, validated against our own lists by `IntentValidator`. (2) `rank`: orders ≤ 20 candidates and writes the summary (own language + English). Hit/gap chips on each card come from `MatchExplainer` (real columns), not the AI.
- **Closest matches:** `CandidateFinder` runs the exact query; below 3 results it relaxes one filter at a time (furnishing, amenities, house rules, budget +15/+30%, wider radius / whole city / nearby cities, type). Every row still says what it misses ("₱500 over budget", "7.7 km away"). "Near X" uses `config/landmarks.php` (**coordinates are approximate, drafted from memory: verify on a map**) and Haversine in PHP.
- **Providers:** `AiSearchDriver` interface; `GeminiDriver` (free tier, for testing) and `NullDriver`. `AnthropicDriver` is Phase 5 and not written. Choose with `AI_SEARCH_DRIVER` + `GEMINI_API_KEY` / `GEMINI_MODEL` in `.env`; `php artisan ai-search:models` lists the models a key can call. No key = keyword search only.
- **Guardrails:** 20 searches/hour per user or IP, 8/day overall by default (`AI_SEARCH_DAILY_CAP`), 8 s timeout + 1 retry, caches (24 h per sentence, 10 min per result set). Any failure, limit or missing key lands on `KeywordFallback` plus a template summary and an amber "smart search is busy" notice with Retry. `ai_search_logs` table (30-day prune).
- `php artisan ai-search:eval` scores the live driver on `tests/fixtures/ai-search-cases.json` (30 sentences, EN/TL/CEB). 27 new tests in `tests/Feature/AiSearch/`; suite 97 passing.
- Deviation from `plans/ai-search.md`: results are fetched with `POST /search/results` (not GET), because the chip-edited intent travels in the body. Privacy page now names Google/Anthropic as processors of the search text.
- **First live Gemini run (Oct 9, `gemini-3.5-flash`, 19 of 30 cases answered):** location 68%, price_max 75%, amenities 55%, property_type / rules / for 100%. The model left `location` and `price_max` null and dumped them in `unmapped`, so `Prompts::interpret()` was rewritten (fill every field it can, synonym map for aircon/wifi/parking/labadora, city + landmark rules, 3 more examples). **The rewritten prompt has NOT been re-measured yet.**
- **Free-tier reality (changes the plan's numbers):** Gemini free tier is **20 requests/day per model**, not ~1,000. A search is 2 calls, so ~10 searches/day, and the 30-case eval does not fit in one day. Also `gemini-2.5-flash` returns 404 "no longer available to new users" (use `ai-search:models` to see what the key can call), and `gemini-3.x` models often answer 503 "high demand" or time out past the 8 s limit. `AI_SEARCH_DAILY_CAP` default is now 8 and `GEMINI_THINKING=minimal` asks the model to skip long reasoning (untested whether every model accepts it).
- **OpenRouter driver added (same day, no budget for Anthropic):** `OpenRouterDriver` (OpenAI-style API, JSON mode, shape spelled out in the prompt, fenced JSON tolerated). Free models end in `:free`: 20 requests/minute, **50/day**, 1,000/day once $10 of credit has ever been bought. `OPENROUTER_MODEL` is the whole upgrade path to a paid model. Default `google/gemma-4-31b-it:free`; `nvidia/nemotron-3-super-120b-a12b:free` and `openrouter/free` also advertise structured output. Free model lists rotate: re-check openrouter.ai/models?max_price=0.
- **Live OpenRouter eval (Oct 9, `dots-studio/dots-3-note-preview:free`, the one free model that worked):** 22 of 30 answered. **Every field 100%** (location 21/21, price_max 14/14, amenities 9/9, rules 6/6, type 13/13, for, furnishing) except language 83% (some Tagalog/Bisaya called "mixed", harmless: "mixed" gets English summaries). The 8 misses were "no usable JSON": a reasoning model cut off by `max_tokens` (raised 1500 -> 4000). It is slow (10-15 s), a preview model that may disappear, and `google/gemma-4-31b-it:free` was jammed upstream (429), `nvidia/nemotron-3-super-120b-a12b:free` too slow. Failed requests did not count against the 50/day. **Prompt rewrite confirmed: location 68% -> 100%, price_max 75% -> 100%, amenities 55% -> 100%.**
- **Free-model reliability (hours later, same day):** the `dots` preview model took 28 s and then timed out at 40 s; `google/gemma-*:free` returned 429 (Google's shared pool); `openrouter/free` (a router that picks any available free model) answered in 12 s, so it is the default now. Trade-off: the model behind it changes, so quality can vary; re-run `ai-search:eval` after switching. Free capacity is shared and unpredictable: a paid model (or $10 of credit for 1,000 free requests/day) is the real fix.
- **Eval on `openrouter/free` (22 of 30 answered):** location 95%, type / price / amenities / furnishing 100%, rules 83%, language 50%. Fixes: (1) `LanguageDetector` reads Tagalog / Bisaya from the words typed (17/17 on the labelled cases, no AI needed, also fixes the keyword fallback's summary language) and overrides the model's guess in `AiSearchService::interpret()` and the eval; (2) `provider.require_parameters` so OpenRouter routes only to models that support JSON mode (6 of 8 failures were prose / empty answers); (3) prompt now spells out "no curfew" / "smoking is fine" / "visitors allowed" -> rules. Rules + location fixes are confirmed on 2 repeat runs of the one failing sentence; the full 30 have not been re-run (free quota).
- **Instant keyword results first.** Because free models take 10-15 s, the page asks for a keyword-only reading (`interpret` with `fast: true`, no AI, not rate limited) and shows those results at once, then replaces them when the AI reading arrives ("Reading your full request with AI…"). `AI_SEARCH_TIMEOUT` default is now 40 s (the free model measured 13 s, then 28 s an hour later; the concurrency cap keeps workers free).
- **Concurrency limit (`AI_SEARCH_MAX_CONCURRENT`, default 5):** at most 5 AI calls run at once across all visitors, because each holds a PHP worker for as long as the model takes (10-15 s on free models). Over it, the search answers with keyword results and a "Lots of people are searching right now" notice (`fallback_reason: crowded`) and does not spend the daily cap. The slot counter lives in the cache and expires after 60 s, so a crashed request can't hold a slot. Raise it once a fast paid model is in use.
- **The second AI call is now optional (`AI_SEARCH_RANK`, default off).** Off = one AI call per search (read the sentence); PHP orders by fit score and writes the summary from real data (en / tl / ceb templates). That halves latency and doubles searches per free quota. On = the AI also reorders and writes the summary ("AI summary" label shows).
- **Recommendation (if a budget appears later):** use Anthropic for real testing (Haiku 5.5, about $0.10 / $0.50 per 1M tokens, so the whole eval costs a cent or two). That needs the `AnthropicDriver` (Phase 5) and an API key.
- **Not done / needs Axcee:** re-run the eval once quota resets (`php artisan ai-search:eval --only=...` for subsets) or an Anthropic key; check landmark coordinates; browser pass on the checklist in the plan; `AnthropicDriver`; mobile API endpoint (Phase 6, plan only). Old `components/search-pill.blade.php` is now unused (delete after the browser pass).

## Oct 8 2026 — inline field-error tags replace the browser's validation bubble (`faaef48`, branch `axci`)
- The browser's "Please fill out this field." popup could not be styled and differed per browser. `resources/js/field-validation.js` (loaded from `app.js`) now turns it off on **every** form and shows a short tag on the field's top border instead: "Required", "Invalid email", "Min 8 characters", "Numbers only", "Doesn't match". The full sentence is screen-reader only and a hover tooltip. Checkboxes/radios keep a visible red line. Styles: `.fv-*` in `app.css`.
- Also in that script: `*_confirmation` fields must match their base field; `type="tel"` inputs drop letters as typed; a password strength meter on any `<input data-strength>` (advice only, the 8-character minimum still blocks); `window.fieldValidation.show()` places a fetch 422 error on its field.
- Signup modal (`layouts/app.blade.php`): server errors now land on their own field (login keeps its banner); password has `minlength="8"`; contact number has a digits-only pattern. `RegisteredUserController` and `Api/AuthController` reject non-phone `contact_number` (`regex:/^[0-9+()\s-]{7,20}$/`). Register and reset-password pages got `minlength="8"` + the meter.
- Bug found while testing: `bad.forEach(showError)` passed the list index as the override argument, so the first bad field got an empty tag. Fixed.
- Not changed: profile "New password" form (own `pw` state), and all other `contact_number` rules (profile, verification, business) still allow letters server-side. Not verified in a browser beyond the signup modal. Rule: RULES.md "Forms: validation". Design: DESIGN.md §35.

## Oct 8 2026 — new house-and-keyhole logo from the mobile app (`25bfb58`, branch `axci`)
- Web now uses the mobile app's mark. SVGs copied from `AbangananHubMobile/assets/brand/` to `public/images/brand/` (`logo-mark`, `logo-mark-on-dark`, `logo-mark-mono`, `logo-app-icon`).
- Light surfaces (public header, 404) use `logo-mark.svg`; dark surfaces (admin/landlord sidebars, public footer) use `logo-mark-on-dark.svg`; favicon is `logo-app-icon.svg` in all layouts. The wordmark stays HTML text.
- Old `public/images/AbangananHub-*.png` files are now unused and left in place. Supersedes the Aug 20 2026 logo row in ARCHITECTURE.md.

## Oct 8 2026 — home page redesign reverted (`b8be7d1`, branch `axci`)
- `properties/index.blade.php` restored to its `c18e701` version, undoing the `94f2e15` changes to that file. `components/section-texture.blade.php` restored too: the old page uses it and `94f2e15` had deleted it.
- **Reverted:** two-column hero and "Why AbangananHub" panel, minimal "Browse by neighborhood", two-row home grid, wider filters modal, the texture removal.
- **Kept from `94f2e15`:** category icons, header search band `py-5`, 404 font, inline form errors, the browse-sort index migration.
- Checked: `/properties` and `/properties?sort=price_asc` return 200 with no log errors. Not checked in a browser. DESIGN.md §31 notes which parts are gone.
- `origin/joseph` still has the redesign, so a future merge of `joseph` could bring it back.

## Oct 8 2026 — browse-sort index migration rollback fixed (`6a7240d`, branch `axci`)
- `2026_10_08_000000_add_browse_sort_indexes` could run but not roll back: `down()` failed with MySQL error 1553 because the new `reviews_property_rating_index` had become the `reviews.property_id` foreign key's index.
- `down()` now adds `reviews_property_id_index` before dropping the composite. Checked on `abanganan_hub_test`: rollback, migrate, rollback, migrate all succeed; suite 70 passing. Dev database unaffected (only `down()` changed). Rule: RULES.md "Laravel Conventions".
- `origin/joseph` still has the old `down()`.

## Oct 9 2026 — budget moved from the search pill into the Filters modal
- Landlord CTA section: the isometric-building illustration is replaced by a "From sign-up to first tenant" panel (3 steps + live listing, unit and area counts). DESIGN.md §34.
- The search pill no longer has a Budget field (`components/search-pill.blade.php`); a price chosen in Filters rides along as hidden `price_min` / `price_max`.
- New `<x-budget-filter>` card at the top of the Filters modal: percentile-based quick ranges with live counts and a "Most" tag, a slider whose steps stop at the dearest unit, a histogram, typed Min / Max, "Typical rent" (median) and an exact matching-listings count. Details: DESIGN.md §32.
- `Property::budgetBands()` / `budgetHistogram()` are now derived from the available units instead of a fixed table; new `budgetStops()`, `budgetMedian()`, `budgetPoints()`.
- `BrowseFilters::activeCount()` counts a price as one filter; the modal's Clear all and "N selected" include it.
- Search-pill field hover/focus is a visibly darker `#ECEEF6` (dark-mode rule added in `resources/css/app.css`).
- Test: `tests/Feature/BudgetFilterTest.php` (4 tests). Not yet checked in a browser.
- Browse toolbar (Sort, Filters, Show map, Clear all, Saved) is icon-only with hover/focus tooltips, giving the property-type chips about 250px more room; the strip is full width and the chips stretch to fill it (no blank margins or gap). `components/category-strip.blade.php`; DESIGN.md §33.

## Oct 8 2026 — teacher feedback pass + home page redesign (`94f2e15`)
> The `properties/index.blade.php` and `section-texture` parts of this entry were reverted in `b8be7d1` (above).
A teacher reviewed the public home page. Each suggestion was checked against the code; some were applied, some declined. Plan: `plans/teacher-feedback-pass.md`. Design details: DESIGN.md §31.

Applied:
- **Hero** two columns from `lg` with a "Why AbangananHub" trust panel; search pill centered below. `properties/index.blade.php`.
- **"Browse by neighborhood"** centered and restyled minimal (no borders or pills, rounded-3xl, lighter gradient, quiet "View all areas" tile). Same file.
- **Home grid** capped at two rows at every width (cards hidden per breakpoint, "View all" tile stays last).
- **Filters modal** wider (`max-w-7xl`) and tighter, to avoid the desktop scrollbar.
- **Search band spacing** `py-4` → `py-5` in `layouts/app.blade.php`.
- **Category icons** redrawn for Apartment, Condominium, Boarding House (`components/category-strip.blade.php`).
- **404 page** uses Plus Jakarta Sans instead of the only serif on the site (`errors/404.blade.php`).
- **Removed** `components/section-texture.blade.php` (dots, stripes, wavy lines in the side gutters).
- **Forms:** `novalidate` + per-field inline errors on login, register, forgot/reset password, profile edit, password change, delete account. Login/forgot/reset lost their single top banner. RULES.md "Forms".
- **Sort performance:** migration `2026_10_08_000000_add_browse_sort_indexes` (SCHEMA.md). No measurable change on dev data (~8 ms before and after).

Declined (with the reason):
- **"Font looks like Times New Roman":** the app uses Inter and Plus Jakarta Sans, never a serif. The Times look was unstyled text from CSS failing to build when npm packages were missing (see Environment below).
- **"Profile: Show all properties must stay on the profile page":** no such redirect exists. `landlord/profile/show.blade.php` "View all" switches tabs in place and "Show more" loads the next 12 via fetch.
- **Native validation removed from all forms:** limited to auth + profile on purpose (RULES.md "Forms").
- **A cache layer for sorting:** `CACHE_STORE=database` and the `cache` table is what froze MariaDB.

## Oct 6 2026 — landlord: unit double-booking bug (`c88056c`)
- `Reservation::releaseUnit()` freed a unit even when another live reservation held it, so rejecting or cancelling one inquiry could re-list a unit another tenant had reserved. The controller guards ran after the unit was already freed.
- New `Reservation::releaseUnitIfUnclaimed()` is used by `reject()` and `cancel()`; `endTenancy()` still uses `releaseUnit()`. Guards removed from `Landlord\ReservationController` and `Api\Landlord\ReservationController`. Tenant-side cancel is covered too.
- Checked by creating two live reservations on one unit in tinker (cancel one → unit stays Reserved; reject the second → Available) and by the reservation/tenancy/cancel tests.

## Oct 5 2026 — admin: deposit charge oversight (`2be180b`)
- `ReservationPolicy::voidDepositCharge()` now also allows Admin. `recordDepositCharge` unchanged.
- New `Admin\DepositChargeController` (`index` with Active / Voided / All tabs, `void` in a locked transaction with `AuditLog`, notifies tenant **and** landlord), routes `admin.deposit-charges.index` / `.void`, view `admin/deposit-charges/index.blade.php` with a void-reason `<x-modal>`, and a "Deposit Charges" sidebar link in `layouts/admin.blade.php`.
- Checked in tinker: admin sees and voids a charge, both notifications created, a tenant hitting the route gets 403, the landlord's own void still works. Test data deleted afterwards.

## Oct 5 2026 — tenant: read-only lease view (`3e86290`)
- `viewLease` already allowed tenants but no tenant route existed. New `Tenant\LeaseController` with `tenancy.lease` and `tenancy.lease.file` (gated by `viewLease`, not the landlord-only `manageLease`).
- `leases/show.blade.php` takes `isLandlordView`; `Landlord\LeaseController::show` passes `true`. The "View lease" link in `tenant/tenancy/show.blade.php` was fixed.
- Checked in tinker: own tenant 200, a different tenant 403, landlord unchanged.

## Environment (not in a commit)
- `npm install` was needed: `@fontsource/inter`, `plus-jakarta-sans`, `dm-serif-display` and `chart.js` were in `package.json` but not installed, so Vite showed an error overlay and `npm run build` failed. Fixed by installing; build passes. Rule: RULES.md "Dev environment gotchas".
- Pending `emman` migrations (viewing requests, blocked dates, viewing hours, lease fields, deposit charges, property-type changes) were run on the dev DB.
- Dev MariaDB froze on the `cache` table; restarted `mysqld`. ARCHITECTURE.md Known Tradeoffs.
- Branch check: `joseph` was fully merged with `origin/main` (`c18e701`) before the teacher pass.

## Known and still open (landlord survey, Oct 6 2026)
Verified but not fixed; full list in ARCHITECTURE.md Known Tradeoffs.
- Mobile list views: Tenants page empty on phones when `tenantsView` is `table`; Units defaults to a wide table; properties and tenancy ledger tables lack a mobile card fallback.
- No audit log or tenant notification in `Landlord\PaymentController::store()` and `Landlord\LeaseController::upload()`.
- `landlord.properties.units.show` has no controller method (500); `data-confirm-type="danger"` is invalid in `landlord/viewings/_row.blade.php`; no sidebar highlight on the Viewings tab; six landlord loops miss `depositCharges` eager loading; no viewing, lease or deposit-charge endpoints on the landlord API.
- Admin has no oversight yet of viewing scheduling or lease agreements (only deposit charges were done).

## Not verified in a browser
Everything above was checked with tinker requests, Blade compile and the test suite (36 passing for auth, profile, browse, property, reservation and viewing tests), never by clicking through. Pending a manual pass: hero layout, filters modal scrollbar, two-row cap per breakpoint, icons, dark mode, mobile width, and the empty-form inline errors.
Update Oct 8 2026: `ViewingSchedulingTest::test_only_the_reservations_tenant_can_request` now passes. The tests build their own fixtures (`tests/Support/CreatesMarketplaceFixtures.php`, `b529738`), and the full suite runs green on `abanganan_hub_test` (70 tests).
