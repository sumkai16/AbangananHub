# Deployment cost and payment approach

Decided Oct 6 2026. A reference note, not an implementation plan: it records what deployment will
cost and why payments stay as they are. The deploy procedure itself is
`plans/hostinger-vps-deployment.md`.

**Prices come from third-party comparison sites, not the providers' own pages. Check each one at
checkout before paying, and look at the renewal price, not the first-term price.**

---

## 1. Recommended hosting setup

| Item | Choice | Cost | Notes |
|---|---|---|---|
| Server | Hostinger KVM 1, Singapore region | ~US$6/mo first term, ~US$12/mo on renewal | 1 vCPU, 4 GB RAM, 50 GB NVMe. Meets the runbook's 2 GB minimum. Singapore is nearest to Cebu |
| Domain | `.com` (e.g. abangananhub.com) | ~US$14–19/yr on renewal | `.ph` is ~₱2,549/yr; its registration rules were not checked |
| SSL | Let's Encrypt (Certbot) | Free | Already in the runbook |
| MySQL, Nginx, Reverb | Self-hosted on the server | Free | |
| Photos | Cloudinary free tier | Free | 25 credits/mo. 1 credit = 1 GB storage, 1 GB bandwidth, or 1,000 transformations. Paid plans start ~US$89/mo |
| Email | Resend free tier | Free | 3,000 emails/mo, **capped at 100/day** |
| ID OCR | Google Vision free tier | Free | Free-tier limit not verified |
| Social login | Google / Facebook OAuth | Free | Both apps still in Testing mode; publishing may need review |
| Backups | Nightly `mysqldump` + `storage/app/private` to Cloudflare R2 | Free tier (limit not verified) | Required: `storage/app/private` holds the only copy of every government ID and property document |

**Estimate:** ~US$7–13/mo for the server and domain together. First year: ~US$90–160, depending on
the server term.

A shared host will not work. The app needs a persistent private disk, a long-running Reverb process
and real cron (see the runbook §1).

### Open decisions
1. Server term: a 24-month term is cheaper per month but commits to two years.
2. Domain: `.com`, `.ph`, or both.

---

## 2. Payments: keep the current flow

**Decision: keep the current PayMongo payment feature as it is, in sandbox (test) mode.** No
rewrite, no removal. This matches `context/PRD.md`, which scopes payments to sandbox only for the
capstone. In sandbox mode payments cost nothing and no real money moves.

### How it works today
1. The tenant pays through PayMongo checkout into AbangananHub's own account.
2. A deposit is marked `Held`. Rent is marked `Paid` with payout pending.
3. An admin releases the money (`Admin\PaymentController::release`).
4. An admin sends it to the landlord's GCash by hand from the payouts queue.

The code is sound: the webhook checks PayMongo's signature, a polling fallback settles payments the
webhook misses, and money changes lock the row first.

### Problems if we ever take real money
| Problem | Why it matters |
|---|---|
| We would hold other people's money | Collecting for landlords and paying out later works like escrow. In the Philippines this may need regulatory approval, and a standard PayMongo account may not allow collecting for third parties. **Ask PayMongo before going live.** |
| Payouts are manual | Every rent payment needs an admin to send it by hand. Slow, error-prone, and each GCash transfer may cost a fee |
| Live account needs business papers | PayMongo usually requires business registration documents for a live account |
| We pay the fees | ~2.2–2.5% per GCash payment, ~3.125% + ₱13.39 per card payment, unless passed on |

### Options for a live launch later
| Option | How money moves | Trade-off |
|---|---|---|
| **PayMongo Platforms** (recommended) | Each landlord is a sub-account; payments split and pay out automatically; supports delayed settlement for the deposit hold | Least rework (same provider). Landlords must pass PayMongo's ID check. Pricing not published; contact sales |
| Xendit xenPlatform | Same idea, different provider | Published prices: ₱85 per active sub-account per month, 0.5% (max ₱35) per transaction, GCash 2.3%. Means replacing the PayMongo integration |
| Tenant pays landlord directly | Landlord shows their GCash QR; the app only records the payment | No fees, no held funds. Loses the deposit protection |

Questions to ask PayMongo about Platforms: does it support holding a deposit until move-in, what does
it cost, and what documents do we and our landlords need.

### Why we are not removing payments
- 15 files call PayMongo and 26 depend on held-payment/escrow state (`ProcessMoveInDeadlines`,
  `RentLedger`, `Reservation`, the move-in clock in chat).
- Several features marked done in the PRD depend on it: reservation payment, the Move-In
  Confirmation Window, Tenant Online Rent Payment, Landlord Payouts.
- The held deposit protects tenants from a landlord who takes the money and never hands over keys.

If online payments ever need to be switched off, prefer an admin setting that hides the pay buttons
and keeps manual recording, over deleting the code.

---

## 3. Follow-ups (not done)
- [ ] Add a visible "Test mode, no real money is charged" label on the checkout before the public
      deployment, so real visitors are not misled.
- [ ] Answer the two open hosting decisions in §1.
- [ ] Re-check every price on the providers' own pages before buying.
