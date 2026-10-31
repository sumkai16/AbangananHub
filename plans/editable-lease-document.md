# Editable lease document (template + per-tenant edit)

Status: phases 1 to 3 built Oct 29 2026, pending manual verification. Phase 4 (walk-in, API) not started. Superseded in part by plans/lease-templates-page.md: the property-settings editor moved to the Leases page, and walk-ins now use the unit's template. Decided with Axcee on Oct 29 2026: the landlord edits the whole lease like a doc,
starting from a template; edited once per property as a template, then tweaked per tenant before sending.

## Problem
The lease is 10 fixed clauses (`resources/views/leases/_document.blade.php`) filled from data by
`App\Support\LeaseTerms::snapshot()`. The only free text is "Additional terms" (`default_lease_terms`, plain
textarea, 2000 chars). A landlord cannot reword, add or remove a clause.

## The one hard rule
The ledger, escrow and late fees read their numbers from structured data (`agreed_monthly_rent`, `rent_due_day`,
`lease_snapshot.grace_days` / `late_fee`, unit deposit), never from the document text. A free document must not be
able to say a different rent than the system charges.

How the plan keeps that true:
1. **Key terms block is locked.** Parties, unit, start/end, rent, due day, deposit, late fee render first from
   system data. The editor cannot touch it.
2. **Merge fields instead of typed numbers.** The template inserts chips such as `[Monthly rent]`, `[Due day]`,
   `[Security deposit]`, `[Tenant name]`. They are resolved from system data when the agreement is sent, so
   the wording and the ledger can never disagree.
3. **Fixed precedence line** under the Key terms: "If the text below conflicts with these Key terms, the Key
   terms apply."

## Design
- **Storage**
  - `properties.lease_template` (longText, nullable): sanitized HTML. Replaces `default_lease_terms`; existing text
    is migrated into an "Additional terms" section of the starter template.
  - `reservations.lease_body` (longText, nullable): the edited body for one tenant, with merge fields already
    resolved, frozen with `lease_snapshot` when the agreement is sent (signed contract never changes).
  - No lease_body means the legacy 10-clause view, so every existing reservation renders as before.
- **Starter template:** the current 10 clauses rewritten as editable HTML with merge fields.
- **Editor:** TipTap (headless, via npm, bundled by Vite as an Alpine component `<x-lease-editor>`). Headings,
  bold, italic, underline, bullet and numbered lists, undo/redo, "Insert field" dropdown for merge fields. Phone
  friendly toolbar (mobile-first).
- **Sanitizing:** `ezyang/htmlpurifier` with a small allowlist (h2 h3 p strong em u ul ol li br) on every save.
  Never output unsanitized HTML. XSS tests are part of phase 1.
- **Where it is edited**
  1. Property settings: "Lease template" editor.
  2. Inquiry/Negotiation chat and the Reservations modal: "Edit lease" opens the editor pre-filled with the
     template, plus a "Preview as tenant" step before "Send agreement".
  3. Walk-in form (phase 2, same component).
- **Rendering:** `leases/_document` shows Key terms, then `lease_body` when present. Print and PDF keep working
  (plain HTML). Tenant `agreements.show` and `leases.show` need no new UI.

## Phases
1. Sanitizer, migrations, merge-field resolver (`LeaseBody` support class), rendering, tests (XSS, merge fields,
   legacy lease unchanged, snapshot frozen).
2. `<x-lease-editor>` component and the property template screen.
3. Per-tenant edit and preview in the chat and Reservations modal; replace the "Edit terms" textarea added Oct 29.
4. Walk-in form, API read of `lease_body`, docs, manual test guide.

## Risks and open questions
- **Legal:** the platform does not review landlord-written clauses. Keep the "template, not legal advice" footer,
  and decide with the PM whether admin should be able to read any sent lease (it can already read snapshots).
- **Dependencies:** new npm package (TipTap) and composer package (HTMLPurifier).
- **Size:** about 3 to 4 days. Phases 1 to 3 are the useful release.
- **Not covered:** tracked changes, comments, version history, real-time co-editing. Not needed because only the
  landlord edits and the tenant either signs or asks for a change in chat.

## Manual test guide
1. Property settings > Standard lease terms > "Lease wording": click "Edit lease wording". The starter clauses open in a full-screen editor.
2. Type, bold, underline, make a heading and a list. Insert a field ("Insert field" > Monthly rent): it shows as a grey [Monthly rent] chip. Click "Use this lease", then Save the property. Reopen: your wording is there.
3. Paste text with colours or a link from a web page: only plain formatting should remain.
4. In an Inquiry chat, "Edit lease" opens the same editor with your template. Edit one clause, open "Preview as tenant": the summary of key terms sits on top and the chips show real values (rent, due day, tenant name).
5. "Use this lease", tick the terms box if shown, "Accept & send agreement". As the tenant, open the agreement: your wording appears under the key terms, with "If the text below conflicts..." above it. Rent in the summary matches the unit.
6. Change the property template afterwards: the sent agreement does not change.
7. A property with no template still sends the standard 10 clauses.
8. "Go back to the standard lease" (property) and "Reset to starter" (editor) both ask or clear as labelled.
9. Check the editor and the tenant's agreement at 375px, and print the agreement.
