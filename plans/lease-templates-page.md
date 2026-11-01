# Leases page: lease template library per property and unit

Status: phases 1 to 3 built Oct 29 2026, pending manual verification. Phase 4 (Sent & signed tab) built the same day; phase 5 docs done. Decided with Axcee on Oct 29 2026. Builds on `plans/editable-lease-document.md`
(phases 1 to 3 built the editor, merge fields, sanitizer and the frozen `lease_snapshot.body`).

## Decisions (Oct 29 2026)
| Question | Answer |
|---|---|
| Structure | **Template library.** Named leases ("Bedspace lease", "Private room lease") assigned to properties or units. |
| Which lease is sent | **Unit, then property, then the standard 10 clauses.** Most specific wins. |
| What a template sets | **Wording + rent rules (grace days, late fee) + notice period + extra house rules.** Blank = fall back to the property, then the platform default. |
| House rules | **Listing rules + lease extras.** The lease always includes the property's listed rules; a template can only add to them, so the signed lease never contradicts the listing. |
| Old "Lease wording" editor in property settings | **Moves to the Leases page.** Existing property wording becomes a template named after the property. |
| Per-tenant edit when sending | **Kept.** Opens the resolved template; changes apply to that tenant only. |
| Sent and signed leases | **Listed on the page** in a "Sent & signed" tab. |

## Data
- **New table `lease_templates`**
  - `lease_template_id` PK, `landlord_id` FK users (cascade), `name` varchar(100)
  - `body` longtext null: sanitized HTML with merge fields (`LeaseBody::clean`). Null = the standard 10 clauses.
  - `rent_grace_days` tinyint null, `late_fee_type` varchar(10) null (`none` | `fixed` | `percent`; null = use the
    property's rule), `late_fee_value` decimal(8,2) null, `notice_days` smallint null, `extra_house_rules` json null
  - timestamps
- **`properties.lease_template_id`** and **`property_units.lease_template_id`**: FK nullable, `set null` on delete,
  so deleting a template sends the standard lease instead of breaking anything.
- **Migration of today's data:** every property with `lease_template` text gets a template named
  "<property title> lease", assigned to that property; then `properties.lease_template` is dropped (added on
  Oct 29, dev data only).
- **Snapshot gains** `template` (`{id, name}`) so the Sent & signed tab can say which template a lease came from,
  and the frozen values stay the source of truth (`grace_days`, `late_fee`, `notice_days`, `house_rules`, `body`).

## Resolution (one place)
`App\Support\LeaseTemplates::for(Reservation|PropertyUnit)`: unit template ?? property template ?? null.
`LeaseTerms::snapshot()` calls it, so **every path that issues a lease gets it for free** (chat, Reservations
modal, walk-in form, API):
- `body`: per-tenant edit ?? template body ?? none (standard clauses)
- `grace_days`: template ?? property ?? `rentals.rent_overdue_grace_days`
- `late_fee`: template (`none` means no fee) ?? property rule
- `notice_days`: template ?? `rentals.lease_notice_days`
- `house_rules`: property rules + template `extra_house_rules`, duplicates removed

`LeaseTerms::rentRules()` already reads the frozen snapshot, so the ledger charges what the lease says.

## The page: sidebar "Leases" (after Tenants), `/landlord/leases`
Mobile-first (375px). Three tabs, `?tab=` in the URL.

1. **Templates**: a card per template with name, where it is used ("3 properties, 2 units"), and a one-line rules
   summary (grace, late fee, notice). Actions: Edit, Duplicate, Delete (confirm says what falls back to the
   standard lease). "New lease" starts from the standard clauses or a copy of another template. The standard
   lease is shown as a read-only card at the top ("Used when nothing is set").
2. **Where used**: each property with a template dropdown (default "Standard lease"), expandable to its units,
   each with "Same as property" or a template. The dropdowns save on change, like the Payments filters.
3. **Sent & signed**: every reservation with a lease snapshot. Tenant, property · unit, template name, status
   (Awaiting signature, Signed, Active, Ended), sent and signed dates, View / Print (existing
   `landlord.leases.show`). Filters: property, status, search. Desktop table, phone cards.

**Template editor** (`/landlord/leases/templates/{id}/edit`, full page, not a modal):
- Name; the TipTap editor inline (the `<x-lease-editor>` component gets an `inline` mode).
- Side panel (stacked under the editor on phones): grace days, late fee (Use property rule / No fee / Fixed /
  Percent), notice days, extra house rules (add/remove list).
- **Preview with a property**: pick one of the landlord's properties; renders the full lease with that property's
  data and a sample tenant ("Sample Tenant"), clearly marked as a sample.
- Note on the page: "Changes apply to agreements you send from now on. Leases already sent don't change."

## Changes elsewhere
- **Property settings:** the "Lease wording" editor is replaced by "Lease: Bedspace lease · Change on the Leases
  page". Rent rules stay there as the property's fallback, with a note when a template overrides them.
- **Unit edit page:** same one-line "Lease used" with a link.
- **Chat and Reservations modal:** the "Edit lease" label names the template being sent ("Sending: Bedspace
  lease"); the editor opens on that template's body.
- **Routes:** `landlord.leases.index` (page), `landlord.lease-templates.{create,store,edit,update,destroy,duplicate,preview}`,
  `landlord.lease-templates.assign` (PATCH, property or unit). `landlord.leases.show` is already taken by the
  tenancy lease page and stays as it is.
- **Policy:** a landlord sees and assigns only their own templates and properties; assigning another landlord's
  template id is refused (tested).

## Phases
1. **Data and resolution:** migration + model + `LeaseTemplates::for()` + `LeaseTerms::snapshot()` changes +
   migrating property wording. Tests: unit beats property beats standard, fallbacks per field, house rules merge,
   rent rules frozen and charged by the ledger, delete falls back, walk-in picks up the template.
2. **Templates tab and editor page:** CRUD, duplicate, inline editor, rules panel, sample preview.
3. **Where used tab:** property and unit assignment, links from property settings and unit edit; remove the old
   property editor.
4. **Sent & signed tab.**
5. **Chat labels, docs (SCHEMA, ARCHITECTURE, CHANGELOG, DESIGN if a new pattern), manual test guide.**

About 3 to 4 days. Phases 1 to 3 make it usable; 4 and 5 finish it.

## Risks
- **Rent rules in two places** (template and property). The page must always say which one applies, or
  landlords will edit the wrong one. The rules summary on each template card and the note in property settings are
  the guard.
- **Late fee tri-state** (use property / none / set). `late_fee_type = null` means "use property"; `none` is an
  explicit no-fee. Easy to get wrong; tested.
- **Templates edited after sending** never change sent leases (frozen snapshot). Stated on the editor page.
- **Admin** cannot see templates; it can already read sent snapshots. Open question from the editable-lease plan.
- **Mobile API** still does not read `body` or templates (phase 4 of the editable-lease plan).

## House rules sections (Oct 29 2026)
Built; pending manual verification. Manual test guide:
1. Policies > edit a policy > **House rules**. Under "Applies to" tick a property, then open House rules: its listed rules appear read-only with an Edit link.
2. Click "+ Visitors" and "+ Curfew and quiet hours": two sections with starting rules. Edit a rule, add one, remove one, rename a section. The row summary reads "2 sections, N rules · listing rules included".
3. Save, then send an agreement on that policy. As the tenant, the lease has **House rules**: "Property rules" first, then your sections with their headings.
4. Save with a section that has a title but only blank rules: it is dropped. A rule left blank is dropped.
5. A lease sent before this change still shows its rules as one list.
6. Click **Hide** on a section: its rules fold away with "Hidden: kept here but left off the lease", and the row summary says "1 hidden". Save and send: the lease does not show it. **Show** brings it back; **Delete** (shown only while hidden) removes it for good.

## Redesign: Policies page (Oct 29 2026, mockups 3 and 4)
Built; pending manual verification. Manual test guide:
1. Sidebar says **Policies**. The page opens on **By property**: pick a property, see "This property uses" with its policy and chips.
2. Change the property's policy in the dropdown: it saves at once. "Edit policy" opens the editor.
3. Under Units, switch a unit on: a policy dropdown appears; pick one. Switch it off: it goes back to "Same as property".
4. "Your policies" on the right lists each policy with how many places use it; "+ New policy" starts a new one.
5. In the editor, rows show a one-line summary (clause count, rent rules, documents, "N properties"). Open one at a time; change grace days and watch the Rent rules summary update.
6. **Wording > Open editor** fills the screen: a white page on a grey desk with the toolbar above. Type, insert a field, **Done**. The summary says "N clauses". Close with the X to discard.
7. Save with a bad late fee (percent, no amount): the Rent rules row opens by itself with the error.
8. The chat's "Edit policy" (send agreement) opens the same full-screen editor with an Edit / Preview switch.
9. Check By property and the editor at 375px; the full-screen editor should use the whole phone screen.

## Sent & signed and the move-in checklist (Oct 29 2026)
Built; pending manual verification. Manual test guide:
1. Leases > Sent & signed lists every agreement you sent, newest first, with the lease name and version (or "Standard lease"), status, and sent / signed dates.
2. Filter by status, property and tenant name; Clear resets. Walk-in tenants carry a Walk-in tag.
3. "View / Print" opens the frozen lease. Edit the template afterwards: the row and the lease do not change.
4. In a chat after sending an agreement, the side card shows "Move-in checklist 1 of 6". As the tenant, "Emergency contact and ID on file" links to Account Settings. It ticks off as the tenant signs, pays and the keys are handed over.
5. Check both at 375px (rows become cards; the checklist sits in the side card only on wide screens, as the card does today).
Not built: required documents per lease. Needs its own plan.

## Round 2 (Oct 29 2026, from a reference app): starters by type, assign from the editor, versions, welcome, cover
Built; pending manual verification. Manual test guide:
1. Leases > New lease shows "Start from" chips. Pick Bedspace: the name reads "Bedspace lease" and the wording has a "Shared room" clause. Pick Blank page: almost empty.
2. Type a welcome message, tick two properties under "Use this lease for" (try Select all / Clear), save. Where used shows both on this lease.
3. Untick one and save: that property goes back to "Standard lease". A property's units with their own lease keep it.
4. Change a clause and save: the badge reads "Version 2" and the card "v2". Only renaming does not change the version.
5. Send an agreement on that lease; as the tenant the welcome message sits under the names. Print it: page one is a cover with property, unit, tenant, landlord and reference.

## Manual test guide (phases 1 to 3)
1. Sidebar shows **Leases** after Tenants. The Templates tab shows a "Standard lease" card. If a property had wording saved in settings earlier, it appears as "<property> lease" and is in use.
2. **New lease**: the standard wording opens inline. Name it "Bedspace lease", edit a clause, insert a [Monthly rent] field, set Late fee = Fixed 200, Notice = 15, add an extra house rule. Save. The card shows "0 properties, 0 units", the rules line and "Not used".
3. Leave Late fee as "Percent" with no amount and save: an error asks for the amount.
4. **Preview as tenant**: pick a property. The summary of key terms is on top with that property's rent, your wording below, then House rules (listing rules first, then your extra rule). The tenant is "Sample Tenant".
5. **Where used**: set a property to "Bedspace lease". Expand its units: each says "Same as property (Bedspace lease)". Set one unit to another lease. The page reloads with the choice kept.
6. Property settings > Standard lease terms shows "Lease: Bedspace lease · Change on the Leases page". Unit edit shows the unit's lease ("from the property" when inherited).
7. In an Inquiry chat for that property: "Edit lease" reads "Sending: Bedspace lease" and opens your wording. Send it; as the tenant the agreement has your wording, the 200 late fee line and 15 days notice.
8. Edit the template afterwards: the sent agreement does not change.
9. **Duplicate** a template: the copy opens named "Copy of ...". **Delete** a template in use: the confirm says how many places fall back; after deleting, Where used shows "Standard lease" there.
10. Check every screen at 375px (tabs, cards, dropdowns, inline toolbar scrolls sideways, rules panel stacks under the editor).
