<?php
$by = []; foreach (($results ?? []) as $r) $by[$r['agent']] = $r;
$pass = $results ? count(array_filter($results, fn($x) => $x['ok'])) : 0;
$ago = fn(?string $at) => $at ? substr($at, 0, 16) . ' UTC' : '';
?>
<div class="db sim">
<div class="db-head"><div><h1>Agent simulator</h1><p class="db-bc">Each agent handles a realistic request with sample data. You get a step-by-step report of what it did and what the customer would get. Nothing real happens: emails are held back and every change is undone.</p></div>
  <div class="db-acts"><form method="post"><?= App\Csrf::field() ?><button class="btn btn-primary btn-sm">Run all <?= count($agents) ?> agents</button></form></div></div>
<?php if ($results): ?><div class="ct-warn <?= $pass === count($results) ? 'ok' : '' ?>"><p><b><?= $pass ?> of <?= count($results) ?> passed</b> · run <?= e($ago($ran_at)) ?>. Open any agent below to read its report.</p></div><?php endif; ?>

<div class="sim-list">
<?php foreach ($agents as $slug => $def):
  $rep = $last[$slug] ?? null; $r = $by[$slug] ?? ($rep ? array_diff_key($rep, ['report' => 1]) : null); $p = $rep['report'] ?? null;
  $open = ($focus === $slug) || ($r && !$r['ok']); ?>
  <details class="db-card sim-a <?= !$r ? 'st-none' : ($r['ok'] ? (!empty($r['notes']) ? 'st-warn' : 'st-ok') : 'st-bad') ?>" id="sim-<?= e($slug) ?>"<?= $open ? ' open' : '' ?>>
    <summary>
      <span class="sim-dot" aria-hidden="true"></span>
      <span class="sim-n"><b><?= e(preg_replace('/^AI /', '', $def[0])) ?></b><small><?= $p ? e(str_limit($p['summary'], 140)) : 'Not run yet' ?></small></span>
      <span class="sim-st"><?= !$r ? 'Not run' : ($r['ok'] ? (!empty($r['notes']) ? 'Passed with notes' : 'Passed') : 'Needs attention') ?></span>
      <span class="sim-t"><?= $r ? number_format($r['ms'] / 1000, 1) . ' s' : '' ?><?= isset($rep['at']) ? '<br>' . e($ago($rep['at'])) : '' ?></span>
      <form method="post" class="sim-run"><?= App\Csrf::field() ?><input type="hidden" name="agent" value="<?= e($slug) ?>"><button class="btn btn-sm">Run</button></form>
    </summary>
    <?php if ($p): ?>
    <div class="sim-rep">
      <p class="sim-ask"><span>Request from <?= e($p['who']) ?></span>“<?= e($p['ask']) ?>”</p>
      <div class="sim-cols">
        <section><h3>What the agent did</h3>
          <?php if (!$p['steps']): ?><p class="muted small">No tools were used.</p><?php endif; ?>
          <ol class="sim-steps"><?php foreach ($p['steps'] as $s): ?><li class="<?= !empty($s['skipped']) ? 'skip' : ($s['ok'] ? 'ok' : 'bad') ?>"><span><?= e($s['text']) ?></span><?php if ($s['ms'] > 0): ?><small><?= (int)$s['ms'] ?> ms</small><?php endif; ?></li><?php endforeach; ?></ol>
          <?php if (trim((string)$p['final']) !== '' && $p['final'] !== 'Simulation complete.'): ?><p class="small"><b>Final reply:</b> <?= e($p['final']) ?></p><?php endif; ?>
        </section>
        <section>
          <h3>Result for the customer</h3>
          <?php if ($p['outcome']): ?><ul class="sim-out"><?php foreach ($p['outcome'] as $o): ?><li>The customer <?= e($o) ?>.</li><?php endforeach; ?></ul><?php else: ?><p class="small muted">Internal work: nothing customer-facing in this run.</p><?php endif; ?>
          <h3>What it created</h3>
          <?php if ($p['created']): ?><p class="sim-chips"><?php foreach ($p['created'] as $c): ?><span><?= e($c) ?></span><?php endforeach; ?></p><?php else: ?><p class="small muted">No records.</p><?php endif; ?>
          <h3>Emails it would send</h3>
          <?php if ($p['emails']): ?><ul class="sim-mail"><?php foreach ($p['emails'] as $m): ?><li><b><?= e($m['subject']) ?></b><small>to <?= e($m['to']) ?></small></li><?php endforeach; ?></ul><?php else: ?><p class="small muted">None.</p><?php endif; ?>
          <h3>Checks</h3>
          <ul class="sim-checks"><?php foreach ($p['checks'] as [$l, $ok]): ?><li class="<?= $ok ? 'ok' : 'bad' ?>"><?= e($l) ?></li><?php endforeach; ?></ul>
        </section>
      </div>
    </div>
    <?php elseif ($r && !empty($r['error'])): ?><div class="sim-rep"><p class="small bad"><?= e($r['error']) ?></p></div><?php endif; ?>
  </details>
<?php endforeach; ?>
</div>
</div>
