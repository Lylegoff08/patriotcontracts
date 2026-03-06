# PatriotContracts Installation Guide (XAMPP -> cPanel)

## 1. Local XAMPP setup
1. Copy project to `C:\xampp\htdocs\patriotcontracts`.
2. Start Apache + MySQL in XAMPP.
3. Import `sql/schema.sql` into MySQL.
4. If upgrading an existing DB, run:
   - `sql/migration_2026_03_actionability.sql`
   - `sql/migration_2026_03_membership_auth.sql`
5. Copy `config.sample.php` to `config.php` and update values.
6. Open `http://localhost/patriotcontracts/`.

## 2. Config checklist
In `config.php`, configure:
- DB (`db.*`)
- Site base URL (`app.base_url`)
- Security/session/token pepper values
- Mail sender values (`mail.*`)
- Stripe:
  - `stripe.secret_key`
  - `stripe.publishable_key`
  - `stripe.webhook_secret`
- Plan Stripe price IDs (`plans.MEMBER_BASIC`, `plans.MEMBER_PRO`, `plans.API_MEMBER`)
- OAuth:
  - Google client id/secret + redirect URI
  - Apple client id/client secret + redirect URI
  - Facebook app id/secret + redirect URI
- API enforcement mode (`api.require_key`, `api.dev_bypass_localhost`)

## 3. Email verification + password reset
Implemented routes:
- `/verify-email.php`
- `/forgot-password.php`
- `/reset-password.php`

Token policy:
- Verification links expire in 24 hours.
- Reset links expire in 1 hour.
- Tokens are one-time use and stored hashed.

Mail transport uses PHP `mail()` through `includes/mailer.php` for cPanel compatibility.

## 4. Stripe setup
1. Create monthly recurring prices in Stripe for:
   - MEMBER_BASIC
   - MEMBER_PRO
   - API_MEMBER
2. Put each Stripe price ID in `config.php` (`plans.*.stripe_price_id`).
3. Set Stripe API keys in `config.php`.
4. Add webhook endpoint:
   - URL: `https://your-domain.com/api/stripe_webhook.php`
5. Subscribe webhook to at least:
   - `checkout.session.completed`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `customer.subscription.deleted`
   - `customer.subscription.updated`
6. Set webhook signing secret in `config.php`.

Important: membership activation is webhook-driven; do not rely only on redirect pages.

## 5. OAuth setup
### Google
- Configure OAuth consent and Web application credentials.
- Redirect URI: `/oauth/google/callback.php`.

### Apple
- Configure Sign in with Apple service + redirect URI `/oauth/apple/callback.php`.
- Provide `client_id` and generated `client_secret` in config.

### Facebook
- Configure Facebook Login product.
- Redirect URI: `/oauth/facebook/callback.php`.

## 6. Feature gating rules
- Anonymous: basic browse/search + limited stats.
- Member Basic: member dashboard/account tools.
- Member Pro: advanced search + saved searches + alerts + CSV export.
- API Member: all Pro features + API keys + API access/limits.

Server-side enforcement exists in auth/membership/API middleware.

## 7. API key lifecycle
- Keys are generated in dashboard/account.
- Full key shown once on creation.
- DB stores hash + prefix only.
- API requests require valid key + active API membership + daily limit headroom.

## 8. cPanel deployment notes
1. Upload files to document root/subfolder.
2. Import schema/migrations in production DB.
3. Update `config.php` with production values and HTTPS base URL.
4. Enable secure cookies (`security.secure_cookies = true`).
5. Register Stripe webhook endpoint in Stripe dashboard.
6. Set OAuth redirect URIs to production domain.
7. Optional: schedule ingest scripts via cron.

## 9. Admin visibility
`/admin/health.php` now includes:
- users count
- active memberships
- pending email verifications
- API usage totals (24h)
- recently created API keys
- unprocessed webhook events
