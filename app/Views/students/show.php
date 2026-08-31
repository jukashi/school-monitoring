<?php

use App\Core\Authorization;

$teacherView = $teacherView ?? false;
$teacherSubjects = $teacherSubjects ?? [];
?>
<div class="page-heading">
    <div>
        <p class="eyebrow">Student <?= e($student['student_no']) ?></p>
        <h1><?= e($student['first_name'].' '.$student['last_name']) ?></h1>
        <p><span class="badge"><?= e($student['student_status']) ?></span></p>
    </div>
    <div class="page-actions">
        <?php if ($teacherView): ?>
            <a class="button" href="<?= e(url('/students')) ?>">Back to My assigned students</a>
        <?php endif; ?>
        <?php if (Authorization::canEditStudent((int) $student['id'])): ?>
            <a class="button primary" href="<?= e(url('/students/'.$student['id'].'/edit')) ?>">Edit profile</a>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid">
    <section class="card form-section">
        <h2><?= $teacherView ? 'Student contact' : 'Profile' ?></h2>
        <dl>
            <dt>Email</dt><dd><?= e($student['email'] ?: '—') ?></dd>
            <dt>Phone</dt><dd><?= e($student['phone'] ?: '—') ?></dd>
            <?php if (!$teacherView): ?>
                <dt>Birth date</dt><dd><?= e($student['birth_date'] ?: '—') ?></dd>
                <dt>Address</dt><dd><?= e($student['address'] ?: '—') ?></dd>
            <?php endif; ?>
        </dl>
    </section>

    <section class="card form-section">
        <h2><?= $teacherView ? 'Current enrollment' : 'Enrollment history' ?></h2>
        <?php foreach ($enrollments as $enrollment): ?>
            <p><strong><?= e($enrollment['grade_level'].' '.$enrollment['section_name']) ?></strong><br><small><?= e($enrollment['school_year'].' · '.$enrollment['status']) ?></small></p>
        <?php endforeach; ?>
        <?php if (!$enrollments): ?><p class="empty">No enrollment recorded.</p><?php endif; ?>
    </section>

    <?php if ($teacherView): ?>
        <section class="card form-section">
            <h2>My subjects with this student</h2>
            <?php foreach ($teacherSubjects as $subject): ?>
                <p><strong><?= e($subject['code'].' — '.$subject['name']) ?></strong></p>
            <?php endforeach; ?>
            <?php if (!$teacherSubjects): ?><p class="empty">You can view this student as their class adviser.</p><?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!$teacherView && Authorization::allows('inventory.view')): ?>
        <section class="card form-section profile-inventory">
            <div class="profile-section-heading">
                <div><p class="eyebrow">Assigned supplies</p><h2>Issued uniforms, IDs &amp; other items</h2></div>
                <a class="button small" href="<?= e(url('/uniform-id')) ?>">View inventory</a>
            </div>
            <div class="table-card"><table>
                <thead><tr><th scope="col">Item</th><th scope="col">Type</th><th scope="col">Issued</th><th scope="col">Returned</th><th scope="col">Outstanding</th><th scope="col">Status</th></tr></thead>
                <tbody>
                <?php foreach ($inventoryIssues as $issue): ?>
                    <?php $typeLabel = ['uniform' => 'Uniform', 'id_card' => 'ID card', 'other' => 'Other item'][$issue['item_type']] ?? 'Other item'; $outstanding = (int) $issue['quantity'] - (int) $issue['returned_quantity']; ?>
                    <tr>
                        <td><strong><?= e($issue['item_name']) ?></strong><?php if ($issue['variant']): ?> <span class="badge"><?= e($issue['variant']) ?></span><?php endif; ?><br><small><?= e($issue['sku']) ?></small></td>
                        <td><?= e($typeLabel) ?></td>
                        <td><?= (int) $issue['quantity'] ?> <?= e($issue['unit']) ?><br><small><?= e($issue['issued_on']) ?></small></td>
                        <td><?= (int) $issue['returned_quantity'] ?></td><td><strong><?= $outstanding ?></strong></td>
                        <td><span class="badge"><?= e(str_replace('_', ' ', $issue['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$inventoryIssues): ?><tr><td colspan="6" class="empty">No uniforms, IDs, or other inventory items assigned.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </section>
    <?php endif; ?>

    <section class="card form-section">
        <h2><?= $teacherView ? 'Primary guardian contact' : 'Guardians' ?></h2>
        <?php foreach ($guardians as $guardian): ?>
            <div class="guardian-entry" style="display:flex; flex-direction:column; gap:.75rem; padding:.5rem 0; border-bottom:1px solid rgba(91,66,145,.12);">
                <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:1rem;">
                    <p style="margin:0; flex:1;"><strong><?= e($guardian['first_name'].' '.$guardian['last_name']) ?></strong> <?= $guardian['is_primary'] ? '<span class="badge">Primary</span>' : '' ?><br><small><?= e($guardian['relationship'].' · '.$guardian['phone']) ?></small></p>
                    <?php if (Authorization::isStudentAdministrator() && Authorization::allows('students.edit')): ?>
                        <div class="guardian-actions" style="margin-left:auto;">
                            <a class="button small" href="<?= e(url('/students/'.$student['id'].'?edit_guardian='.$guardian['id'])) ?>">Edit</a>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (Authorization::isStudentAdministrator() && Authorization::allows('students.edit') && (int)($_GET['edit_guardian'] ?? 0) === (int)$guardian['id']): ?>
                    <form class="stack-form guardian-edit-form" method="post" action="<?= e(url('/students/'.$student['id'].'/guardians/'.$guardian['id'])) ?>" style="display:grid; gap:.75rem; margin:0; padding:.9rem; border:1px solid rgba(91,66,145,.18); border-radius:10px; background:rgba(143,115,198,.04);">
                        <?= \App\Core\Csrf::field() ?>
                        <div class="grid two" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.75rem;">
                            <label>First name<input required name="first_name" value="<?= e($guardian['first_name']) ?>"></label>
                            <label>Last name<input required name="last_name" value="<?= e($guardian['last_name']) ?>"></label>
                            <label>Relationship<input required name="relationship" value="<?= e($guardian['relationship']) ?>"></label>
                            <label>Phone<input type="text" inputmode="numeric" autocomplete="tel" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" name="phone" value="<?= e($guardian['phone']) ?>"></label>
                            <label>Email<input type="email" name="email" value="<?= e($guardian['email']) ?>"></label>
                            <label>Occupation<input name="occupation" value="<?= e($guardian['occupation']) ?>"></label>
                        </div>
                        <label>Address<textarea name="address" rows="2"><?= e($guardian['address']) ?></textarea></label>
                        <div class="grid two" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.75rem;">
                            <label class="inline-check"><input type="checkbox" name="is_primary" value="1" <?= !empty($guardian['is_primary']) ? 'checked' : '' ?>> Primary contact</label>
                            <label class="inline-check"><input type="checkbox" name="can_pick_up" value="1" <?= !empty($guardian['can_pick_up']) ? 'checked' : '' ?>> Authorized pickup</label>
                        </div>
                        <div class="inline-actions" style="display:flex; gap:.5rem; flex-wrap:wrap;">
                            <button class="button primary" type="submit">Save changes</button>
                            <a class="button" href="<?= e(url('/students/'.$student['id'])) ?>">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <?php if (!$guardians): ?><p class="empty"><?= $teacherView ? 'No primary guardian contact is recorded.' : 'No guardians recorded yet.' ?></p><?php endif; ?>
    </section>

    <?php if (Authorization::isStudentAdministrator() && Authorization::allows('students.edit')): ?>
        <section class="card form-section">
            <h2>Add guardian</h2>
            <form class="stack-form" method="post" action="<?= e(url('/students/'.$student['id'].'/guardians')) ?>">
                <?= \App\Core\Csrf::field() ?>
                <div class="grid two">
                    <label>First name<input required name="first_name"></label><label>Last name<input required name="last_name"></label>
                    <label>Relationship<input required name="relationship" placeholder="Mother, father, guardian"></label><label>Phone<input type="text" inputmode="numeric" autocomplete="tel" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" name="phone"></label>
                    <label>Email<input type="email" name="email"></label><label>Occupation<input name="occupation"></label>
                </div>
                <label>Address<textarea name="address" rows="2"></textarea></label>
                <div class="grid two"><label class="inline-check"><input type="checkbox" name="is_primary" value="1"> Primary contact</label><label class="inline-check"><input type="checkbox" name="can_pick_up" value="1" checked> Authorized pickup</label></div>
                <button class="button primary">Add guardian</button>
            </form>
        </section>
    <?php endif; ?>
</div>
