# Admin portal integration

The attached admin and superadmin screens are installed in the existing `public/` tree:

- Admin: `http://localhost/rc2000/admin/`
- Superadmin: `http://localhost/rc2000/superadmin/`
- Direct public-tree URLs also work under `/rc2000/public/admin/`.
- The project root `http://localhost/rc2000/` loads the public home page.

The portals use shared code in `includes/portal/`, settings in `config/portal/`, and assets in `public/assets/`. Their database tables are installed into the separate PostgreSQL schema `rc2000_admin` inside the existing `rc2000_db` database. The public site's existing `public` schema and `database/schema.sql` are preserved.

## One-time setup

1. Start Apache and PostgreSQL in XAMPP.
2. In pgAdmin, select the existing `rc2000_db` database and run `database/admin_portal_schema.sql`.
3. Run `database/super-admin.sql`, then `database/integration.sql` in the same database.
4. In XAMPP Shell, create the initial superadmin:

   ```powershell
   cd C:\xampp\htdocs\rc2000
   C:\xampp\php\php.exe database\create-super-admin.php
   ```

   Follow the prompts for the superadmin email and password. Then open `/rc2000/superadmin/` and use the admin management page to create regular admin accounts. Regular staff sign in at `/rc2000/admin/`.

The portal connection reuses `config/db.php` and its `RC2000_DB_*` environment variables. Keep that file's local database credentials configured for XAMPP.

The current public forms continue to use the existing public tables. The portal's separate donation and inquiry tables are ready for submissions sent to the added /api/public/ endpoints; the existing form handlers are not yet wired to those endpoints, so current form submissions will not automatically appear in the new admin lists.

