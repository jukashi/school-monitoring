-- Separate Registrar access from full administration and preserve existing combined-role users as Administrators.
UPDATE roles
SET name = 'Administrator',
    description = 'Manages school operations, profiles, academics, users, and reports.'
WHERE name = 'Administrator / Registrar';

INSERT IGNORE INTO permissions (code,module,action,description) VALUES
('students.import','students','import','Import student profiles from CSV'),
('enrollments.view','enrollments','view','View student enrollments'),
('enrollments.manage','enrollments','manage','Enroll, transfer, withdraw, and graduate students'),
('student_accounts.reset_password','student_accounts','reset_password','Generate temporary passwords for linked student accounts');

INSERT IGNORE INTO roles (name,description,is_system)
VALUES ('Registrar','Manages student records and enrollment without finance or system-administration access.',1);

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN ('students.import','enrollments.view','enrollments.manage','student_accounts.reset_password')
WHERE r.name IN ('Super Administrator','Administrator');

DELETE rp
FROM role_permissions rp
JOIN roles r ON r.id=rp.role_id
WHERE r.name='Registrar';

INSERT IGNORE INTO role_permissions (role_id,permission_id)
SELECT r.id,p.id
FROM roles r
JOIN permissions p ON p.code IN (
    'dashboard.view',
    'students.view','students.create','students.edit','students.export','students.import',
    'enrollments.view','enrollments.manage','student_accounts.reset_password',
    'teachers.view','academics.view',
    'attendance.view','attendance.export',
    'events.view','reports.view','reports.export'
)
WHERE r.name='Registrar';
