# XAMPP Installation

## Option A: Apache virtual host (recommended)

Keep the project in its current location and add a virtual host whose document root is the `public` folder.

```apache
<VirtualHost *:80>
    ServerName school-monitor.local
    DocumentRoot "C:/Users/jukashi/Documents/SCHOOL MONITORING SYSTEM/public"

    <Directory "C:/Users/jukashi/Documents/SCHOOL MONITORING SYSTEM/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Add `127.0.0.1 school-monitor.local` to the Windows hosts file, ensure Apache's `mod_rewrite` is enabled, restart Apache, and browse to:

```text
http://school-monitor.local/install
```

Use `http://school-monitor.local` as the Application URL on the installer screen.

## Option B: Place the project under htdocs

Place or link the folder at `C:\xampp\htdocs\school-monitoring`, then browse to:

```text
http://localhost/school-monitoring/public/install
```

Use `http://localhost/school-monitoring/public` as the Application URL.

Option A is safer because only `public/` is web-accessible.

## Installer fields

Default local XAMPP database values are commonly:

- Host: `127.0.0.1`
- Port: `3306`
- Database: `school_monitoring`
- User: `root`
- Password: blank, unless the local MariaDB account was secured

Choose a unique administrator username and a password of at least 10 characters. The installer will:

1. Create the database if needed.
2. Create the application tables.
3. Seed the permission catalog and five initial roles.
4. Hash the administrator password using PHP's password API.
5. Assign the protected Super Administrator role.
6. Write local configuration to `.env`.
7. Create `storage/installed.lock` to disable repeat installation.

After installation, sign in at `/login`. Do not delete the lock file during normal operation.

## Development-only preview

From the project directory:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8080 -t public public\index.php
```

Then visit `http://127.0.0.1:8080/install`. Apache is recommended for normal use because it serves static assets and rewrite rules exactly as deployed.

