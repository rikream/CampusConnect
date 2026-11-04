# CampusFind

CampusFind is a responsive campus lost-and-found application built with PHP, MySQL, HTML, CSS, JavaScript, Bootstrap, and Chart.js. Students can report lost and found items, browse and filter approved reports, send private inquiries, receive notifications, and see suggested matches. Administrators can review reports, manage users, categories, and campus locations, and inspect recent activity.

## Requirements

- PHP 8.1 or later with PDO MySQL, Fileinfo, and mbstring extensions enabled
- MySQL 8.0 or MariaDB 10.5+
- Apache (XAMPP is suitable)

## Run locally with XAMPP

1. Copy the project folder into `C:\xampp\htdocs\campusfind`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open phpMyAdmin, select **Import**, and import `database/schema.sql`. The script creates the `campusfind` database, tables, categories, and sample campus locations.
4. If your local MySQL credentials are not the XAMPP defaults, set these environment variables for Apache before starting it:
   - `CAMPUSFIND_DB_HOST` (default `127.0.0.1`)
   - `CAMPUSFIND_DB_NAME` (default `campusfind`)
   - `CAMPUSFIND_DB_USER` (default `root`)
   - `CAMPUSFIND_DB_PASSWORD` (default empty)
5. Visit `http://localhost/campusfind/` and create a student account.
6. To grant administrator access, run this from the project directory in a terminal:

   ```powershell
   C:\xampp\php\php.exe scripts\create_admin.php student@college.edu
   ```

   Use the email address of the student account you registered, then sign out and back in. The admin setup script is command-line-only and does not create a default password.

The `uploads` directory must be writable by Apache. Uploaded files are restricted to JPG, PNG, and WebP images up to 3 MB. Apache configuration in `uploads/.htaccess` disables directory listings and blocks PHP script execution in that directory.

## One-click local run (PHP and MariaDB installed)

If PHP 8.3 and MariaDB 13 were installed with the paths used on this development computer, double-click `Start-CampusFind.bat`. It starts the database and PHP web server in the background, then opens the app at `http://127.0.0.1:8000/`. Double-click `Stop-CampusFind.bat` when finished. Local server logs are saved in `%LOCALAPPDATA%\CampusFind\logs`.

The PowerShell launchers read the existing database in the MariaDB data directory; they do not reinstall software or recreate the database. The launch script checks for the local `campusfind` database and reports an error if it is missing. On another computer, first install PHP (with PDO MySQL, Fileinfo, and mbstring extensions) and MariaDB, and adjust the executable paths at the top of `Start-CampusFind.ps1` and `Stop-CampusFind.ps1` to match that installation.

Bootstrap and Chart.js are included locally in `assets/vendor`, with their license files alongside the libraries. The application does not need a package manager or a CDN connection to load its styles or dashboard chart. PHP with the required extensions, MySQL/MariaDB, and Apache are server software: install them separately (XAMPP bundles them), then import the included database schema.

## Included features

- Student registration, secure password hashing, login, and session-based access control
- Lost and found submissions with category, campus location, occurrence date, contact preference, and optional photo
- Administrator approval before reports are visible on the public campus board
- Search, type/category/location/status/date filters, and pagination
- Rule-based match suggestions between approved lost and found reports
- Private in-app inquiries and notifications
- Recovered-item status, notification center, and activity log
- Admin dashboard, report moderation, account access and role management, and editable categories and locations
- Prepared SQL statements, output escaping, CSRF tokens, and server-side validation
