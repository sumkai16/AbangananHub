# Business-first public identity, and searching by business or listing name

**Status:** Both phases implemented Oct 19 2026 on branch `axci`, pending manual verification (checklist at the end). `ai-search:eval` not run, see the changelog.

## What this plan does

| # | Change | Why |
|---|---|---|
| 1 | Public pages show the **rental business**, not the person: business name and logo on the listing's contact card, a business page instead of a personal profile, the business phone number | The public landlord profile shows the landlord's personal email (with a `mailto:`), personal phone, personal photo and "Email not verified" to anyone. The mobile property API sends the same. Every listed landlord is verified, and verification requires a business name (`StoreVerificationRequest`), so a business identity always exists |
| 2 | Public reviews show tenants as **"Juan D."** | The same privacy rule, applied to tenants on public pages |
| 3 | Search understands **business names and listing titles**: "property ni maria santos" → places by *Santos Rentals Cebu* | Tenants coming from Facebook search by who owns the place. Today that query returns "Nothing matches yet" |
| 4 | When the search names a place or owner we don't have, **say so plainly** | "Nothing matches yet" doesn't tell the tenant why. They should know the name isn't on AbangananHub, not that their filters were too strict |

Build order: **1+2, then 3+4.** They touch different files (profile and listing views vs `app/Services/AiSearch`), so either can go first, but 1 is the privacy fix and should not wait on search.

### Decisions already made (Oct 19)
- **Personal names are not searchable.** Only business names and listing titles. Otherwise anyone could type a person's name and get the list of what they own. "maria santos" still finds *Santos Rentals Cebu* through the shared surname, and the page says which business it matched.
- **The business page is always public while the landlord has a live listing** (the page itself still needs a login, as before; the mobile API route is open). It holds only business information. `profile_visibility` keeps applying to personal (tenant) profiles.
- **Phone reveal uses the business number only.** No business number → no reveal button (Send Inquiry still works).
- **Reviewer names are shortened** on public pages.

### The rule this plan writes down
**Before a deal, people see the business. Once there is a reservation between two people, they see each other.**
Public surfaces (listing page, business page, search, public API) show the business. Private surfaces between the two parties keep personal names, because the lease has to name both: agreement, lease, tenancy pages, reservations lists, chat, notifications, receipts. Admin is unchanged.

---

## Phase 1 — Business-first public identity (items 1 and 2)

### Listing page contact card (`properties/show.blade.php` ~600–724)
| Today | After |
|---|---|
| Initials of the landlord's first and last name | Business logo, else initials of the **business name** |
| "Wilfredo Lozada" | "Lozada Urban Living" |
| "Owner · Listed since 2026" | "Verified landlord · Listed since 2026" (unchanged logic: `hasVerifiedDocuments()` decides "Verified") |
| "View profile →" | "View business →" (same route) |
| Phone reveal shows `users.contact_number` | Shows `rental_businesses.contact_number`; button hidden when it is blank |

Also on that page:
- The landlord's reply under a review (~1256) shows the business name.
- Line 1726 ("To: …") already prefers the business name. Leave it.

One helper so the name and initials are computed in one place, not repeated across views:
`User::publicName()` returns the business name, else first name + last initial, for the rare landlord with no business row. `User::publicInitials()`. Add a Blade component `<x-business-avatar :landlord="…" size="…">` (logo or initials) for the card, the business page banner and review replies.

### Business page (`landlord/profile/show.blade.php`, `Landlord\ProfileController::show`)
Same URL (`/landlord/{user}/profile`). No route change, so existing links keep working.

**Visitors see:**
- **Banner:** business logo (or business initials) instead of the personal photo, and the business name as the title. Subtitle: "Verified landlord · Member since October 2026".
- **Details tab:** About and Business (name, address, description). The **contact number is behind a login + reveal**, same as the listing card. "Identity: Verified landlord · Checked Jul 30 2026" stays: it's a trust signal with no personal data.
- **Removed:** personal email, personal phone, "Email not verified", personal full name. The report modal subtitle uses the business name.

**The owner (`$isOwner`) keeps seeing everything.** Each personal field gets a small "Only you can see this" note, so the landlord understands what tenants don't see. Add a "View as tenant" toggle only if it is cheap. Not required.

**Visibility:** `ensureVisible()` no longer 404s a landlord who has at least one browsable listing. A landlord with no live listing still follows `profile_visibility`. This fixes a live bug: today a landlord set to Private has a "View profile" link on every listing that leads to a 404.

### Reviewer names (item 2)
`User::shortName()` returns "Juan D." Used on:
- `properties/show.blade.php` (~1224–1228, the review list)
- `landlord/profile/show.blade.php` (~294 featured review, ~387 review list)

Not changed (private or staff surfaces): admin views, the landlord's own dashboard and property pages, notifications, tenant ratings.

### Mobile API (public endpoints)
`PropertyResource` nests the full `UserResource` for `landlord`, which includes `email`, `email_verified_at` and `contact_number`. For the public property endpoints, replace it with a `PublicLandlordResource`:
`{user_id, business_name, logo_url, business_contact_number, verified, member_since}`.
`ReviewResource` → `tenant` becomes `{user_id, short_name, profile_picture}` on public endpoints.
**Mobile-app impact:** any screen reading `property.landlord.first_name/email/contact_number` must move to the new keys. List them in the changelog entry.

Keep the full `UserResource` where the viewer is a party (reservations, conversations, the user's own record).

### Tests (`PublicIdentityTest`)
- A guest's listing page shows the business name and not the landlord's personal name, email or personal number.
- The phone reveal renders the business number; with a blank business number there is no reveal button.
- A visitor's business page has no personal email, personal number or "Email not verified". The owner still sees them.
- A Private landlord with a live listing: the business page is 200. With no live listing: 404.
- Review names render as "Juan D." on both public pages.
- The public property API has no `email` or `contact_number` under `landlord`.
- `grep` guard in the test: `properties/show.blade.php` must not contain `landlord->first_name` (stops a later edit from bringing it back, the same idea as `BrandLogoTest`).

---

## Phase 2 — Search by business or listing name, and "not found" (items 3 and 4)

### How names are matched (local, never the AI)
New `App\Services\AiSearch\NameMatcher`. RULES.md › AI search: a model never queries the database or states facts, and the provider gets only the sentence and public listing facts. So matching happens here, against our own tables, after the intent is read.

1. **The index:** names of landlords with at least one browsable property, `rental_businesses.business_name`, plus `properties.title` of browsable properties. Cached 10 minutes, the same as `Property::searchLocations()`.
2. **Distinctive words:** each name is cut into words, minus generic ones: rentals, rental, homes, properties, property, living, stays, boarding, house(s), dorm(itory), apartment(s), rooms, family, cebu, north, south, western, urban, residences, and the city and barangay names. "Santos Rentals Cebu" → `santos`; "Pepito Homes North Cebu" → `pepito`; "Mabolo Garden Apartment" → `mabolo garden`.
3. **A hit** = every distinctive word of a name appears in the query (normalized with `Landmarks::normalize()`). Business hits beat title hits. A one-word distinctive name needs that word to be at least 4 letters, so short words don't make false matches.
4. **Name phrases are recognized** so the leftover words can be reported: `ni`, `kang`, `kay`, `owned by`, `by`, `named`, `called`, `property of`, `place of`, `sa ... ni` (en/tl/ceb). "property ni maria santos" → wanted name "maria santos".

### What the intent gets
A new intent key `host`: `null | {landlord_ids: list<int>, name: string, kind: 'business'|'listing', property_ids?: list<int>}`.
- `IntentValidator` re-checks the ids against browsable properties (a posted-back intent can't point at anything else) and keeps the name only from our own table, never the tenant's text.
- `IntentValidator::isEmpty()` counts `host` as searchable.
- `CandidateFinder::query()` adds `whereIn('landlord_id', …)` (or `whereIn('property_id', …)` for a title hit). `host` is **never relaxed** in `ladder()`, the same as property type: someone who asked for Santos's places only gets Santos's places in the main list.
- **Clash with the AI's location guess:** the model may put "maria santos" into `location` as `kind: text`, which then matches nothing. When `NameMatcher` hits, drop a `text` location whose words are all inside the matched name phrase.
- `MatchExplainer` hit: "Listed by Santos Rentals Cebu". `ChipBuilder`: a removable chip "By Santos Rentals Cebu".
- `SummaryComposer`: "Showing places by **Santos Rentals Cebu**." When the tenant typed a different full name ("maria santos"), add "the closest business name we found", so a near miss is visible and never presented as an exact match.

### "Not found" (item 4)
When the query **names** something (a name phrase from step 4, or the AI's `location` is `kind: text` and returns zero rows) and `NameMatcher` finds nothing, the results payload carries `name_notice: {name}` and the page shows:

> **No listing or business called "maria santos" on AbangananHub.**
> Only verified, approved listings appear here. Check the spelling, or browse all places.
> [Browse all places]

- Wording says *listing or business*, never "no landlord named …", because personal names aren't indexed and the page must not confirm or deny that a person exists.
- If the rest of the query still has filters ("boarding house ni Juan in Talamban under 3k"), drop the name and show those results **below** the notice, labelled "Other places that match the rest of your search".
- Rendered in `properties/partials/ai-results.blade.php` (the results HTML), not the page's `notice` slot: that slot is for AI-availability messages, and this one depends on the results. Amber, like the slot.
- Logged: `ai_search_logs` gets the miss in its `intent` JSON (no schema change), so the admin can see which names tenants look for that aren't on the platform.

### Tests (`NameSearchTest`, plus extend the AI search suite)
- "property ni maria santos" → only Santos Rentals Cebu's places, chip present, summary names the business.
- "Lozada Urban Living" and "kang Lozada" → Lozada's places. "Mabolo Garden Apartment" → that listing first.
- "boarding house ni Juan Dela Cruz" (no match) → `name_notice` with "juan dela cruz", no false host match.
- A landlord's **personal first name** alone ("Wilfredo") matches nothing (privacy decision).
- A business with no browsable listing is not matched. A posted-back intent with a foreign `landlord_id` is dropped by the validator.
- Plain searches without a name ("room near IT Park under 5k") are unchanged: no `host`, no notice. Run `php artisan ai-search:eval` before and after. It spends real quota, so run it once.
- Keyword-fallback path (AI off) behaves the same as the AI path for names.

---

## Docs to update
- `context/RULES.md`: the "before a deal → business; after → person" rule, next to "A picker must not be a directory".
- `context/ARCHITECTURE.md` decision log: business-first public identity, personal names not searchable, names matched locally.
- `context/CHANGELOG.md`: one entry per phase, including the mobile API key changes.
- `plans/ai-search.md`: the new `host` intent key.

## Manual test checklist (375px first, then desktop, light and dark)
1. As a guest, open a listing: business name and logo/initials, "View business", no personal name anywhere on the page. Reviews read "Juan D.".
2. Log in as a tenant, reveal the phone: the business number. Use a landlord with no business number: no button.
3. Open the business page as a tenant: no personal email or phone. Then as the landlord: everything still there, with "Only you can see this".
4. Set a landlord with live listings to Private: the business page still opens from the listing.
5. Search "property ni maria santos", "kang Lozada", "Lozada Urban Living", "Mabolo Garden Apartment": the right places, a chip, a summary naming the business.
6. Search "boarding house ni Juan Dela Cruz": the "No listing or business called…" notice, and other boarding houses below it.
7. Search "room near IT Park under 5k": same results as before this change.
