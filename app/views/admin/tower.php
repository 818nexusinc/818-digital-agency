<?php $badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; }; $t = $m['tasks']; ?>
<div class="topbar"><div><h1>Control Tower</h1><p class="muted" style="margin:4px 0 0">Live business and AI operations. Every figure comes from the database (last 30 days unless noted).</p></div>
  <div class="row"><a class="btn btn-ghost btn-sm" href="/admin/tasks">All tasks</a><a class="btn btn-primary btn-sm" href="/admin/approvals">Action center (<?= (int)$m['approvals_pending'] ?>)</a></div></div>
<?php if (!$cron || strtotime($cron . ' UTC') < time() - 600): ?><div class="alert alert-warn"><b>Background worker isn't running.</b> <?= $cron ? 'Last cron run: ' . e($cron) . ' UTC.' : 'Cron has never run.' ?> Agents, workflows and follow-ups need the cron job. See <a href="/admin/system">System health</a>.</div><?php endif; ?>
<?php if (!$providers['anthropic'] && !$providers['openai']): ?><div class="alert alert-info">No AI provider connected. The assistant runs in basic mode and agent tasks wait. <a href="/admin/integrations#anthropic">Connect Anthropic</a></div><?php endif; ?>
<?php if (!$providers['stripe']): ?><div class="alert alert-info">Stripe isn't connected, so checkout falls back to invoice requests. <a href="/admin/integrations#stripe">Connect Stripe</a></div><?php endif; ?>
<div class="kpis">
  <div class="kpi"><div class="l">Revenue today</div><div class="v"><?= e($m['revenue_today']) ?></div></div>
  <div class="kpi"><div class="l">Revenue (30d)</div><div class="v"><?= e($m['revenue']) ?></div></div>
  <div class="kpi"><div class="l">Recurring (MRR)</div><div class="v"><?= e($m['mrr']) ?></div></div>
  <div class="kpi"><div class="l">Paid orders (30d)</div><div class="v"><?= (int)$m['orders'] ?></div></div>
  <div class="kpi"><div class="l">Leads (30d)</div><div class="v"><?= (int)$m['leads'] ?></div></div>
  <div class="kpi"><div class="l">Lead conversion</div><div class="v"><?= e($m['conversion_rate']) ?></div></div>
  <div class="kpi"><div class="l">Active projects</div><div class="v"><?= (int)$m['projects_active'] ?></div></div>
  <div class="kpi<?= $m['projects_overdue'] ? ' bad' : '' ?>"><div class="l">Overdue projects</div><div class="v"><?= (int)$m['projects_overdue'] ?></div></div>
  <div class="kpi"><div class="l">Open tickets</div><div class="v"><?= (int)$m['tickets_open'] ?></div></div>
  <div class="kpi"><div class="l">Est. gross profit</div><div class="v"><?= e($m['gross_profit_estimate']) ?></div></div>
</div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><div class="row spread"><h2>Owner action center</h2><a class="small" href="/admin/approvals">View all</a></div>
    <?php if (!$approvals): ?><div class="empty">Nothing needs you right now.</div><?php endif; ?>
    <?php foreach ($approvals as $a): $d = jdec($a['details']); ?><div style="padding:10px 0;border-bottom:1px solid var(--line-2)"><div class="row spread"><b><?= e($a['title']) ?></b><?php $badge($a['risk']); ?></div><div class="small muted"><?= e(label($a['kind'])) ?> · from <?= e($a['requested_by']) ?> · <?= e(substr($a['created_at'], 0, 16)) ?></div><div class="small"><?= e(str_limit((string)($d['what'] ?? ''), 160)) ?></div></div><?php endforeach; ?>
    <?php if ($failed): ?><h2 style="margin-top:16px">Failed tasks</h2><?php foreach ($failed as $f): ?><div class="row spread small" style="padding:5px 0"><a href="/admin/tasks/<?= (int)$f['id'] ?>"><?= e($f['title']) ?></a><span class="muted"><?= e($f['agent_slug']) ?></span></div><?php endforeach; endif; ?></div>
  <div class="panel"><h2>Owner assistant</h2><p class="small muted">Ask about the business. Answers use live data; actions need your confirmation.</p>
    <div id="owner-log" class="chat-log" style="max-height:300px;min-height:120px;border-radius:12px;margin-bottom:10px"></div>
    <div class="chips" id="owner-chips" style="margin-bottom:10px"><button class="chip" type="button">What happened today?</button><button class="chip" type="button">Which leads should I contact?</button><button class="chip" type="button">What requires my approval?</button><button class="chip" type="button">Which AI agents failed this week?</button></div>
    <form id="owner-form" class="row"><label class="sr" for="owner-q">Question</label><input id="owner-q" type="text" placeholder="e.g. How much did we make this month?" style="flex:1"><button class="btn btn-dark">Ask</button></form></div>
</div>
<div class="grid g2" style="align-items:start">
  <div class="panel"><h2>AI operations</h2>
    <div class="kpis" style="grid-template-columns:repeat(3,1fr)"><div class="kpi"><div class="l">Queued</div><div class="v"><?= $t['queued'] ?></div></div><div class="kpi"><div class="l">Running</div><div class="v"><?= $t['running'] ?></div></div><div class="kpi"><div class="l">Awaiting approval</div><div class="v"><?= $t['needs_approval'] ?></div></div>
      <div class="kpi"><div class="l">Completed (30d)</div><div class="v"><?= $t['completed'] ?></div></div><div class="kpi<?= $t['failed'] ? ' bad' : '' ?>"><div class="l">Failed (30d)</div><div class="v"><?= $t['failed'] ?></div></div><div class="kpi"><div class="l">AI cost today</div><div class="v"><?= e($m['ai_cost_today_usd']) ?></div></div></div>
    <p class="small muted">30-day AI spend <?= e($m['ai_cost_usd']) ?> (USD) · <?= number_format($m['ai_tokens']) ?> tokens · agents enabled: <?= count(array_filter($agents, fn($a) => $a['status'] === 'enabled')) ?>/<?= count($agents) ?></p></div>
  <div class="panel"><div class="row spread"><h2>Leads to contact</h2><a class="small" href="/admin/leads">All leads</a></div><?php if (!$leads): ?><div class="empty">No open leads yet.</div><?php endif; ?>
    <?php foreach ($leads as $l): ?><div class="row spread" style="padding:7px 0;border-bottom:1px solid var(--line-2)"><span><a href="/admin/customers/<?= (int)$l['customer_id'] ?>"><?= e($l['name'] ?: $l['email']) ?></a> <span class="small muted"><?= e($l['business_name']) ?></span></span><span class="badge <?= $l['score'] >= 60 ? 'b-green' : 'b-blue' ?>">score <?= (int)$l['score'] ?></span></div><?php endforeach; ?>
    <p class="small muted" style="margin-top:8px">Pipeline value <?= e($m['pipeline_value']) ?> · <?= (int)$m['leads_overdue'] ?> overdue follow-up(s)</p></div>
</div>
