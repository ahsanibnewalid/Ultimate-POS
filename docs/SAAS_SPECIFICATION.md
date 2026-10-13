# Ultimate POS SaaS — Product & Technical Specification

**Status:** Target architecture and delivery contract. This document does not imply every listed feature is already implemented.
**Repository:** https://github.com/ahsanibnewalid/Ultimate-POS

## 1. Product vision

Build a secure, multi-tenant, subscription-based point-of-sale and inventory platform for retail, grocery, fashion, electronics, pharmacy (subject to applicable regulatory requirements), restaurants, wholesalers, and multi-branch businesses. The platform must support independent businesses on shared infrastructure while preventing any tenant from accessing another tenant's records.

## 2. Technology baseline

- API: Laravel 12+, PHP 8.3+, REST API, Laravel Sanctum for first-party authentication.
- Web app: Next.js, TypeScript, responsive component system.
- Data: PostgreSQL as system of record; Redis for cache, queues, rate limiting, and coordination.
- Runtime: Docker for local development; production containers or managed application hosting.
- Files: private object storage (S3-compatible) for receipts, exports, and uploaded assets; short-lived signed URLs.
- Async work: Laravel queues for notifications, exports, webhook delivery, and reconciliation.
- Quality: PHPUnit/Pest, frontend type checks, linting, build checks, integration tests, security scans.
- Observability: structured logs, request/correlation IDs, error reporting, health/readiness checks, queue monitoring.

Pin supported dependency versions and update them through reviewed pull requests. Do not commit secrets or production credentials.

## 3. Tenancy and identity

### Tenancy model
Use a shared PostgreSQL database with mandatory tenant_id scoping and PostgreSQL Row-Level Security (RLS) as defense in depth, or explicitly adopt database/schema-per-tenant after operational cost and migration strategy are documented. A tenant is a business account; locations/branches belong to a tenant. Platform operators are separate from tenant users.

- Derive tenant identity from authenticated server-side membership/session context, never trust a tenant_id supplied in request bodies.
- Resolve tenant membership on every request; reject inactive memberships and cross-tenant object references.
- Enforce tenant ownership in policies, query scopes, jobs, exports, file access, websocket channels, cache keys, and storage paths.
- Use composite unique constraints where appropriate, e.g. (tenant_id, sku), and tenant-aware foreign keys where feasible.
- Add automated cross-tenant denial tests for every resource family.
- Define tenant provisioning, suspension, data export, retention, deletion, and migration procedures.

### Authentication and authorization
- Registration creates a business and its first owner atomically.
- Email verification, password reset, secure session/token revocation, login throttling, optional MFA, and suspicious-login events.
- Roles: platform super-admin/support (strictly audited), tenant owner, business admin, branch manager, cashier, inventory manager, accountant, and read-only auditor.
- Permissions are explicit and server-enforced. UI visibility is not authorization.
- Scope branch/location permissions independently of tenant membership.
- Use policies for all read/write/delete/export actions; require re-authentication or step-up MFA for sensitive operations.

## 4. Core business modules

### Catalog
Products, services, categories, brands, units and unit conversions, variants, attributes, SKU, barcode, cost, tax class, sale price, wholesale price, price lists, images, active/archive status, and import/export. SKU/barcode uniqueness is tenant-scoped and configurable where business rules require it.

### Inventory
Per-location stock, stock movement ledger, receiving, adjustments with reason and approval, transfers, cycle counts, low-stock thresholds, expiry/batch/serial tracking when enabled, reservations, reorder suggestions, and valuation (weighted average initially; FIFO only after accounting rules and tests are specified).

Never directly edit an aggregate quantity without recording a corresponding ledger movement. Corrections are compensating movements, not destructive history rewrites. Define negative-stock policy per tenant and enforce it atomically.

### Purchasing and suppliers
Supplier directory, purchase orders, approvals, goods received notes, partial receipts, supplier bills, landed costs (later phase), supplier returns, payables, and supplier statements. Receiving must be idempotent and transactionally update inventory and the stock ledger.

### POS and sales
Fast keyboard/touch-friendly checkout, barcode scanning, cart persistence, customer lookup, discounts, taxes, price overrides guarded by permissions, multiple payment methods, split tenders, cash change, receipt generation, parked/held sales, shift opening/closing, cash drawer reconciliation, refunds, exchanges, voids, and sales history.

Sale creation must be idempotent and transactional: validate stock and prices server-side, persist sale/items/payments, create stock movements, and record audit events together. Never trust client-calculated totals. Store currency and tax/rounding policy explicitly. Prevent duplicate sales on retries.

### Customers and loyalty
Customer profiles, contact preferences, purchase history, credit/receivable policy (disabled by default until configured), points/rewards, consent records, and privacy-aware export/deletion workflows.

### Expenses and accounting
Expense categories, attachments, approval rules, cash/bank accounts, payment/receipt records, tax summaries, sales and purchase ledgers, receivables/payables, journal entries, trial balance, profit/loss, balance sheet, and cash-flow reporting. Accounting reports must reconcile to source transactions; do not advertise full double-entry accounting until balanced journal posting and period-close tests pass.

### Reporting
Date/location/product/category/cashier/customer filters; gross/net sales, discounts, tax, refunds, COGS, gross profit, inventory valuation, stock movement, fast/slow sellers, low stock, purchase and supplier reports, expense summaries, payment reconciliation, and shift reports. Apply tenant/location permissions to exports and asynchronous report jobs. Document timezone, currency, rounding, and date-boundary rules.

### Multi-branch operations
Location management, branch-specific stock and price policies, inter-branch transfers with dispatch/receive states, branch staff membership, location-aware reporting, and optional head-office consolidated reporting.

### Platform SaaS administration
Plan catalog, monthly/yearly billing, trials, grace periods, entitlements, usage limits, invoices, subscription state transitions, failed-payment retries, cancellation, and tenant suspension/reactivation. Integrate a payment provider behind a provider-neutral interface; verify webhook signatures, enforce idempotency, and never trust browser redirect results as payment proof. Do not store raw card data.

Platform admin tools include tenant search, subscription status, support access with reason and audit trail, service health, usage metrics, webhook delivery status, and controlled feature flags. Support access must be least-privilege and visible in audit logs.

## 5. Bangladesh-ready configuration

Support configurable BDT (৳), Asia/Dhaka timezone, local date/number formatting, VAT/tax settings, and payment-provider adapters appropriate to the merchant's contract. Do not hardcode tax rates or claim legal compliance; rates and invoice requirements must be configurable and reviewed against current regulations. Store timestamps in UTC and render using each user's/tenant's configured timezone. Use integer minor units or a rigorously defined decimal strategy for money; never use binary floating point for financial calculations.

## 6. API conventions

- Version APIs under /api/v1 before public integrations are committed.
- Validate all input with request validators; return consistent JSON errors and stable error codes.
- Use pagination, filters with allowlists, request size limits, throttling, and documented sorting.
- Use idempotency keys for sale finalization, payment callbacks, inventory receiving, and other retryable financial writes.
- Add optimistic locking or row locks to prevent overselling and double-processing.
- Document endpoints with OpenAPI and include authorization requirements and examples.
- Health endpoints expose minimal non-sensitive status; readiness checks dependencies separately from liveness.

## 7. Security requirements

- Enforce authorization and tenant isolation in the API and background workers.
- CSRF protection for cookie-authenticated browser sessions; secure, HttpOnly, SameSite cookies and HTTPS in production.
- Password hashing through Laravel's supported secure hasher; rate-limit authentication and recovery routes.
- Validate uploads by content, size, and allowed type; store privately; randomize storage names; never execute uploaded files.
- Parameterized queries, output escaping, strict CORS allowlists, CSP and security headers.
- Encrypt transport and managed database/object-storage data at rest; rotate secrets and signing keys with documented procedures.
- Keep secrets in environment/secret manager, never in source control, frontend bundles, logs, or exception responses.
- Verify webhook signatures and replay protection; redact payment and personal data from logs.
- Audit role changes, logins, exports, stock adjustments, discounts, refunds, tenant impersonation/support access, and subscription changes.
- Define retention, deletion, data export, incident response, vulnerability reporting, and dependency patching policies.
- Do not claim PCI DSS compliance merely because a payment gateway is used; scope must be assessed by a qualified provider/advisor.

## 8. Reliability, backups, and operations

- Production database backups with encryption, retention, point-in-time recovery where supported, and off-account copies.
- Regular restore drills into an isolated environment with recorded recovery time and recovery point results.
- Database migrations are reviewed, repeatable, and safe for existing data; never run destructive schema changes casually during deploy.
- Queue jobs use bounded retries, backoff, idempotency, dead-letter/failed-job review, and alerting.
- Use transactional outbox or equivalent durable delivery for critical events and webhooks.
- Define SLOs for API availability/latency and operational targets for RPO/RTO before launch.
- Monitor HTTP errors, latency, DB pool/locks, queue age, storage capacity, webhook failures, payment reconciliation, and unusual authorization failures.
- Separate local, test, staging, and production configuration; deploy with rollback instructions and database compatibility checks.

## 9. Offline and hardware roadmap

Offline checkout is a later milestone, not assumed by the initial online API. It requires a local encrypted queue, stable client-generated transaction IDs, conflict policy, stock reservation/oversell rules, replay protection, and explicit sync status. Never imply offline sales are reconciled until the server confirms them.

Receipt printers, cash drawers, barcode scanners, label printers, and customer displays require a documented device compatibility matrix and browser/OS integration tests. Begin with browser-print receipts and keyboard wedge barcode scanners.

## 10. UX requirements

- Responsive desktop/tablet/mobile layouts with accessible contrast, keyboard navigation, clear focus states, loading/empty/error states, and confirmation for irreversible actions.
- POS checkout optimized for fast barcode-first workflows and touch targets.
- Role-aware navigation; all hidden actions remain protected server-side.
- Search, pagination, bulk import with preview/validation, export progress, and actionable errors.
- Locale, timezone, currency display, date/time preference, and accessible receipt layout.
- Do not expose sensitive customer details in shared-device screens by default.

## 11. Data model domains

Expected entities (final schema must be migration-backed): tenants, users, memberships, roles/permissions, locations, subscriptions, plans, entitlements, customers, suppliers, categories, products, variants, price lists, stock balances, stock movements, transfers, purchase orders, receipts, supplier bills, sales, sale items, payments, refunds, shifts, expenses, accounts, journal entries, audit events, webhook deliveries, idempotency records, notification preferences, and export jobs.

Every tenant-owned entity must have an explicit ownership strategy and indexes. Define deletion behavior, retention, uniqueness, currency, decimal precision, timestamps, and status transitions before implementation.

## 12. Testing and acceptance gates

Required gates before production claims:
1. Backend unit and feature tests; frontend typecheck, lint, and production build.
2. Tenant A cannot read, modify, delete, export, or infer Tenant B data through IDs, filters, relations, search, files, jobs, cache, or websocket subscriptions.
3. Concurrent checkout cannot oversell when stock policy disallows it; duplicate idempotency keys do not duplicate transactions.
4. Sale totals, discounts, taxes, split payments, change, refund, and stock ledger reconcile exactly under defined rounding rules.
5. Purchasing/receiving and transfers are atomic, auditable, and retry-safe.
6. Role/branch permissions are enforced server-side.
7. Subscription webhooks are signed, idempotent, and safe under retries/out-of-order delivery.
8. Backup restoration and deployment rollback are demonstrated.
9. Security checks cover authentication throttling, session handling, mass assignment, upload validation, XSS, CSRF, SQL injection, secrets, and dependency vulnerabilities.
10. A staging smoke test covers registration through sale, refund, reporting, and subscription lifecycle.

## 13. Delivery milestones

- M0 Foundation: repeatable setup, CI, authentication, tenant context, authorization baseline, database migrations, error conventions.
- M1 Safe core: tenant-isolated catalog, location stock ledger, POS sale and payments, receipt, shift close, idempotency and concurrency tests.
- M2 Operations: purchasing, suppliers, transfers, returns, customers, expenses, imports/exports.
- M3 Finance: reconciliation, accounting posting, tax configuration, validated financial reports.
- M4 SaaS: plan entitlements, subscription provider, signed webhooks, usage enforcement, platform admin.
- M5 Production hardening: RLS or selected isolation model, MFA, audit review, restore drills, monitoring, incident runbooks, penetration testing.
- M6 Advanced: offline sync, hardware integrations, loyalty, advanced valuation and analytics.

Each milestone requires code, migrations, tests, updated documentation, and CI evidence. Features are not complete merely because routes or UI controls exist.

## 14. Release checklist

- [ ] No known cross-tenant data leakage; automated tests cover all tenant-owned resources.
- [ ] Authorization matrix reviewed and enforced in policies.
- [ ] Financial arithmetic and concurrency verified.
- [ ] Migrations tested against a clean DB and a production-like upgrade snapshot.
- [ ] Backups and restore drills verified.
- [ ] Production secrets, HTTPS, domains, mail, storage, queues, monitoring, and alerts configured.
- [ ] Webhook signatures, retries, and idempotency tested.
- [ ] OpenAPI and operator runbooks published.
- [ ] Terms, privacy, retention, tax, and payment compliance reviewed by appropriate professionals.
- [ ] CI passes and staging smoke test is recorded.

**Release policy:** Until these gates pass, describe the project as a foundation or beta, not a fully audited production-ready SaaS.
