# Compact admin pages

**Status: built, pending Axcee's manual pass.** Delete this plan after that pass.

Scope, as Axcee set it: the dashboard, `verifications/{id}`, `catalogue/properties/{id}`, `properties/{id}/units/{id}`, `reservations` and `payments`. The sidebar and every other admin page stay as they were. The `/admin/payouts` crash is fixed; that page's look is unchanged.

## Manual test guide

Run `npm run build` first, and sign in as an admin. Check each page in light mode and dark mode.

1. **Sidebar:** one Users row (no Landlords or Tenants rows) that stays lit on `/admin/users?role=Landlord`; every other old entry is there, including Payouts and Audit Logs.
2. **`/admin/dashboard`:** everything fits on one screen with no scrolling at 1600 wide. Each queue cell and total opens its list. Quick actions is gone.
3. **`/admin/verifications/2`:** ID and selfie sit side by side at the same height, and clicking one enlarges it. Reject opens the reason box, and pressing it again closes it. Submitting an empty reason is refused.
4. **Approve and reject (use a pending record you can spare):** both ask for confirmation first and then do what they say.
5. **`/admin/catalogue/properties/78`:** photos are one strip and each opens full size. The Request a document form still opens. Reject listing opens its reason box.
6. **`/admin/properties/176/units/261`:** blank fields appear once under "Not filled in". On a pending unit, approve works and reject works with an empty reason.
7. **`/admin/reservations`:** search updates the list as you type, and Clear keeps the current tab. Rows stay on one line, and disputed rows show a cut-off reason with the full text on hover.
8. **`/admin/payments`:** the Status column shows only on All. Release asks for confirmation. Refund tenant opens its modal.
9. **`/admin/payouts`:** the page opens without the lazy-loading error and lists each owed payment with its tenant.
10. **`/admin/catalogue/properties` and `/units`, grid view:** cards are small, the whole card opens the record, and there is no View Details button. Chips are readable over photos. On a phone it is two cards across.
11. **`/admin/catalogue/properties/78` (pending):** looks like `/landlord/properties/78`: hero photo, four facts, status rail and tabs. The rail has Your decision with "4 of 5 checks pass. Missing: verified documents", and Approve and Reject stacked. Approve and Reject both ask for confirmation; Reject needs a reason.
12. **Tabs:** Overview, Units, Photos, Documents, Reservations, Reviews and Activity each open; `.../78#photos` opens Photos. On Documents, Request a document opens its form. The Units tab's View opens the unit page.
13. **Map (Overview):** pin on the street map, Satellite switches to rooftops and back, Open in Google Maps opens the right spot. Open the Photos or Units tab first, then Overview: the map should still fill its box.
14. **`/admin/catalogue/properties/76` (approved):** Listing status shows the readiness summary and no Approve or Reject. **`/176` (no photos):** "No cover photo" in the hero and a warning in the summary.
15. **A listing never pinned** (coordinates 10.3157, 123.8854): Location says "Never pinned", the map has no pin and there is no Google Maps link.
16. **`/admin/catalogue/units`:** looks like `/landlord/units`. Clicking Available, Reserved or Occupied in the strip filters; clicking it again clears. Grid and table both work, and the choice is remembered. In table view each property has its own table, and View property opens the admin property page. A unit card or the eye button opens the popup (photos, rent, facts, amenities, Pending or Approved chip). View property opens the admin property page and Review unit opens the admin unit page. Esc and the X close it.
17. **Phone width:** the rail drops below the tabs on the property page, and unit cards stay one per row.
18. **Unit order:** `/admin/catalogue/units` with the default sort lists Pending units first. Pick Lowest rent or Oldest first and the order follows that instead.
