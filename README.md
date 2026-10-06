# Portfolio Showcase CMS

The project contains a React/Vite public portfolio and a PHP 8.2 + MySQL CMS. The CMS and API read and write portfolio content directly in MySQL; there is no session-memory or frontend sample-content fallback.

## Project layout

- `frontend/` – React public portfolio
- `backend/config/` – PDO connection, validation, session authentication, and API
- `backend/admin/` – PHP session-protected CMS and login
- `backend/public/` – web document root, API front controller, admin entry pages, and CMS script
- `database/portfolio_schema.sql` – schema for a new database
- `database/migrations/20261005_admin_cms.sql` – one-time migration for the earlier project schema
- `database/seed_data.sql` – creates the development admin account; does not add sample portfolio content
- `postman/` – API collection

## Database setup

Start MySQL in XAMPP. For a new install, run these files from the MySQL command line:

```text
mysql -u root < database/portfolio_schema.sql
mysql -u root < database/seed_data.sql
```

For a database created from the earlier schema, back it up first, then run only the migration and seed:

```text
mysql -u root portfolio_db < database/migrations/20261005_admin_cms.sql
mysql -u root portfolio_db < database/seed_data.sql
```

The migration carries the previous hero heading/subheading into the new title/subtitle fields and adds video type and project work-performed data. It deliberately does not drop the legacy `clients` table, so its data is preserved; all client API and CMS surfaces are removed.

Database connection environment variables are `PORTFOLIO_DB_HOST`, `PORTFOLIO_DB_NAME`, `PORTFOLIO_DB_USER`, and `PORTFOLIO_DB_PASSWORD`. Defaults target the standard local XAMPP MySQL install (`127.0.0.1`, `portfolio_db`, `root`, empty password). To allow a different Vite origin, set `PORTFOLIO_CORS_ORIGINS` to a comma-separated allowlist.

The seed creates a development account:

- Email: `admin@portfolio.dev`
- Password: `admin123`

Change the development password hash/account before exposing the site outside a trusted local environment.

## Run locally

From the project root:

```powershell
php -S localhost:8000 -t backend\public backend\public\router.php
```

Open the CMS at `http://localhost:8000/admin/login`. The React app runs at `http://localhost:5173`:

```powershell
cd frontend
npm install
npm run dev
```

For Apache/XAMPP, configure the virtual host/document root to `backend/public` and enable `mod_rewrite` with `.htaccess` overrides. Do not serve the whole project root as the web document root.

## Public API

- `GET /api/hero` – active hero slides, ordered by display order
- `GET /api/videos`
- `GET /api/websites`
- `GET /api/websites/{id}`
- `GET /api/other-work`
- `GET /api/services`
- `POST /api/enquiries`

## Admin API

All routes below require the PHP session returned by login. Use `credentials: "include"` from a permitted frontend origin.

- `POST /api/admin/login`
- `POST /api/admin/logout`
- `GET /api/admin/dashboard`
- `GET|POST /api/admin/hero`; `GET|PUT|DELETE /api/admin/hero/{id}`
- `GET|POST /api/admin/videos`; `GET|PUT|DELETE /api/admin/videos/{id}`
- `GET|POST /api/admin/websites`; `GET|PUT|DELETE /api/admin/websites/{id}`
- `GET /api/admin/websites/{id}/images`
- `POST /api/admin/websites/{id}/images`
- `GET /api/admin/website-images?website_id={id}`
- `POST /api/admin/website-images`
- `GET|PUT|DELETE /api/admin/website-images/{image_id}`
- `GET|POST /api/admin/other-work`; `GET|PUT|DELETE /api/admin/other-work/{id}`
- `GET|POST /api/admin/services`; `GET|PUT|DELETE /api/admin/services/{id}`
- `GET /api/admin/enquiries`
- `GET|PUT|DELETE /api/admin/enquiries/{id}` (`PUT` accepts `{ "status": "New|Contacted|Completed" }`)

JSON responses use `{ "success": true|false, "message": "...", "data": ..., "errors": ... }`. Validation errors return `422`, missing/invalid resources `404`/`422`, and unauthenticated admin access `401`.

The admin UI includes dashboard counts and create/edit/delete, active/featured toggles, and display-order controls for each content module. Website image URLs can be reordered and managed in the project edit form, or managed individually through the website-image API.
