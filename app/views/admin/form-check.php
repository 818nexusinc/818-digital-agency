<?php
use App\{FormCheck, Csrf, Filings};
$tabs = ['open' => 'To review', 'done' => 'Done', 'dismissed' => 'Dismissed', 'all' => 'All'];
$sev = ['high' => 'High', 'medium' => 'Medium', 'low' => 'Low'];
?>
<div class="db fc">
<div class="db-head"><div><h1>Registry form check</h1><p class="db-bc">The Filing Agent compares our order forms with what each government registry asks for, and suggests changes so every filing has what it needs</p></div>
  <div class="db-acts"><a class="btn btn-ghost btn-sm" href="/admin/agents/filing">Filing Agent</a><a class="btn btn-ghost btn-sm" href="/admin/automations">Monthly schedule</a></div></div>

<?php if (!$aiOn): ?><div class="ct-warn"><p><b>The check needs the Anthropic connection</b> so the agent can read the registries' websites. <a href="/admin/integrations">Connect Anthropic</a></p></div><?php endif; ?>

<section class="db-card fc-run">
  <form method="post" action="/admin/form-check" class="fc-form"><?= Csrf::field() ?><input type="hidden" name="action" value="run">
    <label>Check<select name="slug"><option value="">Every registry form</option><?php foreach ($services as $s): ?><option value="<?= e($s['slug']) ?>"><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
    <label class="fc-note">Note for the agent (optional)<input type="text" name="note" maxlength="500" placeholder="e.g. Nova Scotia changed its forms last month"></label>
    <button class="btn btn-primary">Run the check now</button></form>
  <?php if ($tasks): ?><ul class="pz-tasks"><?php foreach ($tasks as $t): ?><li><a href="/admin/tasks/<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a> <span class="pill-s s-<?= e($t['status']) ?>"><?= e(label($t['status'])) ?></span> <small><?= e(substr((string)($t['finished_at'] ?: $t['created_at']), 0, 16)) ?> UTC</small><?php if ($t['progress_note']): ?><br><small><?= e(str_limit((string)$t['progress_note'], 200)) ?></small><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
</section>

<nav class="wk-types fc-tabs" aria-label="Findings"><?php foreach ($tabs as $k => $n): ?><a href="/admin/form-check?status=<?= $k ?>"<?= $st === $k ? ' aria-current="true"' : '' ?>><?= e($n) ?> <span><?= $k === 'all' ? array_sum($counts) : (int)($counts[$k] ?? 0) ?></span></a><?php endforeach; ?></nav>

<?php if (!$rows): ?><div class="db-card"><div class="ct-empty"><b><?= $st === 'open' ? 'Nothing to review.' : 'Nothing here.' ?></b><p>Findings from the Filing Agent appear here, each with the official page it is based on.</p></div></div><?php endif; ?>
<div class="fc-list">
<?php foreach ($rows as $f): ?>
  <article class="db-card fc-item sev-<?= e($f['severity']) ?>">
    <header><span class="fc-sev"><?= e($sev[$f['severity']] ?? $f['severity']) ?></span><b><?= e($f['name'] ?: $f['service_slug']) ?></b><span class="fc-j"><?= e($f['jurisdiction'] ? (Filings::JURISDICTIONS[$f['jurisdiction']][0] ?? $f['jurisdiction']) : 'All jurisdictions') ?></span><span class="fc-k"><?= e(FormCheck::ISSUES[$f['issue']] ?? $f['issue']) ?></span></header>
    <?php if ($f['field']): ?><p class="fc-field">Field or step: <b><?= e($f['field']) ?></b></p><?php endif; ?>
    <div class="fc-cols"><div><h4>What the registry requires</h4><p><?= nl2br(e((string)$f['detail'])) ?></p></div><div><h4>Suggested change to our form</h4><p><?= nl2br(e((string)$f['suggestion'])) ?></p></div></div>
    <footer><a href="<?= e((string)$f['source_url']) ?>" target="_blank" rel="noopener noreferrer">Official source</a><small>Found <?= e(substr((string)$f['created_at'], 0, 10)) ?> by the <?= e($f['found_by'] === 'filing' ? 'Filing Agent' : $f['found_by']) ?><?= $f['decided_by'] ? ' · ' . e(label($f['status'])) . ' by ' . e($f['decided_by']) : '' ?></small>
      <span class="fc-act"><?php foreach ($f['status'] === 'open' ? ['done' => 'Mark done', 'dismissed' => 'Dismiss'] : ['open' => 'Reopen'] as $a => $l): ?><form method="post" action="/admin/form-check"><?= Csrf::field() ?><input type="hidden" name="action" value="<?= $a ?>"><input type="hidden" name="id" value="<?= (int)$f['id'] ?>"><input type="hidden" name="st" value="<?= e($st) ?>"><button class="btn btn-sm<?= $a === 'done' ? ' btn-primary' : '' ?>"><?= $l ?></button></form><?php endforeach; ?></span></footer>
  </article>
<?php endforeach; ?>
</div>
</div>
