<?php
use App\Core\Database;

$guestSchoolName = 'School Monitoring System';
$guestSchoolLogo = null;
try {
    $identityStatement = Database::connection()->query('SELECT setting_key,setting_value FROM system_settings WHERE setting_key IN ("school_name","school_logo")');
    $identity = [];
    foreach ($identityStatement->fetchAll() as $setting) $identity[$setting['setting_key']] = $setting['setting_value'];
    $guestSchoolName = trim((string)($identity['school_name'] ?? '')) ?: $guestSchoolName;
    $guestSchoolLogo = $identity['school_logo'] ?? null;
} catch (\Throwable) {
    // Installation and database error screens must remain available before setup.
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'School Monitoring System') ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/modules.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/theme-purple.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/ui-polish.css?v=2')) ?>">
</head>
<body class="guest-body">
<a class="skip-link" href="#main-content">Skip to main content</a>
<main class="guest-container" id="main-content" tabindex="-1">
    <div class="guest-shell">
        <header class="guest-brand" aria-label="<?=e($guestSchoolName)?>">
            <?php if($guestSchoolLogo):?><img src="<?=e(url('/'.$guestSchoolLogo))?>" alt=""><?php else:?><span class="mark" aria-hidden="true">SM</span><?php endif;?>
            <span><strong><?=e($guestSchoolName)?></strong><small>School operations ledger</small></span>
        </header>
        <?= $content ?>
    </div>
</main>
<script>document.querySelectorAll('.alert.danger').forEach(alert=>alert.setAttribute('role','alert'));</script>
</body>
</html>
