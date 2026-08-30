<?php

use App\Core\Csrf;

$v=fn($key)=>$input[$key]??$employee[$key]??'';
$editing=(bool)$employee;
?>
<?php if(!$editing):?><div class="alert success">The employee number will become the login username. A temporary password will be generated after saving.</div><?php endif;?>
<div class="page-heading">
    <div><p class="eyebrow">Insurance directory</p><h1><?=$editing?'Edit employee':'Add employee'?></h1><p>Non-teaching employee information for insurance coverage.</p></div>
    <a class="button" href="<?=e(url('/insurance/employees'))?>">Cancel</a>
</div>
<?php if($errors):?><div class="alert danger"><?=e($errors['form']??implode(' ',array_values($errors)))?></div><?php endif;?>
<form method="post" action="<?=e($editing?url('/insurance/employees/'.$employee['id']):url('/insurance/employees'))?>" class="stack-form">
    <?=Csrf::field()?>
    <section class="card form-section">
        <h2>Employee details</h2>
        <div class="grid three">
            <label>Employee number<input required name="employee_no" value="<?=e($v('employee_no'))?>"></label>
            <label>First name<input required name="first_name" value="<?=e($v('first_name'))?>"></label>
            <label>Middle name<input name="middle_name" value="<?=e($v('middle_name'))?>"></label>
            <label>Last name<input required name="last_name" value="<?=e($v('last_name'))?>"></label>
            <label>Job title<input name="job_title" value="<?=e($v('job_title'))?>"></label>
            <label>Department<select name="department_id"><option value="">Not assigned</option><?php foreach($departments as $department):?><option value="<?=$department['id']?>" <?=(string)$v('department_id')===(string)$department['id']?'selected':''?>><?=e($department['name'])?></option><?php endforeach;?></select></label>
            <label>Email<input type="email" name="email" value="<?=e($v('email'))?>"></label>
            <label>Phone<input type="text" inputmode="numeric" autocomplete="tel" pattern="[0-9]{11}" minlength="11" maxlength="11" title="Enter exactly 11 digits." placeholder="09XXXXXXXXX" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,11)" name="phone" value="<?=e($v('phone'))?>"></label>
            <label>Status<select name="employment_status"><?php foreach(['active','on_leave','inactive','separated'] as $status):?><option value="<?=$status?>" <?=$v('employment_status')===$status?'selected':''?>><?=e(ucwords(str_replace('_',' ',$status)))?></option><?php endforeach;?></select></label>
        </div>
    </section>
    <div class="sticky-actions"><button class="button primary">Save employee</button></div>
</form>
