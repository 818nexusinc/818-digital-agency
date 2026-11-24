<?php
$t = $m['tasks'];
$ago = function (?string $at): string {
    if (!$at) return '';
    $s = max(0, time() - strtotime($at . ' UTC'));
    return $s < 3600 ? max(1, (int)round($s / 60)) . 'm' : ($s < 86400 ? (int)round($s / 3600) . 'h' : ($s < 86400 * 30 ? (int)round($s / 86400) . 'd' : substr($at, 0, 10)));
};
$cronOk = $cron && strtotime($cron . ' UTC') >= time() - 900;
$aiOn = $providers['anthropic'] || $providers['openai'];
$enabled = count(array_filter($agents, fn($a) => $a['status'] === 'enabled'));
$first = trim(explode(' ', (string)($me['name'] ?? ''))[0] ?? '');
?>
<div class="ct">
<header class="ct-head">
  <div><p class="ct-date"><?= e(gmdate('l, j F')) ?></p><h1>Today<?= $first && $first !== 'Owner' ? ', ' . e($first) : '' ?></h1></div>
  <nav class="ct-quick" aria-label="Quick actions">
    <a href="/admin/orders">Orders</a><a href="/admin/customers">Customers</a><a href="/admin/blog">Write an article</a><a class="btn btn-primary btn-sm" href="/admin/approvals">Action center<?= $m['approvals_pending'] ? ' (' . (int)$m['approvals_pending'] . ')' : '' ?></a>
  </nav>
</header>

<?php if (!$cronOk || $demo): ?><div class="ct-warn">
  <?php if (!$cronOk): ?><p><b>The background job isn't running.</b> Follow-ups, agents, the weekly article and backups are paused. <a href="/admin/automations">Set it up</a></p><?php endif; ?>
  <?php if ($demo): ?><p><b><?= (int)$demo ?> demo records are loaded.</b> <a href="/admin/demo">Delete them before taking real orders</a></p><?php endif; ?>
</div><?php endif; ?>

<section class="ct-stats" aria-label="Last 30 days">
  <div><span>Revenue today</span><b><?= e($m['revenue_today']) ?></b></div>
  <div><span>Revenue, 30 days</span><b><?= e($m['revenue']) ?></b></div>
  <div><span>Monthly recurring</span><b><?= e($m['mrr']) ?></b></div>
  <div><span>Paid orders</span><b><?= (int)$m['orders'] ?></b></div>
  <div><span>New leads, 7 days</span><b><?= (int)$newLeads ?></b></div>
  <div><span>Active projects</span><b><?= (int)$m['projects_active'] ?><?php if ($m['projects_overdue']): ?><em><?= (int)$m['projects_overdue'] ?> late</em><?php endif; ?></b></div>
</section>

<div class="ct-cols">
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

  <aside class="ct-side">
    <section class="ct-box"><div class="ct-bh"><h2>Ask about the business</h2></div>
      <div id="owner-log" class="chat-log ct-log"></div>
      <div class="chips" id="owner-chips"><button class="chip" type="button">What happened today?</button><button class="chip" type="button">Which leads should I contact?</button></div>
      <form id="owner-form" class="ct-ask"><label class="sr" for="owner-q">Question</label><input id="owner-q" type="text" placeholder="How much did we make this month?"><button class="btn btn-primary btn-sm">Ask</button></form>
    </section>

    <section class="ct-box"><div class="ct-bh"><h2>Running on its own</h2><a href="/admin/automations">All automations</a></div>
      <dl class="ct-sys">
        <dt>Background job</dt><dd class="<?= $cronOk ? 'ok' : 'bad' ?>"><?= $cron ? ($cronOk ? 'Ran ' . e($ago($cron)) . ' ago' : 'Stopped, last ' . e($ago($cron)) . ' ago') : 'Not set up' ?></dd>
        <?php foreach ($autos as $au): ?><dt><?= e($au['name']) ?></dt><dd class="<?= !$au['on'] ? 'mut' : ($au['last'] && !$au['last']['ok'] ? 'bad' : '') ?>"><?= !$au['on'] ? 'Off' : ($au['last'] ? ($au['last']['ok'] ? e($ago($au['last']['at'])) . ' ago' : 'Failed') : 'Waiting') ?></dd><?php endforeach; ?>
        <dt>AI agents</dt><dd class="<?= $aiOn ? '' : 'mut' ?>"><?= $enabled ?> on · <?= $aiOn ? 'AI connected' : 'basic mode' ?></dd>
        <dt>Spam blocked today</dt><dd><?= (int)$spamToday ?></dd>
      </dl>
    </section>

    <section class="ct-box"><div class="ct-bh"><h2>Recent activity</h2></div>
      <?php if (!$activity): ?><p class="ct-muted">Orders, leads and articles will appear here.</p><?php endif; ?>
      <ol class="ct-feed"><?php foreach ($activity as [$at, $what, $detail]): ?><li><time><?= e($ago($at)) ?></time><div><b><?= e($what) ?></b><span><?= e(str_limit((string)$detail, 70)) ?></span></div></li><?php endforeach; ?></ol>
    </section>
  </aside>
</div>
</div>
