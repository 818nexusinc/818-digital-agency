<?php
$score = App\SecurityCheck::score($results);
$n = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0]; foreach ($results as $x) $n[$x['s']]++;
$col = $score >= 85 ? '#047857' : ($score >= 65 ? '#b45309' : '#b42318');
$groups = []; foreach ($results as $x) $groups[$x['g']][] = $x;
$order = ['fail' => 0, 'warn' => 1, 'info' => 2, 'pass' => 3];
$sym = ['pass' => '✓', 'warn' => '!', 'fail' => '×', 'info' => 'i'];
?>
<div class="topbar"><div><h1>System security check</h1><p class="muted" style="margin:4px 0 0">A read-only audit of settings, team accounts, keys, AI agents, recent activity and what the live website exposes. Secret values are never shown, only whether they are set.</p></div>
  <form method="post" class="row"><?= App\Csrf::field() ?><button class="btn btn-primary">Run check again</button></form></div>
<section class="sc-score">
  <div class="sc-ring" style="--p:<?= $score ?>;--c:<?= $col ?>"><b><?= $score ?></b></div>
  <div class="sc-sum"><h2><?= $n['fail'] ? $n['fail'] . ' issue' . ($n['fail'] === 1 ? '' : 's') . ' to fix before launch' : ($n['warn'] ? 'No critical issues. ' . $n['warn'] . ' to tidy up.' : 'Everything checked is in good shape.') ?></h2>
    <p>Checked <?= e(substr($ran_at, 0, 16)) ?> UTC · <?= count($results) ?> checks. Fix red items first; they are ordered by importance within each group.</p></div>
  <div class="sc-counts"><span class="s-fail"><?= $n['fail'] ?> fail</span><span class="s-warn"><?= $n['warn'] ?> warning</span><span class="s-pass"><?= $n['pass'] ?> pass</span></div>
</section>
<?php foreach ($groups as $g => $items): usort($items, fn($a, $b) => [$order[$a['s']], $a['sev'] === 'high' ? 0 : ($a['sev'] === 'medium' ? 1 : 2)] <=> [$order[$b['s']], $b['sev'] === 'high' ? 0 : ($b['sev'] === 'medium' ? 1 : 2)]); ?>
<div class="sc-grp"><h2><?= e($g) ?></h2><div class="sc-list">
<?php foreach ($items as $x): ?><div class="sc-item <?= $x['s'] ?>"><i aria-hidden="true"><?= $sym[$x['s']] ?></i>
  <div><b><?= e($x['t']) ?></b><p><?= e($x['found']) ?></p><?php if (in_array($x['s'], ['fail', 'warn'], true) && $x['fix']): ?><p class="fix"><b>Fix:</b> <?= e($x['fix']) ?></p><?php endif; ?></div>
  <span class="sev"><?= e(ucfirst($x['sev'])) ?> priority</span></div><?php endforeach; ?>
</div></div>
<?php endforeach; ?>
