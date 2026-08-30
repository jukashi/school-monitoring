# Application Structure

## Architecture

Use a small server-rendered MVC application. Apache sends all non-file requests to `public/index.php`; the router selects a controller; middleware authenticates and authorizes the request; controllers call services; repositories/models use PDO to access MySQL; views render escaped HTML.

```text
Browser
  -> Apache rewrite
  -> public/index.php
  -> Router
  -> Session / CSRF / Authentication / Permission middleware
  -> Controller
  -> Service (business rules)
  -> Repository or Model (PDO)
  -> MySQL
  -> View / redirect / download
```

## Proposed project layout

```text
school-monitoring-system/
|-- app/
|   |-- Controllers/
|   |   |-- AuthController.php
|   |   |-- DashboardController.php
|   |   |-- StudentController.php
|   |   |-- TeacherController.php
|   |   |-- AttendanceController.php
|   |   |-- EventController.php
|   |   |-- ReportController.php
|   |   `-- Admin/
|   |       |-- UserController.php
|   |       |-- RoleController.php
|   |       |-- AcademicController.php
|   |       |-- SettingController.php
|   |       `-- BackupController.php
|   |-- Core/
|   |   |-- Application.php
|   |   |-- Router.php
|   |   |-- Controller.php
|   |   |-- Database.php
|   |   |-- Request.php
|   |   |-- Response.php
|   |   |-- Session.php
|   |   `-- View.php
|   |-- Middleware/
|   |   |-- AuthMiddleware.php
|   |   |-- GuestMiddleware.php
|   |   |-- PermissionMiddleware.php
|   |   `-- CsrfMiddleware.php
|   |-- Models/
|   |-- Repositories/
|   |-- Services/
|   |   |-- AuthService.php
|   |   |-- AuthorizationService.php
|   |   |-- AttendanceService.php
|   |   |-- EventService.php
|   |   |-- AuditService.php
|   |   `-- BackupService.php
|   |-- Validation/
|   `-- Views/
|       |-- layouts/
|       |-- auth/
|       |-- dashboard/
|       |-- students/
|       |-- teachers/
|       |-- attendance/
|       |-- events/
|       |-- reports/
|       |-- admin/
|       `-- errors/
|-- bootstrap/
|   `-- app.php
|-- config/
|   |-- app.php
|   |-- database.php
|   `-- permissions.php
|-- database/
|   |-- schema.sql
|   `-- seeds/
|-- docs/
|-- public/
|   |-- index.php
|   |-- .htaccess
|   |-- assets/
|   |   |-- css/
|   |   |-- js/
|   |   `-- images/
|   `-- uploads/
|       `-- profiles/
|-- routes/
|   `-- web.php
|-- storage/
|   |-- backups/
|   |-- logs/
|   `-- exports/
|-- tests/
|-- .env.example
|-- composer.json
`-- README.md
```

Only `public/` should be web-accessible. Configuration, logs, SQL dumps, and backup files must not be served by Apache. For a typical XAMPP installation, the project may live under `htdocs`, with the Apache document root or a virtual host pointed at the project's `public` directory.

## Main route groups

| Area | Example routes | Required permission |
|---|---|---|
| Authentication | `/login`, `/logout`, `/password/change` | Guest/authenticated rules |
| Dashboard | `/dashboard` | `dashboard.view` |
| Students | `/students`, `/students/create`, `/students/{id}` | Corresponding `students.*` |
| Teachers | `/teachers`, `/teachers/create`, `/teachers/{id}` | Corresponding `teachers.*` |
| Attendance | `/attendance/students`, `/attendance/teachers` | `attendance.view/record/edit` |
| Events | `/events`, `/events/{id}/participants`, `/events/{id}/attendance` | Corresponding `events.*` |
| Reports | `/reports/*` | `reports.view/export` |
| Administration | `/admin/users`, `/admin/roles`, `/admin/academics` | Corresponding admin permission |
| Operations | `/admin/settings`, `/admin/audit`, `/admin/backups` | Corresponding operations permission |

All create, update, delete, attendance-recording, and restore routes use POST requests with CSRF tokens. Record identifiers are validated and authorization is checked again in the controller/service layer.

## Security baseline

- Use `password_hash()` and `password_verify()`; never store plain-text passwords.
- Regenerate the PHP session ID after login and privilege changes.
- Use secure, HTTP-only, SameSite session cookies.
- Use PDO prepared statements for every value supplied by a user.
- Escape view output by default.
- Validate uploaded image type, size, generated filename, and storage location.
- Rate-limit repeated login attempts and record successful/failed logins.
- Check permissions server-side on every protected request.
- Require current-password confirmation for sensitive account changes.
- Require explicit confirmation and fresh authorization before restoring a backup.

## Build sequence

1. Bootstrap the PHP front controller, configuration, PDO connection, router, views, and error handling.
2. Add schema installation, permission seeds, and first-administrator setup.
3. Implement authentication, sessions, CSRF protection, roles, and permissions.
4. Implement academic structure and student/teacher profile management.
5. Implement student and teacher attendance workflows.
6. Implement events, participants, and event attendance.
7. Add dashboard summaries, reports, exports, audit logs, settings, and backup/restore.
8. Run permission, validation, database, and browser workflow tests; prepare XAMPP installation documentation.

