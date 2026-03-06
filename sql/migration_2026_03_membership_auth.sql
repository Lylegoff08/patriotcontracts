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
