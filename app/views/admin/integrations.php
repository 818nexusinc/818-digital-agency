<?php $badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; }; ?>
<div class="topbar"><div><h1>Integration hub</h1><p class="muted" style="margin:4px 0 0">Credentials are encrypted at rest (libsodium) and never sent to the browser. Environment variables in <span class="kbd">app/.env</span> take precedence.</p></div></div>
<?php
$cats = ['ai' => 'AI', 'payments' => 'Payments', 'email' => 'Email', 'social' => 'Social media', 'marketing' => 'Marketing & search', 'domains' => 'Domains', 'hosting' => 'Hosting', 'government' => 'Government registries', 'security' => 'Security', 'productivity' => 'Productivity'];
$show = array_values(array_filter($rows, fn($r) => $tab === 'connected' ? $r['configured'] : !$r['configured']));
usort($show, fn($a, $b) => [array_search($a['cat'], array_keys($cats)), $a['stale'] ? 0 : 1, $a['name']] <=> [array_search($b['cat'], array_keys($cats)), $b['stale'] ? 0 : 1, $b['name']]);
$hdr = null;
?>
<nav class="int-tabs" aria-label="Integrations">
  <a href="/admin/integrations?tab=connected"<?= $tab === 'connected' ? ' aria-current="page"' : '' ?>>Connected <span><?= (int)$nOn ?></span></a>
  <a href="/admin/integrations?tab=available"<?= $tab === 'available' ? ' aria-current="page"' : '' ?>>Not connected <span><?= count($rows) - (int)$nOn ?></span></a>
</nav>
<?php if (!$show): ?><div class="empty"><?= $tab === 'connected' ? 'Nothing is connected yet. Open "Not connected" to add your first integration.' : 'Everything is connected.' ?></div><?php endif; ?>
<div class="grid g2"><?php foreach ($show as $r): $row = $r['row'] ?? []; $grp = $cats[$r['cat']] ?? ucfirst($r['cat']);
  if ($grp !== $hdr): $hdr = $grp; ?></div><h2 class="int-g"><?= e($grp) ?></h2><?php if ($r['cat'] === 'social'): ?><p class="small muted" style="margin:-4px 0 10px">Add each profile link to show it on the website and to search engines. Add a token to publish posts from Marketing → Social media.</p><?php endif; ?><div class="grid g2"><?php endif; ?>
<div class="panel" id="<?= e($r['slug']) ?>"><?php if ($r['stale']): ?><p class="small warn-t" style="margin:0 0 8px">Saved under an older encryption key this server no longer has. Paste the keys in again once.</p><?php endif; ?><div class="row spread"><div><h2 style="margin:0"><?= e($r['name']) ?></h2><span class="small muted"><?= e($r['cat']) ?> · <?= e($r['desc']) ?></span></div><?php $badge($r['configured'] ? ($row['status'] ?? 'configured') : 'not_configured'); ?></div>
  <p class="small muted" style="margin:8px 0">Last OK: <?= e($row['last_ok_at'] ?? '—') ?><?= !empty($row['last_error']) ? ' · last error: ' . e(str_limit($row['last_error'], 140)) : '' ?></p>
  <form method="post" action="/admin/integrations/<?= e($r['slug']) ?>"><?= App\Csrf::field() ?>
    <?php foreach ($r['fields'] as $f => $env): ?><div class="field"><label for="<?= e($r['slug'] . $f) ?>"><?= e(label($f)) ?> <span class="muted small">(env <?= e($env) ?><?= isset($r['env'][$f]) ? ' — set in environment' : '' ?>)</span></label><input id="<?= e($r['slug'] . $f) ?>" name="secret[<?= e($f) ?>]" type="password" autocomplete="off" placeholder="<?= App\Integrations::secret($r['slug'], $f) ? '•••••••• stored — leave blank to keep' : 'not set' ?>"></div><?php endforeach; ?>
    <?php if ($r['cat'] === 'social'): $idf = ['facebook' => ['page_id', 'Facebook Page ID'], 'instagram' => ['ig_user_id', 'Instagram business account ID'], 'linkedin' => ['org_id', 'LinkedIn organization ID'], 'youtube' => ['channel_id', 'YouTube channel ID']][$r['slug']] ?? null; ?>
      <div class="field"><label for="<?= e($r['slug']) ?>pu">Profile link</label><input id="<?= e($r['slug']) ?>pu" name="setting[profile_url]" type="url" placeholder="https://" value="<?= e(App\Integrations::setting($r['slug'], 'profile_url', '')) ?>"></div>
      <?php if ($idf): ?><div class="field"><label for="<?= e($r['slug']) ?>id"><?= e($idf[1]) ?></label><input id="<?= e($r['slug']) ?>id" name="setting[<?= $idf[0] ?>]" type="text" value="<?= e(App\Integrations::setting($r['slug'], $idf[0], '')) ?>"></div><?php endif; ?>
      <p class="small muted" style="margin:0 0 8px"><?= e(App\Social::NETWORKS[$r['slug']][2] ?? '') ?></p>
    <?php endif; ?>
    <?php if ($r['slug'] === 'resend'): ?><div class="field"><label for="rfe">From address (verified in Resend)</label><input id="rfe" name="setting[from_email]" type="email" value="<?= e(App\Integrations::setting('resend', 'from_email', '')) ?>"></div><?php endif; ?>
    <?php if ($r['slug'] === 'bc_registry'): ?><div class="field"><label for="bcb">API gateway base URL</label><input id="bcb" name="setting[base_url]" type="url" value="<?= e(App\Integrations::setting('bc_registry', 'base_url', 'https://bcregistry-prod.apigee.net')) ?>"><div class="hint">Use the sandbox gateway from the BC developer portal for your first test filing.</div></div><?php endif; ?>
    <?php if ($r['slug'] === 'webmail'): ?><div class="grid g2"><input name="setting[imap_host]" type="text" placeholder="imap host" value="<?= e(App\Integrations::setting('webmail', 'imap_host', 'mail.818nexus.ca')) ?>"><input name="setting[email]" type="email" placeholder="mailbox" value="<?= e(App\Integrations::setting('webmail', 'email', '')) ?>"></div><?php endif; ?>
    <div class="row" style="margin-top:8px"><button class="btn btn-primary btn-sm">Save</button><button class="btn btn-ghost btn-sm" name="test" value="1">Test connection</button></div></form>
  <?php foreach ($r['logs'] as $l): ?><div class="small muted"><?= $l['ok'] ? '✓' : '✗' ?> <?= e($l['created_at']) ?> <?= e($l['action']) ?> — <?= e(str_limit((string)$l['detail'], 100)) ?></div><?php endforeach; ?></div>
<?php endforeach; ?></div>
<div class="panel"><h2>Webhook endpoints to register</h2><p class="small">Stripe: <span class="kbd"><?= e(url('/webhooks/stripe')) ?></span>. Events: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, invoice.paid, invoice.payment_failed, charge.refunded, customer.subscription.deleted.<br>Resend: <span class="kbd"><?= e(url('/api/resend-webhook.php')) ?></span> (same URL as before).</p></div>
