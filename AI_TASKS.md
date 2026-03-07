# AI_TASKS.md

## Project Task Queue
PatriotContracts AI implementation queue.

Rules:
- Follow `AGENTS.md`
- Preserve working systems
- Prefer minimal safe changes
- Update this file as tasks are completed
- Keep notes short and concrete

---

## STATUS LEGEND
- [ ] not started
- [~] in progress
- [x] complete
- [!] blocked / needs review

---

## CURRENT HIGH-PRIORITY GOALS

### 1. Audit data flow for missing fields
- [x] inspect ingestion scripts
- [x] inspect normalization logic
- [x] inspect listing queries
- [x] inspect contract detail page queries
- [x] identify exact causes of empty fields
- [x] document smallest safe fixes

Notes:
- Focus on agency, vendor, amount, dates, identifiers, NAICS/PSC, set-aside, and place of performance
- Do not rewrite architecture during audit

---

### 2. Prevent NULL overwrites during ingestion
- [ ] inspect insert/update/upsert logic
- [ ] find places where NULL or blank values can overwrite better existing values
- [ ] patch only affected logic
- [ ] add validation/logging where needed

Notes:
- Prefer non-empty values
- Preserve existing ingest flow where possible

---

### 3. Improve search performance with minimal disruption
- [ ] inspect search.php and related query builders
- [ ] inspect current indexes
- [ ] identify slow joins / repeated lookups
- [ ] add targeted indexes
- [ ] clean up queries without changing frontend behavior

Notes:
- Prefer indexes/query cleanup before schema redesign
- Keep filters and routes stable

---

### 4. Improve contract detail page data completeness
- [ ] inspect contract detail page backend flow
- [ ] add fallback resolution where normalized data exists in alternate mapped fields
- [ ] prevent blank output when source data exists somewhere usable

Notes:
- Keep page layout unchanged

---

### 5. Add ingestion logging and validation
- [ ] add readable logs for fetch/insert/update/skip/failure counts
- [ ] add validation checks for critical fields
- [ ] flag suspicious incomplete records

Notes:
- Do not rebuild the entire ingestion pipeline unless required

---

## LOWER-PRIORITY TASKS

### 6. Search index strategy review
- [ ] determine whether current dataset size justifies a denormalized search index
- [ ] if needed, propose a minimal adoption path
- [ ] do not implement without clear necessity

---

### 7. Pagination review
- [ ] inspect current pagination
- [ ] determine whether offset pagination is acceptable for current scale
- [ ] only optimize if current performance actually requires it

---

## DO NOT TOUCH WITHOUT EXPLICIT INSTRUCTION
- [ ] authentication flow
- [ ] registration flow
- [ ] Stripe/billing logic
- [ ] membership entitlements
- [ ] API gating
- [ ] visual redesign
- [ ] routes / URL structure

---

## IMPLEMENTATION LOG

### Template
- Date:
- Task:
- Files changed:
- Summary:
- Commit:

- Date: 2026-03-06
- Task: 1. Audit data flow for missing fields
- Files changed: `contract.php`, `AI_TASKS.md`
- Summary: Audited ingest -> normalize -> listing/detail query path. Confirmed 2,500/2,500 current records are `source_type=usaspending`, which explains missing opportunity-style fields (`posted_date`, `response_deadline`, NAICS/PSC, place of performance). Confirmed `source_name` is blank in existing `contracts_clean` rows; added safe detail-page query fallback to `sources.name` to prevent empty source display without altering layout.
- Commit:

- Date: 2026-03-06
- Task: Add archive page for contracts that are already over
- Files changed: `archive.php`, `archieve.php`, `index.php`, `AI_TASKS.md`
- Summary: Added new `archive.php` listing route for contracts considered over (past deadline, past end date, closed/archive status, or awarded), added homepage pipeline count/link, and added `archieve.php` alias route for typo-safe access.
- Commit:

- Date: 2026-03-06
- Task: Award classification backfill safeguards in normalization
- Files changed: `ingest/normalize_contracts.php`, `AI_TASKS.md`
- Summary: Updated normalization to treat records as award rows only when `source_type IN ('usaspending','sam_award')` and `award_date IS NOT NULL`; for those rows, status now falls back to `awarded` when blank/unknown and `is_awarded` is forced to `1` during normalize backfill.
- Commit:

- Date: 2026-03-07
- Task: Public-readiness cleanup pass (BASE_URL consistency, root entry cleanup, listing data sanitation, public UX trust polish)
- Files changed: `includes/db.php`, `includes/functions.php`, `includes/auth.php`, `includes/oauth.php`, `includes/stripe.php`, `templates/header.php`, `templates/footer.php`, `index.php`, `home.php`, `search.php`, `contract.php`, `agency.php`, `vendor.php`, `category.php`, `open-this-week.php`, `early-signals.php`, `deadline-soon.php`, `recent-awards.php`, `archive.php`, `login.php`, `register.php`, `pricing.php`, `verify-email.php`, `reset-password.php`, `subscribe.php`, `billing/success.php`, `billing/cancel.php`, `api-docs.php`, `api-access.php`, `assets/css/main.css`, `../index.php`, `../.htaccess`, `AI_TASKS.md`
- Summary: Added runtime host-aware BASE_URL resolution and shared app URL helpers, removed public nav/path leaks to static localhost assumptions, added root redirect to `/patriotcontracts/` and disabled root directory indexes, standardized public listing metadata rendering to hide empty/junk values, validated/safeguarded source links, suppressed non-configured social OAuth CTAs, replaced placeholder ad/demo block with source-trust copy, and aligned public API docs examples to app-relative endpoints.
- Commit:

---

## REVIEW NOTES
Use this section for brief findings that affect future tasks.

- 2026-03-06: Current dataset composition is only USAspending awards (`contracts_clean.source_type=usaspending` for all 2,500 rows), so many fields are structurally absent from upstream payloads rather than lost in rendering.
- 2026-03-06: `contracts_clean.source_name` is blank on existing rows; normalize now sets it for new rows, but historical rows need query fallback/backfill.
- 2026-03-06: `upsert_raw_record` and `normalize_contracts` already include null/blank overwrite protections; next pass should focus on targeted field fallbacks and ingestion-source coverage.
