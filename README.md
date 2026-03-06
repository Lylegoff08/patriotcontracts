# PatriotContracts

Federal contract data website + API built with plain PHP 8, MySQL, and Apache (XAMPP locally, cPanel-compatible in production).

## Stack
- PHP 8+
- MySQL/MariaDB
- Apache (XAMPP/cPanel)
- PDO (prepared statements)
- Stripe Checkout + Webhooks
- OAuth (Google, Apple, Facebook)

## Core model
- Free anonymous browsing is supported.
- Paid membership is required for member tools.
- API access requires active `API_MEMBER` plan + active API key.

### Plans
- `MEMBER_BASIC`: member login + dashboard/member tools
- `MEMBER_PRO`: adds advanced search, saved searches, alerts, CSV export
- `API_MEMBER`: adds API key generation, API access, request limits

## Authentication
Supported methods:
- Email + password
- Google OAuth
- Apple Sign In
- Facebook OAuth

Rules:
- Email/password users must verify email.
- Social logins are treated as verified after provider validation.
- Accounts link by email when safe.

## Account lifecycle
Status values:
- `pending_payment`
- `pending_email_verification`
- `active`
- `suspended`
- `cancelled`

Activation rules:
- No premium access before active Stripe subscription.
- Email accounts require both active payment and verified email.

## Security
- PDO prepared statements throughout
- `password_hash()` / `password_verify()`
- Secure session cookies + `session_regenerate_id()` on login
- CSRF protection on forms
- Login rate limiting via `login_attempts`
- Verification/reset/API tokens stored hashed (never plain)
- Stripe webhook signature verification
- OAuth state validation

## Schema updates
Run:
- `sql/schema.sql` for fresh install
- `sql/migration_2026_03_actionability.sql` for prior actionability upgrade
- `sql/migration_2026_03_membership_auth.sql` for auth/membership/Stripe/OAuth/API upgrades

New/extended entities include:
- `users` (provider, verification, account status, Stripe customer)
- `email_verifications`
- `password_resets`
- `login_attempts`
- `subscriptions` + `subscription_plans` Stripe fields
- `api_keys` hashed-key fields
- `api_usage` request timestamp/IP fields
- `webhook_events`

## Important routes
- Auth: `/register.php`, `/login.php`, `/logout.php`
- Verification/reset: `/verify-email.php`, `/forgot-password.php`, `/reset-password.php`
- Member: `/dashboard.php`, `/account.php`
- Billing: `/pricing.php`, `/subscribe.php`, `/billing/success.php`, `/billing/cancel.php`
- Stripe webhook: `/api/stripe_webhook.php`
- OAuth:
  - `/oauth/google/start.php`, `/oauth/google/callback.php`
  - `/oauth/apple/start.php`, `/oauth/apple/callback.php`
  - `/oauth/facebook/start.php`, `/oauth/facebook/callback.php`

## API access and gating
API endpoints (`/api/contracts.php`, `/api/agencies.php`, `/api/vendors.php`, `/api/stats.php`) enforce:
- valid API key
- active user account
- active API membership
- daily request limits

API key management:
- Generated in Dashboard/Account
- Full key shown once at creation
- Only key hash is stored in DB

## Config
See `config.sample.php` and copy to `config.php`.

Configure:
- DB credentials
- app base URL/timezone
- security/session/token settings
- mail sender values
- Stripe secret/publishable/webhook secret
- Stripe price IDs for `MEMBER_BASIC`, `MEMBER_PRO`, `API_MEMBER`
- OAuth credentials and redirect URIs
- API key enforcement mode

## Ingestion scripts (existing)
- `ingest/ingest_usaspending.php`
- `ingest/ingest_sam_opportunities.php`
- `ingest/ingest_sam_awards.php`
- `ingest/ingest_grants.php`
- `ingest/normalize_contracts.php`
- `ingest/recategorize_contracts.php`
- `ingest/calculate_stats.php`
- `ingest/run_alerts.php`

## cPanel notes
- Upload project files.
- Import SQL schema/migrations.
- Update `config.php` with production keys.
- Set HTTPS and secure cookies.
- Register Stripe webhook URL: `https://your-domain.com/api/stripe_webhook.php`.
- Set OAuth redirect URIs to production domain.
