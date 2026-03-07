USE patriotcontracts;

ALTER TABLE contracts_clean
  MODIFY COLUMN description MEDIUMTEXT NULL,
  ADD COLUMN IF NOT EXISTS description_raw MEDIUMTEXT NULL AFTER description,
  ADD COLUMN IF NOT EXISTS description_clean MEDIUMTEXT NULL AFTER description_raw,
  ADD COLUMN IF NOT EXISTS summary_plain TEXT NULL AFTER description_clean;
