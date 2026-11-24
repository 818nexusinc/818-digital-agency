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
$spark = function (array $v, string $cls): string {
    $max = max(1, ...$v); $n = count($v); $pts = [];
    foreach ($v as $i => $x) $pts[] = round($i * (120 / max(1, $n - 1)), 1) . ',' . round(30 - ($x / $max) * 26, 1);
    return '<svg class="spark ' . $cls . '" viewBox="0 0 120 32" preserveAspectRatio="none" aria-hidden="true"><polyline points="0,32 ' . implode(' ', $pts) . ' 120,32" class="area"/><polyline points="' . implode(' ', $pts) . '" class="line"/></svg>';
};
$hour = (int)gmdate('G') - 4; $hour = ($hour + 24) % 24;
$hello = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
$P = ['register' => ['Register', '/register', '#4f46e5', 'Incorporations and registrations'], 'manage' => ['Manage', '/manage-business', '#047857', 'Annual returns, changes, documents'],
      'web' => ['Web Services', '/web-services', '#be185d', 'Domains, email, websites, apps'], 'bundle' => ['Bundles', '/bundle', '#c2410c', 'Register + web in one order']];
?>
<div class="ct">
<header class="ct-hero">
  <svg class="ct-rings" viewBox="0 0 74 40" aria-hidden="true"><g fill="none" stroke-width="2.2"><circle cx="13" cy="11" r="7.2"/><circle cx="13" cy="28.5" r="9"/><circle cx="61" cy="11" r="7.2"/><circle cx="61" cy="28.5" r="9"/><path d="M33 9.5 L38 4 V36"/></g></svg>
  <div class="ct-hi"><p><?= e(gmdate('l, j F')) ?></p><h1><?= e($hello) ?><?= $first && $first !== 'Owner' ? ', ' . e($first) : '' ?></h1>
    <p class="ct-line"><?php $nn = count($inbox); echo $nn ? e($nn . ($nn === 1 ? ' thing needs' : ' things need') . ' you today') : 'Nothing needs you right now'; ?> · <?= e($m['revenue']) ?> earned in the last 30 days</p></div>
  <nav class="ct-quick" aria-label="Quick actions"><a href="/admin/approvals">Action center<?= $m['approvals_pending'] ? ' <b>' . (int)$m['approvals_pending'] . '</b>' : '' ?></a><a href="/admin/orders">Orders</a><a href="/admin/customers">Customers</a><a href="/admin/blog">Write an article</a><a href="/" target="_blank" rel="noopener">View website</a></nav>
</header>

<?php if (!$cronOk || $demo): ?><div class="ct-warn">
  <?php if (!$cronOk): ?><p><b>The background job isn't running.</b> Follow-ups, agents, the weekly article and backups are paused. <a href="/admin/automations">Set it up</a></p><?php endif; ?>
  <?php if ($demo): ?><p><b><?= (int)$demo ?> demo records are loaded.</b> <a href="/admin/demo">Delete them before taking real orders</a></p><?php endif; ?>
</div><?php endif; ?>

<section class="ct-stats" aria-label="Last 30 days">
  <div><span>Revenue today</span><b><?= e($m['revenue_today']) ?></b></div>
  <div class="has-spark"><span>Revenue, 30 days</span><b><?= e($m['revenue']) ?></b><?= $spark($trend['revenue'], 'sp-rev') ?><small>Last 14 days</small></div>
  <div><span>Monthly recurring</span><b><?= e($m['mrr']) ?></b></div>
  <div><span>Paid orders</span><b><?= (int)$m['orders'] ?></b></div>
  <div class="has-spark"><span>New leads, 7 days</span><b><?= (int)$newLeads ?></b><?= $spark($trend['leads'], 'sp-lead') ?><small>Last 14 days</small></div>
  <div><span>Active projects</span><b><?= (int)$m['projects_active'] ?><?php if ($m['projects_overdue']): ?><em><?= (int)$m['projects_overdue'] ?> late</em><?php endif; ?></b></div>
</section>

<section class="ct-biz" aria-label="Your businesses, last 30 days">
  <?php foreach ($P as $k => [$name, $href, $col, $sub]): $d = $pillars[$k]; ?>
  <a class="ct-p" href="/admin/orders" style="--pc:<?= $col ?>"><span class="ct-pn"><?= e($name) ?></span><small><?= e($sub) ?></small>
    <span class="ct-pv"><b><?= (int)$d['orders'] ?></b> order<?= $d['orders'] === 1 ? '' : 's' ?><?php if ($d['cents']): ?> · <?= money((int)$d['cents']) ?><?php endif; ?></span>
    <span class="ct-pp"><?= (int)$d['projects'] ?> in progress</span></a>
  <?php endforeach; ?>
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
