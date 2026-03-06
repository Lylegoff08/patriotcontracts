# INGESTION_RULES.md

## Ingestion Principles

PatriotContracts depends on trustworthy data. Data quality is more important than architectural cleverness.

---

## Core Rules

### Preserve raw data
- Never discard original source values unless explicitly intended
- Keep raw payloads or raw source fields available if already supported

### Normalize safely
- Standardize text, dates, and money carefully
- Map agencies, vendors, NAICS, PSC, and set-asides consistently
- Prefer deterministic mappings over guesswork

### Safe upserts
- Never overwrite a good existing value with NULL or blank unless explicitly required
- Prefer non-empty source values
- Preserve existing identifiers
- Update timestamps correctly

### Deduplication
Prefer stable identifiers such as:
- solicitation number
- notice id
- award id
- PIID
- other deterministic source identifiers

Do not use weak dedupe logic if a stronger key exists.

### Validation
Validate these fields where applicable:
- title
- source identifier
- agency
- relevant dates
- amount
- source type
- status

If a record is incomplete:
- log it
- flag it if appropriate
- do not silently pass it through as healthy data

### Logging
Track:
- records fetched
- records inserted
- records updated
- records skipped
- validation failures
- API/source failures
- transform/normalization failures

### Minimal-change policy
- Improve ingestion reliability without rebuilding the entire pipeline unless the current structure truly prevents safe fixes
- Prefer patching weak points over replacing whole scripts