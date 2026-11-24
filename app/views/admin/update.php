<?php $last = jdec((string)App\Settings::get('system.last_update') ?: 'null'); ?>
<div class="topbar"><div><h1>Install an update</h1><p class="muted" style="margin:4px 0 0">Upload the release zip you receive. The site's settings, uploads and logs are never touched, and the current version is saved first so you can roll back in one click.</p></div></div>
<?php if ($why): ?><div class="alert alert-info"><?= e($why) ?></div><?php else: ?>
<?php $others = array_filter($team, fn($u) => $u['on'] && $u['id'] !== (int)App\Auth::user()['id']); ?>
<div class="up-steps">
  <section class="panel up-step <?= $mfaOn ? 'is-now' : 'is-done' ?>" id="mfa">
    <h2><span>1</span> Pause two-step sign-in</h2>
    <?php if ($mfaOn): ?>
      <p class="small">Required before every update. Your authenticator secret is sealed with your password so the update can't affect it. After the update you turn it back on with your password and a code from the <b>same</b> app; no new QR code.</p>
      <form method="post" action="/admin/mfa/pause" class="up-inline"><?= App\Csrf::field() ?><label class="sr" for="pp">Your password</label><input id="pp" type="password" name="password" autocomplete="current-password" required placeholder="Your password"><button class="btn btn-primary">Pause for this update</button></form>
    <?php elseif ($paused): ?>
      <p class="small ok-t">Paused on <?= e(substr($paused['at'], 0, 16)) ?> UTC (version <?= e($paused['version']) ?>). You can install the update.</p>
    <?php else: ?>
      <p class="small muted">Two-step sign-in is off for your account, so there is nothing to pause. Turn it on in <a href="/admin/settings#mfa">Settings &amp; team</a> after the update.</p>
    <?php endif; ?>
    <?php if ($others): ?><p class="small warn-t">Also on for: <?= e(implode(', ', array_column($others, 'email'))) ?>. Ask them to pause it from their own Updates page or Settings before you install.</p><?php endif; ?>
  </section>
  <section class="panel up-step <?= $mfaOn ? 'is-locked' : 'is-now' ?>"><h2><span>2</span> Install the update</h2>
    <p class="small muted">Running version <b><?= e(APP_VERSION) ?></b>.<?php if ($last): ?> Last update: <?= e($last['from'] ?? '') ?> → <?= e($last['to'] ?? '') ?> on <?= e(substr((string)($last['at'] ?? ''), 0, 16)) ?> UTC (<?= (int)($last['files'] ?? 0) ?> files).<?php endif; ?></p>
    <?php if ($mfaOn): ?><p class="small muted">Available after step 1.</p><?php else: ?>
    <form method="post" enctype="multipart/form-data" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="update">
      <label>Release package (.zip)<input type="file" name="package" accept=".zip,application/zip" required></label>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> I understand the site's code will be replaced with this package.</label>
      <button class="btn btn-primary">Install update</button></form><?php endif; ?>
    <p class="small muted" style="margin-top:10px">Largest upload this server accepts: <?= e(ini_get('upload_max_filesize')) ?>.</p>
  </section>
  <section class="panel up-step <?= $paused ? 'is-now' : '' ?>"><h2><span>3</span> Turn two-step sign-in back on</h2>
    <?php if ($paused): ?>
      <p class="small">Enter your password and the current 6-digit code from the authenticator app you already use for this site.</p>
      <form method="post" action="/admin/mfa/resume" class="up-inline"><?= App\Csrf::field() ?><input type="password" name="password" autocomplete="current-password" required placeholder="Your password" aria-label="Your password"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required placeholder="6-digit code" aria-label="Code from your app" maxlength="8"><button class="btn btn-primary">Turn back on</button></form>
    <?php else: ?><p class="small muted"><?= $mfaOn ? 'After the update, this step brings your two-step sign-in back.' : 'Nothing paused.' ?></p><?php endif; ?>
  </section>
</div>
<div class="grid g2">
  <div class="panel"><h2>Roll back</h2>
    <?php if (!$backups): ?><div class="empty">A copy of the current version is saved here every time you install an update.</div><?php else: ?>
    <table><thead><tr><th>Saved copy</th><th>When (UTC)</th><th></th></tr></thead><tbody>
    <?php foreach ($backups as $b): ?><tr><td class="small"><?= e($b['file']) ?></td><td class="small"><?= e($b['at']) ?></td><td><form method="post" data-confirm="Restore this version of the code?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup" value="<?= e($b['file']) ?>"><button class="btn btn-ghost btn-sm">Restore</button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?></div>
</div>
<?php endif; ?>
