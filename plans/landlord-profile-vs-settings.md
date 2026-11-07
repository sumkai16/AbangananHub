# Landlord: what goes in My Profile vs Settings

## Context
The landlord sidebar has two Account items that overlap.

| Page | Route | What it shows now |
|---|---|---|
| **My Profile** | `landlord.profile.me` | The public business page: banner, Details, Properties and Reviews tabs. Owner-only extras: email, personal number, visibility, GCash. |
| **Settings** | `profile.edit` | Profile strength meter, Personal info (name, email, number, **bio**, visibility), Password, a **Payout pointer** card, **Business information** form, Danger zone. |

Problems:
- **The business page is edited somewhere else.** It is edited in Settings, but it lives on My Profile. Its "Edit profile" and "Add them" links jump across to `Settings#business`.
- **Private facts sit on the public page.** My Profile shows email, personal number and GCash under "Only you can see this", on a page meant to show what tenants see.
- **GCash is in three places.** My Profile, the Settings pointer card, and Payments › Payouts, which is now its real home.
- **Settings has tenant-only parts:**
  - **Bio:** shown only on tenant profiles. Landlords use the business description instead.
  - **Profile strength:** counts photo and bio.
  - **Trust tip:** the copy is aimed at tenants.
  - **"Email status: Not verified":** shown to everyone, but there is no email-verify flow.
  - **Visibility wording:** "visible to landlords you've reserved with" is written for tenants.

**The rule after this change:**
- **My Profile** is your business page as tenants see it, and you edit it there.
- **Settings** is your private account: who you are, how you log in, and who can see your page.

## What goes where (landlord only; tenant and admin pages unchanged)

### My Profile (`resources/views/landlord/profile/show.blade.php`)
- **Keep:**
  - The banner: logo, business name, Landlord and Verified badges, member since.
  - The stat tiles, About, Business (address and contact), and Identity (verification).
  - The Listings and Reviews side cards, plus the Properties and Reviews tabs.
- **Add:**
  - **"Edit business page" button**, replacing "Edit profile". It opens a sheet with the business form: logo, name, description, business contact and address. It is a bottom sheet on phones and a dialog on desktop, built the same way as `landlord/complaints/partials/_file-report-modal.blade.php`.
  - **Empty-state links** ("Tell tenants about your rentals", "Add them") open the same sheet instead of going to Settings.
  - **Owner-only strip** at the top: "This is what tenants see", plus the visibility status. It says "Public while you have a live listing" or "Private / Landlords only", with a "Change in Settings" link.
- **Remove:** the owner-only email, personal number, visibility and GCash rows in the Details list. The strip replaces visibility; the rest live in Settings and Payments › Payouts.

### Settings (`resources/views/profile/edit.blade.php`, landlord shell only)
- **Keep:**
  - **Hero** with the avatar upload (the photo still shows in chat and the sidebar).
  - **Personal info:** legal name (locked after ID check), email, personal contact number.
  - **Password** and **Danger zone**.
- **Change:** Profile visibility gets landlord wording.
  - Help text: "Only matters while you have no live listing. With a live listing your business page is always public."
  - Option: "Landlords only — other landlords can view it".
- **Remove:**
  - The Business information form (moves to My Profile).
  - The Payout pointer card. GCash is on Payments › Payouts, linked from the sidebar and the dashboard alert.
  - The Bio field.
  - The profile strength meter and the trust tip card.
  - The "Email status" row.
- **Section rail** becomes: Personal info · Password · Danger zone.

## Implementation
1. **Save the plan:** copy it to `plans/landlord-profile-vs-settings.md`. Invoke `impeccable` before the UI edits, as the memory rule asks.
2. **New partial `resources/views/landlord/profile/partials/_business-sheet.blade.php`:**
   - Move the business fields from `profile/partials/update-landlord-business-form.blade.php` into it.
   - Use the modal shell from `_file-report-modal`: teleport, `open-business-sheet` event, fixed header and footer, inputs 16px on phones.
   - Hidden `return_to=profile`.
   - It opens by itself when `$errors` has `business_*` or `logo` keys.
3. **`app/Http/Controllers/Landlord/ProfileController.php::update`:** `return_to=profile` sends you back to `landlord.profile.me` with "Business page saved."
   - GCash keys are absent from this form, so the stored values are kept; that path already exists.
   - `return_to=payouts` and the default redirect stay as they are.
4. **`landlord/profile/show.blade.php`:**
   - Button and empty-state links become `$dispatch('open-business-sheet')`.
   - Add the owner strip and remove the four private rows.
   - Include the sheet when `$isOwner`.
5. **`profile/edit.blade.php` and `update-profile-information-form.blade.php`:** gate on `$user->usesLandlordShell()`.
   - Hide bio, the strength meter, the trust tip and the email-status row.
   - Swap the visibility labels.
   - Drop the `#payout` and `#business` rail links and the `update-landlord-business-form` include.
6. **Delete `update-landlord-business-form.blade.php`** once nothing includes it (check with a grep).
7. **Check `ProfileController::update` (personal):** a missing `bio` key must leave the stored bio alone, not clear it. Fix it if it doesn't.
8. **CHANGELOG entry** in `context/CHANGELOG.md`.

## Tests
- **New `tests/Feature/LandlordProfileVsSettingsTest.php`:**
  - **My Profile (owner):** has the business sheet with `business_name`, and does not show the email, personal number or GCash rows.
  - **Saving with `return_to=profile`:** redirects to `landlord.profile.me`. A validation error goes back with the errors, so the sheet reopens.
  - **Landlord Settings:** no "Business information", no `name="bio"`, no "Profile strength". Name, email, password, visibility and danger zone are present.
  - **Tenant Settings:** unchanged, still showing bio, profile strength and emergency contact.
- **Update existing tests:**
  - `tests/Feature/Payments/PayoutsTabTest.php:94`, which asserts the payout link on Settings.
  - `tests/Feature/ProfileSettingsPageTest.php`, for its landlord assertions.
- **Run:** the affected files, then the full suite in the background, then Pint.
- **Visual check:** headless Edge screenshots as Maria at 375px and 1440px for both pages, with the sheet open.

## Manual check (for you, in the browser)
1. My Profile: Edit business page → change the description → Save. You land back on My Profile with the new text.
2. Settings: no business, bio or GCash; the name, email, password, visibility and delete sections still work.
3. Log in as a tenant: Settings looks the same as before.

## Out of scope (noted)
- Chat bubbles show the landlord's personal photo to tenants, not the business logo. This may break the "business, never the person" rule; it is worth a separate look.

## Change after review (Nov 2 2026)
The user asked to keep payment settings in Settings. Settings now has a **Payments** section:
- **How tenants can pay you:** Online / Your QR / Cash buttons (`users.accepts_qr_payments`, `users.accepts_cash_payments`, and `user_payout_accounts.accepts_online`).
- **The GCash payout form:** the same partial as Payments › Payouts.

The methods apply to move-in and monthly rent, and the server refuses a turned-off method (`App\Support\PaymentMethods`).
