<?php
use App\Core\Auth;
use App\Core\Authorization;
use App\Core\Csrf;
?>
<div class="page-heading">
    <div><p class="eyebrow">Administration</p><h1>User accounts</h1><p>Control sign-in access, role assignments, and account recovery.</p></div>
    <?php if(Authorization::allows('users.create')):?><a class="button primary" href="<?=e(url('/admin/users/create'))?>">Create user</a><?php endif;?>
</div>
<section class="card table-card"><table><thead><tr><th>User</th><th>Username</th><th>Roles</th><th>Status</th><th>Last login</th><th></th></tr></thead><tbody>
<?php foreach($users as $u):?><tr>
    <td><strong><?=e($u['display_name'])?></strong><br><small><?=e($u['email'])?></small></td>
    <td><?=e($u['username'])?></td><td><?=e($u['roles']?:'No role')?></td><td><span class="badge"><?=e($u['status'])?></span></td><td><?=e($u['last_login_at']?:'Never')?></td>
    <td><div class="list-heading-actions">
        <?php if(Authorization::allows('users.edit')):?><a class="button small" href="<?=e(url('/admin/users/'.$u['id'].'/edit'))?>">Edit</a><?php endif;?>
        <?php if(Authorization::allows('users.edit')&&(int)$u['id']!==(int)Auth::user()['id']):?><form method="post" action="<?=e(url('/admin/users/'.$u['id'].'/temporary-password'))?>" onsubmit="return confirm('Generate a new temporary password? The current password will stop working immediately.')"><?=Csrf::field()?><button class="button small" type="submit">Temporary password</button></form><?php endif;?>
    </div></td>
</tr><?php endforeach;?>
</tbody></table></section>
