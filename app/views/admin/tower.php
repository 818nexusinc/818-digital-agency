<?php
$badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; };
$t = $m['tasks'];
$h = (int)gmdate('G') - 4; $h = ($h + 24) % 24; // Atlantic time, close enough for a greeting
$hi = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
$first = trim(explode(' ', (string)($me['name'] ?? ''))[0] ?? '') ?: 'there';
$cronOk = $cron && strtotime($cron . ' UTC') >= time() - 600;
$aiOn = $providers['anthropic'] || $providers['openai'];
$enabled = count(array_filter($agents, fn($a) => $a['status'] === 'enabled'));
$simPass = $sim ? count(array_filter($sim, fn($x) => $x['ok'])) : null;
$todo = [
    ['/admin/approvals', 'inbox', (int)$m['approvals_pending'], 'Approvals waiting', 'Agents paused until you decide', 'amber'],
    ['/admin/drafts', 'text', $draftsOpen, 'Unfinished forms', 'Visitors who stopped part-way', 'violet'],
    ['/admin/leads', 'search', $newLeads, 'New leads this week', $chatLeads . ' from the website chat (30 days)', 'blue'],
    ['/admin/tickets', 'help', $openTickets, 'Open tickets', 'Customer questions to answer', 'teal'],
    ['/admin/tasks?status=failed', 'check', count($failed), 'Failed AI tasks', 'Retry or reassign', 'red'],
];
$need = array_sum(array_column($todo, 2));
include_once APP_ROOT . '/views/partials/icons.php';
?>
<div class="tw">
<header class="tw-hello">
  <div><p class="tw-date"><?= e(gmdate('l, F j')) ?></p><h1><?= e($hi) ?>, <?= e($first) ?>.</h1>
    <p class="tw-sum"><?= $need ? e($need . ($need === 1 ? ' thing needs' : ' things need') . ' your attention today.') : 'Everything is handled. Nothing needs you right now.' ?></p></div>
  <div class="tw-quick">
    <a class="btn btn-primary btn-sm" href="/admin/approvals">Review approvals</a>
    <a class="btn btn-ghost btn-sm" href="/admin/blog">Write this week's article</a>
    <a class="btn btn-ghost btn-sm" href="/admin/simulator">Test the AI agents</a>
  </div>
</header>

<?php if (!$cronOk || !$aiOn || !$providers['stripe'] || $demo): ?><div class="tw-status">
  <?php if (!$cronOk): ?><a href="/admin/system" class="tw-pill warn"><i></i>Background worker <?= $cron ? 'last ran ' . e(substr($cron, 0, 16)) . ' UTC' : 'has not run yet' ?></a><?php endif; ?>
  <?php if (!$aiOn): ?><a href="/admin/integrations#anthropic" class="tw-pill info"><i></i>No AI provider connected: agents use basic mode</a><?php endif; ?>
  <?php if (!$providers['stripe']): ?><a href="/admin/integrations#stripe" class="tw-pill info"><i></i>Stripe not connected: checkout sends invoice requests</a><?php endif; ?>
  <?php if ($demo): ?><a href="/admin/demo" class="tw-pill demo"><i></i><?= (int)$demo ?> demo records loaded: delete before launch</a><?php endif; ?>
</div><?php endif; ?>

<section class="tw-todo" aria-label="Needs you today">
  <?php foreach ($todo as [$href, $icon, $n, $label, $sub, $tone]): ?>
  <a class="tw-card t-<?= $tone ?><?= $n ? ' has' : '' ?>" href="<?= e($href) ?>"><span class="tw-ic"><?= nx_icon($icon) ?></span><b><?= (int)$n ?></b><span class="tw-l"><?= e($label) ?></span><small><?= e($sub) ?></small></a>
  <?php endforeach; ?>
</section>

<section class="tw-kpis" aria-label="Last 30 days">
  <div><span>Revenue today</span><b><?= e($m['revenue_today']) ?></b></div>
  <div><span>Revenue, 30 days</span><b><?= e($m['revenue']) ?></b></div>
  <div><span>Recurring (MRR)</span><b><?= e($m['mrr']) ?></b></div>
  <div><span>Paid orders</span><b><?= (int)$m['orders'] ?></b></div>
  <div><span>Lead conversion</span><b><?= e($m['conversion_rate']) ?></b></div>
  <div><span>Active projects</span><b><?= (int)$m['projects_active'] ?><?php if ($m['projects_overdue']): ?> <em><?= (int)$m['projects_overdue'] ?> late</em><?php endif; ?></b></div>
</section>

<div class="tw-grid">
  <div class="tw-col">
    <section class="panel tw-p"><div class="tw-ph"><h2>Follow up next</h2><a href="/admin/leads">All leads</a></div>
      <?php if (!$leads && !$drafts): ?><div class="empty">No open leads or unfinished forms. New ones appear here as soon as they arrive.</div><?php endif; ?>
      <?php foreach ($leads as $l): ?><a class="tw-row" href="/admin/customers/<?= (int)$l['customer_id'] ?>">
        <span class="tw-av"><?= e(mb_strtoupper(mb_substr($l['name'] ?: $l['email'], 0, 1))) ?></span>
        <span class="tw-who"><b><?= e($l['name'] ?: $l['email']) ?></b><small><?= e($l['business_name'] ?: label((string)$l['source'])) ?> · <?= e(str_limit((string)$l['summary'], 70)) ?></small></span>
        <span class="badge <?= $l['score'] >= 60 ? 'b-green' : 'b-blue' ?>"><?= (int)$l['score'] ?></span></a><?php endforeach; ?>
      <?php if ($drafts): ?><h3 class="tw-sub">Stopped part-way through a form</h3>
      <?php foreach ($drafts as $d): ?><a class="tw-row" href="/admin/drafts">
        <span class="tw-av v"><?= e(mb_strtoupper(mb_substr($d['name'] ?: $d['email'], 0, 1))) ?></span>
        <span class="tw-who"><b><?= e($d['name'] ?: $d['email']) ?></b><small><?= e(label($d['form'])) ?> · step <?= (int)$d['step'] + 1 ?> of <?= max(1, (int)$d['steps']) ?> · <?= e(substr($d['updated_at'], 0, 10)) ?></small></span>
        <span class="tw-go">Follow up</span></a><?php endforeach; endif; ?>
      <p class="tw-foot">Pipeline value <?= e($m['pipeline_value']) ?> · <?= (int)$m['leads_overdue'] ?> follow-up<?= $m['leads_overdue'] == 1 ? '' : 's' ?> overdue</p>
    </section>

    <section class="panel tw-p"><div class="tw-ph"><h2>Approvals</h2><a href="/admin/approvals">Action center</a></div>
      <?php if (!$approvals): ?><div class="empty">Nothing waiting for a decision.</div><?php endif; ?>
      <?php foreach ($approvals as $a): ?><a class="tw-row" href="/admin/approvals"><span class="tw-who"><b><?= e($a['title']) ?></b><small><?= e(label($a['kind'])) ?> · from <?= e($a['requested_by']) ?> · <?= e(substr($a['created_at'], 0, 16)) ?></small></span><?php $badge($a['risk']); ?></a><?php endforeach; ?>
      <?php if ($failed): ?><h3 class="tw-sub">Failed tasks</h3><?php foreach ($failed as $f): ?><a class="tw-row" href="/admin/tasks/<?= (int)$f['id'] ?>"><span class="tw-who"><b><?= e($f['title']) ?></b><small><?= e($f['agent_slug']) ?> · <?= e(str_limit((string)$f['error'], 80)) ?></small></span><span class="badge b-red">failed</span></a><?php endforeach; endif; ?>
    </section>
  </div>

  <div class="tw-col">
    <section class="panel tw-p tw-ask"><div class="tw-ph"><h2>Ask about the business</h2></div>
      <p class="small muted">Answers use live data. Anything that changes something asks you first.</p>
      <div id="owner-log" class="chat-log" style="max-height:300px;min-height:110px;border-radius:12px;margin-bottom:10px"></div>
      <div class="chips" id="owner-chips" style="margin-bottom:10px"><button class="chip" type="button">What happened today?</button><button class="chip" type="button">Which leads should I contact?</button><button class="chip" type="button">What requires my approval?</button></div>
      <form id="owner-form" class="row"><label class="sr" for="owner-q">Question</label><input id="owner-q" type="text" placeholder="How much did we make this month?" style="flex:1"><button class="btn btn-dark">Ask</button></form>
    </section>

    <section class="panel tw-p"><div class="tw-ph"><h2>AI workforce</h2><a href="/admin/agents">Agents</a></div>
      <div class="tw-mini">
        <div><span>Agents on</span><b><?= $enabled ?>/<?= count($agents) ?></b></div>
        <div><span>Queued</span><b><?= (int)$t['queued'] ?></b></div>
        <div><span>Done (30d)</span><b><?= (int)$t['completed'] ?></b></div>
        <div class="<?= $t['failed'] ? 'bad' : '' ?>"><span>Failed</span><b><?= (int)$t['failed'] ?></b></div>
      </div>
      <div class="tw-line"><span><?= $sim ? ($simPass === count($sim) ? '<i class="dot ok"></i>All ' . count($sim) . ' agents passed the last simulation' : '<i class="dot bad"></i>' . (count($sim) - $simPass) . ' of ' . count($sim) . ' agents failed the last simulation') : '<i class="dot"></i>Not simulated yet in this session' ?></span><a class="btn btn-ghost btn-sm" href="/admin/simulator">Run simulation</a></div>
      <p class="tw-foot">AI spend: <?= e($m['ai_cost_today_usd']) ?> today, <?= e($m['ai_cost_usd']) ?> in 30 days (USD) · <?= $aiOn ? 'AI provider connected' : 'basic mode' ?></p>
    </section>

    <section class="panel tw-p"><div class="tw-ph"><h2>Weekly blog</h2><a href="/admin/blog">Blog engine</a></div>
      <div class="tw-line"><span><i class="dot <?= $blog['enabled'] ? 'ok' : '' ?>"></i><?= $blog['enabled'] ? 'On: a new article every 7 days' : 'Paused' ?></span><span class="small muted"><?= $blog['last'] ? 'Last: ' . e(substr($blog['last'], 0, 10)) : 'No article yet' ?></span></div>
      <p class="tw-foot"><?= (int)$blog['count'] ?> published<?= $blog['review'] ? ' · <a href="/admin/blog">' . (int)$blog['review'] . ' waiting for review</a>' : '' ?> · RSS for social scheduling: <a href="/blog/feed.xml">/blog/feed.xml</a></p>
    </section>
  </div>
</div>
</div>
