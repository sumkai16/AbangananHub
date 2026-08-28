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
