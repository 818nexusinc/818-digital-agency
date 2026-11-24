<?php
/* Overview: the AI team's daily brief — what each agent did, what is coming due, and what needs the owner. */
$tz = new DateTimeZone(App\Settings::get('company.tz', 'America/Moncton') ?: 'America/Moncton');
$loc = fn(string $at) => (new DateTime($at . ' UTC'))->setTimezone($tz);
$todayKey = (new DateTime('now', $tz))->format('Y-m-d');
$tomorrowKey = (new DateTime('tomorrow', $tz))->format('Y-m-d');
$ago = function (?string $at): string {
    if (!$at) return '';
    $s = max(0, time() - strtotime($at . ' UTC'));
    return $s < 3600 ? max(1, (int)round($s / 60)) . ' min ago' : ($s < 86400 ? (int)round($s / 3600) . ' h ago' : (int)round($s / 86400) . ' d ago');
};
$cronOk = $cron && strtotime($cron . ' UTC') >= time() - 900;
$aiOn = $providers['anthropic'] || $providers['openai'];
$names = []; foreach ($team as $a) $names[$a['slug']] = $a['name'];
$p = $pulse;
// Group due items: overdue, today, tomorrow, then each later day.
$groups = [];
foreach ($due as $it) {
    $d = $loc($it[0]); $k = $it[6] ? 'overdue' : $d->format('Y-m-d');
    $groups[$k][] = $it + [7 => $d];
}
uksort($groups, fn($a, $b) => $a === 'overdue' ? -1 : ($b === 'overdue' ? 1 : strcmp($a, $b)));
$dayName = fn(string $k) => $k === 'overdue' ? 'Overdue' : ($k === $todayKey ? 'Today' : ($k === $tomorrowKey ? 'Tomorrow' : (new DateTime($k, $tz))->format('l j F')));
$active = count(array_filter($team, fn($a) => $a['state'] === 'busy' || $a['state'] === 'attn'));
$horizonLabel = [1 => 'in the next 24 hours', 3 => 'in the next 3 days', 7 => 'in the next 7 days'][$horizon];
?>
<div class="db ov">
<div class="db-head">
  <div><h1>Overview</h1><p class="db-bc">Daily brief from your AI team · <?= e((new DateTime('now', $tz))->format('l j F')) ?></p></div>
  <div class="db-acts"><a class="btn btn-ghost btn-sm" href="/admin/tasks">All tasks</a><a class="btn btn-ghost btn-sm" href="/admin/agents">Agents</a><a class="btn btn-primary btn-sm" href="/admin/approvals">Action center<?= $p['waiting'] ? ' · ' . $p['waiting'] : '' ?></a></div>
</div>

<?php if (!$cronOk || $demo || !$aiOn): ?><div class="ct-warn">
  <?php if (!$cronOk): ?><p><b>The background job isn't running,</b> so agents only work when you open a page. <a href="/admin/automations">Set it up</a></p><?php endif; ?>
  <?php if (!$aiOn): ?><p><b>No AI provider is connected.</b> Agents run in basic mode with templates. <a href="/admin/integrations">Connect Anthropic</a></p><?php endif; ?>
  <?php if ($demo): ?><p><b><?= (int)$demo ?> demo records are loaded.</b> <a href="/admin/demo">Delete them before taking real orders</a></p><?php endif; ?>
</div><?php endif; ?>

<section class="ov-sum db-card" aria-label="Team summary">
  <p class="ov-lead">In the last 24 hours your AI team finished <b><?= $p['done'] ?> task<?= $p['done'] === 1 ? '' : 's' ?></b><?= $p['failed'] ? ', <b class="bad">' . $p['failed'] . ' failed</b>' : '' ?>
    and handled <b><?= $p['runs'] ?> request<?= $p['runs'] === 1 ? '' : 's' ?></b>. <?= $p['open'] ?> task<?= $p['open'] === 1 ? ' is' : 's are' ?> open<?= $p['waiting'] ? ' and <b>' . $p['waiting'] . ' need' . ($p['waiting'] === 1 ? 's' : '') . ' your approval</b>' : '' ?>.</p>
  <dl class="ov-k">
    <div><dt>Agents working</dt><dd><?= $active ?><small> of <?= $p['agents_on'] ?> on</small></dd></div>
    <div><dt>Finished, 24 h</dt><dd><?= $p['done'] ?></dd></div>
    <div><dt>Open tasks</dt><dd><?= $p['open'] ?></dd></div>
    <div><dt>Waiting for you</dt><dd class="<?= $p['waiting'] ? 'warn' : '' ?>"><?= $p['waiting'] ?></dd></div>
    <div><dt>AI spend today</dt><dd>US$<?= number_format($p['spend_cents'] / 100, 2) ?></dd></div>
  </dl>
</section>

<div class="ov-grid">
  <section class="db-card ov-due" aria-labelledby="due-h">
    <div class="db-ch">
      <div><h2 id="due-h">Coming due</h2><p class="db-sub">Tasks, milestones, follow-ups, registry checks and posts due <?= e($horizonLabel) ?>, plus anything overdue.</p></div>
      <nav class="seg" aria-label="How far ahead"><?php foreach ([1 => '1 day', 3 => '3 days', 7 => '7 days'] as $n => $l): ?><a href="/admin?days=<?= $n ?>"<?= $horizon === $n ? ' aria-current="true"' : '' ?>><?= $l ?></a><?php endforeach; ?></nav>
    </div>
    <?php if (!$due): ?><div class="ct-empty"><b>Nothing due <?= e($horizonLabel) ?>.</b><p>New tasks, deadlines and follow-ups appear here as soon as they are scheduled.</p></div><?php endif; ?>
    <?php foreach ($groups as $k => $items): ?>
      <h3 class="ov-day<?= $k === 'overdue' ? ' late' : '' ?>"><?= e($dayName($k)) ?> <span><?= count($items) ?></span></h3>
      <ul class="ov-list">
      <?php foreach ($items as $n => [$at, $agent, $kind, $title, $detail, $href, $late, $d]): ?>
        <?php if ($n === 6 && count($items) > 8): ?></ul><details class="ov-more"><summary>Show <?= count($items) - 6 ?> more</summary><ul class="ov-list"><?php endif; ?>
        <li><a href="<?= e($href) ?>">
          <time datetime="<?= e(str_replace(' ', 'T', $at)) ?>Z"><?= e($late ? $d->format('M j') : $d->format('H:i')) ?></time>
          <span class="ov-kind"><?= e($kind) ?></span>
          <span class="ov-what"><b><?= e($title) ?></b><small><?= e($detail) ?></small></span>
          <span class="ov-who"><?= e($names[$agent] ?? label($agent)) ?></span>
        </a></li>
      <?php endforeach; ?>
      </ul><?php if (count($items) > 8): ?></details><?php endif; ?>
    <?php endforeach; ?>
  </section>

  <aside class="ov-side">
    <section class="ct-box"><div class="ct-bh"><h2>Ask the AI team</h2></div>
      <div id="owner-log" class="chat-log ct-log"></div>
      <div class="chips" id="owner-chips"><button class="chip" type="button">What did the agents do today?</button><button class="chip" type="button">What is overdue?</button></div>
      <form id="owner-form" class="ct-ask"><label class="sr" for="owner-q">Question</label><input id="owner-q" type="text" placeholder="Ask about tasks, customers or revenue"><button class="btn btn-primary btn-sm">Ask</button></form>
    </section>
    <section class="ct-box"><div class="ct-bh"><h2>Running on its own</h2><a href="/admin/automations">All automations</a></div>
      <dl class="ct-sys">
        <dt>Background job</dt><dd class="<?= $cronOk ? 'ok' : 'bad' ?>"><?= $cron ? ($cronOk ? 'Ran ' . e($ago($cron)) : 'Stopped, last ' . e($ago($cron))) : 'Not set up' ?></dd>
        <?php foreach ($autos as $au): ?><dt><?= e($au['name']) ?></dt><dd class="<?= !$au['on'] ? 'mut' : ($au['last'] && !$au['last']['ok'] ? 'bad' : '') ?>"><?= !$au['on'] ? 'Off' : ($au['last'] ? ($au['last']['ok'] ? e($ago($au['last']['at'])) : 'Failed') : 'Waiting') ?></dd><?php endforeach; ?>
        <dt>Spam blocked today</dt><dd><?= (int)$spamToday ?></dd>
      </dl>
    </section>
  </aside>
</div>

<section aria-labelledby="briefs-h">
  <div class="db-row"><h2 class="db-sec" id="briefs-h">Daily briefs</h2><a class="db-more" href="/admin/agents">Manage agents</a></div>
  <div class="ov-briefs">
  <?php foreach ($team as $a): ?>
    <article class="ov-agent st-<?= $a['state'] ?>">
      <header><span class="ov-dot" aria-hidden="true"></span><h3><a href="/admin/agents/<?= e($a['slug']) ?>"><?= e($a['name']) ?></a></h3>
        <span class="ov-state"><?= ['attn' => 'Needs attention', 'busy' => 'Working', 'idle' => 'Idle', 'off' => 'Off'][$a['state']] ?></span></header>
      <p class="ov-line"><?= e($a['line']) ?></p>
      <?php if ($a['recent']): ?><ul class="ov-rec"><?php foreach ($a['recent'] as $r): ?><li><?= e(str_limit((string)$r['summary'], 140)) ?> <time><?= e($ago($r['created_at'])) ?></time></li><?php endforeach; ?></ul><?php endif; ?>
      <?php if ($a['next']): ?><p class="ov-next"><span>Next</span> <a href="/admin/tasks/<?= (int)$a['next']['id'] ?>"><?= e(str_limit($a['next']['title'], 70)) ?></a><?= $a['next']['at'] && $a['next']['at'] < now() ? ' <em>overdue</em>' : '' ?></p><?php endif; ?>
      <footer><span><b><?= $a['done'] ?></b> done</span><span><b><?= $a['open'] ?></b> open</span><?php if ($a['failed']): ?><span class="bad"><b><?= $a['failed'] ?></b> failed</span><?php endif; ?><span>US$<?= number_format($a['cents'] / 100, 2) ?></span></footer>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="ct-box ct-inbox" aria-labelledby="inbox-h">
  <div class="ct-bh"><h2 id="inbox-h">Needs you</h2><span><?= count($inbox) ? count($inbox) . ' item' . (count($inbox) === 1 ? '' : 's') : '' ?></span></div>
  <?php if (!$inbox): ?><div class="ct-empty"><b>You're all caught up.</b><p>New approvals, leads, support requests and unfinished orders will show up here.</p></div><?php endif; ?>
  <ul>
  <?php foreach ($inbox as [$type, $title, $sub, $at, $href, $act]): ?>
    <li><a href="<?= e($href) ?>"><span class="ct-type t-<?= e(strtolower(str_replace(' ', '-', $type))) ?>"><?= e($type) ?></span>
      <span class="ct-what"><b><?= e($title) ?></b><small><?= e($sub) ?></small></span>
      <time datetime="<?= e(str_replace(' ', 'T', (string)$at)) ?>Z"><?= e($ago($at)) ?></time><span class="ct-act"><?= e($act) ?></span></a></li>
  <?php endforeach; ?>
  </ul>
</section>
</div>
