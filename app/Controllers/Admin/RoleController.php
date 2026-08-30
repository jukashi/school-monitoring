<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Database;
use App\Core\View;

final class RoleController
{
    public function index(): void
    {
        $pdo = Database::connection();
        $roles = $pdo->query('SELECT r.*, COUNT(DISTINCT ur.user_id) AS user_count, COUNT(DISTINCT rp.permission_id) AS permission_count FROM roles r LEFT JOIN user_roles ur ON ur.role_id=r.id LEFT JOIN role_permissions rp ON rp.role_id=r.id GROUP BY r.id ORDER BY r.name')->fetchAll();
        View::render('admin/roles/index', compact('roles'));
    }

    public function edit(string $id): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $stmt->execute([(int) $id]);
        $role = $stmt->fetch();
        if (!$role) { http_response_code(404); View::render('errors/message', ['title'=>'Role not found','message'=>'The requested role does not exist.']); return; }
        $permissions = $pdo->query('SELECT * FROM permissions ORDER BY module, action')->fetchAll();
        $assigned = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $assigned->execute([$role['id']]);
        View::render('admin/roles/edit', ['role'=>$role, 'permissions'=>$permissions, 'assigned'=>array_map('intval', array_column($assigned->fetchAll(), 'permission_id'))]);
    }

    public function update(string $id): void
    {
        $pdo = Database::connection();
        $role = $pdo->prepare('SELECT * FROM roles WHERE id = ?');
        $role->execute([(int) $id]);
        $existing = $role->fetch();
        if (!$existing) { http_response_code(404); View::render('errors/message', ['title'=>'Role not found','message'=>'The requested role does not exist.']); return; }
        $ids = array_values(array_unique(array_filter(array_map('intval', $_POST['permissions'] ?? []))));
        if ($existing['name'] === 'Super Administrator' && (int) $existing['is_system'] === 1) {
            $ids = array_map('intval', array_column($pdo->query('SELECT id FROM permissions')->fetchAll(), 'id'));
        }
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM role_permissions WHERE role_id = ?')->execute([$existing['id']]);
        $insert = $pdo->prepare('INSERT INTO role_permissions (role_id, permission_id) SELECT ?, id FROM permissions WHERE id = ?');
        foreach ($ids as $permissionId) $insert->execute([$existing['id'], $permissionId]);
        $pdo->commit();
        Authorization::forget();
        Auth::audit('roles.permissions_updated', 'roles', (string) $existing['id'], null, ['permission_ids'=>$ids]);
        flash('success', 'Role permissions updated. Other signed-in users will receive changes when their session permission cache refreshes.');
        Auth::redirect('/admin/roles/' . $existing['id'] . '/edit');
    }
}
