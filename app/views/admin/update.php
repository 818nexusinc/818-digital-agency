<?php $last = jdec((string)App\Settings::get('system.last_update') ?: 'null'); ?>
<div class="topbar"><div><h1>Install an update</h1><p class="muted" style="margin:4px 0 0">Upload the release zip you receive. The site's settings, uploads and logs are never touched, and the current version is saved first so you can roll back in one click.</p></div></div>
<?php if ($why): ?><div class="alert alert-info"><?= e($why) ?></div><?php else: ?>
<div class="grid g2">
  <div class="panel"><h2>Upload a release</h2>
    <p class="small muted">Running version <b><?= e(APP_VERSION) ?></b>.<?php if ($last): ?> Last update: <?= e($last['from'] ?? '') ?> → <?= e($last['to'] ?? '') ?> on <?= e(substr((string)($last['at'] ?? ''), 0, 16)) ?> UTC (<?= (int)($last['files'] ?? 0) ?> files).<?php endif; ?></p>
    <form method="post" enctype="multipart/form-data" class="stack"><?= App\Csrf::field() ?><input type="hidden" name="action" value="update">
      <label>Release package (.zip)<input type="file" name="package" accept=".zip,application/zip" required></label>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> I understand the site's code will be replaced with this package.</label>
      <button class="btn btn-primary">Install update</button></form>
    <p class="small muted" style="margin-top:10px">Largest upload this server accepts: <?= e(ini_get('upload_max_filesize')) ?>.</p></div>
  <div class="panel"><h2>Roll back</h2>
    <?php if (!$backups): ?><div class="empty">A copy of the current version is saved here every time you install an update.</div><?php else: ?>
    <table><thead><tr><th>Saved copy</th><th>When (UTC)</th><th></th></tr></thead><tbody>
    <?php foreach ($backups as $b): ?><tr><td class="small"><?= e($b['file']) ?></td><td class="small"><?= e($b['at']) ?></td><td><form method="post" onsubmit="return confirm('Restore this version of the code?')"><?= App\Csrf::field() ?><input type="hidden" name="action" value="rollback"><input type="hidden" name="backup" value="<?= e($b['file']) ?>"><button class="btn btn-ghost btn-sm">Restore</button></form></td></tr><?php endforeach; ?>
    </tbody></table><?php endif; ?></div>
</div>
<?php endif; ?>
