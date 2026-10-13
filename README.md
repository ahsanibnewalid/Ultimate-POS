# Ultimate POS SaaS
Laravel 12 / PHP 8.3 API foundation and Next.js operator dashboard for a multi-tenant POS and inventory SaaS.

## Current scope
This initial foundation provides registration/login, authenticated tenant context, tenant-scoped product CRUD, atomic sale creation with split payments, an append-only stock-movement ledger, a responsive dashboard, Docker PostgreSQL/Redis, and CI checks. It is a working starting point, **not yet a fully audited production SaaS**. Tenant-specific database/schema provisioning, subscription payments, full accounting reports, purchase/return flows, printer support, and resilient offline sync remain planned.

## Run locally
Requirements: PHP 8.3+, Composer, Node 22+, Docker.
1. `docker compose up -d`
2. `cd backend && composer install && cp .env.example .env && php artisan key:generate && php artisan migrate && php artisan serve`
3. In another terminal: `cd frontend && npm install && cp .env.example .env.local && npm run dev`
API: `http://127.0.0.1:8000/api`; UI: `http://localhost:3000`.

## API
- `POST /api/register` — business and first admin
- `POST /api/login`, `POST /api/logout`, `GET /api/me`
- `GET|POST /api/products`
- `POST /api/sales` — idempotency key via client UUID; transactionally records sale, items, payments, and stock ledger movements
- `GET /api/health`

Authenticated tenant ID is derived from the signed-in user, not a client-supplied tenant ID. Before production onboarding, implement and test database-per-tenant/schema isolation or PostgreSQL RLS, domain provisioning, role/location authorization, queue retries, backups/restore drills, provider webhooks, and full security/concurrency tests. Never commit secrets.
