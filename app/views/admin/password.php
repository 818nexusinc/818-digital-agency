<div class="db um">
<div class="db-head"><div><h1>Change password</h1><p class="db-bc"><?= $forced ? 'Your password was set by an admin. Choose your own to continue.' : 'Signed in as ' . e($u['email']) ?></p></div></div>
<section class="db-card um-pw"><?php include APP_ROOT . '/views/partials/password-form.php'; ?>
<?php if (!$forced): ?><p class="small muted">Prefer no password at all? Add a passkey under <a href="/admin/sign-in">Sign-in &amp; recovery</a>.</p><?php endif; ?></section>
</div>
