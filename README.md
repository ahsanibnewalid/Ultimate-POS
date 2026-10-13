# Ultimate POS — POS & Inventory SaaS

[![CI](https://github.com/ahsanibnewalid/Ultimate-POS/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/ahsanibnewalid/Ultimate-POS/actions/workflows/ci.yml)

Ultimate POS is an actively developed, multi-tenant point-of-sale (POS), sales, and inventory web application. It is intended to become a subscription-based service for retailers and other small and medium businesses that need to manage products, stock, customers, sales, and multiple payment methods from a browser.

> **Production-readiness statement:** The project builds and its current GitHub Actions checks pass, including frontend type-check/build, PHP tests on PHP 8.2 and 8.3, and MySQL bootstrap/migration tests. That is not equivalent to a production security audit or proof that every workflow is ready for live business use. **Treat this repository as a pilot/staging release, not a fully production-certified POS SaaS.** Review the remaining gates in [Production readiness](#production-readiness) before processing real transactions.

- **Repository:** https://github.com/ahsanibnewalid/Ultimate-POS
- **Product and architecture specification:** [docs/SAAS_SPECIFICATION.md](docs/SAAS_SPECIFICATION.md)
- **Shared cPanel installation guide:** [deployment/cpanel/README.md](deployment/cpanel/README.md)
- **cPanel bootstrap SQL:** [deployment/cpanel/mysql-schema.sql](deployment/cpanel/mysql-schema.sql)
- **Latest main commit:** [view on GitHub](https://github.com/ahsanibnewalid/Ultimate-POS/commits/main)

## Contents

- [What it is](#what-it-is)
- [Why use it](#why-use-it)
- [Current features and limitations](#current-features-and-limitations)
- [Architecture](#architecture)
- [Requirements](#requirements)
- [Run locally](#run-locally)
- [Deploy on regular shared cPanel](#deploy-on-regular-shared-cpanel)
- [Configure payment integrations](#configure-payment-integrations)
- [Configure POS hardware](#configure-pos-hardware)
- [Security and operations](#security-and-operations)
- [Testing and CI](#testing-and-ci)
- [Troubleshooting](#troubleshooting)
- [Production readiness](#production-readiness)
- [Contributing](#contributing)

## What it is

Ultimate POS is a browser-based POS and inventory system with a Laravel API and a responsive Next.js/TypeScript web interface. It is designed around businesses (tenants), so each business should only be able to access its own records.

The current foundation includes account/business registration, authentication, tenant-scoped product management, customer records, inventory adjustments, sales history, checkout, stock availability checks, stock-movement records, dashboard metrics, and an initial SaaS subscription-billing flow. A platform operator can configure supported payment-provider credentials through a restricted settings screen.

The product specification describes the intended long-term platform. **A feature listed in the specification is a target until the code, tests, and staging workflow confirm that it is implemented.** Do not assume the complete feature list is already available.

## Why use it

- **Centralize daily sales and stock:** use one dashboard for products, customers, sales, stock adjustments, and basic business metrics.
- **Browser-based access:** use a compatible desktop, laptop, tablet, or POS computer without installing a separate desktop application for the core web UI.
- **Multi-business SaaS foundation:** the application is structured around tenant/business accounts, with tenant authorization requiring continuous verification and testing.
- **Payment-provider configuration:** Stripe and SSLCommerz billing credentials can be set from the protected platform settings UI rather than hard-coded in frontend source.
- **Affordable hosting option:** the static frontend can be served by ordinary Apache/cPanel hosting, while the Laravel API runs on a PHP-enabled API subdomain. See the cPanel guide for the hosting limitations.
- **Extensible codebase:** Laravel, PHP, Next.js, TypeScript, and MySQL/PostgreSQL support a familiar foundation for future modules.

Use it as a **staging/pilot system** while completing the production gates below. If you need fully audited accounting, regulatory compliance, integrated card terminals, guaranteed offline checkout, or mature returns/refunds today, verify those requirements against the actual implementation before choosing it.

## Current features and limitations

| Area | Current state |
|---|---|
| Authentication and business registration | Implemented foundation; verify account recovery and email verification requirements before public launch |
| Products and customers | Basic tenant-scoped CRUD |
| Sales and checkout | API checkout with transaction handling, stock checks, payment rows, and idempotency support |
| Inventory | Stock adjustments and stock ledger foundation; not a complete purchasing, transfers, valuation, or warehouse system |
| Dashboard and sales history | Basic metrics and sales history |
| SaaS plan billing | Initial Stripe Checkout subscription flow and SSLCommerz hosted one-month checkout |
| Platform payment settings | Allowlisted platform operator can configure supported gateway settings; secret values are encrypted at rest and write-only in the UI |
| Barcode scanners | Keyboard-wedge USB/Bluetooth scanners can send barcode input as keyboard text when the appropriate POS field is focused |
| Receipt printing | Browser print dialog for a printer installed on the POS computer; basic receipt output |
| Card terminal and cash drawer | **Not generically integrated.** A compatible model, acquiring provider API/SDK, and often a local hardware bridge are required |
| Purchasing, supplier bills, returns, refunds, full accounting | Not complete; do not assume these are production-ready |
| Subscription expiry and full billing operations | Further lifecycle enforcement, reconciliation, refund and renewal work remains |
| Offline mode | No guarantee of safe, conflict-free offline sales and later synchronization |
| Security certification | No independent penetration test or security certification is claimed |

## Architecture

- **Frontend:** Next.js, React, TypeScript; can be built as a static export for shared cPanel.
- **API:** Laravel 12 / PHP, JSON REST endpoints, Laravel authentication/token foundation.
- **Database:** PostgreSQL is the default local-development configuration. MySQL is exercised in CI and is used by the cPanel bootstrap path. Validate the exact database engine/version and schema on staging before production.
- **Local services:** Docker Compose provides PostgreSQL and Redis for development.
- **Payment providers:** Stripe Checkout and SSLCommerz hosted checkout for SaaS billing. This is not the same as direct card-terminal integration for a retail customer's in-store sale.

The intended architecture and future requirements are documented in [docs/SAAS_SPECIFICATION.md](docs/SAAS_SPECIFICATION.md).

## Requirements

### Local development

- Git
- PHP 8.2 or 8.3 and the extensions required by Laravel
- Composer
- Node.js 22 and npm
- Docker Desktop (or Docker Engine with Compose) for local PostgreSQL/Redis services

### Shared cPanel hosting

- PHP 8.2+ (PHP 8.3 preferred) with required Laravel extensions, including PDO and the correct database driver
- MySQL/MariaDB and permission to create a database and database user
- HTTPS certificates for the frontend domain and API subdomain
- A way to install Composer dependencies or use the prebuilt GitHub Actions package
- A private Laravel application directory and a web document root pointing only to Laravel's `public/` directory

Ordinary shared cPanel does not need Node.js to serve the static frontend. It may still have limits on CLI access, scheduled tasks, background workers, storage, execution time, and traffic. A VPS or managed Laravel host is more appropriate when the product needs persistent workers, device agents, high volume, or more operational control.

## Run locally

Commands below are for a development machine. Do not reuse the sample local database password or development settings on a public server.

1. Clone the repository and start the local services:

   ```bash
   git clone https://github.com/ahsanibnewalid/Ultimate-POS.git
   cd Ultimate-POS
   docker compose up -d
   ```

2. Install and configure the Laravel API:

   ```bash
   cd backend
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

   Check `.env` and ensure the database connection points to the local Docker PostgreSQL service. The sample compose service exposes PostgreSQL on `127.0.0.1:5432`; use the local-only database name/user/password from `docker-compose.yml`. Then run:

   ```bash
   php artisan migrate
   php artisan serve --host=127.0.0.1 --port=8000
   ```

3. In a second terminal, configure and start the frontend:

   ```bash
   cd frontend
   npm install
   cp .env.example .env.local
   ```

   Set `NEXT_PUBLIC_API_URL=http://127.0.0.1:8000/api` in `.env.local`, then run:

   ```bash
   npm run dev
   ```

4. Open `http://localhost:3000`. The API health endpoint is `http://127.0.0.1:8000/api/health`.

If your shell does not support `cp`, copy the example files using your operating system's file manager or shell equivalent. Keep real secrets out of source control. The frontend and API must use matching local URLs and the backend's allowed frontend origins.

## Deploy on regular shared cPanel

For a host without Node.js application support, use the static frontend + Laravel API layout described in the dedicated guide. **Read that guide fully before uploading:** [deployment/cpanel/README.md](deployment/cpanel/README.md).

### Recommended layout

- Main domain, e.g. `https://example.com`: static files from `frontend/out/` in the domain's public document root.
- API subdomain, e.g. `https://api.example.com`: Laravel served from the `backend/public/` directory.
- Laravel source, `.env`, `vendor/`, logs, and application storage: kept outside public document roots wherever possible.
- Database: a dedicated cPanel MySQL/MariaDB database and restricted database user.

Never point a public document root at the Laravel project root. Never put `.env`, payment credentials, backups, or database dumps in the public web directory.

### Deployment steps

1. Confirm the hosting plan's PHP version/extensions, MySQL/MariaDB support, SSL, file permissions, and CLI/Composer availability.
2. Create the main domain and API subdomain. Install HTTPS on both.
3. Create a new database and a dedicated database user in cPanel. Do not use a hosting account root/database administrator credential in the application.
4. Download the `Ultimate-POS-cPanel` artifact from a **successful** [GitHub Actions run](https://github.com/ahsanibnewalid/Ultimate-POS/actions/workflows/ci.yml?query=branch%3Amain). The artifact is temporary and may expire; rerun the workflow if necessary. It contains static frontend files and the Laravel backend with production Composer dependencies.
5. Upload the contents of `cpanel-package/public_html/` to the main domain's public root. Upload `cpanel-package/ultimate-pos-backend/` to a private directory, outside the frontend public root.
6. In the frontend's `config.js`, set the API URL to `https://api.example.com/api`. Set the API subdomain document root to the backend's `public/` folder.
7. Copy `deployment/cpanel/backend.env.example` to a private backend `.env`. Configure the unique `APP_KEY`, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, frontend origins, and database credentials. Do not upload a real `.env` to GitHub.
8. If SSH/Terminal is available, install production dependencies, run migrations, and cache configuration as described in the [cPanel guide](deployment/cpanel/README.md). For a fresh database on a host without CLI access, the guide explains the phpMyAdmin bootstrap SQL alternative. **Do not import the bootstrap SQL into an existing database.**
9. Create the platform operator account and allowlist its user ID in `PLATFORM_ADMIN_USER_IDS` in the private backend environment. Clear/rebuild Laravel config cache after environment changes.
10. Verify `https://api.example.com/api/health`, register a test business, and test login, product creation, stock adjustment, checkout, sale history, and receipt printing with test data.
11. Configure provider sandbox credentials and test webhook/IPN flows before switching any provider to live mode.

The exact commands, cPanel-specific details, schema bootstrap warning, CORS configuration, and payment callback URLs are in [deployment/cpanel/README.md](deployment/cpanel/README.md). A healthy `/api/health` response only confirms that the endpoint responds; it does not prove every feature or payment flow works.

## Configure payment integrations

Ultimate POS includes an initial **SaaS subscription-billing** integration. This is for charging a business for use of the Ultimate POS service; it is not a claim that an in-store card terminal is connected.

### Platform operator access

1. Register the dedicated platform operator account.
2. Sign in and obtain its numeric user ID from the authenticated `GET /api/me` response.
3. Add that ID to `PLATFORM_ADMIN_USER_IDS` in the private backend `.env` file. Multiple IDs may be comma-separated. Alternatively, verified email allowlisting is supported through `PLATFORM_ADMIN_EMAILS`.
4. Reload Laravel configuration/redeploy as required by the host.
5. Sign in as the allowlisted operator and open **Settings → Platform payment integrations**.

Do not allowlist every tenant/store administrator. The platform settings page is intended for platform-level operators only.

### Credentials and modes

- **Stripe:** configure the secret API key, webhook signing secret, currency, and test/live mode. Configure the provider webhook to call `https://api.example.com/api/billing/stripe/webhook` and enable the event types documented in the [cPanel deployment guide](deployment/cpanel/README.md).
- **SSLCommerz:** configure Store ID and Store Password, sandbox/live mode, and the IPN endpoint `https://api.example.com/api/billing/sslcommerz/ipn`.
- Credentials are encrypted with Laravel's `APP_KEY` and are not displayed back in full. Back up the key securely: losing it can make encrypted settings unreadable. Never commit secrets or share them in issues/chat.
- Use sandbox/test credentials first. Test successful payment, cancellation, invalid signatures, duplicate callbacks, mismatched amounts/currencies, provider downtime, and subscription-state transitions before enabling live mode.
- Plan prices are server-controlled and should not be trusted from browser input. Review the current defaults in [backend/config/billing.php](backend/config/billing.php) and verify whether the platform settings override them as expected before launch.

**Bangladesh note:** Stripe's merchant and Terminal availability depends on the business's supported country and eligibility. Do not misrepresent business location or banking information to access Stripe. For a Bangladesh-based business, use an eligible locally supported provider such as SSLCommerz for the relevant payment flow, subject to its merchant approval and API requirements. SSLCommerz billing in this implementation is a one-month hosted payment with manual renewal, not automatic recurring subscription charging.

## Configure POS hardware

### Barcode scanner

Many USB and Bluetooth barcode scanners work in keyboard-wedge mode: they type the scanned barcode into the focused input just like a keyboard. Pair/install the scanner using the operating system, focus the POS product/barcode field, and test the scanner with sample products. The exact scanner behavior, suffix key, and focus handling depend on its configuration and the POS UI.

### Receipt printer

The current basic receipt flow uses the browser/operating system's print support. Install the printer and driver on the POS computer, select the paper width in the POS settings, and test the receipt layout. The browser may show a print dialog and may not support silent printing on every platform.

### Cash drawer and payment terminal

Selecting a cash-drawer or terminal preference **does not itself connect the hardware**. Automatic drawer pulses require a compatible printer/driver or separately installed local bridge. Direct card-terminal integration requires the exact device model, acquiring bank/provider, SDK/API, payment status verification, and safe failure/reconciliation behavior. That vendor-specific integration has not been generically implemented here. Do not treat a sale as paid just because a user says a terminal transaction succeeded; use a verified provider response.

## Security and operations

Before using production data:

- Set `APP_ENV=production`, `APP_DEBUG=false`, a unique random `APP_KEY`, secure database credentials, and HTTPS everywhere.
- Keep backend `.env`, logs, backups, SQL dumps, and Composer source outside public document roots. Restrict file permissions.
- Use dedicated least-privilege database users. Limit CORS to exact trusted frontend origins.
- Restrict platform operator access by exact user ID; protect operator accounts with strong unique passwords and MFA where available.
- Never trust a tenant ID, price, discount, role, or payment status supplied by the browser. Enforce authorization server-side on every read/write.
- Use HTTPS, secure cookie/token practices, login and sensitive-action rate limits, and current dependency versions.
- Configure off-site database backups and uploaded-file backups. Perform and document restore drills; a backup that has never been restored is unverified.
- Monitor application errors, payment callbacks, stock/sale consistency, failed jobs, storage capacity, and suspicious access. Have an incident response and credential rotation procedure.
- Use sandbox transactions and a staging database before live deployment. Do not test destructive migrations on the production database.

## Testing and CI

GitHub Actions currently checks:

- Frontend TypeScript type checking and production build/static export.
- PHP syntax and PHPUnit tests on PHP 8.2 and 8.3.
- PHPUnit/migration tests against MySQL.
- Importability of the cPanel bootstrap SQL and migration status.
- Assembly of the cPanel deployment artifact.

Run local checks before submitting a change:

```bash
cd frontend
npm install
npx tsc --noEmit
npm run build
```

In another terminal:

```bash
cd backend
composer install
find app routes config database tests -name '*.php' -print0 | xargs -0 -n1 php -l
vendor/bin/phpunit
```

For MySQL compatibility and full details, follow the CI workflow in [.github/workflows/ci.yml](.github/workflows/ci.yml). Passing CI verifies only the automated checks that exist; it does not replace a security review, provider sandbox testing, load/concurrency testing, or a real deployment rehearsal.

## Troubleshooting

| Problem | What to check |
|---|---|
| Frontend loads but API calls fail | `config.js` API URL, HTTPS certificate, Laravel health endpoint, CORS `FRONTEND_URL`, and browser console/network errors |
| API returns 500 | Check private Laravel logs, PHP extensions, `APP_KEY`, database credentials, migrations, and storage/cache permissions; keep debug mode off publicly |
| Login/registration fails | Confirm the API/database are reachable, migrations are current, and the frontend points at the correct API |
| Migration fails on cPanel | Confirm the database engine/version and read the MySQL compatibility section in the cPanel guide; do not retry on important data without a backup |
| Platform payment settings are unavailable | Confirm the signed-in account ID is in `PLATFORM_ADMIN_USER_IDS`, clear/rebuild config cache, and sign in again |
| Payment callback does not update billing | Check the public HTTPS webhook/IPN URL, provider dashboard delivery logs, signature/merchant credentials, sandbox/live mode, and backend logs |
| Barcode scanner types nothing | Pair/install the scanner, test it in a text editor, then focus the correct barcode field and check the scanner's suffix configuration |
| Receipt does not print correctly | Install the OS printer driver, check paper width, use the browser print dialog, and test from the same POS computer |
| Direct card terminal does not connect | Identify the exact terminal model and acquiring provider; a generic hardware profile is not a vendor SDK integration |

## Production readiness

**Current verdict: suitable for continued development and controlled staging/pilot testing; not yet certified for unrestricted production use.**

Before launch, close and verify these gates:

- [ ] Complete an independent security review and remediate findings.
- [ ] Test cross-tenant and cross-role access for every resource, export, report, job, and file; document the tenant-isolation model.
- [ ] Test concurrent sales, stock contention, idempotent retries, duplicate callbacks, and partial failures.
- [ ] Complete and test account email verification, password reset/recovery, session/token revocation, and operator-account protection.
- [ ] Finish and test subscription activation/expiry/plan enforcement, payment reconciliation, refunds, cancellations, and renewal policy.
- [ ] Implement required purchasing, returns, accounting, tax, and audit/reporting workflows before advertising them as available.
- [ ] Run real sandbox end-to-end tests for each configured payment provider, including failed and duplicate notifications.
- [ ] Test on the target hosting plan and target MySQL/MariaDB version with a clean staging database.
- [ ] Configure backups, verify restore, retention, monitoring, error alerts, and an incident/rollback plan.
- [ ] Test the exact barcode scanner, receipt printer, cash drawer, and payment terminal model(s) used by the business.
- [ ] Perform load/concurrency testing and obtain legal/accounting/privacy review relevant to the operating country and industry.
- [ ] Document a release owner, versioned deployment process, rollback plan, and customer support process.

Do not store real customer data or accept live payments until the gates relevant to your deployment and business are verified. For a small controlled pilot, use non-sensitive test data and sandbox payment credentials.

## Contributing

1. Create a feature branch.
2. Keep secrets out of source control and use environment variables or a managed secret store.
3. Add tests for new behavior, especially tenant isolation, financial operations, and webhook handling.
4. Run the frontend and backend checks.
5. Open a pull request and review the CI results before merging.

## License

Check the repository's license file before redistributing or using this project commercially. If no license is present, ask the repository owner for permission rather than assuming the code is open-source licensed.
