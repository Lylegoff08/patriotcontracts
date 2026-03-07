# Listing Field Audit (March 6, 2026)

## Scope
- Audited listing/detail/search rendering and fallback behavior in:
  - `index.php`, `search.php`, `archive.php`, `open-this-week.php`, `deadline-soon.php`, `early-signals.php`, `recent-awards.php`, `contract.php`
- Traced ingestion/storage pipeline in:
  - `ingest/ingest_*.php` -> `contracts_raw.payload_json`
  - `ingest/normalize_contracts.php` -> `contracts_clean`
  - rendering helpers in `includes/functions.php`

## Field Inventory and End-to-End Mapping
| Frontend Field | Frontend Usage | Frontend Key | DB Column | Source Payload Keys (primary + fallback) | Parser/Transform Location | Issue Category | Fix Applied | Remaining Legit Missing? |
|---|---|---|---|---|---|---|---|---|
| Title | listing cards + detail headers | `title` | `contracts_clean.title` | `title`, `solicitationTitle`, `opportunityTitle`, `Award ID`, `awardId`, `subject` | `ingest/normalize_contracts.php` | C, G | Added broader key fallback + non-empty fallback using source id only when truly absent | Rarely |
| Agency | list/detail metadata | `agency_name` | `agency_id` (join `agencies.name`) | `agencyName`, `agency`, `department`, `Awarding Agency`, `Awarding Sub Agency`, `departmentIndAgency`, `office`, `organization.name` | `ingest/normalize_contracts.php` | B, G | Expanded mapping; stop forcing fake `"Unknown Agency"` values; preserve null and show field-specific fallback | Yes |
| Vendor | list/detail metadata | `vendor_name` | `vendor_id` (join `vendors.name`) | `organizationName`, `awardeeName`, `Recipient Name`, `legalBusinessName`, `entityName`, `applicantType` | `ingest/normalize_contracts.php` | B, G | Expanded mapping; stop forcing fake `"Unknown Vendor"` values; preserve null with clear fallback | Yes |
| Notice Type | list/detail metadata | `notice_type` | `notice_type` | `noticeType`, `type`, `Notice Type`, `awardType`, `opportunityType`, `noticeTypeCode` | `includes/functions.php` + `ingest/normalize_contracts.php` | B, G | Added alternate key fallback + cleanup of placeholders | Yes |
| Contract Number | list/detail metadata | `contract_number` | `contract_number` | `solicitationNumber`, `opportunityNumber`, `piid`, `Award ID`, `awardId`, `referenceNumber`, `solicitationId` | `ingest/normalize_contracts.php` | B, G | Expanded mapping and cleanup | Yes |
| Posted Date | list/detail metadata | `posted_date` | `posted_date` | `postedDate`, `publishDate`, `openDate`, `createdDate` | `ingest/normalize_contracts.php` | B, C | Added `clean_date()` with alternate keys + safe parsing | Yes |
| Response Deadline | list/detail metadata | `response_deadline` | `response_deadline` | `responseDeadLine`, `responseDeadline`, `responseDate`, `closeDate`, `dueDate`, `offersDueDate` | `ingest/normalize_contracts.php` | B, G | Added alternate spellings/keys and robust date cleanup | Yes |
| Award Date | list/detail metadata | `award_date` | `award_date` | `awardDate`, `awardDateSigned`, `Start Date`, `signedDate` | `ingest/normalize_contracts.php` | B | Added alternate date keys + cleanup | Yes |
| End Date | detail/archive metadata | `end_date` | `end_date` | `archiveDate`, `End Date`, `closeDate`, `periodOfPerformanceEndDate` | `ingest/normalize_contracts.php` | B | Added alternate date keys + cleanup | Yes |
| Set-Aside | list/detail metadata | `set_aside_label` | `set_aside_label`, `set_aside_code` | `typeOfSetAsideDescription`, `typeOfSetAside`, `setAsideCode`, `setAside` | `includes/functions.php` + `ingest/normalize_contracts.php` | C, G | Applied normalized cleanup and field-specific fallback text | Yes |
| NAICS | detail + descriptions | `naics_code` | `naics_code` | `naicsCode`, `naics`, `naicsCodeValue`, `naicsCodes[0]` | `ingest/normalize_contracts.php` | B, G | Expanded mapping + cleanup | Yes |
| PSC | detail + descriptions | `psc_code` | `psc_code` | `pscCode`, `psc`, `pscCodeValue` | `ingest/normalize_contracts.php` | B, G | Expanded mapping + cleanup | Yes |
| Place of Performance | detail + summaries | `place_of_performance`, `place_state` | `place_of_performance`, `place_state` | `placeOfPerformance`, `placeOfPerformanceAddress`, `placeOfPerformanceStateName`, `placeOfPerformanceCode`, `city/state/country` composition | `ingest/normalize_contracts.php` | B, C, G | Added composed location fallback and improved state extraction | Yes |
| Contact Name | detail | `contact_name` | `contact_name` | `pointOfContact[0].fullName/name/title`, `contacts[0].name/fullName`, `primaryContactName`, `contactName` | `ingest/normalize_contracts.php` | B, G | Expanded nested mapping + `clean_contact_field()` | Yes |
| Contact Email | detail | `contact_email` | `contact_email` | `pointOfContact[0].email`, `contacts[0].email`, `primaryContactEmail`, `contactEmail`, `officeAddress.email` | `ingest/normalize_contracts.php` | B, G | Expanded nested mapping + `clean_contact_field()` | Yes |
| Contact Phone | detail | `contact_phone` | `contact_phone` | `pointOfContact[0].phone`, `contacts[0].phone`, `primaryContactPhone`, `contactPhone`, `officeAddress.phone` | `ingest/normalize_contracts.php` | B, G | Expanded nested mapping + `clean_contact_field()` | Yes |
| Contracting Office | detail | `contracting_office` | `contracting_office` | `office`, `officeAddress.officeName/name`, `departmentIndAgency`, `agency`, `agencyName`, `organization.office` | `ingest/normalize_contracts.php` | B, G | Expanded mapping + cleanup | Yes |
| Contact Address | detail | `contact_address` | `contact_address` | `officeAddress`, contact address nested keys | `ingest/normalize_contracts.php` | B, G | Flatten/normalize object or nested values | Yes |
| Description/Summary | listings + detail | `description` | `description` | `description`, `synopsis`, `summary`, `awardDescription`, `fullParentPathName` | `ingest/normalize_contracts.php` + `includes/functions.php` | C, G | Expanded mapping + avoid showing placeholder junk as real data | Yes |
| Status | listings + detail | `status` | `status` | `status`, `type`, `opportunityStatus`, `awardStatus`, notice type fallback | `ingest/normalize_contracts.php` | B, G | Better status source selection then normalize | Yes |
| Source URL | detail | `source_url` | `source_url` | `contracts_raw.source_url`, plus `uiLink`, `url`, `link`, `opportunityUrl`, `awardUrl` | `ingest/normalize_contracts.php` + `contract.php` | D, G | Added fallback mapping + changed UI text to "View source notice"/"Source notice unavailable" | Yes |
| Award/Value | listings + detail | `award_amount`, `value_min`, `value_max` | same | amount/range keys from payload | `includes/functions.php` + `ingest/normalize_contracts.php` | C | Kept numeric parse, improved display fallback to "Not listed" | Yes |

## Rendering Fallback Changes
- Replaced generic `"N/A"` behavior with field-specific text through `display_field_value()` in `includes/functions.php`.
- Updated listing/detail pages to use consistent messages:
  - agency: `Not provided`
  - deadline: `No deadline listed`
  - set-aside: `Not specified`
  - place: `Location not specified`
  - contact email: `No contact email listed`
  - value: `Not listed`
  - summary: `No summary provided`
  - source URL: `Source notice unavailable`

## Diagnostic / Debug Support Added
- `ingest/normalize_contracts.php` now tracks missing-field counts during normalization and appends JSON lines to:
  - `logs/normalize_missing_fields.log`
- Optional per-record debug logging enabled by either:
  - config: `ingest.debug_missing_fields = true`
  - env var: `PC_DEBUG_MISSING_FIELDS=1`
- Added CLI audit script:
  - `ingest/audit_listing_fields.php`

## Repair / Backfill Support Added
- Added safe script:
  - `ingest/repair_listing_fields.php`
- Behavior:
  - default `dry-run`
  - updates only empty/placeholder fields
  - never overwrites non-empty DB values
  - can repair agency/vendor ids when resolvable from payload
- Usage:
  - Dry-run: `C:\\xampp\\php\\php.exe ingest\\repair_listing_fields.php`
  - Apply: `C:\\xampp\\php\\php.exe ingest\\repair_listing_fields.php --apply`
  - Limit: append `--limit=5000`

## Classification Summary
- Fully fixed (mapping/fallback issues): title, notice type, contract number, date parsing, place of performance, contact fields, source URL fallback behavior.
- Partially fixed (source-dependent): NAICS, PSC, set-aside, vendor/agency presence.
- Still source-limited: some grants/award records do not include deadlines/contact fields by design.

## Notes
- Database connectivity was not available in this execution environment, so live row-level verification must be run in the target runtime where MySQL is reachable.
