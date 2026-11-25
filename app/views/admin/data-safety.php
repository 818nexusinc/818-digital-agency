<?php
use App\Csrf;
$key = $st['key']; $kit = $st['kit']; $last = $st['last'];
$kb = fn(int $b) => $b > 1048576 ? number_format($b / 1048576, 1) . ' MB' : max(1, (int)round($b / 1024)) . ' KB';
?>
<div class="db ds">
<div class="db-head"><div><h1>Data safety</h1><p class="db-bc">Your settings, integrations and site edits are kept safe from updates, folder mistakes and host resets</p></div></div>

<?php if ($code): ?>
<section class="db-card ds-code" aria-labelledby="code-h">
  <h2 id="code-h">Your recovery code</h2>
  <p>Write this down or save it in your password manager <b>now</b>. It is shown only once and is not stored anywhere. With it, the site can be put back exactly as it is today, even on a new server.</p>
  <p class="ds-big" data-code><?= e($code) ?></p>
  <div class="ds-acts"><button type="button" class="btn btn-primary btn-sm" data-copy-code>Copy</button><button type="button" class="btn btn-sm" data-print>Print</button><a class="btn btn-sm" href="/admin/data-safety/kit">Download the kit file</a></div>
  <p class="small muted">Keep the code and the kit file in different places. Making a new kit replaces this one.</p>
</section>
<?php endif; ?>

<div class="ds-grid">
  <section class="db-card ds-st st-<?= e($key) ?>">
    <h2>Encryption key</h2>
    <?php if ($key === 'mismatch'): ?>
      <p><b>The key on this server is not the one your saved data was made with.</b> Integrations and two-step secrets can't be read until it is put back. Nothing is lost.</p>
      <form method="post" class="ds-form"><?= Csrf::field() ?><input type="hidden" name="action" value="unlock"><label>Recovery code<input type="text" name="code" autocomplete="off" spellcheck="false" placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXX" required></label><button class="btn btn-primary">Put my key back</button></form>
    <?php else: ?>
      <p class="ok-t"><b><?= $key === 'healed' ? 'Put back automatically from a safe copy.' : 'Correct.' ?></b> Saved integrations and two-step secrets can be read.</p>
    <?php endif; ?>
    <h3>Safe copies</h3>
    <ul class="ds-dirs"><?php foreach ($st['dirs'] as $d): $out = in_array($d, $st['outside'], true); ?><li><span class="<?= $out ? 'ok' : '' ?>"><?= $out ? 'Outside the website folder' : 'Inside the website folder' ?></span><code><?= e($d) ?></code></li><?php endforeach; ?></ul>
    <?php if (!$st['outside']): ?><p class="small warn-t">This server doesn't let the site write outside the website folder, so the copies could be lost with it. Make a recovery kit and keep the code safe.</p><?php endif; ?>
  </section>

  <section class="db-card ds-st">
    <h2>Recovery kit</h2>
    <?php if ($kit): ?><p class="ok-t"><b>Made on <?= e(substr((string)$kit['at'], 0, 16)) ?> UTC</b> by <?= e((string)$kit['by']) ?>.</p><p class="small">It seals the encryption key and the server settings with your recovery code. Lost the code? Make a new kit; the old code stops working.</p>
    <?php else: ?><p class="warn-t"><b>Not made yet.</b> Make one now: it's the only way back if the website folder and its copies are ever all lost.</p><?php endif; ?>
    <form method="post" class="ds-form" data-confirm="<?= $kit ? 'Make a new recovery kit? Your old code will stop working.' : 'Make your recovery kit now? The code is shown once.' ?>"><?= Csrf::field() ?><input type="hidden" name="action" value="kit"><button class="btn btn-primary"><?= $kit ? 'Make a new recovery kit' : 'Make my recovery kit' ?></button></form>
    <?php if ($kit): ?><a class="db-more" href="/admin/data-safety/kit">Download the kit file</a><?php endif; ?>
    <?php if (!$recent): ?><p class="small muted">You'll be asked to sign in again first (needed for kits and restores).</p><?php endif; ?>
  </section>
</div>

<?php if ($preview): ?>
<section class="db-card ds-prev">
  <h2>Restore the backup from <?= e(substr((string)$preview['at'], 0, 16)) ?> UTC?</h2>
  <p>It holds <?php $parts = []; foreach ($preview['counts'] as $t => $n) if ($n) $parts[] = $n . ' ' . ['settings' => 'settings', 'integrations' => 'integrations', 'site_text' => 'text changes', 'site_assets' => 'logos', 'site_images' => 'pictures', 'agents' => 'agent setups', 'price_costs' => 'fee records', 'workflows' => 'workflows', 'passkeys' => 'passkeys'][$t] ?? $t; echo e(implode(', ', $parts)); ?> (version <?= e((string)$preview['version']) ?>).</p>
  <p class="small">Each one is put back as it was in the backup. Anything added since is kept. A backup of how things are right now is made first, so this can be undone.</p>
  <form method="post" class="ds-acts"><?= Csrf::field() ?><input type="hidden" name="action" value="restore"><input type="hidden" name="name" value="<?= e($preview['name']) ?>"><button class="btn btn-primary">Restore</button><a class="btn" href="/admin/data-safety">Cancel</a></form>
</section>
<?php endif; ?>

<section class="db-card ds-list">
  <div class="db-ch"><div><h2>Configuration backups</h2><p class="db-sub">Settings, integrations, site edits, logos, pictures, agent setups, workflows, fees and passkeys. Made every day and before every update; encrypted; the last 30 are kept in each safe folder.</p></div>
    <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="backup"><button class="btn btn-sm">Back up now</button></form></div>
  <?php if (!$snaps): ?><div class="ct-empty"><b>No backups yet.</b><p>The first one is made tonight, before your next update, or when you click "Back up now".</p></div>
  <?php else: ?><table><thead><tr><th>When (UTC)</th><th>Why</th><th>Kept</th><th class="num">Size</th><th></th></tr></thead><tbody>
    <?php foreach ($snaps as $s): ?><tr><td><?= e($s['at']) ?></td><td><?= e(ucfirst($s['reason'])) ?></td><td><?= $s['outside'] ? 'Outside the website folder' : 'In the website folder' ?></td><td class="num"><?= $kb((int)$s['size']) ?></td><td><a class="db-more" href="/admin/data-safety?preview=<?= e(rawurlencode($s['name'])) ?>">Restore…</a></td></tr><?php endforeach; ?>
  </tbody></table><?php endif; ?>
</section>
</div>
