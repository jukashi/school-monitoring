<?php use App\Core\Csrf; ?>
<section class="card auth-card">
    <p class="eyebrow">First login</p>
    <h1>Create your password</h1>
    <p>Replace the temporary password before continuing to your account.</p>
    <?php if ($error): ?><div class="alert danger"><?=e($error)?></div><?php endif; ?>
    <form method="post" action="<?=e(url('/password/change'))?>" class="stack-form">
        <?=Csrf::field()?>
        <label>New password<input name="password" type="password" minlength="10" required autofocus autocomplete="new-password"><small>At least 10 characters with uppercase, lowercase, and a number.</small></label>
        <label>Confirm new password<input name="password_confirmation" type="password" minlength="10" required autocomplete="new-password"></label>
        <button class="button primary" type="submit">Set password and continue</button>
    </form>
    <form method="post" action="<?=e(url('/logout'))?>" class="stack-form"><?=Csrf::field()?><button class="button" type="submit">Sign out</button></form>
</section>
