<?php use App\Core\Csrf; ?>
<section class="card wide-card">
    <div class="card-header">
        <p class="eyebrow">Initial setup</p>
        <h1>Install School Monitoring System</h1>
        <p>Connect to MariaDB, create the tables, and establish the first administrator.</p>
    </div>
    <?php if ($errors): ?><div class="alert danger"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <form method="post" action="<?= e(url('/install')) ?>" class="stack-form">
        <?= Csrf::field() ?>
        <fieldset><legend>Application</legend><label>Application URL<input name="app_url" type="url" required value="<?= e($values['app_url']) ?>"></label></fieldset>
        <fieldset>
            <legend>Database</legend>
            <div class="grid two"><label>Host<input name="db_host" required value="<?= e($values['db_host']) ?>"></label><label>Port<input name="db_port" required value="<?= e($values['db_port']) ?>"></label></div>
            <div class="grid two"><label>Database name<input name="db_name" required value="<?= e($values['db_name']) ?>"></label><label>Database user<input name="db_user" required value="<?= e($values['db_user']) ?>"></label></div>
            <label>Database password<input name="db_pass" type="password" autocomplete="new-password"><small>Blank is common for a default local XAMPP installation.</small></label>
        </fieldset>
        <fieldset>
            <legend>Administrator</legend>
            <div class="grid two"><label>Display name<input name="admin_name" required value="<?= e($values['admin_name']) ?>"></label><label>Username<input name="admin_username" required value="<?= e($values['admin_username']) ?>"></label></div>
            <label>Email (optional)<input name="admin_email" type="email" value="<?= e($values['admin_email']) ?>"></label>
            <div class="grid two"><label>Password<input name="admin_password" type="password" minlength="10" required autocomplete="new-password"></label><label>Confirm password<input name="admin_password_confirmation" type="password" minlength="10" required autocomplete="new-password"></label></div>
        </fieldset>
        <button class="button primary" type="submit">Install system</button>
    </form>
</section>

