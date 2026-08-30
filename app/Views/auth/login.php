<?php use App\Core\Csrf; ?>
<section class="card auth-card">
    <div class="mark">SM</div>
    <p class="eyebrow">School Monitoring System</p>
    <h1>Welcome back</h1>
    <p>Sign in with your username or email.</p>
    <?php if ($success = flash('success')): ?><div class="alert success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="stack-form">
        <?= Csrf::field() ?>
        <label>Username or email<input name="login" required autofocus autocomplete="username"></label>
        <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
        <button class="button primary" type="submit">Sign in</button>
    </form>
    <p><small>Forgot your password? Ask a school administrator to generate a new temporary password for your account.</small></p>
</section>
