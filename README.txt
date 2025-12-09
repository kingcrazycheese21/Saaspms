PMS Rebuild - Deploy Instructions
================================

Files included:
- config.php (example) - update with your DB credentials
- FrontDeskOS.sql - original SQL dump you uploaded
- session_config.php - session handling (creates /sessionstorage)
- init.php - global initialization and auth guard
- login.php - secure login (supports legacy MD5 and upgrades to password_hash)
- dashboard.php - main dashboard with sidebar and KPIs
- housekeeping.php, reservations.php - simple pages
- logout.php - logs out the user
- sidebar_ui.php - shared sidebar UI

Deployment steps:
1. Upload all files to your site's document root.
2. Edit config.php with your database credentials.
3. Import FrontDeskOS.sql into your MySQL database (via phpMyAdmin or command line).
4. Ensure the webserver can write to the 'sessionstorage' directory (create it or let session_config do it).
   Example: chmod 700 sessionstorage
5. Visit login.php and login with the admin user from your SQL dump:
   Email: admin@hotel.com
   Password: admin

Notes:
- After first successful login, legacy MD5 passwords are automatically migrated to PHP's password_hash.
- If you prefer to keep MD5 (not recommended), you can disable the upgrade in login.php.
