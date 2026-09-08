INSERT IGNORE INTO permissions (code, module, action, description)
VALUES ('employees.delete', 'employees', 'delete', 'Delete employee records');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
JOIN permissions p ON p.code = 'employees.delete'
WHERE r.name = 'Super Administrator';
