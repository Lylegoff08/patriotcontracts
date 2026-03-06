-- PatriotContracts consolidated installer
-- Generated from schema + migrations
-- Order: schema -> actionability -> membership_auth -> search_ingest_hardening
-- Idempotency relies on IF NOT EXISTS / ON DUPLICATE KEY UPDATE patterns in source files.

USE patriotcontracts;

-- ===== BEGIN sql/schema.sql =====

CREATE DATABASE IF NOT EXISTS patriotcontracts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE patriotcontracts;

CREATE TABLE IF NOT EXISTS sources (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  base_url VARCHAR(255) NULL,
  auth_type VARCHAR(50) NOT NULL DEFAULT 'none',
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agencies (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  normalized_name VARCHAR(255) NOT NULL,
  agency_code VARCHAR(40) NULL,
  subagency VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_agency_normalized (normalized_name),
  KEY idx_agency_name (name)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vendors (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  normalized_name VARCHAR(255) NOT NULL,
  uei VARCHAR(20) NULL,
  duns VARCHAR(20) NULL,
  cage_code VARCHAR(20) NULL,
  city VARCHAR(120) NULL,
  state VARCHAR(80) NULL,
  country VARCHAR(80) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vendor_normalized (normalized_name),
  KEY idx_vendor_name (name),
  KEY idx_vendor_uei (uei)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contracts_raw (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_id INT NOT NULL,
  source_record_id VARCHAR(191) NULL,
  source_url VARCHAR(500) NULL,
  payload_json LONGTEXT NOT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_raw_source (source_id),
  KEY idx_raw_processed (processed),
  KEY idx_raw_source_record (source_record_id),
  KEY idx_raw_source_source_record (source_id, source_record_id),
  CONSTRAINT fk_raw_source FOREIGN KEY (source_id) REFERENCES sources(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contracts_clean (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_id INT NOT NULL,
  raw_id BIGINT NOT NULL,
  source_record_id VARCHAR(191) NULL,
  source_type VARCHAR(60) NOT NULL,
  source_name VARCHAR(120) NULL,
  contract_number VARCHAR(120) NULL,
  title VARCHAR(500) NOT NULL,
  description TEXT NULL,
  agency_id BIGINT NULL,
  vendor_id BIGINT NULL,
  naics_code VARCHAR(20) NULL,
  psc_code VARCHAR(20) NULL,
  notice_type VARCHAR(80) NULL,
  set_aside_code VARCHAR(80) NULL,
  set_aside_label VARCHAR(255) NULL,
  award_amount DECIMAL(18,2) NULL,
  value_min DECIMAL(18,2) NULL,
  value_max DECIMAL(18,2) NULL,
  currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
  posted_date DATE NULL,
  award_date DATE NULL,
  response_deadline DATE NULL,
  start_date DATE NULL,
  end_date DATE NULL,
  status VARCHAR(80) NULL,
  place_of_performance VARCHAR(255) NULL,
  place_state VARCHAR(80) NULL,
  contact_name VARCHAR(255) NULL,
  contact_email VARCHAR(255) NULL,
  contact_phone VARCHAR(60) NULL,
  contracting_office VARCHAR(255) NULL,
  contact_address VARCHAR(255) NULL,
  source_url VARCHAR(500) NULL,
  category_id INT NULL,
  is_biddable_now TINYINT(1) NOT NULL DEFAULT 0,
  is_upcoming_signal TINYINT(1) NOT NULL DEFAULT 0,
  is_awarded TINYINT(1) NOT NULL DEFAULT 0,
  deadline_soon TINYINT(1) NOT NULL DEFAULT 0,
  has_contact_info TINYINT(1) NOT NULL DEFAULT 0,
  has_value_estimate TINYINT(1) NOT NULL DEFAULT 0,
  is_duplicate TINYINT(1) NOT NULL DEFAULT 0,
  duplicate_of BIGINT NULL,
  dedupe_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contract_raw (raw_id),
  KEY idx_clean_source_record (source_record_id),
  KEY idx_clean_contract_number (contract_number),
  KEY idx_clean_title (title(191)),
  KEY idx_clean_status (status),
  KEY idx_clean_dates (posted_date, award_date),
  KEY idx_clean_agency (agency_id),
  KEY idx_clean_vendor (vendor_id),
  KEY idx_clean_category (category_id),
  KEY idx_clean_actionability (is_biddable_now, is_upcoming_signal, is_awarded, deadline_soon),
  KEY idx_clean_set_aside (set_aside_code),
  KEY idx_clean_place_state (place_state),
  KEY idx_clean_duplicate (is_duplicate),
  KEY idx_clean_duplicate_posted_id (is_duplicate, posted_date, id),
  KEY idx_clean_duplicate_deadline_posted_id (is_duplicate, response_deadline, posted_date, id),
  CONSTRAINT fk_clean_source FOREIGN KEY (source_id) REFERENCES sources(id),
  CONSTRAINT fk_clean_raw FOREIGN KEY (raw_id) REFERENCES contracts_raw(id),
  CONSTRAINT fk_clean_agency FOREIGN KEY (agency_id) REFERENCES agencies(id),
  CONSTRAINT fk_clean_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id),
  CONSTRAINT fk_clean_category FOREIGN KEY (category_id) REFERENCES contract_categories(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_category_map (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  category_id INT NOT NULL,
  confidence DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  rule_name VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_contract_category (contract_id, category_id),
  CONSTRAINT fk_map_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id),
  CONSTRAINT fk_map_category FOREIGN KEY (category_id) REFERENCES contract_categories(id)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS contract_updates (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  update_type VARCHAR(80) NOT NULL,
  old_value TEXT NULL,
  new_value TEXT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source_note VARCHAR(255) NULL,
  KEY idx_updates_contract (contract_id),
  CONSTRAINT fk_updates_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stats_daily (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  stat_date DATE NOT NULL,
  metric_key VARCHAR(120) NOT NULL,
  metric_value DECIMAL(20,2) NOT NULL DEFAULT 0,
  extra_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_stats_day_metric (stat_date, metric_key)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ingest_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_id INT NULL,
  script_name VARCHAR(120) NOT NULL,
  status VARCHAR(30) NOT NULL,
  message TEXT NULL,
  records_fetched INT NOT NULL DEFAULT 0,
  records_processed INT NOT NULL DEFAULT 0,
  started_at DATETIME NOT NULL,
  finished_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ingest_script (script_name),
  KEY idx_ingest_status (status),
  KEY idx_ingest_started (started_at),
  CONSTRAINT fk_ingest_source FOREIGN KEY (source_id) REFERENCES sources(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(255) NULL,
  role VARCHAR(40) NOT NULL DEFAULT 'user',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscription_plans (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_code VARCHAR(80) NOT NULL UNIQUE,
  plan_name VARCHAR(120) NOT NULL,
  plan_type VARCHAR(40) NOT NULL,
  price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
  request_limit_daily INT NULL,
  feature_json TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS subscriptions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  plan_id BIGINT NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'active',
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  auto_renew TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sub_user (user_id),
  KEY idx_sub_status (status),
  CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_keys (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  api_key CHAR(64) NOT NULL UNIQUE,
  label VARCHAR(120) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  daily_limit INT NOT NULL DEFAULT 1000,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at DATETIME NULL,
  CONSTRAINT fk_api_key_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS api_usage (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  api_key_id BIGINT NOT NULL,
  endpoint VARCHAR(120) NOT NULL,
  request_count INT NOT NULL DEFAULT 1,
  usage_date DATE NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usage_daily (api_key_id, endpoint, usage_date),
  CONSTRAINT fk_usage_key FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS saved_searches (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  name VARCHAR(120) NOT NULL,
  query_json TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_saved_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alerts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  saved_search_id BIGINT NULL,
  alert_type VARCHAR(40) NOT NULL DEFAULT 'email',
  frequency VARCHAR(40) NOT NULL DEFAULT 'daily',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  last_run_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alert_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_alert_saved_search FOREIGN KEY (saved_search_id) REFERENCES saved_searches(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_scores (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  score_type VARCHAR(80) NOT NULL,
  score_value DECIMAL(8,2) NOT NULL,
  detail_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_scores_contract (contract_id),
  CONSTRAINT fk_scores_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_similarities (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  similar_contract_id BIGINT NOT NULL,
  similarity_value DECIMAL(8,4) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_similarity_pair (contract_id, similar_contract_id),
  CONSTRAINT fk_sim_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id),
  CONSTRAINT fk_sim_similar FOREIGN KEY (similar_contract_id) REFERENCES contracts_clean(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contract_renewals (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  expected_renewal_date DATE NULL,
  renewal_status VARCHAR(80) NULL,
  detail_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_renewal_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS vendor_metrics (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vendor_id BIGINT NOT NULL,
  metric_date DATE NOT NULL,
  total_contracts INT NOT NULL DEFAULT 0,
  total_awarded DECIMAL(20,2) NOT NULL DEFAULT 0,
  detail_json TEXT NULL,
  UNIQUE KEY uq_vendor_metric_day (vendor_id, metric_date),
  CONSTRAINT fk_vendor_metrics_vendor FOREIGN KEY (vendor_id) REFERENCES vendors(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS agency_metrics (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  agency_id BIGINT NOT NULL,
  metric_date DATE NOT NULL,
  total_contracts INT NOT NULL DEFAULT 0,
  total_obligated DECIMAL(20,2) NOT NULL DEFAULT 0,
  detail_json TEXT NULL,
  UNIQUE KEY uq_agency_metric_day (agency_id, metric_date),
  CONSTRAINT fk_agency_metrics_agency FOREIGN KEY (agency_id) REFERENCES agencies(id)
) ENGINE=InnoDB;

INSERT INTO sources (name, slug, base_url, auth_type, notes)
VALUES
('USAspending API', 'usaspending', 'https://api.usaspending.gov', 'none', 'Federal spending and award data API'),
('SAM Contract Opportunities API', 'sam_opportunities', 'https://api.sam.gov', 'api_key', 'Public opportunities API via SAM OpenGSA docs'),
('SAM Contract Awards API', 'sam_awards', 'https://api.sam.gov/contract-awards/v1', 'api_key', 'SAM contract awards API via OpenGSA'),
('Grants.gov Opportunities API', 'grants', 'https://api.grants.gov/v1/api', 'none', 'Federal assistance opportunities (kept separate from contract flow)')
ON DUPLICATE KEY UPDATE name=VALUES(name), base_url=VALUES(base_url), auth_type=VALUES(auth_type), notes=VALUES(notes);

INSERT INTO contract_categories (slug, name, description)
VALUES
('defense', 'Defense', 'Defense and military-related contracts'),
('construction', 'Construction', 'Building, infrastructure, and construction services'),
('it-cybersecurity', 'IT / Cybersecurity', 'Software, IT services, cyber, and telecom'),
('logistics', 'Logistics', 'Transportation, supply chain, and operations support'),
('healthcare', 'Healthcare', 'Medical, health systems, and life sciences support'),
('professional-services', 'Professional Services', 'Consulting, legal, admin, and program support'),
('manufacturing', 'Manufacturing', 'Manufactured goods, components, and production contracts'),
('research-and-development', 'Research and Development', 'R&D, testing, labs, and scientific development'),
('facilities-maintenance', 'Facilities Maintenance', 'Base operations, maintenance, janitorial, and building support'),
('security-and-law-enforcement', 'Security and Law Enforcement', 'Security services, policing support, and protective services'),
('aerospace-and-aviation', 'Aerospace and Aviation', 'Aircraft, avionics, space, and flight-related procurement'),
('energy-and-environment', 'Energy and Environment', 'Energy systems, utilities, sustainability, and environmental remediation'),
('education-and-training', 'Education and Training', 'Training, curriculum, instruction, and education support'),
('telecom', 'Telecom', 'Telecommunications infrastructure, voice/data services, and related equipment'),
('professional-scientific-technical', 'Professional, Scientific, and Technical', 'Advanced technical and scientific services'),
('grants-assistance', 'Grants Assistance', 'Assistance and grant-related awards where ingested'),
('supplies-equipment', 'Supplies and Equipment', 'General commodity procurement, supplies, and equipment purchases'),
('field-services', 'Field Services', 'On-site service delivery and operational support services'),
('repair-maintenance', 'Repair and Maintenance', 'Repair, overhaul, sustainment, and maintenance support'),
('administrative-support', 'Administrative Support', 'Clerical, back-office, records, and program administration support'),
('uncategorized', 'Uncategorized', 'No rules matched')
ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description);

INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '23', id, 'Construction NAICS family' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '236', id, 'Building construction' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '237', id, 'Heavy and civil engineering construction' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '238', id, 'Specialty trade contractors' FROM contract_categories WHERE slug = 'repair-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '31', id, 'Manufacturing' FROM contract_categories WHERE slug = 'manufacturing'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '32', id, 'Manufacturing' FROM contract_categories WHERE slug = 'manufacturing'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '33', id, 'Manufacturing' FROM contract_categories WHERE slug = 'manufacturing'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '42', id, 'Wholesale trade / supplies' FROM contract_categories WHERE slug = 'supplies-equipment'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '48', id, 'Transportation and warehousing' FROM contract_categories WHERE slug = 'logistics'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '51', id, 'Information and telecom services' FROM contract_categories WHERE slug = 'telecom'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '517', id, 'Telecommunications' FROM contract_categories WHERE slug = 'telecom'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '54', id, 'Professional services' FROM contract_categories WHERE slug = 'professional-services'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5415', id, 'IT services' FROM contract_categories WHERE slug = 'it-cybersecurity'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5416', id, 'Management and advisory services' FROM contract_categories WHERE slug = 'professional-services'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5417', id, 'R&D services' FROM contract_categories WHERE slug = 'research-and-development'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '56', id, 'Administrative and support services' FROM contract_categories WHERE slug = 'administrative-support'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5612', id, 'Facilities support services' FROM contract_categories WHERE slug = 'facilities-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '5616', id, 'Investigation and security services' FROM contract_categories WHERE slug = 'security-and-law-enforcement'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '562', id, 'Waste management and remediation' FROM contract_categories WHERE slug = 'energy-and-environment'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '611', id, 'Educational services' FROM contract_categories WHERE slug = 'education-and-training'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO naics_category_map (naics_prefix, category_id, notes)
SELECT '62', id, 'Healthcare and social assistance' FROM contract_categories WHERE slug = 'healthcare'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);

INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'D3', id, 'IT and telecom support' FROM contract_categories WHERE slug = 'it-cybersecurity'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT '7', id, 'IT hardware/software and related services' FROM contract_categories WHERE slug = 'it-cybersecurity'
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
SELECT 'K', id, 'Modification of equipment' FROM contract_categories WHERE slug = 'repair-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'M', id, 'Operation of government-owned facilities' FROM contract_categories WHERE slug = 'facilities-maintenance'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'N', id, 'Installation of equipment' FROM contract_categories WHERE slug = 'field-services'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);
INSERT INTO psc_category_map (psc_prefix, category_id, notes)
SELECT 'Y', id, 'Construction of structures and facilities' FROM contract_categories WHERE slug = 'construction'
ON DUPLICATE KEY UPDATE category_id=VALUES(category_id), notes=VALUES(notes);

INSERT INTO subscription_plans (plan_code, plan_name, plan_type, price_monthly, request_limit_daily, feature_json)
VALUES
('api_dev', 'Developer API', 'api', 49.00, 5000, '{"api":true,"dashboard":false}'),
('api_business', 'Business API', 'api', 199.00, 50000, '{"api":true,"priority_support":true}'),
('contractor_free', 'Contractor Free', 'contractor', 0.00, NULL, '{"saved_searches":false,"alerts":false}'),
('contractor_pro', 'Contractor Pro', 'contractor', 79.00, NULL, '{"saved_searches":true,"alerts":true}'),
('contractor_business', 'Contractor Business', 'contractor', 249.00, NULL, '{"saved_searches":true,"alerts":true,"advanced_filters":true}')
ON DUPLICATE KEY UPDATE plan_name=VALUES(plan_name), price_monthly=VALUES(price_monthly), request_limit_daily=VALUES(request_limit_daily), feature_json=VALUES(feature_json);

-- Membership/Auth/API hardening for fresh installs
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER full_name,
  ADD COLUMN IF NOT EXISTS provider_id VARCHAR(191) NULL AFTER provider,
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_id,
  ADD COLUMN IF NOT EXISTS account_status VARCHAR(40) NOT NULL DEFAULT 'pending_payment' AFTER email_verified,
  ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(120) NULL AFTER role,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER updated_at,
  ADD UNIQUE KEY IF NOT EXISTS uq_users_provider (provider, provider_id),
  ADD KEY IF NOT EXISTS idx_users_status (account_status);

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_verifications_token (token_hash),
  KEY idx_email_verifications_user (user_id),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NULL,
  ip_address VARCHAR(64) NOT NULL,
  was_success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_email_time (email, attempted_at),
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS name VARCHAR(120) NULL AFTER plan_code,
  ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120) NULL AFTER price_monthly,
  ADD COLUMN IF NOT EXISTS feature_flags_json TEXT NULL AFTER stripe_price_id,
  ADD COLUMN IF NOT EXISTS api_daily_limit INT NULL AFTER feature_flags_json,
  ADD UNIQUE KEY IF NOT EXISTS uq_subscription_plan_code (plan_code);

ALTER TABLE subscriptions
  ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(120) NULL AFTER plan_id,
  ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(120) NULL AFTER stripe_customer_id,
  ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(120) NULL AFTER stripe_subscription_id,
  ADD COLUMN IF NOT EXISTS current_period_end DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD KEY IF NOT EXISTS idx_subscriptions_stripe_subscription (stripe_subscription_id);

ALTER TABLE api_keys
  ADD COLUMN IF NOT EXISTS api_key_hash CHAR(64) NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS api_key_prefix VARCHAR(16) NULL AFTER api_key_hash,
  ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER label,
  ADD UNIQUE KEY IF NOT EXISTS uq_api_keys_hash (api_key_hash);

ALTER TABLE api_usage
  ADD COLUMN IF NOT EXISTS request_ip VARCHAR(64) NULL AFTER endpoint,
  ADD COLUMN IF NOT EXISTS requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER request_ip;

CREATE TABLE IF NOT EXISTS webhook_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_webhook_provider_event (provider, event_id)
) ENGINE=InnoDB;

INSERT INTO subscription_plans (plan_code, name, plan_name, plan_type, price_monthly, stripe_price_id, feature_flags_json, api_daily_limit, request_limit_daily, feature_json, is_active)
VALUES
('MEMBER_BASIC', 'Member Basic', 'Member Basic', 'member', 29.00, '', '{"member_tools":true}', NULL, NULL, '{"member_tools":true}', 1),
('MEMBER_PRO', 'Member Pro', 'Member Pro', 'member', 79.00, '', '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true}', NULL, NULL, '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true}', 1),
('API_MEMBER', 'API Member', 'API Member', 'api', 199.00, '', '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true,"api_access":true,"api_keys":true}', 50000, 50000, '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true,"api_access":true,"api_keys":true}', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  plan_name = VALUES(plan_name),
  price_monthly = VALUES(price_monthly),
  feature_flags_json = VALUES(feature_flags_json),
  feature_json = VALUES(feature_json),
  api_daily_limit = VALUES(api_daily_limit),
  request_limit_daily = VALUES(request_limit_daily),
  is_active = VALUES(is_active);

-- Maintenance backfill helper:
-- Requeue raw rows tied to award records that still have incomplete award classification.
UPDATE contracts_raw cr
JOIN contracts_clean cc ON cc.raw_id = cr.id
SET cr.processed = 0
WHERE cc.source_type IN ('usaspending','sam_award')
  AND cc.award_date IS NOT NULL
  AND (
    cc.status IS NULL
    OR cc.status = ''
    OR cc.status = 'unknown'
    OR cc.is_awarded = 0
  );


-- ===== END sql/schema.sql =====

-- ===== BEGIN sql/migration_2026_03_actionability.sql =====

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


-- ===== END sql/migration_2026_03_actionability.sql =====

-- ===== BEGIN sql/migration_2026_03_membership_auth.sql =====

USE patriotcontracts;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS provider VARCHAR(30) NOT NULL DEFAULT 'email' AFTER full_name,
  ADD COLUMN IF NOT EXISTS provider_id VARCHAR(191) NULL AFTER provider,
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_id,
  ADD COLUMN IF NOT EXISTS account_status VARCHAR(40) NOT NULL DEFAULT 'pending_payment' AFTER email_verified,
  ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(120) NULL AFTER role,
  ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER updated_at;

ALTER TABLE users
  ADD UNIQUE KEY IF NOT EXISTS uq_users_provider (provider, provider_id),
  ADD KEY IF NOT EXISTS idx_users_status (account_status),
  ADD KEY IF NOT EXISTS idx_users_verified (email_verified),
  ADD KEY IF NOT EXISTS idx_users_stripe_customer (stripe_customer_id);

UPDATE users
SET provider = 'email'
WHERE provider IS NULL OR provider = '';

UPDATE users
SET email_verified = CASE WHEN is_active = 1 THEN 1 ELSE 0 END
WHERE email_verified IS NULL OR email_verified = 0;

UPDATE users
SET account_status = CASE WHEN is_active = 1 THEN 'active' ELSE 'suspended' END
WHERE account_status IS NULL OR account_status = '';

CREATE TABLE IF NOT EXISTS email_verifications (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_email_verifications_token (token_hash),
  KEY idx_email_verifications_user (user_id),
  KEY idx_email_verifications_exp (expires_at),
  CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id),
  KEY idx_password_resets_exp (expires_at),
  CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NULL,
  ip_address VARCHAR(64) NOT NULL,
  was_success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_email_time (email, attempted_at),
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

ALTER TABLE subscription_plans
  ADD COLUMN IF NOT EXISTS name VARCHAR(120) NULL AFTER plan_code,
  ADD COLUMN IF NOT EXISTS stripe_price_id VARCHAR(120) NULL AFTER price_monthly,
  ADD COLUMN IF NOT EXISTS feature_flags_json TEXT NULL AFTER stripe_price_id,
  ADD COLUMN IF NOT EXISTS api_daily_limit INT NULL AFTER feature_flags_json;

ALTER TABLE subscription_plans
  MODIFY COLUMN plan_code VARCHAR(80) NOT NULL,
  MODIFY COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD UNIQUE KEY IF NOT EXISTS uq_subscription_plan_code (plan_code);

UPDATE subscription_plans
SET name = COALESCE(NULLIF(name, ''), plan_name)
WHERE name IS NULL OR name = '';

UPDATE subscription_plans
SET feature_flags_json = COALESCE(feature_flags_json, feature_json)
WHERE feature_flags_json IS NULL;

UPDATE subscription_plans
SET api_daily_limit = request_limit_daily
WHERE api_daily_limit IS NULL AND request_limit_daily IS NOT NULL;

ALTER TABLE subscriptions
  ADD COLUMN IF NOT EXISTS stripe_customer_id VARCHAR(120) NULL AFTER plan_id,
  ADD COLUMN IF NOT EXISTS stripe_subscription_id VARCHAR(120) NULL AFTER stripe_customer_id,
  ADD COLUMN IF NOT EXISTS stripe_checkout_session_id VARCHAR(120) NULL AFTER stripe_subscription_id,
  ADD COLUMN IF NOT EXISTS current_period_end DATETIME NULL AFTER status,
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

ALTER TABLE subscriptions
  ADD KEY IF NOT EXISTS idx_subscriptions_stripe_subscription (stripe_subscription_id),
  ADD KEY IF NOT EXISTS idx_subscriptions_stripe_customer (stripe_customer_id),
  ADD KEY IF NOT EXISTS idx_subscriptions_checkout (stripe_checkout_session_id);

UPDATE subscriptions
SET current_period_end = ends_at
WHERE current_period_end IS NULL AND ends_at IS NOT NULL;

ALTER TABLE api_keys
  ADD COLUMN IF NOT EXISTS api_key_hash CHAR(64) NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS api_key_prefix VARCHAR(16) NULL AFTER api_key_hash,
  ADD COLUMN IF NOT EXISTS status VARCHAR(30) NOT NULL DEFAULT 'active' AFTER label;

ALTER TABLE api_keys
  ADD KEY IF NOT EXISTS idx_api_keys_prefix (api_key_prefix),
  ADD KEY IF NOT EXISTS idx_api_keys_status (status),
  ADD KEY IF NOT EXISTS idx_api_keys_user_status (user_id, status),
  ADD UNIQUE KEY IF NOT EXISTS uq_api_keys_hash (api_key_hash);

UPDATE api_keys
SET api_key_hash = SHA2(api_key, 256),
    api_key_prefix = LEFT(api_key, 8)
WHERE api_key_hash IS NULL AND api_key IS NOT NULL;

ALTER TABLE api_usage
  ADD COLUMN IF NOT EXISTS request_ip VARCHAR(64) NULL AFTER endpoint,
  ADD COLUMN IF NOT EXISTS requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER request_ip;

ALTER TABLE api_usage
  ADD KEY IF NOT EXISTS idx_api_usage_key_time (api_key_id, requested_at),
  ADD KEY IF NOT EXISTS idx_api_usage_endpoint_time (endpoint, requested_at);

CREATE TABLE IF NOT EXISTS webhook_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  event_id VARCHAR(191) NOT NULL,
  event_type VARCHAR(120) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  processed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_webhook_provider_event (provider, event_id),
  KEY idx_webhook_type (event_type),
  KEY idx_webhook_created (created_at)
) ENGINE=InnoDB;

INSERT INTO subscription_plans (plan_code, name, plan_name, plan_type, price_monthly, stripe_price_id, feature_flags_json, api_daily_limit, request_limit_daily, feature_json, is_active)
VALUES
('MEMBER_BASIC', 'Member Basic', 'Member Basic', 'member', 29.00, '', '{"member_tools":true}', NULL, NULL, '{"member_tools":true}', 1),
('MEMBER_PRO', 'Member Pro', 'Member Pro', 'member', 79.00, '', '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true}', NULL, NULL, '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true}', 1),
('API_MEMBER', 'API Member', 'API Member', 'api', 199.00, '', '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true,"api_access":true,"api_keys":true}', 50000, 50000, '{"member_tools":true,"advanced_search":true,"saved_searches":true,"alerts":true,"csv_export":true,"api_access":true,"api_keys":true}', 1)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  plan_name = VALUES(plan_name),
  price_monthly = VALUES(price_monthly),
  feature_flags_json = VALUES(feature_flags_json),
  feature_json = VALUES(feature_json),
  api_daily_limit = VALUES(api_daily_limit),
  request_limit_daily = VALUES(request_limit_daily),
  is_active = VALUES(is_active);


-- ===== END sql/migration_2026_03_membership_auth.sql =====

-- ===== BEGIN sql/migration_2026_03_search_ingest_hardening.sql =====

USE patriotcontracts;

ALTER TABLE contracts_clean
  ADD INDEX IF NOT EXISTS idx_clean_duplicate_posted_id (is_duplicate, posted_date, id),
  ADD INDEX IF NOT EXISTS idx_clean_duplicate_deadline_posted_id (is_duplicate, response_deadline, posted_date, id);

ALTER TABLE contracts_raw
  ADD INDEX IF NOT EXISTS idx_raw_source_source_record (source_id, source_record_id);


-- ===== END sql/migration_2026_03_search_ingest_hardening.sql =====

