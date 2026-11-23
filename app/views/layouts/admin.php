<?php
$pend = (int)App\Db::val("SELECT COUNT(*) FROM approvals WHERE status = 'pending'");
$failed = (int)App\Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'failed'");
$openT = (int)App\Db::val("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
$groups = ['Operate' => ['/admin' => 'Control Tower', '/admin/approvals' => ['Action center', $pend], '/admin/projects' => 'Projects', '/admin/filings' => ['Government filings', (int)App\Db::val("SELECT COUNT(*) FROM filings WHERE status IN ('ready_to_file','awaiting_approval')")], '/admin/tickets' => ['Support', $openT]],
  'Sell' => ['/admin/leads' => 'Leads', '/admin/customers' => 'Customers', '/admin/proposals' => 'Proposals', '/admin/orders' => 'Orders'],
  'AI workforce' => ['/admin/agents' => 'Agents', '/admin/tasks' => ['Tasks', $failed], '/admin/workflows' => 'Workflows', '/admin/kb' => 'Knowledge base'],
  'Grow' => ['/admin/content' => 'Content studio', '/admin/growth' => 'Growth & reports', '/admin/finance' => 'Finance', '/admin/catalog' => 'Services & pricing'],
  'System' => ['/admin/integrations' => 'Integrations', '/admin/settings' => 'Settings & team', '/admin/audit' => 'Audit log', '/admin/system' => 'System health', '/classic' => 'Classic v2.6 (archive)']];
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($title ?? 'Admin') ?> · 818 Control Tower</title><meta name="robots" content="noindex">
<meta name="csrf" content="<?= e($csrf ?? '') ?>"><link rel="icon" href="/assets/img/favicon.svg"><link rel="preconnect" href="https://api.fontshare.com"><link rel="preconnect" href="https://cdn.fontshare.com" crossorigin><link rel="stylesheet" href="https://api.fontshare.com/v2/css?f[]=sentient@1,2&amp;display=swap"><link rel="stylesheet" href="/assets/css/app.css?v=<?= APP_VERSION ?>"></head><body>
<div class="mobile-top"><a class="brand" href="/admin"><?php include APP_ROOT . '/views/partials/logo.php'; ?></a><button class="menu-btn" data-toggle="side" aria-label="Menu">☰</button></div>
<div class="shell"><aside class="side" id="side"><a class="brand" href="/admin"><?php include APP_ROOT . '/views/partials/logo.php'; ?><span class="wm"><b>818</b><span>CONTROL TOWER</span></span></a>
  <nav aria-label="Admin"><?php foreach ($groups as $g => $links): ?><h6><?= e($g) ?></h6><?php foreach ($links as $h => $l): [$lbl, $n] = is_array($l) ? $l : [$l, 0]; $cur = ($path ?? '') === $h || ($h !== '/admin' && str_starts_with($path ?? '', $h . '/')); ?>
    <a href="<?= $h ?>"<?= $cur ? ' aria-current="page"' : '' ?>><?= e($lbl) ?><?php if ($n): ?><span class="count"><?= $n ?></span><?php endif; ?></a><?php endforeach; endforeach; ?>
    <h6>Site</h6><a href="/">View website</a></nav>
  <p class="small muted" style="margin:18px 10px 6px"><?= e($user['name'] ?? '') ?> · <?= e($user['role'] ?? '') ?></p>
  <form method="post" action="/logout"><?= App\Csrf::field() ?><button class="btn btn-ghost btn-sm" style="width:100%">Sign out</button></form></aside>
<main class="main" id="main"><?php include APP_ROOT . '/views/partials/flash.php'; ?><?= $content ?></main></div>
<script src="/assets/js/app.js?v=<?= APP_VERSION ?>" nonce="<?= e($nonce ?? '') ?>" defer></script></body></html>
