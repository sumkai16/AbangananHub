# CLAUDE.md

Project context and rules live in `context/`:
- `context/RULES.md` — coding & implementation rules (SOLID/DRY/KISS, naming, error handling, concurrency)
- `context/ARCHITECTURE.md` — architecture
- `context/DESIGN.md` — design system / UI conventions
- `context/PRD.md` — product requirements
- `context/SCHEMA.md` — database schema

Read the relevant file(s) before non-trivial work.

## Device priority
Tenant and Landlord surfaces are **mobile-first** — most real usage is a phone browser, not a
desktop. Design and build for 375px first, then scale up. Admin stays desktop-oriented (single
administrator, dense dashboards) — do not force it into a mobile-first pass. See `context/DESIGN.md`
§0b for the full rationale and practical implications.

## Planning
When finalizing a plan (plan mode), save a copy into `plans/` in this repo (descriptive kebab-case filename, e.g. `plans/google-facebook-oauth-login.md`), in addition to the default plan-mode location. Keeps design decisions and their reasoning versioned alongside the code instead of only living in a local scratch file.

Once a plan is fully implemented **and Axcee has manually verified it** (ran the plan's own test
checklist in-browser, per `test-before-commit`), delete it from `plans/` rather than leaving it
marked done. Code landing/being committed is not enough on its own — confirm the manual pass
actually happened before deleting; if the plan's own status line says "pending manual verification"
or you're not sure, ask rather than assume. git history preserves the file if it's ever needed again.
A plan that documents a decision worth keeping long-term (e.g. a runbook like
`plans/hostinger-vps-deployment.md`, meant to be reused, not "finished") is not a candidate for this
— only one-shot implementation plans that have fully landed and been verified.

## Models
Plan with **Opus 5 (high effort)**; implement/code with **Sonnet 5**. Planning is where reasoning
quality pays off; once the plan is agreed the coding is mechanical. Switch models at the handoff
from plan to implementation.

## Git commits
Author commits as the user only. Do not add a `Co-Authored-By: Claude` (or any AI) trailer.

## Recommendations
Optimize for the best actual outcome, not the least implementation effort. If the better option
costs a bit more setup — a free API key signup, a little extra config — recommend that option and
explain the tradeoff; don't quietly steer toward the easier-but-worse option just to avoid asking
for that effort. Let Axcee weigh effort against result himself.

## Explaining things
Write for a reader who is skimming, not studying. Plain words over jargon, short sentences, and
the answer first — then the reasoning if it is still needed. Prefer a table or a numbered list
over a paragraph whenever the content is a set of items. Name the concrete file, column or number
instead of describing it abstractly. If a term is unavoidable (escrow, allocation, derived
period), define it once in a half-sentence the first time it appears. Never pad with restatement,
hedging, or a recap of what was just said.
