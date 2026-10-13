# Deploy Ultimate POS on regular shared cPanel (no Node.js app support)

This is the recommended low-cost deployment layout for shared cPanel:

- **Frontend:** Next.js static export uploaded to the main domain's document root.
- **API:** Laravel 12 served by PHP 8.2+ from an API subdomain, such as `api.example.com`.
- **Database:** cPanel MySQL/MariaDB.
- **No server-side Node.js, Docker, PostgreSQL or Redis required.**

> **Read before deploying:** this is an active product foundation, not yet a production-audited POS SaaS. The dashboard now connects to the API for authentication, products, stock adjustments, customers, checkout, sales history and basic metrics. Purchasing, returns, accounting, subscriptions, full audit trails, password recovery and security review remain outstanding. MySQL/MariaDB compatibility must be proven on staging before importing business data or accepting payments.

## 1. Check the hosting plan

In cPanel, verify that the account provides:

- PHP **8.2 or newer** for the API domain.
- PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo`, `pdo_mysql`, `session`, `tokenizer`, and `xml`.
- MySQL/MariaDB database creation and a database user.
- HTTPS/SSL for both the main domain and API subdomain.
- Preferably SSH/Terminal and Composer. If unavailable, build Composer dependencies in a compatible PHP environment and upload `vendor/` as part of the deployment package.

If the host only offers PHP 8.2, ask them to enable PHP 8.3 before deploying this repository.

## 2. Create the database

In **cPanel → MySQL® Databases** (wording may vary):

1. Create a database, for example `pos`.
2. Create a dedicated database user with a strong, unique password.
3. Add that user to the database and grant the privileges needed for migrations and normal application use.
4. Record the full cPanel-prefixed database and username (often `accountname_pos`), the host (commonly `localhost`), and password.

Do not use a database administrator/root account in the app.

## 3. Prepare the Laravel API

1. Create an API subdomain, e.g. `api.example.com`, with a document root pointing to the Laravel project's **`backend/public` directory**. Keep the rest of the Laravel code outside `public_html`. If your cPanel provider will not allow a custom document root, ask the provider for their supported secure Laravel layout; do not expose the `backend` project root to the internet.
2. Upload the contents of this repository's `backend/` folder to a private directory such as `/home/CPANEL_USER/ultimate-pos/backend`.
3. Copy `deployment/cpanel/backend.env.example` to `backend/.env` on the server, then replace all placeholders. Set `APP_URL` to the HTTPS API URL, `FRONTEND_URL` to the exact frontend origin(s), and the cPanel MySQL credentials.
4. Generate a new application key and install production dependencies. With cPanel Terminal/SSH, run:

   ```bash
   cd ~/ultimate-pos/backend
   composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   php artisan key:generate
   php artisan config:clear
   php artisan migrate --force
   php artisan storage:link
   php artisan config:cache
   ```

   If you set `APP_KEY` manually from `php artisan key:generate --show`, paste the generated value into `.env` and do not run `key:generate` again.
5. Make `storage/` and `bootstrap/cache/` writable by the account's PHP process. Do not set world-writable permissions.
6. In cPanel's PHP selector, ensure the API domain uses PHP 8.2+ and the required extensions.
7. Open `https://api.example.com/api/health`. It should return JSON. This health check does not prove database migrations or all application features are working.


### If cPanel has no SSH/Terminal or Composer

The GitHub Actions ZIP includes the Composer `vendor/` directory. For a fresh database, you can also use phpMyAdmin instead of running Laravel migrations:

1. Create a new empty database and dedicated database user in cPanel.
2. In phpMyAdmin, select that empty database and import [`deployment/cpanel/mysql-schema.sql`](mysql-schema.sql) from the repository. **Never import it over an existing database**; it creates tables and marks the included migrations as applied.
3. In the private Laravel directory, copy `deployment/cpanel/backend.env.example` to `.env`. Replace the example domains and all database credentials.
4. Generate a unique Laravel key on your Windows PC in PowerShell (no online key generator):
   ```powershell
   $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
   $bytes = New-Object byte[] 32
   $rng.GetBytes($bytes)
   $rng.Dispose()
   "base64:" + [Convert]::ToBase64String($bytes)
   ```
   Paste the generated value into `APP_KEY=` in the private `.env` file. Do not reuse the same key across installations or commit it to GitHub.
5. Ensure `storage/` and `bootstrap/cache/` are writable by PHP using the least-permissive mode your host supports (often 755/775, never 777 unless your host specifically requires a documented safer ACL alternative).
6. Open `https://api.example.com/api/health`, then register the first store through the frontend.

The SQL import is a bootstrap workaround for hosts without command-line access, not a substitute for MySQL integration tests. If you cannot set a secure document root, create the API subdomain with a document root at the Laravel `public/` folder or ask your provider to help. Never expose the Laravel project root or its `.env` file publicly.

### MySQL compatibility gate

The app was originally configured for PostgreSQL. The deployment config now includes a MySQL connection, but **that alone does not convert PostgreSQL-specific migrations or SQL**. Before running migrations on a real database:

- Test every migration and the PHPUnit suite against MySQL/MariaDB.
- Fix any PostgreSQL-only SQL, column types, indexes, or transaction assumptions.
- Confirm Sanctum token storage, foreign keys, unique constraints, and stock/sale transactions behave correctly under concurrent requests.
- Use a new staging database first; do not point the first migration attempt at a database containing important data.

## 4. Build the frontend on your own computer

The cPanel server does not need Node.js because Next.js is configured to export a static site.

You can build the frontend on your computer, or download the `Ultimate-POS-cPanel.zip` artifact from the successful **cpanel-package** job in GitHub Actions. The ZIP contains `cpanel-package/public_html/` (static frontend files) and `cpanel-package/ultimate-pos-backend/` (Laravel source plus Composer production dependencies). Upload the contents of the first folder into the main domain's document root, and upload the second folder outside all public document roots. The API URL can be changed in `public_html/config.js` after upload. The artifact removes the need for Node.js or Composer on the host, but Laravel still needs a secure app key and database migrations; if your plan has no SSH/Terminal, ask the provider how to run Laravel CLI commands securely.

To build the frontend yourself, from the repository's `frontend/` folder:

**Windows PowerShell**
```powershell
npm install
$env:NEXT_PUBLIC_API_URL = "https://api.example.com/api"
npm run build
```

**macOS/Linux**
```bash
npm install
NEXT_PUBLIC_API_URL=https://api.example.com/api npm run build
```

The build should create `frontend/out/`. If it fails, fix the build errors before uploading. For the cPanel artifact, set the API URL in `public_html/config.js` after upload; no frontend rebuild is required for a URL change.

Upload the **contents** of `frontend/out/` to the main domain's document root (usually `public_html/`), not the `frontend/` source folder. The `.htaccess` included in `frontend/public/` is copied into the export by Next.js and enables Apache's static fallback/security headers.

## 5. Configure URLs and CORS

Edit `public_html/config.js` and set `apiUrl` to your HTTPS API endpoint, for example `https://api.example.com/api`. This runtime setting lets you deploy the same static frontend build to different domains.

- Main dashboard: `https://example.com`
- API: `https://api.example.com`
- Laravel `.env`: `FRONTEND_URL=https://example.com,https://www.example.com` (only list origins you actually use).
- Frontend build variable: `NEXT_PUBLIC_API_URL=https://api.example.com/api`.

Use HTTPS on both domains. Never set `APP_DEBUG=true` on a public production site. Never upload a real `.env` file to the frontend document root or commit production secrets to Git.

## 6. Shared-hosting limitations

- Queues are configured to run synchronously for the starter deployment. Long-running jobs and high-volume workloads need a supported queue worker or a different host.
- File cache and local file storage are suitable only within the host's storage limits; configure external object storage and off-site backups before production.
- Cron jobs can run scheduled Laravel commands if the provider allows them. Do not create a cron entry until the application's scheduled tasks are defined.
- WebSockets, Redis, server-side Next.js, and always-on workers are not assumed to be available.
- Shared hosting is best for a small pilot. For a multi-tenant SaaS with many shops, busy checkout traffic, real-time features, or strict uptime requirements, use a VPS/managed application host.

## 7. Pre-launch checklist

- [ ] PHP version/extensions and HTTPS verified.
- [ ] MySQL migrations and tests pass on a disposable staging database.
- [ ] Tenant isolation and role permissions tested with two independent tenants.
- [ ] Registration, login, logout, product CRUD, sale creation, stock ledger, and payment handling tested end-to-end.
- [ ] Production `.env` is private, `APP_DEBUG=false`, and `APP_KEY` is unique.
- [ ] Backups are stored off-server and a restore has been rehearsed.
- [ ] No real customer/payment data until security and financial correctness have been reviewed.
