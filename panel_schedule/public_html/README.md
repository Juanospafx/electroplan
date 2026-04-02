# PanelMaster Web (MVC)

## Requirements
- PHP 8+
- MySQL 5.7+ / 8+
- Apache (XAMPP/Laragon)

## Setup
1) Create DB + tables:
   - Run `app/migrations/001_create_tables.sql`
   - (Optional) Seed data: `app/migrations/002_seed.sql`
   - If upgrading an existing DB, run:
     - `app/migrations/003_add_balance_fields.sql`
     - `app/migrations/004_add_panel_fields.sql` (only if those columns are missing)

2) Configure DB credentials:
   - Edit `app/config/database.php` (legacy `backend/config.php` removed)

3) Install dependencies (Excel export)
   - `composer install`
   - Or `composer require phpoffice/phpspreadsheet`

4) Serve the app
- Recommended: set Apache DocumentRoot to `.../Panel_boards/public`
- Alternative: open `http://localhost/Panel_boards/public/projects`

## Routes
- `/projects` list
- `/projects/new` create
- `/projects/{id}` detail
- `/projects/{id}/panels/new` create panel
- `/panels/{id}/edit` schedule editor
- API:
  - `GET /api/panels/{id}/schedule`
  - `POST /api/panels/{id}/schedule`
  - `POST /api/panels/{id}/recalc`
  - `POST /api/projects/{id}/recalc`
 - Exports:
   - `GET /projects/{id}/export.xlsx`
   - `GET /panels/{id}/export.xlsx`

## Notes
- CSRF protection required for POST (included via hidden input or `X-CSRF-Token`).
- Schedule spans use placeholder boundaries configured in `public/assets/js/schedule-config.js`.
- Demand factors are in `app/config/demand_factors.php`.
- Schedule JSON is stored in `panelboards.schedule_json` for deterministic recalculation.
- Optional debug export routes can be enabled in `app/config/app.php` (`debug_export`).
