<?php
$q = fn(string $sql) => (int)App\Db::val($sql);
$pend = $q("SELECT COUNT(*) FROM approvals WHERE status = 'pending'");
$failed = $q("SELECT COUNT(*) FROM agent_tasks WHERE status = 'failed'");
$openT = $q("SELECT COUNT(*) FROM tickets WHERE status = 'open'");
$drafts = $q("SELECT COUNT(*) FROM form_drafts WHERE status = 'open' AND email IS NOT NULL");
$filings = $q("SELECT COUNT(*) FROM filings WHERE status IN ('ready_to_file','awaiting_approval')");
$demo = App\Demo::count();
/* Eight destinations; each page shows its related pages as tabs. [label, icon, [href => label | [label, count, urgent]]] */
$nav = [
  ['Today', 'home', ['/admin' => 'Overview', '/admin/approvals' => ['Action center', $pend, true], '/admin/automations' => 'Automations']],
  ['Sales', 'cash', ['/admin/leads' => 'Leads', '/admin/drafts' => ['Unfinished forms', $drafts], '/admin/proposals' => 'Proposals', '/admin/orders' => 'Orders']],
  ['Customers', 'users', ['/admin/customers' => 'Customers', '/admin/tickets' => ['Support', $openT]]],
  ['Work', 'folder', ['/admin/projects' => 'All projects', '/admin/filings' => ['Government filings', $filings], '/admin/form-check' => ['Registry form check', $q("SELECT COUNT(*) FROM form_findings WHERE status = 'open'")]] + (function () {
      $open = App\WorkBoard::openByCategory(); $k = [];
      foreach (App\Catalog::CATEGORIES as $c => $n) $k['/admin/work/' . $c] = [$n, $open[$c] ?? 0];
      return $k; })()],
  ['AI team', 'bot', ['/admin/guide' => 'How agents work', '/admin/agents' => 'Agents', '/admin/tasks' => ['Tasks', $failed], '/admin/workflows' => 'Workflows', '/admin/kb' => 'Knowledge base', '/admin/simulator' => 'Simulator']],
  ['Marketing', 'chart', ['/admin/site' => 'Site editor', '/admin/social' => 'Social media', '/admin/search' => 'Search & AI visibility', '/admin/blog' => 'Blog', '/admin/content' => 'Content studio', '/admin/growth' => 'Reports']],
  ['Money', 'stack', ['/admin/money' => 'Financial dashboard', '/admin/pricing' => 'Pricing strategy', '/admin/finance' => 'Finance', '/admin/catalog' => 'Services & pricing']],
  ['Settings', 'cog', ['/admin/settings' => 'Settings & team', '/admin/sign-in' => 'Sign-in & recovery', '/admin/integrations' => 'Integrations', '/admin/data-safety' => 'Data safety', '/admin/security' => 'Security check', '/admin/spam' => 'Spam shield',
      '/admin/system' => 'System health', '/admin/update' => 'Updates', '/admin/demo' => ['Demo data', $demo, false, true], '/admin/audit' => 'Audit log', '/classic' => 'Classic archive']],
];
// Older code paths still read $groups (search, breadcrumbs); build it from $nav.
$groups = []; foreach ($nav as [$lbl, , $kids]) $groups[$lbl] = array_map(fn($v) => is_array($v) ? [$v[0], $v[1]] : $v, $kids);
$area = 'admin'; $areaName = 'Control Tower'; $home = '/admin';
include APP_ROOT . '/views/partials/console-shell.php';
