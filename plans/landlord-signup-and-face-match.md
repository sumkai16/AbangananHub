# Landlord sign-up path and ID-to-selfie face match (Nov 5 2026)

Status: **in progress, pending manual verification.** Source: PM feedback on registration/login and landlord verification.

## What the PM asked
1. "Sign up" is a **tenant** sign-up, with a "Become a landlord? Sign up here." link to landlord registration.
2. Landlord sign-up asks for the landlord's information, and the verification (face + ID) happens as part of it.
3. Facebook login must work (blocked by deploy settings, not code; see checklist below).
4. An API for easier ID verification (decision: option A, face match).

## Decisions
- **Landlord sign-up** = its own page `/register/landlord`: the same account form, then straight into the existing ID + face wizard `/landlord/apply`. A three-step header: Account, Verify ID and face, Admin review. The account is a Tenant until an admin approves (RULES.md: every self-registration starts as Tenant).
- **Social sign-up** from that page (Google, Facebook) also ends in the wizard: the intent is kept in the session across the provider round trip.
- **ID API: option A.** Keep Google Vision OCR; add a face comparison of the ID photo against the selfie with AWS Rekognition `CompareFaces` (`App\Services\FaceMatch`). It is a **signal for the admin, never an automatic reject**: lighting and ID photo quality cause false mismatches. Any failure (no keys, AWS error) means "Not checked" and the application continues to admin review.
- Option B (a full KYC service such as Sumsub, Persona or Veriff: ID authenticity, liveness, Philippine ID types) is the production path; not built.

## Data
`landlord_verifications.face_match_status` (`match` / `mismatch` / `no_face` / `unavailable`) and `face_match_score` (decimal, similarity percent). SCHEMA.md updated with the migration.

## Setup you must do (AWS)
1. Create an AWS account, then an IAM user with only `rekognition:CompareFaces`. Create an access key.
2. Put `AWS_REKOGNITION_KEY`, `AWS_REKOGNITION_SECRET`, `AWS_REKOGNITION_REGION` (Singapore: `ap-southeast-1`) in `.env`. Never in a tracked file.
3. Without keys the app works; the admin sees "Not checked".
   **Check the setup with `php artisan face-match:check <photo1> <photo2>`.** Two pictures with no faces are enough: the answer "no clear face found" proves AWS accepted the keys and the permission. Dev account note (Nov 5 2026): the AWS organization's service control policy denied `CompareFaces` in `ap-southeast-1` (and most other regions); **`ap-southeast-2` (Sydney) worked**, so `.env` uses it. A different AWS account may allow Singapore. The error text in `storage/logs/laravel.log` (`FaceMatch:` lines) names the cause.
4. Both photos are sent to AWS to compare. The privacy page says so.

## Facebook login checklist (no code)
- The redirect address `APP_URL/auth/facebook/callback` must be listed in the Facebook app's Valid OAuth Redirect URIs. Change it together with `APP_URL` when the real domain is live.
- A Meta app in Development mode lets only listed testers log in: add panel members as testers, or publish the app.
- Facebook may not share an email; the code already shows a clear message.

## Manual test guide
1. Logged out, click **Sign up** in the header. The popup is a tenant sign-up with a **Become a landlord? Sign up here.** link. Same link on `/register`.
2. Click it: `/register/landlord` shows "Sign up as a landlord" with four numbered steps, on **1 About you**.
3. Leave First name empty and click **Next**: it stops with a "Required" tag. Type a bad phone (letters are dropped; try `12`): "Invalid number". Fill it in and click **Next**: step 1 gets a check, step 2 opens.
4. In **Email**, type an address that already exists (for example your own) and click away or pause: a red "This email is already registered. Log in instead" appears at once, without clicking the button. A free address shows a green "This email is available".
5. Mismatch the two passwords: "Doesn't match" appears as you type. Click **Back**: step 1 returns with your answers kept.
6. Fill everything and click **Continue to ID verification**. The address bar **stays on `/register/landlord`** and the page shows step 3 "Verify your ID and face" (steps 1 and 2 are checked), with the ID, selfie and business form under it.
7. Complete the form and submit: you stay on `/register/landlord` and the page shows **step 4, Admin review** (the pending application) in the same shell and indicator.
8. Open `/register/landlord` while signed in as an approved landlord: you land on the dashboard.
9. Same page, Google or Facebook (if configured): after the provider you also land on step 3 at `/register/landlord`.
10. As admin, open that verification: a **Face match** line shows the result ("Not checked" without AWS keys).
11. Tenant `/register` still ends on Browse, and also checks the email live.
