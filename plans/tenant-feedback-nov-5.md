# Tenant page feedback (Nov 5 2026)

Status: **built Nov 6 2026 (1644 tests pass), pending manual verification.** Rendered in headless Chrome at 375px and 1440px as tenant, landlord and guest; not clicked through by hand.

Plan with Opus, code with Sonnet. Mobile-first: check 375px first.

## What the PM asked

| Ask | Done as |
|---|---|
| Remove Areas and How it works from the tenant page | Hidden for signed-in users, desktop and phone menu. Guests keep them. The landing page has both sections. |
| Remove Become a Landlord (sign-up has its own page now) | Gone from the header, phone menu and footer. Footer "For Landlords" shows for landlords only. |
| Message icon on top, beside the notification | One icon next to the bell at every width. The floating bubble is gone from these pages. |
| Red indicator for notifications or messages | Both are the same red numbered pill; the message one updates live. |
| Send inquiry should not need move-in and move-out dates | Only the unit is required. Dates and occupants are an optional fold. |
| The search is "so close" (screenshot: Search button crowds the edge of the box) | 10px even gap round the button, 40px button at every width. |

## Decisions

| Decision | Choice | Why |
|---|---|---|
| "List your property" for signed-in tenants | Kept on the landing and About pages | Otherwise an existing tenant account has no way to apply |
| Message icon on desktop | Opens the full `/conversations` page, not a popup | Same as phones, simpler. Desktop loses the quick preview the bubble gave |
| Occupants | Optional too | Same reason as the dates; the landlord list already says "Occupants not given" |
| A lease with no move-in date | Refused: Accept & send is hidden and blocked; Send agreement needs the date in its form | The ledger, due day and lease start all come from it |
| Landlord list with no date | "Not decided yet" | It used to show the inquiry date as if it were the move-in |
| Verification status | Kept for anyone who has applied | They need it to see their result |

## Manual test guide (browser)

Phone width first (375px), then desktop.

1. Log in as a tenant with no landlord application. Header shows Home and Browse Rentals only. No Areas, How it works or Become a Landlord, also in the phone menu (hamburger).
2. Scroll to the footer: no "For Landlords" column.
3. Log out. Header shows Areas and How it works again.
4. Log in as a tenant. The message icon sits right next to the bell, at 375px and at desktop width. Click it: the messages page opens.
5. From another account (landlord), send this tenant a message. The message icon gets a red number without a reload (needs Reverb: `composer dev`). Open the chat: the number goes away.
6. Trigger a notification for the tenant (for example the landlord confirms a payment). The bell gets a red number.
7. Log in as a landlord, open the public home page: message icon and bell are in the header too. On the landlord dashboard the floating chat bubble is still there.
8. Open any listing as a tenant, tick the agreement box, press **Send Inquiry**. The form shows only a collapsed "Add move-in details · optional", the rent box and the message. **Contact Landlord** is enabled with nothing filled in.
9. Send an inquiry with only a message. It lands in the chat with no dates.
10. Open the fold, pick a move-out date but no move-in date, send. Error under the move-out field: "Pick a move-in date first, or leave both blank." The fold is open after the error.
11. Repeat 8 to 10 on a phone (375px): the sheet has the same fold and hint line.
12. As the landlord, open that inquiry in Reservations: Move-in reads **Not decided yet**, not the inquiry date.
13. In the chat as the landlord: no "Accept & send agreement" button, a note says "No move-in date yet", **Accept & negotiate first** works.
14. After accepting, press **Send agreement**: without a move-in date it is refused; with one it goes through.
15. Open Browse (`/properties`) at 375px and desktop: the Search button has the same gap above, right and below, and a space from the box edge.
16. On a wide screen (about 1860px) switch between Browse, Messages, Notifications and a listing: the logo, the message icon, the bell and the avatar stay in exactly the same place, and the Messages page lines up with the logo's left edge. The landlord's Messages page (sidebar layout) is unchanged.

## Not done / left alone
- A quick message preview dropdown on desktop (the bubble's panel). Easy to add beside the bell if the PM misses it.
- The sidebar of the landlord layout and the mobile API are unchanged.
