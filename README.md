# School Monitoring System

Design target: a local, permission-based school monitoring application running on XAMPP (Apache, PHP 8+, and MySQL/MariaDB).

## Current phase

The approved requirements have been translated into:

- `database/schema.sql` — initial MySQL/MariaDB database schema
- `docs/database-design.md` — entities, relationships, and design rules
- `docs/application-structure.md` — proposed PHP application structure, request flow, and build phases

The planned local system is implemented: installer, authentication, role permissions, academic setup, student, teacher, and employee profiles, attendance, events, tuition, insurance policies and claims, uniform and ID inventory/issuance, reporting/CSV export, users, audit logs, settings, announcements, and guarded database backup/restore.

## Operational status

- Browser errors are logged to `storage/logs/application.log` without exposing local paths.
- Database backups are stored in `storage/backups` and tracked by SHA-256 checksum.
- Restores require the `backups.restore` permission, an exact confirmation phrase, and the current administrator password.
- The application remains local-first and is available through the XAMPP `htdocs` junction at `/school-monitoring/public`.

## Proposed stack

- PHP 8.1 or newer
- MySQL 8 or MariaDB 10.4+
- Apache through XAMPP
- Bootstrap 5 and vanilla JavaScript
- Composer autoloading where available
- Server-rendered MVC application with PDO prepared statements

## Database setup preview

The raw schema can be imported through phpMyAdmin when needed, but it does not create an administrator account.

For the normal guided setup, follow [docs/installation.md](docs/installation.md) and open `/install` instead of importing the SQL manually. The installer creates the first administrator without putting a default password in source control.
