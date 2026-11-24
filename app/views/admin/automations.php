<?php
$ago = function (?string $t): string { if (!$t) return 'never'; $s = time() - strtotime($t . ' UTC'); return $s < 90 ? 'just now' : ($s < 3600 ? round($s / 60) . ' min ago' : ($s < 172800 ? round($s / 3600) . ' h ago' : round($s / 86400) . ' days ago')); };
$every = fn(int $s) => $s === 0 ? 'Every run' : ($s < 3600 ? 'Every ' . ($s / 60) . ' min' : ($s < 86400 ? 'Every ' . ($s / 3600) . ' h' : ($s === 86400 ? 'Daily' : ($s === 604800 ? 'Weekly' : 'Every ' . round($s / 86400) . ' days'))));
$cronAge = $cron ? time() - strtotime($cron . ' UTC') : null;
$groups = []; foreach (App\Automations::JOBS as $k => $j) $groups[$j[3]][$k] = $j;
?>
<div class="topbar"><div><h1>Automations</h1><p class="muted" style="margin:4px 0 0">Everything the business does on its own: following up, writing, checking and backing up. Switch any of them off, or run one now.</p></div></div>

<div class="au-head <?= $cronAge !== null && $cronAge < 900 ? 'ok' : 'bad' ?>">
  <div><b><?= $cronAge === null ? 'The background job has never run' : ($cronAge < 900 ? 'Running normally' : 'The background job has stopped') ?></b>
    <p><?= $cronAge === null ? 'Nothing below runs until it is scheduled.' : 'Last run ' . e($ago($cron)) . '. It should run every 5 minutes.' ?>
    <?php if ($cronAge === null || $cronAge >= 900): ?> In My20i, open your package → Web Tools → Scheduled Tasks and add, every 5 minutes: <code><?= e($cmd) ?></code><?php endif; ?></p></div>
  <form method="post" action="/admin/automations/run"><?= App\Csrf::field() ?><input type="hidden" name="key" value="all"><button class="btn btn-ghost btn-sm">Run everything due now</button></form>
</div>
<?php if (!$ai): ?><p class="au-note">No AI provider is connected, so agents answer from your catalog and knowledge base and writing tasks use templates. Connect Anthropic in Integrations for full AI work.</p><?php endif; ?>

<?php foreach ($groups as $g => $jobs): ?>
<h2 class="au-g"><?= e($g) ?></h2>
<div class="au-list">
<?php foreach ($jobs as $k => [$name, $desc, $sec, , $switchable]): $l = App\Automations::last($k); $on = App\Automations::enabled($k); ?>
  <div class="au-row<?= $on ? '' : ' off' ?>">
    <div class="au-main"><b><?= e($name) ?></b><p><?= e($desc) ?></p>
      <p class="au-last"><?php if ($l): ?><span class="<?= $l['ok'] ? 'okt' : 'badt' ?>"><?= $l['ok'] ? 'Last ran' : 'Failed' ?> <?= e($ago($l['at'])) ?></span> · <?= e($l['note']) ?><?php else: ?>Not run yet<?php endif; ?></p></div>
    <div class="au-when"><?= e($every($sec)) ?></div>
    <div class="au-act">
      <form method="post" action="/admin/automations/run"><?= App\Csrf::field() ?><input type="hidden" name="key" value="<?= e($k) ?>"><button class="btn btn-ghost btn-sm"<?= $on ? '' : ' disabled' ?>>Run now</button></form>
      <?php if ($switchable): ?><form method="post" action="/admin/automations/toggle"><?= App\Csrf::field() ?><input type="hidden" name="key" value="<?= e($k) ?>"><button class="sw<?= $on ? ' on' : '' ?>" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= e(($on ? 'Turn off ' : 'Turn on ') . $name) ?>"><i></i></button></form>
      <?php else: ?><span class="au-core">Always on</span><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>
