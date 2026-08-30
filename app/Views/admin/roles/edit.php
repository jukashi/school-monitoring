<?php use App\Core\Csrf; $grouped = []; foreach ($permissions as $permission) $grouped[$permission['module']][] = $permission; ?>
<div class="page-heading"><div><p class="eyebrow">Role configuration</p><h1><?= e($role['name']) ?></h1><p><?= e($role['description']) ?></p></div><a class="button" href="<?= e(url('/admin/roles')) ?>">Back to roles</a></div>
<?php if ($role['name'] === 'Super Administrator' && (int)$role['is_system'] === 1): ?><div class="alert success">This protected role always retains every system permission.</div><?php endif; ?>
<form method="post" action="<?= e(url('/admin/roles/'.$role['id'])) ?>">
    <?= Csrf::field() ?>
    <div class="permission-grid">
        <?php foreach ($grouped as $module => $items): ?><section class="card permission-card"><h2><?= e(ucfirst($module)) ?></h2><?php foreach ($items as $permission): ?><label class="check-row"><input type="checkbox" name="permissions[]" value="<?= (int)$permission['id'] ?>" <?= in_array((int)$permission['id'], $assigned, true) ? 'checked' : '' ?>><span><strong><?= e($permission['action']) ?></strong><small><?= e($permission['description']) ?></small></span></label><?php endforeach; ?></section><?php endforeach; ?>
    </div>
    <div class="sticky-actions"><button class="button primary" type="submit">Save permissions</button></div>
</form>
