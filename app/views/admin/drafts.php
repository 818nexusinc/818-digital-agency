<?php $tabs = ['open' => 'Open', 'followed_up' => 'Followed up', 'completed' => 'Completed', 'dismissed' => 'Dismissed', 'all' => 'All']; ?>
<div class="topbar"><div><h1>Unfinished forms</h1><p class="muted" style="margin:4px 0 0">Visitors who started an order or quote and stopped. Progress saves as they move between steps; payment and ID numbers are never kept.</p></div>
  <div class="row"><?php foreach ($tabs as $k => $l): ?><a class="btn btn-sm <?= $status === $k ? 'btn-dark' : 'btn-ghost' ?>" href="?status=<?= $k ?>"><?= $l ?><?= isset($counts[$k]) ? ' (' . $counts[$k] . ')' : '' ?></a><?php endforeach; ?></div></div>
<?php if (!$rows): ?><div class="empty">No forms in this view. When someone stops part-way through a form, it shows up here with whatever contact details they entered.</div><?php else: ?>
<div class="table-wrap"><table><thead><tr><th>Visitor</th><th>Form</th><th>Progress</th><th>Last activity</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $d): $data = jdec((string)$d['data']); $pct = $d['steps'] > 1 ? (int)round($d['step'] / ($d['steps'] - 1) * 100) : 0; ?>
<tr><td><b><?= e($d['name'] ?: ($d['email'] ?: 'Anonymous visitor')) ?></b><div class="small muted"><?= e($d['email'] ?: 'No email yet') ?><?= $d['phone'] ? ' · ' . e($d['phone']) : '' ?></div></td>
  <td><?= e(label($d['form'])) ?><div class="small muted"><?= e($d['path']) ?></div></td>
  <td style="min-width:140px"><div class="small">Step <?= (int)$d['step'] + 1 ?> of <?= max(1, (int)$d['steps']) ?></div><div class="bar"><i style="width:<?= $pct ?>%"></i></div></td>
  <td class="small"><?= e(substr($d['updated_at'], 0, 16)) ?></td>
  <td><span class="badge <?= $d['status'] === 'open' ? 'b-blue' : ($d['status'] === 'completed' ? 'b-green' : '') ?>"><?= e(label($d['status'])) ?></span></td>
  <td><div class="row" style="gap:6px;flex-wrap:nowrap">
    <?php if ($d['status'] === 'open' && $d['email']): ?><form method="post" action="/admin/drafts/<?= (int)$d['id'] ?>"><?= App\Csrf::field() ?><input type="hidden" name="action" value="lead"><button class="btn btn-primary btn-sm">Make lead</button></form><a class="btn btn-ghost btn-sm" href="mailto:<?= e($d['email']) ?>?subject=<?= rawurlencode('Need a hand finishing your ' . label($d['form']) . '?') ?>">Email</a><?php endif; ?>
    <?php if ($d['status'] === 'open'): ?><form method="post" action="/admin/drafts/<?= (int)$d['id'] ?>"><?= App\Csrf::field() ?><input type="hidden" name="action" value="dismiss"><button class="btn btn-ghost btn-sm">Dismiss</button></form>
    <?php elseif ($d['status'] === 'dismissed'): ?><form method="post" action="/admin/drafts/<?= (int)$d['id'] ?>"><?= App\Csrf::field() ?><input type="hidden" name="action" value="reopen"><button class="btn btn-ghost btn-sm">Reopen</button></form><?php endif; ?>
    <?php if ($d['lead_id']): ?><a class="btn btn-ghost btn-sm" href="/admin/leads">View lead</a><?php endif; ?>
  </div>
  <?php if ($data): ?><details class="small" style="margin-top:6px"><summary>What they entered</summary><pre class="pre"><?= e(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></details><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
