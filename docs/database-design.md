# Database Design

## Design goals

- Keep authentication separate from student and teacher profile records.
- Allow one user account to hold one or more roles.
- Authorize actions through permissions rather than hard-coded role names.
- Preserve attendance and event history across school years.
- Use soft status changes for important records instead of routinely deleting them.
- Record security-sensitive and destructive activity in an audit trail.

## Main relationships

```text
users --< user_roles >-- roles --< role_permissions >-- permissions
  |                            
  +--0..1 students --< enrollments >-- sections --< section_subjects >-- subjects
  |
  +--0..1 teachers --< teacher_assignments >--------------------------+

school_years --< terms
school_years --< sections

students --< student_attendance >-- attendance_sessions
teachers --< teacher_attendance

events --< event_participants >-- students/teachers/users
events --< event_attendance

users --< audit_logs
```

## Entity groups

### Identity and authorization

- `users`: Login identity, password hash, account state, and last login.
- `roles`: Named access groups such as Super Administrator, Registrar, Teacher, Student, and Viewer.
- `permissions`: Atomic abilities using `module.action` codes, for example `students.view` or `events.create`.
- `user_roles`: Supports multiple roles for one account.
- `role_permissions`: Assigns atomic permissions to roles.
- `password_reset_tokens`: Optional local password-recovery workflow managed by an administrator.

Authorization is granted when any active role belonging to the user contains the required permission. The application will protect both navigation items and server-side routes; hiding a menu alone is never treated as security.

### People

- `students`: Student number, personal details, guardian information, and status.
- `teachers`: Employee number, personal details, department, and employment status.
- `departments`: Academic or administrative departments.
- `guardians`: Reusable guardian records.
- `student_guardians`: Many-to-many student and guardian relationship.

The optional `user_id` in student and teacher records allows profiles to exist before portal access is issued.

### Academic structure

- `school_years`: Date-bounded academic years; only one should be active.
- `terms`: Semesters, quarters, or other periods within a school year.
- `grade_levels`: Ordered grade/year definitions.
- `sections`: Class groups for a particular school year and grade level.
- `subjects`: Subject catalog.
- `enrollments`: A student's section membership and enrollment status per school year.
- `section_subjects`: Subjects offered to a section.
- `teacher_assignments`: Teachers assigned to a section subject.

### Attendance

- `attendance_sessions`: A dated attendance context such as a daily homeroom session, class, or event-independent session.
- `student_attendance`: One status per student per attendance session.
- `teacher_attendance`: One daily status per teacher.

Supported initial statuses are present, absent, late, excused, and unrecorded. These can later be moved to configurable lookup tables if the school needs custom statuses.

### Events

- `events`: Event details, schedule, organizer, visibility, and lifecycle status.
- `event_participants`: Invited or registered students, teachers, or users.
- `event_attendance`: Check-in status and time for each participant.

Polymorphic participant records are validated in application code: exactly one matching profile identifier is required based on `participant_type`.

### Operations

- `announcements`: Dashboard notices, optionally attached to an event.
- `audit_logs`: Actor, action, affected record, before/after JSON, IP address, and timestamp.
- `system_settings`: School identity and configurable application values.
- `backup_history`: Metadata for administrator-created backups; backup files remain outside the public web directory.

## Important constraints

- Student numbers, employee numbers, usernames, emails, role names, and permission codes are unique.
- A student can have only one enrollment record per school year.
- An attendance record can occur only once per student/session or teacher/date.
- Event attendance can occur only once per participant/event.
- Foreign keys use restrictive deletes for historical records and cascading deletes only for pure assignment/junction records.
- All displayed dates use the configured school timezone; database timestamps are stored consistently by the application.

## Initial permission catalog

Each module will normally have a subset of these actions:

- `dashboard.view`
- `students.view`, `students.create`, `students.edit`, `students.delete`, `students.export`
- `teachers.view`, `teachers.create`, `teachers.edit`, `teachers.delete`, `teachers.export`
- `academics.view`, `academics.manage`
- `attendance.view`, `attendance.record`, `attendance.edit`, `attendance.export`
- `events.view`, `events.create`, `events.edit`, `events.delete`, `events.manage_participants`
- `reports.view`, `reports.export`
- `users.view`, `users.create`, `users.edit`, `users.disable`
- `roles.view`, `roles.manage`
- `audit.view`
- `settings.view`, `settings.manage`
- `backups.view`, `backups.create`, `backups.restore`

The implementation seed will grant all permissions to Super Administrator. Other roles will receive conservative defaults that an administrator can adjust.

