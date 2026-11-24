<?php $ts = App\Integrations::configured('turnstile'); ?>
<div class="topbar"><div><h1>Spam shield</h1><p class="muted" style="margin:4px 0 0">Every public form (contact, intake, orders, sign-up, newsletter, password reset and the chat) is checked before anything is saved. Bots get a normal-looking reply, so they don't learn what stopped them.</p></div></div>
<div class="kpis">
  <div class="kpi"><div class="l">Blocked today</div><div class="v"><?= (int)$s['today'] ?></div></div>
  <div class="kpi"><div class="l">Blocked, 7 days</div><div class="v"><?= (int)$s['total'] ?></div></div>
  <div class="kpi"><div class="l">Shield</div><div class="v" style="font-size:18px"><?= $enabled ? 'On' : 'Off' ?></div></div>
  <div class="kpi"><div class="l">Cloudflare check</div><div class="v" style="font-size:18px"><?= $ts ? 'On' : 'Not set up' ?></div></div>
</div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><h2>What gets checked</h2>
    <ol class="small" style="padding-left:18px;display:grid;gap:6px;margin:0">
      <li>A hidden field people never see. Bots fill it in.</li>
      <li>A signed token from the page, proving the form came from this site.</li>
      <li>Timing: a person needs a few seconds; scripts submit instantly.</li>
      <li>A real keypress, tap or click on the page before sending.</li>
      <li>Limits per network for each form, and the same message sent over and over.</li>
      <li>Link stuffing, links in name fields, disposable email addresses and known spam pitches.</li>
      <li><?= $ts ? 'Cloudflare Turnstile on contact, intake, sign-up and newsletter forms.' : 'Optional: Cloudflare Turnstile. Add its free keys in Integrations for an extra check.' ?></li>
    </ol></div>
  <div class="panel"><h2>Settings</h2>
    <form method="post" class="stack"><?= App\Csrf::field() ?>
      <label class="check"><input type="checkbox" name="enabled" value="1"<?= $enabled ? ' checked' : '' ?>> Shield on (recommended)</label>
      <label>Most links allowed in a message<input type="number" name="max_links" min="0" max="10" value="<?= (int)$maxLinks ?>"></label>
      <label>Blocked networks (IP addresses, comma separated)<textarea name="blocked_ips" rows="2"><?= e($blocked) ?></textarea></label>
      <div class="row"><button class="btn btn-primary btn-sm">Save</button><?php if (!$ts): ?><a class="btn btn-ghost btn-sm" href="/admin/integrations#turnstile">Set up Cloudflare check</a><?php endif; ?></div>
    </form></div>
</div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><h2>By form, 7 days</h2><?php if (!$s['forms']): ?><div class="empty">Nothing blocked yet.</div><?php else: ?><table><tbody><?php foreach ($s['forms'] as $f => $n): ?><tr><td><?= e(ucfirst($f)) ?></td><td style="text-align:right"><?= (int)$n ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
  <div class="panel"><h2>Top reasons</h2><?php if (!$s['reasons']): ?><div class="empty">Nothing blocked yet.</div><?php else: ?><table><tbody><?php foreach ($s['reasons'] as $r => $n): ?><tr><td><?= e($r) ?></td><td style="text-align:right"><?= (int)$n ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?></div>
</div>
<div class="panel"><h2>Recent blocks</h2>
<?php if (!$s['recent']): ?><div class="empty">Nothing blocked yet. Blocked attempts appear here with the reason, so you can spot a real customer who was caught by mistake.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>When (UTC)</th><th>Form and reason</th><th>Network</th><th></th></tr></thead><tbody>
<?php foreach ($s['recent'] as $r): ?><tr><td class="small"><?= e(substr($r['created_at'], 0, 16)) ?></td><td><?= e($r['detail']) ?></td><td class="small"><?= e($r['ip']) ?></td>
  <td><?php if (!in_array($r['ip'], App\Spam::blocked(), true)): ?><form method="post"><?= App\Csrf::field() ?><input type="hidden" name="block" value="<?= e($r['ip']) ?>"><button class="btn btn-ghost btn-sm">Block network</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></div>
