-- Additive admin CMS structures for moderation/content/settings/audit

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS admin_role VARCHAR(20) NULL AFTER role,
  ADD KEY IF NOT EXISTS idx_users_admin_role (admin_role);

UPDATE users
SET admin_role = 'admin'
WHERE admin_role IS NULL AND role = 'admin';

CREATE TABLE IF NOT EXISTS listing_overrides (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  display_title VARCHAR(500) NULL,
  display_summary TEXT NULL,
  category_override INT NULL,
  tags_override VARCHAR(500) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  quality_flag VARCHAR(50) NULL,
  internal_notes TEXT NULL,
  updated_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_listing_overrides_contract (contract_id),
  KEY idx_listing_overrides_hidden_featured (is_hidden, is_featured),
  KEY idx_listing_overrides_quality_flag (quality_flag),
  CONSTRAINT fk_listing_overrides_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id),
  CONSTRAINT fk_listing_overrides_category FOREIGN KEY (category_override) REFERENCES contract_categories(id),
  CONSTRAINT fk_listing_overrides_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS grant_overrides (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  contract_id BIGINT NOT NULL,
  display_title VARCHAR(500) NULL,
  display_summary TEXT NULL,
  category_override INT NULL,
  tags_override VARCHAR(500) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_hidden TINYINT(1) NOT NULL DEFAULT 0,
  quality_flag VARCHAR(50) NULL,
  internal_notes TEXT NULL,
  updated_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_grant_overrides_contract (contract_id),
  KEY idx_grant_overrides_hidden_featured (is_hidden, is_featured),
  KEY idx_grant_overrides_quality_flag (quality_flag),
  CONSTRAINT fk_grant_overrides_contract FOREIGN KEY (contract_id) REFERENCES contracts_clean(id),
  CONSTRAINT fk_grant_overrides_category FOREIGN KEY (category_override) REFERENCES contract_categories(id),
  CONSTRAINT fk_grant_overrides_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_pages (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  meta_title VARCHAR(255) NULL,
  meta_description VARCHAR(500) NULL,
  body LONGTEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  created_by BIGINT NULL,
  updated_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_pages_slug (slug),
  KEY idx_site_pages_status (status),
  CONSTRAINT fk_site_pages_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_site_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS site_settings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  updated_by BIGINT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_site_settings_key (setting_key),
  CONSTRAINT fk_site_settings_updated_by FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_activity_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_user_id BIGINT NOT NULL,
  action VARCHAR(120) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id BIGINT NULL,
  details_json TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_activity_created (created_at),
  KEY idx_admin_activity_entity (entity_type, entity_id),
  KEY idx_admin_activity_admin_user (admin_user_id),
  CONSTRAINT fk_admin_activity_user FOREIGN KEY (admin_user_id) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO site_settings (setting_key, setting_value)
VALUES
('site_name', 'PatriotContracts'),
('tagline', 'Federal contract discovery and monitoring'),
('homepage_hero_title', 'Find government contract opportunities faster.'),
('homepage_hero_subtitle', 'Structured U.S. procurement records with cleaner browsing and search.'),
('support_email', ''),
('footer_text', 'Public U.S. procurement data. Rule-based categorization only. No AI/ML.'),
('pricing_cta_text', 'Buy Subscription')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

INSERT INTO site_pages (title, slug, meta_title, meta_description, body, status)
VALUES
('Homepage', 'homepage', 'PatriotContracts', 'Federal contract discovery platform.', '', 'published'),
('About', 'about', 'About PatriotContracts', 'About PatriotContracts.', '', 'draft'),
('Pricing', 'pricing', 'PatriotContracts Pricing', 'Pricing and plans.', '', 'draft'),
('FAQ', 'faq', 'PatriotContracts FAQ', 'Frequently asked questions.', '', 'draft'),
('Contact', 'contact', 'Contact PatriotContracts', 'Contact information.', '', 'draft')
ON DUPLICATE KEY UPDATE
  meta_title = VALUES(meta_title),
  meta_description = VALUES(meta_description);