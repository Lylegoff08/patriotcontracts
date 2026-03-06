USE patriotcontracts;

ALTER TABLE contracts_clean
  ADD COLUMN IF NOT EXISTS source_name VARCHAR(120) NULL AFTER source_type,
  ADD COLUMN IF NOT EXISTS notice_type VARCHAR(80) NULL AFTER psc_code,
  ADD COLUMN IF NOT EXISTS set_aside_code VARCHAR(80) NULL AFTER notice_type,
  ADD COLUMN IF NOT EXISTS set_aside_label VARCHAR(255) NULL AFTER set_aside_code,
  ADD COLUMN IF NOT EXISTS value_min DECIMAL(18,2) NULL AFTER award_amount,
  ADD COLUMN IF NOT EXISTS value_max DECIMAL(18,2) NULL AFTER value_min,
  ADD COLUMN IF NOT EXISTS place_state VARCHAR(80) NULL AFTER place_of_performance,
  ADD COLUMN IF NOT EXISTS is_biddable_now TINYINT(1) NOT NULL DEFAULT 0 AFTER category_id,
  ADD COLUMN IF NOT EXISTS is_upcoming_signal TINYINT(1) NOT NULL DEFAULT 0 AFTER is_biddable_now,
  ADD COLUMN IF NOT EXISTS is_awarded TINYINT(1) NOT NULL DEFAULT 0 AFTER is_upcoming_signal,
  ADD COLUMN IF NOT EXISTS deadline_soon TINYINT(1) NOT NULL DEFAULT 0 AFTER is_awarded,
  ADD COLUMN IF NOT EXISTS has_contact_info TINYINT(1) NOT NULL DEFAULT 0 AFTER deadline_soon,
  ADD COLUMN IF NOT EXISTS has_value_estimate TINYINT(1) NOT NULL DEFAULT 0 AFTER has_contact_info;

ALTER TABLE contracts_clean
  ADD INDEX IF NOT EXISTS idx_clean_actionability (is_biddable_now, is_upcoming_signal, is_awarded, deadline_soon),
  ADD INDEX IF NOT EXISTS idx_clean_set_aside (set_aside_code),
  ADD INDEX IF NOT EXISTS idx_clean_place_state (place_state);

CREATE TABLE IF NOT EXISTS naics_category_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  naics_prefix VARCHAR(10) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_naics_category FOREIGN KEY (category_id) REFERENCES contract_categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS psc_category_map (
  id INT AUTO_INCREMENT PRIMARY KEY,
  psc_prefix VARCHAR(10) NOT NULL UNIQUE,
  category_id INT NOT NULL,
  notes VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_psc_category FOREIGN KEY (category_id) REFERENCES contract_categories(id)
) ENGINE=InnoDB;

INSERT INTO sources (name, slug, base_url, auth_type, notes)
VALUES
('SAM Contract Awards API', 'sam_awards', 'https://api.sam.gov/contract-awards/v1', 'api_key', 'SAM contract awards API via OpenGSA'),
('Grants.gov Opportunities API', 'grants', 'https://api.grants.gov/v1/api', 'none', 'Federal assistance opportunities (kept separate from contract flow)')
ON DUPLICATE KEY UPDATE name=VALUES(name), base_url=VALUES(base_url), auth_type=VALUES(auth_type), notes=VALUES(notes);

INSERT INTO contract_categories (slug, name, description)
VALUES
('supplies-equipment', 'Supplies and Equipment', 'General commodity procurement, supplies, and equipment purchases'),
('field-services', 'Field Services', 'On-site service delivery and operational support services'),
('repair-maintenance', 'Repair and Maintenance', 'Repair, overhaul, sustainment, and maintenance support'),
('administrative-support', 'Administrative Support', 'Clerical, back-office, records, and program administration support')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);

INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '23', id, 'Construction NAICS family' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '238', id, 'Specialty trade contractors' FROM contract_categories WHERE slug = 'repair-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '42', id, 'Wholesale trade / supplies' FROM contract_categories WHERE slug = 'supplies-equipment'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5415', id, 'IT services' FROM contract_categories WHERE slug = 'it-cybersecurity'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5417', id, 'R&D services' FROM contract_categories WHERE slug = 'research-and-development'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5612', id, 'Facilities support services' FROM contract_categories WHERE slug = 'facilities-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '62', id, 'Healthcare and social assistance' FROM contract_categories WHERE slug = 'healthcare'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);

INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'D3', id, 'IT and telecom support' FROM contract_categories WHERE slug = 'it-cybersecurity'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'Q', id, 'Medical services/supplies PSC family' FROM contract_categories WHERE slug = 'healthcare'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'R', id, 'Professional support services' FROM contract_categories WHERE slug = 'professional-services'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'S', id, 'Housekeeping/maintenance and field services' FROM contract_categories WHERE slug = 'field-services'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'J', id, 'Maintenance, repair, and rebuild' FROM contract_categories WHERE slug = 'repair-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'Y', id, 'Construction of structures and facilities' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
