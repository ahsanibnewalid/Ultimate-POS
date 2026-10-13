# Ultimate POS SaaS

Laravel 12 / PHP 8.3 API foundation and Next.js operator dashboard for a multi-tenant POS and inventory SaaS.

> **Project status:** foundation / active development. This is not yet a fully audited production SaaS. Consult the [complete SaaS product and technical specification](docs/SAAS_SPECIFICATION.md) for the target modules, architecture, security controls, testing gates, milestones, and release checklist.

## Current scope

The existing foundation provides registration/login, authenticated tenant context, tenant-scoped product CRUD, atomic sale creation with split payments, an append-only stock-movement ledger, a responsive dashboard, Docker PostgreSQL/Redis, and CI checks. Feature coverage must be verified against tests before production use.

Important work still required includes stronger and tested tenant isolation (including RLS or a documented alternative), complete role/location authorization, purchasing and returns, accounting, subscription billing and signed webhooks, robust backup/restore drills, payment reconciliation, resilient offline sync, operational monitoring, and full security/concurrency tests.

## Run locally

Requirements: PHP 8.3+, Composer, Node 22+, Docker.

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
