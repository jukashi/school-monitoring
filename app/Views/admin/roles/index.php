<div class="page-heading"><div><p class="eyebrow">Administration</p><h1>Roles and permissions</h1><p>Roles group the actions that users are allowed to perform.</p></div></div>
<section class="card table-card">
    <table><thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Permissions</th><th></th></tr></thead><tbody>
    <?php foreach ($roles as $role): ?><tr><td><strong><?= e($role['name']) ?></strong><?php if ($role['is_system']): ?> <span class="badge">System</span><?php endif; ?></td><td><?= e($role['description']) ?></td><td><?= (int)$role['user_count'] ?></td><td><?= (int)$role['permission_count'] ?></td><td><a class="button small" href="<?= e(url('/admin/roles/'.$role['id'].'/edit')) ?>">Configure</a></td></tr><?php endforeach; ?>
    </tbody></table>
</section>

