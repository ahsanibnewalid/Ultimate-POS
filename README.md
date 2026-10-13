# Ultimate POS SaaS

Laravel 12 / PHP 8.2+ API foundation and Next.js operator dashboard for a multi-tenant POS and inventory SaaS.

> **Project status:** foundation / active development. This is not yet a fully audited production SaaS. Consult the [complete SaaS product and technical specification](docs/SAAS_SPECIFICATION.md) for the target modules, architecture, security controls, testing gates, milestones, and release checklist.

## Current scope

The current working foundation provides registration/login, authenticated tenant context, tenant-scoped product CRUD, atomic checkout with payment rows, stock availability checks and an append-only stock ledger, customer records, inventory adjustments, sales history, dashboard metrics, and a responsive API-connected dashboard. A first SaaS subscription-billing flow is included for Stripe Checkout subscriptions and SSLCommerz hosted one-month payments. Platform payment credentials can be managed through a separately allowlisted platform-admin screen, stored encrypted at rest, and are write-only in the UI. Tenant admins can configure a basic POS hardware profile and browser-compatible barcode scanning/receipt printing. Purchasing, returns, full accounting, refunds, and automated SSLCommerz renewals remain unfinished. Feature coverage must be verified against tests before production use.

Important work still required includes stronger and tested tenant isolation (including RLS or a documented alternative), complete role/location authorization, purchasing and returns, accounting, payment reconciliation/refunds, robust backup/restore drills, resilient offline sync, operational monitoring, and full security/concurrency tests. The payment code still requires sandbox integration tests and a security review before live charges.

## Run locally

Requirements: PHP 8.2+, Composer, Node 22+, Docker.

1. `docker compose up -d`
2. `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan serve`
3. In another terminal: `cd frontend && npm install && cp .env.example .env.local && npm run dev`

API: `http://127.0.0.1:8000/api`; UI: `http://localhost:3000`.

## API currently documented

- `POST /api/register` — business and first admin
- `POST /api/login`, `POST /api/logout`, `GET /api/me`
- `GET|POST /api/products`
- `POST /api/sales` — client idempotency key; transactionally records sale, items, payments, and stock ledger movements
- `GET /api/health`

Authenticated tenant ID must be derived from the signed-in user and membership, not a client-supplied tenant ID. Treat every tenant-owned read, write, export, background job, cache entry, and file as an authorization boundary.

## Engineering policy

- Never commit credentials or production secrets.
- Do not claim production readiness until the specification's acceptance gates pass.
- Financial operations must be transactional, retry-safe, auditable, and protected against concurrent stock changes.
- Run the CI checks and staging smoke tests before deployment.
- Use reviewed migrations and rehearse rollback/restore procedures.

## Specification

See [docs/SAAS_SPECIFICATION.md](docs/SAAS_SPECIFICATION.md).
## Shared cPanel deployment

For regular shared cPanel hosting without Node.js support, follow the [cPanel deployment guide](deployment/cpanel/README.md). The frontend is built as a static export and the Laravel API is hosted on a PHP subdomain. A MySQL connection is included, but PostgreSQL-to-MySQL migration compatibility must be verified on staging before production use.

## Shared cPanel deployment

For regular shared cPanel hosting without Node.js support, follow the [cPanel deployment guide](deployment/cpanel/README.md). The frontend is a static export, while Laravel runs on a PHP subdomain and uses MySQL/MariaDB where verified.

The current API now includes tenant-scoped dashboard metrics, product creation/update/archive, stock adjustments, customer records, sales history, and checkout with stock availability checks and idempotency. CI checks frontend static export and PHP syntax in addition to the existing test suite.

**Production status:** still requires real MySQL/MariaDB migration testing, comprehensive automated tenant/security and concurrent-sale tests, email verification/password recovery, subscription billing, refunds/returns, purchasing/accounting, backups and restore rehearsal, payment reconciliation, monitoring, and independent security review. Do not accept real payment or business data until those gates pass.


## Payment secrets and POS hardware configuration

Set `PLATFORM_ADMIN_EMAILS` in the private backend environment to a comma-separated allowlist of platform operator email addresses. Do not set this to every tenant administrator. Run migrations before using the new platform settings screen. The allowlisted operator signs in and opens **Settings → Platform payment integrations** to configure Stripe and SSLCommerz credentials, test/live modes, billing return URL, currency and plan prices. Secret values are encrypted using Laravel's `APP_KEY`; rotate and back up that key securely. Empty secret fields keep the saved value; clear checkboxes remove a stored credential. Secrets are never returned by the API. Do not enable live mode until sandbox webhook/IPN flows have been tested.

The POS hardware settings support keyboard-wedge USB/Bluetooth barcode scanners and browser print dialogs for OS-installed receipt printers. Scanners enter barcode text as keyboard input, so the POS product search field must be focused. Browser printing works with printers installed on the POS computer. Automatic cash-drawer pulses require a compatible printer/driver or a separately installed local bridge; selecting a setting does not install such a bridge. In-person card-terminal API integration is provider/model-specific and is not claimed as complete by the generic hardware profile. Obtain the terminal model and acquiring-provider API/SDK before implementing automatic terminal pairing and payment confirmation. For Bangladesh, prefer a locally supported acquirer/QR API; Stripe Terminal does not list Bangladesh as a supported country.

**Production status remains gated:** these settings do not complete refunds/reconciliation, subscription lifecycle enforcement, all tenant authorization tests, email recovery/verification, backup restore drills, hardware integration testing, or independent security review. Use staging and sandbox credentials first.
