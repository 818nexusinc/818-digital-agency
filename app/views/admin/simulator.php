<?php $pass = $results ? count(array_filter($results, fn($x) => $x['ok'])) : 0; ?>
<div class="topbar"><div><h1>Agent simulator</h1><p class="muted" style="margin:4px 0 0">Runs every AI agent through a scripted conversation that calls each of its tools with sample data. Nothing is saved, no email is sent and no filing is submitted: every change is rolled back when the run ends.</p></div>
  <form method="post" class="row"><?= App\Csrf::field() ?><button class="btn btn-primary">Run all <?= count($agents) ?> agents</button></form></div>
<?php if ($results): ?><div class="alert <?= $pass === count($results) ? 'alert-ok' : 'alert-warn' ?>"><b><?= $pass ?> of <?= count($results) ?> passed.</b> Run at <?= e(substr((string)$ran_at, 0, 16)) ?> UTC.</div><?php endif; ?>
<div class="table-wrap"><table><thead><tr><th>Agent</th><th>Result</th><th>Tools checked</th><th>Time</th><th></th></tr></thead><tbody>
<?php $by = []; foreach (($results ?? []) as $r) $by[$r['agent']] = $r;
foreach ($agents as $slug => $def): $r = $by[$slug] ?? null; ?>
<tr><td><b><?= e($def[0]) ?></b><div class="small muted"><?= e($slug) ?></div></td>
  <td><?php if (!$r): ?><span class="badge">not run</span><?php elseif ($r['ok']): ?><span class="badge b-green">passed</span><?php else: ?><span class="badge b-red">failed</span><div class="small" style="color:#b42318"><?= e((string)$r['error']) ?></div><?php endif; ?></td>
  <td class="small"><?php if ($r): foreach ($r['tools'] as $tn => $st): ?><span class="badge <?= $st === 'ran' ? 'b-green' : (str_starts_with($st, 'error') ? 'b-red' : '') ?>" title="<?= e($st) ?>"><?= e($tn) ?></span> <?php endforeach; endif; ?></td>
  <td class="small"><?= $r ? (int)$r['ms'] . ' ms' : '' ?></td>
  <td><form method="post"><?= App\Csrf::field() ?><input type="hidden" name="agent" value="<?= e($slug) ?>"><button class="btn btn-ghost btn-sm">Run</button></form></td></tr>
<?php endforeach; ?></tbody></table></div>
<p class="small muted">Green tools ran without errors. Grey tools were held for approval by design (for example sending money or submitting a filing). A tool that answers "not found" for sample data still passes; only crashes fail.</p>
