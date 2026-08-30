<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'School Monitoring System') ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/modules.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/theme-purple.css')) ?>">
</head>
<body class="guest-body">
<main class="guest-container">
    <?= $content ?>
</main>
</body>
</html>
