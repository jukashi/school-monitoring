-- Teachers may view/edit only their linked profile and view only advisory students.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r JOIN permissions p ON p.code = 'teachers.view'
WHERE r.name = 'Teacher';

DELETE rp
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.name = 'Teacher'
  AND ((p.module = 'students' AND p.code <> 'students.view')
    OR (p.module = 'teachers' AND p.code <> 'teachers.view'));
