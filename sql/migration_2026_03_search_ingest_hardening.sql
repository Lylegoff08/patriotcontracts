USE patriotcontracts;

ALTER TABLE contracts_clean
  ADD INDEX IF NOT EXISTS idx_clean_duplicate_posted_id (is_duplicate, posted_date, id),
  ADD INDEX IF NOT EXISTS idx_clean_duplicate_deadline_posted_id (is_duplicate, response_deadline, posted_date, id);

ALTER TABLE contracts_raw
  ADD INDEX IF NOT EXISTS idx_raw_source_source_record (source_id, source_record_id);
