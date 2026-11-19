# THESIS.md — Capstone manuscript notes

Notes taken from the capstone manuscript in the shared Google Drive folder
(`https://drive.google.com/drive/folders/1ubATu2iBWFZz-i-0WcrKU5MGZN4gPn-k`). Read-only: the Drive is
never written to. These notes are a summary; the Drive documents are the source of truth.

**Read on 2026-11-17:** CPM00 Preliminary Pages, CPM01 Ch. I, CPM02 Ch. II, CPM03 Ch. III, CPM04 Ch. IV
(methodology, requirements, costs, testing, implementation; use-case narratives and data-dictionary rows
only skimmed), CPM05 Ch. V, CPM06 Ch. VI.

**Not read:** `MANUSCRIPT` (combined copy), `AbangananHub_Manual_Testing_Workbook.xlsx`, `SCREENSHOTS/`,
`V2025 - Documentation Guidelines.pdf`, and `NEEDS CHANGES IN DOCS (manually change, ai dont touch this)`
(skipped on purpose; its title asks AI not to touch it).

**How to answer questions about these documents:** in plain sentences or short paragraphs, never tables
(Axcee's instruction, 2026-11-17). Say "landlord", never "property owner".

---

## Title page and preliminary pages (CPM00)

Title: "AbangananHub: A Secure Rental Management and Verification Platform for Tenants and Landlords".
A capstone project for the College of Information Technology, St. Cecilia's College – Cebu, Inc.,
Minglanilla, Cebu, in partial fulfillment of the BS Information Technology degree. Dated November 2026.

Researchers (seven): Axcee F. Cabusas, Emman A. Bas, Ivon Lawrence O. Candilanza, Joseph Anthony D.
Arambala, Michael Ken G. Bernaldez, Myca L. Lariosa, Rica Mae B. Patenio. Adviser: Mr. Jhon Ericson R.
Brigildo. Program Chair: Hitchean E. Lisondra. Panel: Michael John A. Bustamante (member), Hitchean E.
Lisondra (member), Rexy Jade L. Yangyang (chairman). Dean: Dr. Alfredo S. Moreno, Jr. AVP for Academic
Affairs: Dr. Jojo Ronquillo.

The acknowledgement thanks God, the adviser, the faculty, and the Minglanilla community (boarding house
operators, student tenants, and the landlords and tenants who attended the stakeholder meetings). The
dedication is to the researchers' families, loved ones, themselves, the adviser, and friends.

The executive summary says: renting in Cebu still runs on social media, word of mouth and messaging apps,
which exposes tenants to fake listings and unverified landlords, while landlords keep records in notebooks
and chats. AbangananHub fixes this with live-captured government ID and selfie verification checked by the
system and then approved by an administrator, administrator approval of every property and unit, two-factor
sign-in and a tamper-evident audit log for administrators, and tools for landlords (properties, units,
reservations, tenants, agreements, rent records) and tenants (search in English, Tagalog or Cebuano, map,
chat, reserve, sign agreements, rate). Rent is paid directly to the landlord; online payment was tested in a
test environment only. Method: action research with weekly Agile sprints. Estimated total project cost
₱35,854, repaid by expected benefits within three years. Three stakeholder meetings (September to October
2026). Seven deployment requests, six accepted, five already built, caretaker role still to add. Findings
show how users received the system, not a measure of satisfaction. The system does not guarantee every
listing is legitimate.

The list of figures runs Figure 4.1 to 4.70 (Agile cycle plus 69 use-case diagrams) and Figure 5.1 to 5.70
(IPO model plus screenshots). The list of tables runs Table 3.1 to 5.4, with Table 4.85 to 4.135 being the
data dictionary, one table per database table. The appendices planned are: A Team Assignment and
Contribution Report, B Informed Consent Form, C Transmittal Letter, D Interview and Questionnaire Guide,
E System Flow Diagrams (manual and proposed flows, sample reports), F Grammarian Certificate, G User
Guide/Manual, H Curriculum Vitae.

---

## Chapter I — Introduction (CPM01)

**Background argument.** ICT growth has moved services online. PSA: 67.3% of Filipinos aged 10 and up used
the internet in 2024. Housing is part of this. Rental demand is rising in Metro Cebu (OECD names it one of
the fastest-growing metros; Ballesteros 2004 and Ballesteros et al. 2022 on rental housing and
affordability). Online rental listings carry fraud risk: the PNP warned about fake online rentals where
scammers pose as lessors, collect down payments and vanish (Peralta-Malonzo, 2025), and the FTC reported
nearly 65,000 rental scams and about $65 million in losses in the US since 2020, many starting from a fake
Facebook ad. So systems need secure transactions, good information management and reliable user
verification, not just listings. The project supports SDG 11 (Sustainable Cities and Communities).

**Project context.** A software development capstone, June 2026 to November 2026. It combines landlord
identity verification, property and unit management, reservations, occupancy monitoring and administrator
oversight in one web platform. Respondents: tenants and landlords in Cebu. Stack named: Laravel 12, PHP
8.4, MySQL with Eloquent, Breeze, Blade, Tailwind, Alpine.js, Laravel Reverb, Leaflet with OpenStreetMap,
Vite and npm. Administrators get two-factor authentication and a tamper-evident audit log.

**Purpose and beneficiaries** (this is the closest thing to a "Significance of the Study"; there is no
section by that name). Tenants (browse, inquire, reserve, chat with verified landlords; verification,
accurate info and ratings cut risk). Landlords (one place for listings, units, reservations, occupancy and
rental records; tenant ratings help them decide; live property capture adds credibility). System
administrators (verify accounts, review listings, watch activity through the audit log, handle reports).
Researchers (insight into improving Cebu rentals with security and verification). Future researchers (a
reference for similar platforms). No LGU or community beneficiary is listed.

**General objective.** Design and develop AbangananHub, a secure rental management and verification
platform that improves management of local rentals and gives tenants and landlords in Cebu a safe,
transparent, reliable environment.

**Seven specific objectives.** (1) A secure central platform for tenants to browse, search, inquire and
reserve verified rentals. (2) Help landlords organize properties, units, reservations and occupancy. (3) A
landlord verification process that raises user confidence. (4) Review every listing before it is posted.
(5) Central communication for inquiries, reservation updates and notifications. (6) Admin support:
landlord verification, property and unit approval, user management, complaint and review handling,
reports. (7) Protect administrator accounts with two-factor authentication and a tamper-evident audit log.

**Scope** covers: role-based access and mandatory admin 2FA; landlord verification by live ID capture and
live face check with admin review; property, unit and media management; admin approval before publication;
tenant browsing and an AI search assistant in English, Tagalog and Cebuano; favorites and reservation
requests; inquiries and real-time messaging; reservation approval and history; rental agreements generated
from the landlord's template and signed in the system; move-in and rent payment records confirmed by the
landlord, optional PayMongo online payment, rent ledger; escrow of optional online move-in payments with
admin release and refund; viewing requests within landlord hours; occupancy tracking; notifications;
ratings and reviews; complaint reporting; admin tools, reports and analytics; step-up password prompts and
an audit log users can review.

**Limitations.** The system does not draft legal terms or guarantee agreement validity. No legal mediation
or dispute resolution. No integration with government agencies for ID or ownership checks; verification
rests on submitted documents and admin review. Cebu only. Needs stable internet, no offline mode. Web app
only; an API exists for a planned mobile app that is outside this project. Admin 2FA supports authenticator
app and recovery codes only, no SMS codes.

**Definition of terms** covers AbangananHub, Administrator, Audit Log, Authentication, Complaint Management,
Escrow, Landlord, Landlord Verification, Liveness Check, Occupancy Monitoring and Status, Property Listing,
Real-Time Messaging, Rent Ledger, Rental Accommodation, Rental Agreement, Rental Business, Reservation and
Reservation Status, RBAC, Step-Up Password Confirmation (re-prompt, then not asked again for 15 minutes),
Tenant, Two-Factor Authentication, Verification and Web-Based Platform.

---

## Chapter II — Review of Related Literature and Systems (CPM02)

Reviewed systems and studies:

- **Room Renting Management System** (Iu and Mohd Zaki Mohd Salikon, 2022). Centralizes room listings,
  tenant information and rent transactions; improves efficiency and cuts manual work. Gap: no security or
  identity verification between tenants and landlords, no fraud prevention.
- **A Web-Based Rental House Smart Finder using Rapid Application Development** (Monteverde et al., 2023).
  Search, filtering and a map to help tenants find affordable, reliable places; boarding houses get little
  promotion on existing platforms. Stresses user-friendly design and organized information.
- **HomeSeek** (Alegado et al., 2024), a St. Cecilia's College study on dormitory and apartment listing and
  booking in Minglanilla. Central listings, real-time availability, structured feedback, location, price
  and amenity search, online booking. Its own admitted gaps: it does not verify owner uploads or document
  legitimacy, has no in-platform chat, and covers Minglanilla only.
- **Rental fraud and identity verification.** Park et al. (2016) examined over two million Craigslist
  listings and found about 29,000 fraudulent ones across 20 US cities, including cloned listings at lower
  prices; the platform flagged only 47% of them. The 2024 Rently survey (Florida Realtors, 2024): 88% of
  scam victims found the fake listing on Facebook, 39% of renters distrusted online rental platforms, and
  renters strongly backed identity verification and past-tenant reviews. NIST identity-proofing guidance
  (Temoshok et al., 2025) says proofing strength should match the risk of the service.

**Synthesis.** The reviewed systems help with search, listings, reservations and communication but pay
little attention to thorough identity verification, admin approval of listings, complaint management,
occupancy monitoring and reputation management. AbangananHub is positioned as filling those gaps: live ID
capture with liveness detection and face matching, admin approval of landlords and listings, property and
unit management, reservations, occupancy monitoring, real-time messaging, complaints, ratings and reviews,
two-factor sign-in, a tamper-evident audit log, and a Leaflet/OpenStreetMap map.

**Loose ends in this chapter:** the synthesis cites "Diaz et al. (2024)" but there is no Diaz entry in the
reference list and no Diaz section above it. The Monteverde reference title reads "ISO 205010" (probably
meant ISO 25010).

---

## Chapter III — Technical Foundation (CPM03)

**Current practice.** Rentals in Cebu are found through Facebook Marketplace, Groups and Messenger. Tenants
visit many posts and contact landlords one by one, get stale availability, and cannot tell real landlords
from fake ones. Fake listings and scams are described as rampant.

**Organization.** The platform is owned, developed and run by the researchers as a group called
**StarkCorp**, which is also the main system administrator. Roles: StarkCorp (owners and developers),
system administrators (landlord identity verification, property approval, complaints, reports), landlords
(advertise, update occupancy, answer inquiries) and tenants (search, message, review, report).

**Technical environment (Table 3.1).** Development: Windows 11, Intel Core i5, 16 GB RAM, VS Code.
Deployment: Hostinger VPS, Ubuntu 22.04, Nginx, PHP 8.4. Database: MySQL 8.0. Users: Chrome, Firefox, Edge
or Safari on PC or smartphone.

**Tools and technologies (Table 3.2).** Laravel 12.69.3 (MVC), PHP 8.4, Blade, Tailwind CSS, Alpine.js,
MySQL via Eloquent only, Laravel Breeze (session auth), Laravel Reverb (WebSockets), Leaflet with
OpenStreetMap, cloud file storage, Git/GitHub (`github.com/sumkai16/AbangananHub`), VS Code, Composer,
PHPUnit, database-backed sessions, and PragmaRX Google2FA for admin authenticator codes with a keyed-hash
chain that makes the audit log tamper-evident. External services in the prose: Laravel Socialite
(Google/Facebook), Laravel Sanctum (API for the planned mobile app), face-api.js (liveness), Google Cloud
Vision (ID OCR), AWS Rekognition (ID-to-selfie face match), OpenRouter (AI search), PayMongo (optional
online payments, test mode), Cloudinary (public photos and videos). Web only, responsive, no native app.

---

## Chapter IV — Methodology (CPM04)

**Approach.** Action research (plan, act, observe, reflect, repeat), chosen because the system was built
with its users. Each round went through the SDLC phases. Delivery used Agile with weekly sprints, each
ending with a working set of features. Source in Git/GitHub, screens designed in Figma before coding.

**Phases (Tables 4.1 to 4.4).** Planning and requirements (system assessment, requirement refinement,
scheduling). Design (Figma UI, design review, system flow and ERD and data dictionary). Development
(front end, back end with MySQL, feature integration). Testing (unit, integration, system, UAT).

**Requirements.** 67 functional requirements, one per use case; the 69 use cases are these 67 plus Log In
and Register Account. By group: all users FR-C1 to C8 (landing page parts, platform ratings, browse
listings with AI search, property details, rental areas map); administrator FR-A1 to A19 (dashboard, users,
landlord verification, property and unit approval, property documents, monitor reservations and payments,
payouts and refunds, deposit charges, review moderation, reports, conversations, analytics, ratings, audit
log, platform settings, admin security); landlord FR-L1 to L23 (profile and verification, dashboard,
messages, properties, units, reservations, viewing requests, tenants, rentals, lease and agreement, add
rental, payment records, payouts, rent ledger, reviews, analytics, complaints, settings, policies and
policy editor); tenant FR-T1 to T17 (save listing, track reservation, rentals, lease, payment option, sign
agreement, receipt, notifications, contact landlord, inquiry, file concern with up to three photos,
profile, review and rate, my reports, settings).

**Non-functional requirements (NFR-01 to 08).** Performance: pages load within 3 seconds on 4 Mbps with up
to 50 concurrent users. Security: hashed passwords, role check on every page, zero unauthorized access in
testing, admin 2FA and step-up password, tamper-evident log. Privacy: ID photos and face data visible only
to administrators, handled under the Data Privacy Act of 2012 (RA 10173). Usability: a first-time user
finishes an inquiry in 5 minutes unaided; at least 80% of UAT respondents rate it easy to use.
Reliability: 95% availability in testing. Compatibility: latest Chrome, Edge, Firefox, Safari; layout from
360 px phones to desktops. Data integrity: no lost or duplicated reservation and payment records.
Maintainability: Laravel MVC and Git.

**Costs (all in pesos).** Hardware ₱21,000 (computer set ₱20,000, keyboard and mouse ₱1,000). Actual
software already paid: Claude ₱3,000 a year and ChatGPT ₱300 a year, so ₱3,300. Other operating costs
₱2,100 (electricity ₱600, Wi-Fi ₱1,500; allocated resource cost). That makes a development cost of
₱26,400. Deployment first year ₱9,454 (Hostinger KVM 1 VPS in Singapore ₱8,352 plus abangananhub.com
₱1,102). **Total project cost ₱35,854.** Total actual software and hosting spend in Table 4.12 is ₱12,754.
Free-tier services are shown with an indicative replacement cost (not counted in the total): GitHub, Figma,
Cloudinary, Google Cloud Vision, OpenRouter, Mailtrap, Resend, Cloudflare R2, with a total replacement cost
of ₱102,396 (Cloudinary alone ₱61,944). PayMongo is in test mode; a live fee of about 2.2 to 2.5% per GCash
payment would apply. Conversion ₱58 to US$1.

**Cost and benefit.** Yearly benefit ₱15,000 (efficiency, less manual work, organized records, time
saved), discounted at 10%: present values ₱13,636.36, ₱12,396.69 and ₱11,269.72, accumulating to
₱13,636.36, ₱26,033.05 and ₱37,302.77. The accumulated value passes ₱35,854 in year 3, so payback is
within three years. Hosting and domain renewal of ₱9,454 a year continues afterward.

**Design.** 69 use-case diagrams and narratives (Tables 4.16 to 4.84; Figures 4.2 to 4.70). The Log In
narrative covers email and password, password reset (link valid 60 minutes), Google or Facebook sign-in,
admin two-factor check, and first-time 2FA setup. An ERD and a data dictionary (Tables 4.85 to 4.135, one
per table) follow; tables include users, social_accounts, user_payout_accounts, tenant_details,
user_devices, user_roles, landlord_verifications, rental_businesses, properties, property_documents,
property_units, lease_templates, tenancy_documents, media and amenity tables, reservations,
viewing_requests, tenant_concerns, tenant_ratings, late_fee_waivers, landlord_tenants, lease_renewals,
payment_claims, payment_qr_shares, rent_reminders, deposit_charges, payments, payouts, conversations,
messages, notifications, reviews, favorites, reports, landlord_blocked_dates, landlord_viewing_hours,
audit_logs, property_activities, settings, occupancy_snapshots, occupancy_activities, ai_search_logs,
platform_ratings, auth_events, tenant_holds, hold_match_alerts and trusted_devices. Framework tables
(cache, jobs, sessions) are left out.

**Testing plan.** Four levels (Table 4.137): unit, integration, system (user flows per role in four
browsers and on phone screens), and UAT (stakeholder meetings). Twelve sample test cases TC-01 to TC-12
(wrong password, duplicate email, more than three photos, wrong admin 2FA code, landlord verification to
approval, reservation to move-in, direct payment confirmation, search with filters and AI, role access
control, page load time, first-time inquiry, landlord lists a property). Failures were logged, fixed in the
next sprint, and retested.

**Implementation.** Deployment on a Hostinger KVM 1 VPS (Singapore) at abangananhub.com with Nginx, PHP
8.4, HTTPS through Let's Encrypt, Supervisor for the queue worker and Reverb, nightly backups to
Cloudflare R2. HTTPS is required because landlord verification uses the live camera. PayMongo stays in test
mode and online payments stay off until live keys are added. Orientation for administrators, landlords and
tenants uses the same scenarios as UAT. Maintenance: nightly backups and audit-log check, bug fixes,
security updates, yearly hosting and domain renewal (₱9,454), and watching free-tier limits.

**Loose ends in this chapter:** the Gantt Chart heading has no content under it. Table 4.12 still has a
stray row labelled "[DELETE THIS ROW]".

---

## Chapter V — Design of the Prototype (CPM05)

**Operational framework (IPO model).** Inputs: account and profile data, landlord verification
submissions, property and unit listings, search queries and filters, reservations, viewing requests and
agreement signatures, messages and concerns, payment records, reviews, ratings and complaints,
administrator decisions. Processes: authentication and role checking, data validation, identity
verification (four-step liveness with face-api.js, Google Cloud Vision OCR, AWS Rekognition face match,
which only guide the administrator's final decision), listing approval, search and AI query processing
(OpenRouter turns a sentence into filters; map uses Leaflet, OSM, OSRM, Overpass, Nominatim), the
reservation workflow (nine statuses from Inquiry to Occupied and Completed, with Rejected and Cancelled as
exits, and inactive reservations reminded then auto-cancelled), agreement generation from the landlord's
template with terms frozen on issue, payment and rent calculation (direct payments confirmed by the
landlord, optional PayMongo move-in payments held in escrow, monthly status Paid, Partial, Overdue,
Upcoming or Paid Ahead), real-time messaging through Reverb and Echo, and database work through Eloquent
with a hash-chained audit log. Outputs: verified listings, search results and maps, verification results,
reservation updates, agreements, rent ledgers and receipts, messages and notifications, landlord and admin
dashboards and analytics, CSV exports.

The ten-step operating sequence runs: register or log in, landlord verification, landlord sets up
business, properties and units and admin reviews them, tenant searches, reservation, agreement and
documents, payment, handover and move-in, rent tracking and reminders, then dashboards and exports.

**User interface.** Phone-first. One shared look: coral-orange accent on dark navy, Plus Jakarta Sans for
headings, Inter for body text, card panels. Screens described (Figures 5.2 to 5.70): landing page in five
parts, rental listings, property detail, rental areas map, login, registration; landlord dashboard,
messages, property and unit management, reservations, viewing requests, tenants list and detail, rentals
list and detail, lease and agreement, add rental, rent payments, payouts, rent ledger, reviews, analytics,
complaints, profile, settings, policies and policy editor; tenant saved listings, my reservations, my
rentals, rental detail, lease, payment options, agreement signing, receipt, notifications, messages, send
inquiry, report a concern, profile, property reviews, my reports, settings; administrator dashboard, user
management, landlord and property verifications, property documents, properties, units, reservations,
payments, payouts, deposit charges, reviews, complaint reports, conversations, platform analytics, ratings,
audit log, and platform settings in two parts (online-payment switch, then authenticator and recovery
codes).

**Role-based access (Table 5.4).** Visitors reach the landing page, listings, rental areas and
registration. Tenants and landlords reach their own modules, messaging, reservations, agreements and rent
ledger, and payments. Only landlords submit verification; only the administrator reviews it and handles
approvals, user management, platform settings, audit log and security events. Reviews: visitors view,
tenants write, landlords reply, administrator moderates. Complaints: tenants submit, landlords respond,
administrator resolves.

**Loose ends in this chapter:** the "Report a Concern" screen appears twice (Figures 5.46 and 5.50) with
identical text, and Figure 5.49 My Reports is placed between them.

---

## Chapter VI — Results and Discussions (CPM06)

**System outputs by role.** Tenant: landing page and browsing grouped by area, search and map (English,
Tagalog, Cebuano), registration and login, saved listings and reservations, agreement signing and my
rental, payments by GCash QR, cash or PayMongo with printable receipts, viewing requests and move-in
confirmation or dispute, emergency contact and ID (ID number stored encrypted) before the first agreement,
messaging and notifications, reviews, ratings and complaints. Landlord: verification, dashboard and
analytics, property and unit management, reservation and tenancy management, agreements with per-unit
deposit and advance and extra terms, payment records, rent ledger with grace period and late fees (can be
waived), payouts, viewing hours and tenancy renewals, **tenant holds** (flag a tenant with overdue rent;
takes effect only after the administrator confirms; lifts when rent is paid), messaging, reviews and
complaints. Administrator: dashboard, user management, landlord and property verification, payments,
payouts and refunds, reports and ratings, tenant holds and match alerts (a person decides, nothing blocks
automatically), audit logs, administrator security, platform settings. An API (Sanctum) was built for the
planned mobile app.

**Summary of findings.** The working platform brings together what is normally done through social media,
chat apps and paper. Landlord verification (government ID, live face check, admin review) and admin
approval of properties and units keep unreviewed listings from tenants. The system does not guarantee every
listing is legitimate. Automated checks: 193 test files with about 1,650 tests; the last full run recorded
(5 November 2026) passed all 1,626 tests then existing. The tests check behavior, not user satisfaction.

**Stakeholder meetings.** (1) Two landlords in Ward 2, face-to-face interview with live demo, 21 September
2026. (2) Tenants who look for rentals in Cebu, walkthrough of search and listing pages at St. Cecilia's
College, 20 October 2026. (3) Four landlords in Dragonjet who rent to students, presentation and interview,
22 October 2026. Before the system, landlords used notebooks, paper, "For Rent" signs, Facebook and
Marketplace; the Dragonjet landlords used three phones, one per floor, so payments were not tracked in real
time; tenants used Marketplace or walked around areas. Response was positive in all three. Ward 2 landlords
named online payment, the rent ledger and online listing as most useful; one landlord who lives abroad said
she could have managed her rental remotely. Dragonjet landlords said the rent ledger and landlord payments
page solve their real-time tracking problem and that location-based listing suits them. Tenants said area
search saves effort and listings are more informative than Marketplace posts, and raised no new
requirements. Dragonjet landlords worried about who is liable for a loss if the system were hacked, and
some preferred to collect rent outside the system; the team answered that online payments go through
PayMongo and offline payments such as cash can be recorded in the ledger.

**Seven requirements raised for deployment.** Six accepted, one under review. Implemented: direct payment to
the landlord, deposit and advance per unit (including zero), custom rental terms or addendum, grace period
and late fee per property, tenant emergency contact and ID. Accepted but not built: caretaker or property
manager role. To be reviewed: a cleansing period for tenant reviews (a safeguard against malicious
reviews).

**Limits of the evidence (stated in the chapter itself).** The findings come from interviews,
presentations and demonstrations. There are no scored questionnaire results and the number of tenant
participants was not recorded, so the findings show how stakeholders received the system but do not measure
usability or satisfaction. The Chapter IV targets (80% rate it easy, first inquiry in 5 minutes) are
therefore not yet confirmed.

**Recommendations.** Build the Android app for tenants and landlords with React Native and Expo on top of
the existing API. Deploy to production with HTTPS and a process manager for Reverb. Test with more tenants
and landlords using a scored questionnaire. Connect verification to official ID or business verification
services. Extend coverage beyond Cebu after wider use. Add the caretaker role and review the review
cleansing period.

---

## Things worth checking against the repo (observations, not yet verified)

- Chapter VI describes tenant holds, late fees, viewing hours and tenancy renewals, but Chapter I's scope
  and Chapter IV's 67 functional requirements do not list them as their own items.
- Chapter III says MySQL 8.0 on Ubuntu 22.04; the local development database is MariaDB 12.3.2.
- `context/PRD.md` calls the Direct Payments route the default and PayMongo escrow off by default, which
  matches Chapter V and VI; Chapter I's scope still lists escrow as a feature, so the manuscript should
  keep saying it is optional and off until an administrator turns it on.
- Chapter I limitation says no mobile app; `context/PRD.md` says a React Native app is planned. These agree
  (planned, outside the capstone scope).
