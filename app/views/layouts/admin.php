<?php
$pend = (int)App\Db::val("SELECT COUNT(*) FROM approvals WHERE status = 'pending'");
$failed = (int)App\Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'failed'");
$openT = (int)App\Db::val("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
$groups = ['Operate' => ['/admin' => 'Control Tower', '/admin/approvals' => ['Action center', $pend], '/admin/projects' => 'Projects', '/admin/filings' => ['Government filings', (int)App\Db::val("SELECT COUNT(*) FROM filings WHERE status IN ('ready_to_file','awaiting_approval')")], '/admin/tickets' => ['Support', $openT]],
  'Sell' => ['/admin/leads' => 'Leads', '/admin/drafts' => ['Unfinished forms', (int)App\Db::val("SELECT COUNT(*) FROM form_drafts WHERE status = 'open' AND email IS NOT NULL")], '/admin/customers' => 'Customers', '/admin/proposals' => 'Proposals', '/admin/orders' => 'Orders'],
  'AI workforce' => ['/admin/guide' => 'Agent guide', '/admin/agents' => 'Agents', '/admin/tasks' => ['Tasks', $failed], '/admin/workflows' => 'Workflows', '/admin/kb' => 'Knowledge base', '/admin/simulator' => 'Agent simulator'],
  'Grow' => ['/admin/search' => 'Search & AI visibility', '/admin/blog' => 'Blog engine', '/admin/content' => 'Content studio', '/admin/growth' => 'Growth & reports', '/admin/finance' => 'Finance', '/admin/catalog' => 'Services & pricing'],
  'System' => ['/admin/security' => 'Security check', '/admin/spam' => 'Spam shield', '/admin/integrations' => 'Integrations', '/admin/settings' => 'Settings & team', '/admin/audit' => 'Audit log', '/admin/system' => 'System health', '/admin/update' => 'Install an update', '/admin/demo' => ['Demo data', App\Demo::count()], '/classic' => 'Classic v2.6 (archive)']];
$groups['Operate'] = ['/admin' => 'Control Tower', '/admin/automations' => 'Automations'] + $groups['Operate'];
$area = 'admin'; $areaName = 'Control Tower'; $home = '/admin';
include APP_ROOT . '/views/partials/console-shell.php';
