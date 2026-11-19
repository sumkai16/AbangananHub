# Admin approval queues redesign

**Status: built Nov 7 2026, pending Axcee's manual pass.** Delete this plan after that pass.
The mockups (20 boards, five directions for each page) are in the Claude artifact "Admin approval queues: 5 mockups per page".

## What was wrong

Four pages shared one layout: a big four-cell stat strip (repeating the tab counts), tabs, a list card, then "Recently decided" (a copy of the Approved tab) and a checklist stacked underneath. A normal day has an empty Pending queue, so the page was a tall strip, a one-line "No units here" box, and filler. None of it filled the screen.

## What was picked

| Page | Direction | Why |
|---|---|---|
| Unit Approvals `/admin/units` | **Lean table**, then folded into Units (Nov 7) | The queue is now the "needs approval" filter on Units (Property Management), with Approve and Reject on the row. The sidebar row is gone; see the changelog entry "Unit Approvals folded into Units". |
| Property Documents `/admin/documents` | **Lean table** | Quick decision. Review opens the existing document page (preview and verify stay there). |
| Property Verifications `/admin/listings/approval` | **Review desk** | Queue on the left, the selected listing on the right: photos, readiness, units, documents, tick-list, then Approve or Reject. No page-per-listing. |
| Landlord Verifications `/admin/verifications` | **Review desk** | Comparing the ID with the selfie is the whole job, so they sit side by side with the checks the system ran. |

## Shared pieces (all opt-in, nothing else in the admin changes)

- `x-admin.stat-line`: the four numbers as one thin line in the page header (replaces `x-stat-strip` on these four pages only).
- `x-admin.checklist`: the "Before you approve" button with a popover (replaces `x-admin.queue-aside`, which no page uses now).
- `x-admin.decision-bar`: the footer of a desk. Approve, and Reject which opens the reason box. Same confirm dialogs and field names as `x-admin.decision`.
- `admin-fill` + `<x-card fill>` + `<x-empty-state flat fill>`: from the Payments work (DESIGN.md §40).
- The desk selects a row with `?open={id}` (server-rendered, no JavaScript fetch), so a link to a selected listing works and the back button behaves.

## Default tab

All four open on **All** with All as the first tab (same as Payments). On All, waiting items come first, oldest waiting on top, then the rest newest first. Steps 1 to 4 of the test guide below start from All: the first rows should be the waiting ones.

## Not changed

Routes, controllers' decisions and notifications, audit logging, the confirm dialog wording, the document review page, the sidebar. "Recently decided" is gone because the Approved and Rejected tabs hold the same list. The checklist tick boxes on the desks are a memory aid only; nothing is blocked if they are not ticked.

## Decisions to know about

- **Status chips use the shared `x-admin.status`** (good states are coral with white text, 2.3:1). The mockups used a green tint. Same open question as Nov 6; these pages follow the rest of the admin until it is decided.
- **Reject on the unit table needs a reason**, so Reject opens a small dialog (like the old listings page) instead of posting straight away.
- **Landlord approval still goes through the desk's confirm dialog** ("This will grant the applicant the Landlord role").
- **Not-Pending tabs** (Approved, Rejected, All, Suspended) open the same desk read-only: status, reason and decision date instead of buttons. Suspended listings show Unsuspend.

## Manual test guide

Run `npm run build` first. Sign in as an admin. You need a pending item of each kind: a unit, a listing, a landlord application and an uploaded document. Reseed or submit them from a landlord account if the dev data has none.

1. **Units (was Unit Approvals):** open Units; a highlighted line says how many units need a decision, **Show them** filters to them. **Approve** a unit (confirm dialog, then "approved" flash, the row disappears). **Reject** another: a dialog asks for a reason; empty reason is refused; with a reason the row disappears and the landlord is notified. The "Before you approve" button opens a list; Esc and an outside click close it.
2. **Property Documents:** same layout; Review opens the document page. Empty tabs show a centred message with "See all".
3. **Property Verifications:** the left list shows pending listings, oldest first; click one: the right side changes and the address bar gets `?open=`. Reload keeps it. The readiness rows show photos, units and documents. Tick the boxes (nothing is saved). **Approve** (the property and its pending units go live); **Reject** (reason box, landlord notified). Open the Suspended tab: Unsuspend appears instead.
4. **Landlord Verifications:** pick an applicant; the ID front and the selfie show side by side; click one to see it large. The three checks match the old review page. Approve and Reject work as before. A rejection needs a reason.
5. **Empty and other tabs:** with nothing pending, each page shows the centred empty state. Approved and Rejected tabs open the desk read-only.
6. **Phone width (375px):** admin is desktop-first, but nothing should break: tables scroll inside the card, the desk stacks (list above, detail below).
