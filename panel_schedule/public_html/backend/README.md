# Panelboards CRUD

## Database setup
1) Create a MySQL database:
   - Name: panelboards (or edit it in `backend/config.php`)
2) Import schema:
   - Run `backend/schema.sql` in your database

## Configure credentials
- Edit `backend/config.php` and set host/user/pass/db name.

## Run locally (XAMPP/Laragon)
1) Place the project in your web root (already in `C:\xampp\htdocs\Panel_boards`).
2) Start Apache + MySQL.
3) Open in browser:
   - List: `/frontend/pages/index.html`
   - Editor: `/frontend/pages/editor.html`

## Notes
- API endpoints are in `backend/api/` and return JSON.
- PDF export is client-side using jsPDF + AutoTable via CDN.
