-- Keep role permissions aligned with object-level student profile access.
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'students.view'
WHERE r.name IN ('Student', 'Teacher');

DELETE rp
FROM role_permissions rp
JOIN roles r ON r.id = rp.role_id
JOIN permissions p ON p.id = rp.permission_id
WHERE r.name = 'Viewer / Staff'
  AND p.code = 'students.view';
