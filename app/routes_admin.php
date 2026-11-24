<?php
declare(strict_types=1);

use App\{Agents, AgentRunner, Approvals, Audit, Auth, Cart, Catalog, ChatService, Crm, Crypto, Db, Documents, Env, Events, HttpError, Integrations, Kb, Mailer, Metrics, Orchestrator, Orders,
    Projects, Proposals, Rbac, Request, Seo, Session, Settings, Tickets, Totp, UserError, View, Workflows};

/** @var App\Router $r */
$P = fn(string $perm) => ['perm' => $perm];
$me = fn() => Auth::user();
$page = fn(int $per = 50) => [$per, max(0, ((int)($_GET['page'] ?? 1) - 1) * $per)];

$r->get('/admin', function () use ($me) {
    View::page('admin/tower', ['title' => 'Control Tower', 'm' => Metrics::overview(30), 'today' => Metrics::overview(1), 'me' => $me(),
        'drafts' => Db::all("SELECT * FROM form_drafts WHERE status = 'open' AND email IS NOT NULL ORDER BY updated_at DESC LIMIT 5"),
        'draftsOpen' => (int)Db::val("SELECT COUNT(*) FROM form_drafts WHERE status = 'open'"),
        'newLeads' => (int)Db::val("SELECT COUNT(*) FROM leads WHERE created_at >= ?", [gmdate('Y-m-d H:i:s', time() - 7 * 86400)]),
        'chatLeads' => (int)Db::val("SELECT COUNT(*) FROM leads WHERE source = 'ai_chat' AND created_at >= ?", [gmdate('Y-m-d H:i:s', time() - 30 * 86400)]),
        'openTickets' => (int)Db::val("SELECT COUNT(*) FROM tickets WHERE status = 'open'"),
        'blog' => ['last' => Settings::get('blog.last_generated'), 'count' => (int)Db::val("SELECT COUNT(*) FROM content_items WHERE kind = 'blog' AND status = 'published'"),
            'review' => (int)Db::val("SELECT COUNT(*) FROM content_items WHERE kind = 'blog' AND status = 'review'"), 'enabled' => Settings::get('blog.enabled', '1') === '1'],
        'demo' => App\Demo::count(), 'sim' => $_SESSION['sim_results'] ?? null, 'simAt' => $_SESSION['sim_at'] ?? null,
        'inbox' => (function () {
            $i = [];
            foreach (Db::all("SELECT id, title, kind, risk, requested_by, created_at FROM approvals WHERE status = 'pending' ORDER BY id DESC LIMIT 6") as $a)
                $i[] = ['Approval', $a['title'], label($a['kind']) . ' · from ' . $a['requested_by'], $a['created_at'], '/admin/approvals', 'Review', $a['risk'] === 'high' ? 0 : 1];
            foreach (Db::all("SELECT id, title, agent_slug, error, updated_at FROM agent_tasks WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 3") as $t)
                $i[] = ['Failed task', $t['title'], $t['agent_slug'] . ' · ' . str_limit((string)$t['error'], 60), $t['updated_at'], '/admin/tasks/' . $t['id'], 'Open', 2];
            foreach (Db::all("SELECT t.id, t.number, t.subject, t.created_at, c.name, c.email FROM tickets t LEFT JOIN customers c ON c.id = t.customer_id WHERE t.status = 'open' ORDER BY t.id DESC LIMIT 4") as $t)
                $i[] = ['Support', $t['subject'], ($t['name'] ?: $t['email'] ?: 'Customer') . ' · ' . $t['number'], $t['created_at'], '/admin/tickets/' . $t['number'], 'Reply', 2];
            foreach (Db::all("SELECT l.id, l.customer_id, l.summary, l.source, l.score, l.created_at, c.name, c.email, c.business_name FROM leads l JOIN customers c ON c.id = l.customer_id WHERE l.status = 'lead' AND l.follow_up_count = 0 AND l.created_at >= ? ORDER BY l.score DESC LIMIT 5", [gmdate('Y-m-d H:i:s', time() - 14 * 86400)]) as $l)
                $i[] = ['New lead', ($l['name'] ?: $l['email']) . ($l['business_name'] ? ' — ' . $l['business_name'] : ''), str_limit((string)$l['summary'], 80) . ' · ' . label((string)$l['source']), $l['created_at'], '/admin/customers/' . $l['customer_id'], 'Contact', 3];
            foreach (Db::all("SELECT id, form, email, name, step, steps, updated_at FROM form_drafts WHERE status = 'open' AND email IS NOT NULL ORDER BY updated_at DESC LIMIT 3") as $d)
                $i[] = ['Unfinished form', ($d['name'] ?: $d['email']), label($d['form']) . ' · stopped at step ' . ($d['step'] + 1) . ' of ' . max(1, $d['steps']), $d['updated_at'], '/admin/drafts', 'Follow up', 4];
            usort($i, fn($a, $b) => [$a[6], $b[3]] <=> [$b[6], $a[3]]);
            return array_slice($i, 0, 12);
        })(),
        'activity' => (function () {
            $a = [];
            foreach (Db::all("SELECT o.number, o.total_cents, o.paid_at, c.name, c.email FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.paid_at IS NOT NULL ORDER BY o.paid_at DESC LIMIT 5") as $o) $a[] = [$o['paid_at'], 'Order ' . $o['number'] . ' paid', ($o['name'] ?: $o['email']) . ' · ' . money((int)$o['total_cents'])];
            foreach (Db::all("SELECT l.created_at, l.source, c.name, c.email FROM leads l JOIN customers c ON c.id = l.customer_id ORDER BY l.id DESC LIMIT 5") as $l) $a[] = [$l['created_at'], 'New lead', ($l['name'] ?: $l['email']) . ' · ' . label((string)$l['source'])];
            foreach (Db::all("SELECT title, publish_at FROM content_items WHERE kind = 'blog' AND status = 'published' AND publish_at IS NOT NULL ORDER BY publish_at DESC LIMIT 2") as $p) $a[] = [$p['publish_at'], 'Article published', $p['title']];
            foreach (Db::all("SELECT number, subject, created_at FROM tickets ORDER BY id DESC LIMIT 3") as $t) $a[] = [$t['created_at'], 'Support request', $t['subject']];
            usort($a, fn($x, $y) => strcmp((string)$y[0], (string)$x[0]));
            return array_slice($a, 0, 8);
        })(),
        'autos' => array_map(fn($k) => ['name' => App\Automations::JOBS[$k][0], 'last' => App\Automations::last($k), 'on' => App\Automations::enabled($k)], ['lead_followups', 'draft_followups', 'blog_weekly', 'agents_selftest', 'security_daily', 'backup_nightly']),
        'spamToday' => App\Spam::stats(1)['total'],
        'dash' => (function () {
            $r = (int)($_GET['range'] ?? 30); if (!in_array($r, [7, 30, 90], true)) $r = 30;
            $since = gmdate('Y-m-d H:i:s', time() - $r * 86400);
            $paidSql = "status IN ('paid','processing','completed')";
            $tot = (int)Db::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status NOT IN ('cancelled')", [$since]);
            $paid = (int)Db::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND {$paidSql}", [$since]);
            $await = (int)Db::val("SELECT COUNT(*) FROM orders WHERE created_at >= ? AND status IN ('pending','awaiting_payment')", [$since]);
            $leads = (int)Db::val("SELECT COUNT(*) FROM leads WHERE created_at >= ?", [$since]);
            $conv = (int)Db::val("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND status = 'converted'", [$since]);
            // Last 12 months: invoiced (all non-cancelled orders by created month) and paid (by paid month).
            $months = []; for ($i = 11; $i >= 0; $i--) { $t = strtotime(gmdate('Y-m-01') . " -{$i} months"); $months[gmdate('Y-m', $t)] = ['label' => gmdate('M', $t), 'inv' => 0, 'paid' => 0]; }
            $from = array_key_first($months) . '-01 00:00:00';
            foreach (Db::all("SELECT SUBSTR(created_at, 1, 7) m, SUM(total_cents) v FROM orders WHERE created_at >= ? AND status <> 'cancelled' GROUP BY SUBSTR(created_at, 1, 7)", [$from]) as $x) if (isset($months[$x['m']])) $months[$x['m']]['inv'] = (int)$x['v'];
            foreach (Db::all("SELECT SUBSTR(paid_at, 1, 7) m, SUM(total_cents) v FROM orders WHERE paid_at >= ? AND {$paidSql} GROUP BY SUBSTR(paid_at, 1, 7)", [$from]) as $x) if (isset($months[$x['m']])) $months[$x['m']]['paid'] = (int)$x['v'];
            // Earnings for each of the last 7 days.
            $week = []; for ($i = 6; $i >= 0; $i--) { $d = gmdate('Y-m-d', time() - $i * 86400); $week[$d] = ['label' => gmdate('D', strtotime($d)), 'cents' => 0, 'orders' => 0]; }
            foreach (Db::all("SELECT SUBSTR(paid_at, 1, 10) d, SUM(total_cents) v, COUNT(*) n FROM orders WHERE paid_at >= ? AND {$paidSql} GROUP BY SUBSTR(paid_at, 1, 10)", [array_key_first($week) . ' 00:00:00']) as $x) if (isset($week[$x['d']])) { $week[$x['d']]['cents'] = (int)$x['v']; $week[$x['d']]['orders'] = (int)$x['n']; }
            return ['range' => $r, 'orders' => $tot, 'paid' => $paid, 'await' => $await, 'leads' => $leads, 'conv' => $conv, 'months' => array_values($months), 'week' => array_values($week),
                'recent' => Db::all("SELECT o.number, o.total_cents, o.status, o.created_at, c.name, c.email, c.business_name FROM orders o JOIN customers c ON c.id = o.customer_id ORDER BY o.id DESC LIMIT 6"),
                'expenses' => (int)Db::val("SELECT COALESCE(SUM(cost_micros),0) FROM agent_runs WHERE created_at >= ?", [$from]) / 10000];
        })(),
        'trend' => (function () {
            $from = gmdate('Y-m-d', time() - 13 * 86400); $days = []; for ($i = 13; $i >= 0; $i--) $days[gmdate('Y-m-d', time() - $i * 86400)] = 0;
            $rev = $days; foreach (Db::all("SELECT SUBSTR(paid_at, 1, 10) d, SUM(total_cents) v FROM orders WHERE paid_at >= ? GROUP BY SUBSTR(paid_at, 1, 10)", [$from . ' 00:00:00']) as $r) if (isset($rev[$r['d']])) $rev[$r['d']] = (int)$r['v'];
            $lead = $days; foreach (Db::all("SELECT SUBSTR(created_at, 1, 10) d, COUNT(*) v FROM leads WHERE created_at >= ? GROUP BY SUBSTR(created_at, 1, 10)", [$from . ' 00:00:00']) as $r) if (isset($lead[$r['d']])) $lead[$r['d']] = (int)$r['v'];
            return ['revenue' => array_values($rev), 'leads' => array_values($lead)];
        })(),
        'pillars' => (function () {
            $map = ['registration' => 'register', 'management' => 'manage', 'domains' => 'web', 'email' => 'web', 'websites' => 'web', 'apps' => 'web', 'growth' => 'web'];
            $out = []; foreach (['register', 'manage', 'web', 'bundle'] as $k) $out[$k] = ['orders' => 0, 'cents' => 0, 'projects' => 0];
            $since = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
            foreach (Db::all("SELECT s.category c, COUNT(DISTINCT o.id) n, SUM(oi.unit_cents * oi.qty) v FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN services s ON s.slug = oi.service_slug WHERE o.paid_at >= ? GROUP BY s.category", [$since]) as $r)
                if (isset($map[$r['c']])) { $out[$map[$r['c']]]['orders'] += (int)$r['n']; $out[$map[$r['c']]]['cents'] += (int)$r['v']; }
            foreach (Db::all("SELECT s.category c, COUNT(*) n FROM projects p JOIN services s ON s.slug = p.service_slug WHERE p.status NOT IN ('completed','cancelled') GROUP BY s.category") as $r)
                if (isset($map[$r['c']])) $out[$map[$r['c']]]['projects'] += (int)$r['n'];
            $out['bundle']['orders'] = (int)Db::val("SELECT COUNT(*) FROM orders WHERE paid_at >= ? AND meta LIKE '%bundle%'", [$since]);
            return $out;
        })(),
        'approvals' => Db::all("SELECT * FROM approvals WHERE status = 'pending' ORDER BY CASE risk WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END, id DESC LIMIT 8"),
        'failed' => Db::all("SELECT * FROM agent_tasks WHERE status = 'failed' ORDER BY updated_at DESC LIMIT 5"),
        'leads' => Db::all("SELECT l.*, c.name, c.email, c.business_name FROM leads l JOIN customers c ON c.id = l.customer_id WHERE l.status NOT IN ('converted','closed') ORDER BY l.score DESC, l.id DESC LIMIT 6"),
        'agents' => Db::all('SELECT slug, name, status, autonomy_level FROM agents ORDER BY id'),
        'cron' => Settings::get('system.cron_last_run'), 'providers' => ['anthropic' => Integrations::configured('anthropic'), 'openai' => Integrations::configured('openai'), 'stripe' => Integrations::configured('stripe'), 'resend' => Integrations::configured('resend')]]);
}, $P('dashboard.view'));

$r->post('/admin/assistant', function () use ($me) {
    View::json(['ok' => true] + ChatService::owner(Request::str('message', 3000), $me()));
}, $P('dashboard.view'));

/* Approvals */
$r->get('/admin/approvals', function () {
    $st = in_array($_GET['status'] ?? 'pending', ['pending', 'approved', 'rejected', 'changes_requested'], true) ? ($_GET['status'] ?? 'pending') : 'pending';
    View::page('admin/approvals', ['title' => 'Approvals & action center', 'rows' => Db::all('SELECT * FROM approvals WHERE status = ? ORDER BY id DESC LIMIT 100', [$st]), 'status' => $st]);
}, $P('approvals.decide'));
$r->post('/admin/approvals/{id}', function ($p) use ($me) {
    $mod = Request::str('modified_input', 20000);
    $modArr = $mod !== '' ? json_decode($mod, true) : null;
    if ($mod !== '' && !is_array($modArr)) throw new UserError('Modified input must be valid JSON.');
    Approvals::decide((int)$p['id'], Request::str('decision', 20), $me(), Request::str('note', 2000), $modArr);
    Session::flash('success', 'Decision recorded.');
    View::redirect('/admin/approvals');
}, $P('approvals.decide'));

/* Agents */
$r->get('/admin/agents', fn() => View::page('admin/agents', ['title' => 'AI workforce', 'stats' => Metrics::agentStats(30), 'tools' => App\Tools::defs()]), $P('agents.read'));
$r->get('/admin/agents/{slug}', function ($p) {
    $a = Agents::get($p['slug']) ?? throw new HttpError(404, 'Agent not found.');
    View::page('admin/agent', ['title' => $a['name'], 'a' => $a, 'tools' => App\Tools::defs(), 'versions' => Db::all('SELECT version, changed_by, created_at FROM agent_versions WHERE agent_id = ? ORDER BY version DESC LIMIT 20', [$a['id']]),
        'runs' => Db::all('SELECT * FROM agent_runs WHERE agent_slug = ? ORDER BY id DESC LIMIT 20', [$a['slug']]), 'tasks' => Db::all('SELECT * FROM agent_tasks WHERE agent_slug = ? ORDER BY id DESC LIMIT 20', [$a['slug']])]);
}, $P('agents.read'));
$r->post('/admin/agents/{slug}', function ($p) use ($me) {
    $act = Request::str('action', 20);
    if ($act === 'rollback') Agents::rollback($p['slug'], (int)Request::input('version'), $me()['email']);
    elseif ($act === 'test') {
        $res = AgentRunner::run($p['slug'], [['role' => 'user', 'content' => 'TEST (sandbox, no customer): ' . Request::str('prompt', 2000)]], ['channel' => 'test', 'test' => true]);
        Session::flash('success', 'Test reply (' . $res['provider'] . ', $' . number_format($res['cost_micros'] / 1e6, 4) . '): ' . str_limit($res['text'], 900));
    } else {
        Agents::update($p['slug'], ['instructions' => Request::str('instructions', 8000), 'model' => Request::str('model', 80), 'fallback_model' => Request::str('fallback_model', 80),
            'temperature' => Request::input('temperature'), 'max_tokens' => Request::input('max_tokens'), 'autonomy_level' => Request::input('autonomy_level'), 'status' => Request::str('status', 20),
            'daily_budget_cents' => Request::input('daily_budget_cents'), 'monthly_budget_cents' => Request::input('monthly_budget_cents'), 'task_budget_cents' => Request::input('task_budget_cents'),
            'max_iterations' => Request::input('max_iterations'), 'tools' => (array)Request::input('tools', [])], $me()['email']);
        Session::flash('success', 'Agent updated (new version saved).');
    }
    View::redirect('/admin/agents/' . $p['slug']);
}, $P('agents.write'));

/* Tasks */
$r->get('/admin/tasks', function () use ($page) {
    [$per, $off] = $page(50);
    $st = preg_replace('/[^a-z_]/', '', (string)($_GET['status'] ?? ''));
    $ag = preg_replace('/[^a-z_]/', '', (string)($_GET['agent'] ?? ''));
    $w = []; $args = [];
    if ($st) { $w[] = 'status = ?'; $args[] = $st; }
    if ($ag) { $w[] = 'agent_slug = ?'; $args[] = $ag; }
    View::page('admin/tasks', ['title' => 'Agent tasks', 'status' => $st, 'rows' => Db::all('SELECT * FROM agent_tasks' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY id DESC LIMIT {$per} OFFSET {$off}", $args),
        'agents' => Db::all('SELECT slug, name FROM agents ORDER BY id')]);
}, $P('agents.read'));
$r->get('/admin/tasks/{id}', function ($p) {
    $t = Db::one('SELECT * FROM agent_tasks WHERE id = ?', [(int)$p['id']]) ?? throw new HttpError(404, 'Task not found.');
    View::page('admin/task', ['title' => $t['title'], 't' => $t, 'runs' => Db::all('SELECT * FROM agent_runs WHERE task_id = ? ORDER BY id', [$t['id']]), 'children' => Db::all('SELECT * FROM agent_tasks WHERE parent_id = ?', [$t['id']])]);
}, $P('agents.read'));
$r->post('/admin/tasks', function () use ($me) {
    $id = Orchestrator::enqueue(Request::str('agent', 60), 'owner_request', Request::str('title', 190), ['brief' => Request::str('brief', 8000)], ['priority' => (int)Request::input('priority', 3), 'created_by' => 'owner:' . $me()['email'],
        'project_id' => (int)Request::input('project_id') ?: null]);
    Audit::log('task.create', 'agent_task', $id);
    View::redirect('/admin/tasks/' . $id);
}, $P('agents.write'));
$r->post('/admin/tasks/{id}/{act}', function ($p) use ($me) {
    match ($p['act']) { 'cancel' => Orchestrator::cancel((int)$p['id'], $me()['email']), 'retry' => Orchestrator::retry((int)$p['id'], $me()['email']), 'run' => Orchestrator::process((int)$p['id']), default => throw new HttpError(404, 'Unknown action') };
    View::redirect('/admin/tasks/' . $p['id']);
}, $P('agents.write'));

/* Workflows */
$r->get('/admin/workflows', fn() => View::page('admin/workflows', ['title' => 'Workflows', 'rows' => Db::all('SELECT * FROM workflows ORDER BY id'),
    'runs' => Db::all('SELECT * FROM workflow_runs ORDER BY id DESC LIMIT 40'), 'templates' => array_keys(Mailer::TEMPLATES)]), $P('workflows.write'));
$r->post('/admin/workflows', function () {
    $slug = preg_replace('/[^a-z0-9_]/', '', strtolower(Request::str('slug', 60)));
    $def = json_decode(Request::str('definition', 50000), true);
    if (!is_array($def)) throw new UserError('Definition must be valid JSON.');
    if ($errs = Workflows::validate($def)) throw new UserError(implode(' ', $errs));
    $row = ['name' => Request::str('name', 120), 'trigger_event' => preg_replace('/[^a-z_.]/', '', Request::str('trigger_event', 60)), 'definition' => jenc($def), 'enabled' => Request::input('enabled') ? 1 : 0, 'updated_at' => now()];
    if (Db::val('SELECT 1 FROM workflows WHERE slug = ?', [$slug])) { Db::run('UPDATE workflows SET version = version + 1 WHERE slug = ?', [$slug]); Db::update('workflows', $row, 'slug = ?', [$slug]); }
    else Db::insert('workflows', $row + ['slug' => $slug, 'version' => 1]);
    Audit::log('workflow.save', 'workflow', $slug);
    Session::flash('success', 'Workflow saved.');
    View::redirect('/admin/workflows');
}, $P('workflows.write'));
$r->post('/admin/workflows/run/{id}', function ($p) {
    Db::run("UPDATE workflow_runs SET status = 'running', attempts = 0, run_after = ?, locked_until = NULL WHERE id = ? AND status = 'failed'", [now(), (int)$p['id']]);
    View::redirect('/admin/workflows');
}, $P('workflows.write'));

/* CRM */
$r->get('/admin/leads', function () {
    $f = $_GET['f'] ?? 'open';
    $where = match ($f) { 'overdue' => "l.next_follow_up_at < ? AND l.status NOT IN ('converted','closed')", 'converted' => "l.status = 'converted'", 'all' => '1=1', default => "l.status NOT IN ('converted','closed')" };
    View::page('admin/leads', ['title' => 'Leads', 'f' => $f, 'm' => Metrics::overview(30),
        'rows' => Db::all("SELECT l.*, c.name, c.email, c.business_name, c.phone FROM leads l JOIN customers c ON c.id = l.customer_id WHERE {$where} ORDER BY l.score DESC, l.id DESC LIMIT 200", $f === 'overdue' ? [now()] : [])]);
}, $P('crm.read'));
$r->post('/admin/leads/{id}', function ($p) {
    $st = Request::str('status', 30);
    if (in_array($st, ['lead', 'qualified', 'proposal_sent', 'awaiting_decision', 'converted', 'closed'], true)) Db::update('leads', ['status' => $st, 'updated_at' => now(), 'next_follow_up_at' => in_array($st, ['converted', 'closed'], true) ? null : now(86400 * 3)], 'id = ?', [(int)$p['id']]);
    Audit::log('lead.status', 'lead', $p['id'], ['status' => $st]);
    View::redirect('/admin/leads');
}, $P('crm.write'));
$r->get('/admin/customers', function () use ($page) {
    [$per, $off] = $page(50);
    $q = Request::str('q', 100);
    $rows = $q ? Db::all("SELECT * FROM customers WHERE email LIKE ? OR name LIKE ? OR business_name LIKE ? ORDER BY id DESC LIMIT {$per} OFFSET {$off}", ["%{$q}%", "%{$q}%", "%{$q}%"])
        : Db::all("SELECT * FROM customers ORDER BY id DESC LIMIT {$per} OFFSET {$off}");
    View::page('admin/customers', ['title' => 'Customers (CRM)', 'rows' => $rows, 'q' => $q]);
}, $P('crm.read'));
$r->get('/admin/customers/{id}', function ($p) {
    $c = Db::one('SELECT * FROM customers WHERE id = ?', [(int)$p['id']]) ?? throw new HttpError(404, 'Customer not found.');
    View::page('admin/customer', ['title' => $c['name'] ?: $c['email'], 'c' => $c, 'timeline' => Crm::timeline((int)$c['id']), 'leads' => Db::all('SELECT * FROM leads WHERE customer_id = ? ORDER BY id DESC', [$c['id']]),
        'projects' => Projects::forCustomer((int)$c['id']), 'services' => Catalog::all(), 'memories' => App\Memory::forCustomer((int)$c['id']),
        'convs' => Db::all('SELECT * FROM conversations WHERE customer_id = ? ORDER BY id DESC LIMIT 10', [$c['id']])]);
}, $P('crm.read'));
$r->post('/admin/customers/{id}', function ($p) {
    $c = Db::one('SELECT * FROM customers WHERE id = ?', [(int)$p['id']]) ?? throw new HttpError(404, 'Not found');
    $st = Request::str('status', 30);
    if ($st) Crm::setStatus((int)$c['id'], $st);
    if (($n = Request::str('note', 4000)) !== '') { Db::update('customers', ['notes' => trim(($c['notes'] ?? '') . "\n[" . gmdate('Y-m-d') . '] ' . $n), 'updated_at' => now()], 'id = ?', [$c['id']]); Audit::log('crm.note', 'customer', $c['id']); }
    if (Request::input('tags') !== null) Db::update('customers', ['tags' => jenc(array_values(array_filter(array_map('trim', explode(',', Request::str('tags', 500)))))), 'updated_at' => now()], 'id = ?', [$c['id']]);
    View::redirect('/admin/customers/' . $c['id']);
}, $P('crm.write'));

/* Proposals */
$r->get('/admin/proposals', fn() => View::page('admin/proposals', ['title' => 'Proposals', 'rows' => Db::all('SELECT p.*, c.email, c.name FROM proposals p LEFT JOIN customers c ON c.id = p.customer_id ORDER BY p.id DESC LIMIT 200'),
    'services' => Catalog::all(), 'customers' => Db::all('SELECT id, email, name FROM customers ORDER BY id DESC LIMIT 300')]), $P('crm.read'));
$r->post('/admin/proposals', function () use ($me) {
    $items = [];
    foreach ((array)Request::input('slugs', []) as $i => $slug) if ($slug) $items[] = ['slug' => $slug, 'opts' => array_filter(['province' => ((array)Request::input('provinces', []))[$i] ?? null, 'tld' => ((array)Request::input('tlds', []))[$i] ?? null])];
    $prop = Proposals::create((int)Request::input('customer_id') ?: null, Request::str('title', 190), $items, ['scope' => Request::str('scope', 8000)], 'owner:' . $me()['email']);
    if (Request::input('send')) Proposals::send((int)$prop['id']);
    Session::flash('success', 'Proposal ' . $prop['number'] . ' created.');
    View::redirect('/admin/proposals');
}, $P('crm.write'));
$r->post('/admin/proposals/{id}/send', function ($p) { Proposals::send((int)$p['id']); Session::flash('success', 'Proposal sent.'); View::redirect('/admin/proposals'); }, $P('crm.write'));

/* Orders */
$r->get('/admin/orders', function () use ($page) {
    [$per, $off] = $page(50);
    $st = preg_replace('/[^a-z_]/', '', (string)($_GET['status'] ?? ''));
    View::page('admin/orders', ['title' => 'Orders', 'status' => $st, 'rows' => Db::all('SELECT o.*, c.email, c.name FROM orders o JOIN customers c ON c.id = o.customer_id' . ($st ? ' WHERE o.status = ?' : '') . " ORDER BY o.id DESC LIMIT {$per} OFFSET {$off}", $st ? [$st] : [])]);
}, $P('orders.read'));
$r->get('/admin/orders/{num}', function ($p) {
    $o = Db::one('SELECT o.*, c.email, c.name, c.id AS cid FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.number = ?', [$p['num']]) ?? throw new HttpError(404, 'Order not found.');
    View::page('admin/order', ['title' => 'Order ' . $o['number'], 'o' => $o, 'items' => Orders::items((int)$o['id']), 'payments' => Db::all('SELECT * FROM payments WHERE order_id = ?', [$o['id']]), 'projects' => Db::all('SELECT * FROM projects WHERE order_id = ?', [$o['id']])]);
}, $P('orders.read'));
$r->post('/admin/orders/{num}/mark-paid', function ($p) use ($me) {
    $o = Db::one('SELECT * FROM orders WHERE number = ?', [$p['num']]) ?? throw new HttpError(404, 'Not found');
    $ref = Request::str('reference', 120);
    if ($ref === '') throw new UserError('Enter the payment reference (e.g. e-Transfer or cheque number).');
    Orders::markPaid((int)$o['id'], 'manual', 'manual_' . $o['number'] . '_' . preg_replace('/[^A-Za-z0-9]/', '', $ref), (int)$o['total_cents']);
    Audit::log('order.mark_paid_manual', 'order', $o['number'], ['by' => $me()['email'], 'ref' => $ref]);
    Workflows::tick();
    View::redirect('/admin/orders/' . $o['number']);
}, $P('orders.write'));

/* Projects */
$r->get('/admin/projects', fn() => View::page('admin/projects', ['title' => 'Projects', 'rows' => Db::all('SELECT p.*, c.name, c.email FROM projects p JOIN customers c ON c.id = p.customer_id ORDER BY p.updated_at DESC LIMIT 200')]), $P('projects.read'));
$r->get('/admin/projects/{num}', function ($p) {
    $pr = Db::one('SELECT p.*, c.name, c.email FROM projects p JOIN customers c ON c.id = p.customer_id WHERE p.number = ?', [$p['num']]) ?? throw new HttpError(404, 'Project not found.');
    View::page('admin/project', ['title' => $pr['title'], 'p' => $pr, 'milestones' => Db::all('SELECT * FROM project_milestones WHERE project_id = ? ORDER BY sort', [$pr['id']]),
        'activity' => Db::all('SELECT * FROM project_activity WHERE project_id = ? ORDER BY id DESC LIMIT 100', [$pr['id']]), 'deliverables' => Db::all('SELECT * FROM deliverables WHERE project_id = ? ORDER BY id DESC', [$pr['id']]),
        'tasks' => Db::all('SELECT * FROM agent_tasks WHERE project_id = ? ORDER BY id DESC', [$pr['id']]), 'docs' => Db::all('SELECT * FROM documents WHERE project_id = ?', [$pr['id']])]);
}, $P('projects.read'));
$r->post('/admin/projects/{num}', function ($p) use ($me) {
    $pr = Db::one('SELECT * FROM projects WHERE number = ?', [$p['num']]) ?? throw new HttpError(404, 'Not found');
    $act = Request::str('action', 30);
    if ($act === 'status') Projects::setStatus((int)$pr['id'], Request::str('status', 30), 'owner', Request::str('note', 500) ?: null);
    elseif ($act === 'milestone') Projects::completeMilestone((int)Request::input('milestone_id'), 'owner');
    elseif ($act === 'note') Projects::activity((int)$pr['id'], 'note', Request::str('note', 500), 'team', (bool)Request::input('visible'));
    elseif ($act === 'deliver') {
        Db::update('deliverables', ['status' => 'delivered', 'updated_at' => now()], 'id = ? AND project_id = ?', [(int)Request::input('deliverable_id'), $pr['id']]);
        Projects::activity((int)$pr['id'], 'delivered', 'Deliverable ready for your review.', 'team');
    } elseif ($act === 'upload' && !empty($_FILES['file']['name'])) {
        $d = Documents::store($_FILES['file'], (int)$pr['customer_id'], (int)$pr['id'], 'staff:' . $me()['email'], 'deliverable');
        Projects::activity((int)$pr['id'], 'file', 'New file available: ' . $d['filename'], 'team');
    } elseif ($act === 'plan') {
        Orchestrator::enqueue('project_manager', 'plan_project', 'Plan ' . $pr['number'], ['brief' => 'Owner requested (re)planning.'], ['project_id' => $pr['id'], 'customer_id' => $pr['customer_id'], 'priority' => 3, 'created_by' => 'owner']);
    }
    View::redirect('/admin/projects/' . $pr['number']);
}, $P('projects.write'));
$r->post('/admin/deliverables/{id}', function ($p) {
    $d = Db::one('SELECT * FROM deliverables WHERE id = ?', [(int)$p['id']]) ?? throw new HttpError(404, 'Not found');
    Db::update('deliverables', ['body' => Request::str('body', 60000), 'updated_at' => now()], 'id = ?', [$d['id']]);
    Audit::log('deliverable.edit', 'deliverable', $d['id']);
    View::redirect('/admin/projects/' . Db::val('SELECT number FROM projects WHERE id = ?', [$d['project_id']]));
}, $P('projects.write'));

/* Tickets */
$r->get('/admin/tickets', fn() => View::page('admin/tickets', ['title' => 'Support', 'rows' => Db::all("SELECT t.*, c.email, c.name FROM tickets t LEFT JOIN customers c ON c.id = t.customer_id ORDER BY CASE WHEN t.status IN ('resolved','closed') THEN 1 ELSE 0 END, CASE t.priority WHEN 'high' THEN 0 ELSE 1 END, t.updated_at DESC LIMIT 200")]), $P('tickets.read'));
$r->get('/admin/tickets/{num}', function ($p) {
    $t = Db::one('SELECT t.*, c.email, c.name FROM tickets t LEFT JOIN customers c ON c.id = t.customer_id WHERE t.number = ?', [$p['num']]) ?? throw new HttpError(404, 'Ticket not found.');
    View::page('admin/ticket', ['title' => $t['subject'], 't' => $t, 'messages' => Db::all('SELECT * FROM ticket_messages WHERE ticket_id = ? ORDER BY id', [$t['id']])]);
}, $P('tickets.read'));
$r->post('/admin/tickets/{num}', function ($p) use ($me) {
    $t = Db::one('SELECT * FROM tickets WHERE number = ?', [$p['num']]) ?? throw new HttpError(404, 'Not found');
    if (($body = Request::str('body', 10000)) !== '') {
        $internal = (bool)Request::input('internal');
        Tickets::reply((int)$t['id'], $body, 'staff', $me()['name'], $internal);
        if (!$internal && $t['customer_id'] && ($c = Db::one('SELECT * FROM customers WHERE id = ?', [$t['customer_id']])))
            Mailer::send('ticket_update', $c['email'], ['name' => $c['name'], 'number' => $t['number'], 'subject' => $t['subject'], 'update' => $body, 'link' => url('/portal/support/' . $t['number'])], 'tr:' . $t['id'] . ':' . sha1($body), 'ticket', (int)$t['id']);
    }
    if ($st = Request::str('status', 20)) Db::update('tickets', ['status' => $st, 'resolved_at' => in_array($st, ['resolved', 'closed'], true) ? now() : null, 'updated_at' => now()], 'id = ?', [$t['id']]);
    View::redirect('/admin/tickets/' . $t['number']);
}, $P('tickets.write'));

/* Catalog & pricing */
$r->get('/admin/catalog', fn() => View::page('admin/catalog', ['title' => 'Services & pricing', 'rows' => Catalog::all(false), 'packages' => Db::all('SELECT * FROM packages'), 'coupons' => Db::all('SELECT * FROM coupons ORDER BY id DESC')]), $P('catalog.read'));
$r->get('/admin/catalog/{slug}', function ($p) {
    $s = $p['slug'] === 'new' ? null : (Catalog::find($p['slug'], false) ?? throw new HttpError(404, 'Service not found.'));
    View::page('admin/service', ['title' => $s['name'] ?? 'New service', 's' => $s]);
}, $P('catalog.read'));
$r->post('/admin/catalog/{slug}', function ($p) {
    Catalog::save($_POST, $p['slug'] === 'new' ? null : $p['slug']);
    Session::flash('success', 'Service saved. Pages, cart, proposals and AI now use the new values.');
    View::redirect('/admin/catalog');
}, $P('catalog.write'));
$r->post('/admin/coupons', function () {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', Request::str('code', 40)));
    if ($code === '') throw new UserError('Code required.');
    $type = Request::input('type') === 'fixed' ? 'fixed' : 'percent';
    $val = $type === 'fixed' ? (int)round((float)Request::input('value') * 100) : max(1, min(100, (int)Request::input('value')));
    $row = ['type' => $type, 'value_amount' => $val, 'applies_to' => jenc(array_values(array_filter(array_map('trim', explode(',', Request::str('applies_to', 300) ?: 'all'))))),
        'active' => Request::input('active') ? 1 : 0, 'expires_at' => Request::str('expires_at', 10) ? Request::str('expires_at', 10) . ' 23:59:59' : null, 'max_uses' => (int)Request::input('max_uses') ?: null];
    if (Db::val('SELECT 1 FROM coupons WHERE code = ?', [$code])) Db::update('coupons', $row, 'code = ?', [$code]); else Db::insert('coupons', $row + ['code' => $code, 'uses' => 0, 'created_at' => now()]);
    Audit::log('coupon.save', 'coupon', $code, $row);
    View::redirect('/admin/catalog');
}, $P('catalog.write'));

/* Site editor: change the wording on any public page, and the logo */
$sitePage = function (): string { $p = '/' . ltrim((string)($_GET['page'] ?? $_POST['page'] ?? '/'), '/'); $p = rtrim(preg_replace('#[^a-zA-Z0-9/_\-.]#', '', $p), '/') ?: '/'; return mb_substr($p, 0, 190); };
$r->get('/admin/site', function () use ($sitePage) {
    $pg = $sitePage();
    View::page('admin/site', ['title' => 'Site editor', 'tab' => ($_GET['tab'] ?? '') === 'brand' ? 'brand' : 'pages', 'pg' => $pg, 'pages' => App\SiteText::pages(), 'fields' => App\SiteText::fields($pg),
        'logos' => ['dark' => App\SiteText::asset('logo')['mime'] ?? null, 'light' => App\SiteText::asset('logo_light')['mime'] ?? null], 'wordmark' => Settings::get('site.logo_wordmark', '1') === '1', 'company' => Settings::get('company.name')]);
}, $P('content.write'));
$r->get('/admin/site/fields', function () use ($sitePage) {
    header('Cache-Control: no-store');
    echo View::render('admin/site-fields', ['fields' => App\SiteText::fields($sitePage())]);
}, $P('content.write'));
$r->post('/admin/site', function () use ($sitePage, $me) {
    $pg = $sitePage();
    $changes = jdec(Request::str('changes', 2_000_000), []);
    [$n, $bad] = App\SiteText::save($pg, is_array($changes) ? $changes : [], (string)($me()['email'] ?? 'staff'));
    if ($bad) Session::flash('error', 'Not saved: ' . implode('; ', array_slice($bad, 0, 4)) . (count($bad) > 4 ? ' and ' . (count($bad) - 4) . ' more' : '') . '.');
    if ($n) Session::flash('success', $n === 1 ? 'Saved. The change is live.' : "Saved {$n} changes. They are live.");
    elseif (!$bad) Session::flash('success', 'Nothing changed.');
    View::redirect('/admin/site?page=' . rawurlencode($pg));
}, $P('content.write'));
$r->post('/admin/site/brand', function () {
    $act = Request::str('action', 20);
    $slot = Request::str('slot', 10) === 'light' ? 'light' : 'dark';
    $names = ['dark' => 'dark logo', 'light' => 'white logo'];
    try {
        if ($act === 'remove') { App\SiteText::removeAsset(App\SiteText::LOGO_SLOTS[$slot]); Session::flash('success', 'Removed the ' . $names[$slot] . '.'); }
        elseif ($act === 'reset') { foreach (App\SiteText::LOGO_SLOTS as $k) App\SiteText::removeAsset($k); Session::flash('success', 'The original 818 logo is back.'); }
        elseif ($act === 'upload') { App\SiteText::saveLogo($_FILES['logo'] ?? [], $slot); Session::flash('success', 'New ' . $names[$slot] . ' saved. The website now uses it.'); }
        else { Settings::set('site.logo_wordmark', Request::input('wordmark') ? '1' : '0'); Session::flash('success', 'Saved.'); }
    } catch (UserError $e) { Session::flash('error', $e->getMessage()); }
    View::redirect('/admin/site?tab=brand');
}, $P('content.write'));

/* Content studio */
$r->get('/admin/content', fn() => View::page('admin/content', ['title' => 'Content studio', 'rows' => Db::all('SELECT * FROM content_items ORDER BY updated_at DESC LIMIT 200'), 'item' => isset($_GET['edit']) ? Db::one('SELECT * FROM content_items WHERE id = ?', [(int)$_GET['edit']]) : null,
    'versions' => isset($_GET['edit']) ? Db::all('SELECT version, author, created_at FROM content_versions WHERE content_id = ? ORDER BY version DESC', [(int)$_GET['edit']]) : []]), $P('content.read'));
$r->post('/admin/content', function () use ($me) {
    $id = (int)Request::input('id');
    $act = Request::str('action', 20);
    if ($act === 'generate') {
        $tid = Orchestrator::enqueue(Request::input('kind') === 'social' ? 'social' : 'content', 'content_draft', 'Draft: ' . Request::str('topic', 160), ['brief' => 'Write a ' . Request::str('kind', 20) . ' about "' . Request::str('topic', 300) . '" for ' . (Request::str('audience', 200) ?: 'Canadian small-business owners') . '. Tone: ' . (Request::str('tone', 60) ?: 'friendly, expert') . '. Primary keyword: ' . Request::str('keyword', 120) . '. Save it with create_content_draft. Only use facts you can support; mark anything uncertain.'], ['priority' => 4, 'created_by' => 'owner']);
        Session::flash('success', 'Draft requested — the Content agent will save it here for review (task #' . $tid . ').');
        View::redirect('/admin/content');
    }
    $row = ['title' => Request::str('title', 190), 'body' => Request::str('body', 60000), 'excerpt' => Request::str('excerpt', 400), 'keyword' => Request::str('keyword', 120), 'kind' => Request::str('kind', 30) ?: 'blog', 'updated_at' => now()];
    $row['seo_score'] = Seo::score($row['title'], $row['body'], $row['keyword']); $row['readability'] = Seo::readability($row['body']);
    if (in_array($act, ['approve', 'publish', 'schedule', 'draft'], true)) $row['status'] = ['approve' => 'approved', 'publish' => 'published', 'schedule' => 'scheduled', 'draft' => 'draft'][$act];
    if ($act === 'schedule') $row['publish_at'] = (Request::str('publish_at', 16) ?: gmdate('Y-m-d')) . ' 12:00:00';
    if ($act === 'publish') $row['publish_at'] = now();
    if ($id) {
        $old = Db::one('SELECT * FROM content_items WHERE id = ?', [$id]);
        Db::insert('content_versions', ['content_id' => $id, 'version' => $old['version'], 'title' => $old['title'], 'body' => $old['body'], 'author' => $old['author'], 'created_at' => now()]);
        Db::update('content_items', $row + ['version' => (int)$old['version'] + 1, 'author' => 'owner'], 'id = ?', [$id]);
    } else {
        $id = Db::insert('content_items', $row + ['slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($row['title'])), '-') . '-' . substr(rid(2), 0, 4), 'status' => $row['status'] ?? 'draft', 'meta' => '{}', 'author' => 'owner', 'created_at' => now()]);
    }
    Audit::log('content.' . ($act ?: 'save'), 'content', $id, ['by' => $me()['email']]);
    View::redirect('/admin/content?edit=' . $id);
}, $P('content.write'));

/* Knowledge base */
$r->get('/admin/kb', fn() => View::page('admin/kb', ['title' => 'Knowledge base', 'rows' => Db::all('SELECT * FROM kb_articles ORDER BY category, title'), 'item' => isset($_GET['edit']) ? Db::one('SELECT * FROM kb_articles WHERE id = ?', [(int)$_GET['edit']]) : null]), $P('kb.write'));
$r->post('/admin/kb', function () use ($me) {
    if (!empty($_FILES['file']['name'])) {
        $ext = strtolower(pathinfo((string)$_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['txt', 'md', 'csv'], true) || $_FILES['file']['size'] > 1_000_000) throw new UserError('Upload a .txt, .md or .csv file under 1 MB.');
        Kb::save(['title' => Request::str('title', 190) ?: pathinfo((string)$_FILES['file']['name'], PATHINFO_FILENAME), 'category' => Request::str('category', 40), 'body' => (string)file_get_contents($_FILES['file']['tmp_name']), 'tags' => Request::str('tags', 255)], null, $me()['email']);
    } else Kb::save($_POST, (int)Request::input('id') ?: null, $me()['email']);
    Session::flash('success', 'Knowledge saved — agents will use it immediately.');
    View::redirect('/admin/kb');
}, $P('kb.write'));

/* Finance */
$r->get('/admin/finance', function () {
    View::page('admin/finance', ['title' => 'Finance', 'm30' => Metrics::overview(30), 'm365' => Metrics::overview(365),
        'payments' => Db::all('SELECT p.*, o.number FROM payments p LEFT JOIN orders o ON o.id = p.order_id ORDER BY p.id DESC LIMIT 50'),
        'subs' => Db::all('SELECT s.*, c.email FROM subscriptions s JOIN customers c ON c.id = s.customer_id ORDER BY s.id DESC LIMIT 100'),
        'expenses' => Db::all('SELECT * FROM expenses ORDER BY incurred_on DESC LIMIT 100'),
        'byMonth' => Db::all("SELECT substr(paid_at,1,7) AS month, SUM(total_cents) AS cents, COUNT(*) AS n FROM orders WHERE status IN ('paid','processing','completed') AND paid_at IS NOT NULL GROUP BY substr(paid_at,1,7) ORDER BY month DESC LIMIT 12"),
        'aiByMonth' => Db::all('SELECT substr(created_at,1,7) AS month, SUM(cost_micros) AS micros FROM ai_usage GROUP BY substr(created_at,1,7) ORDER BY month DESC LIMIT 12')]);
}, $P('finance.read'));
$r->post('/admin/finance/expense', function () {
    Db::insert('expenses', ['category' => Request::str('category', 40), 'vendor' => Request::str('vendor', 120), 'amount_cents' => (int)round((float)Request::input('amount') * 100),
        'incurred_on' => Request::str('incurred_on', 10) ?: gmdate('Y-m-d'), 'note' => Request::str('note', 255), 'created_at' => now()]);
    Audit::log('expense.create', 'expense', null);
    View::redirect('/admin/finance');
}, $P('finance.read'));

/* Growth: opportunities & reports */
$r->get('/admin/growth', fn() => View::page('admin/growth', ['title' => 'Growth & opportunities', 'opps' => Db::all('SELECT * FROM opportunities ORDER BY score DESC, id DESC LIMIT 50'), 'm' => Metrics::overview(30),
    'reports' => Db::all("SELECT * FROM email_log WHERE template = 'owner_report' ORDER BY id DESC LIMIT 10")]), $P('dashboard.view'));
$r->post('/admin/growth/research', function () {
    Orchestrator::enqueue('research', 'research_opportunities', 'Find three business opportunities', ['brief' => 'Identify three opportunities for 818 Digital Agency relevant to Canadian (especially Atlantic) small businesses. Base them on our catalog and business metrics. Save each with save_opportunity, cite sources you actually know, and label assumptions. Focus: ' . Request::str('focus', 300)], ['priority' => 4, 'created_by' => 'owner']);
    Session::flash('success', 'Research task queued. Results appear here when the Research agent finishes.');
    View::redirect('/admin/growth');
}, $P('agents.write'));

/* Integrations */
$r->get('/admin/integrations', function () {
    $rows = [];
    foreach (Integrations::DEFS as $slug => [$cat, $name, $fields, $desc]) {
        $row = Db::one('SELECT * FROM integrations WHERE slug = ?', [$slug]);
        $configured = Integrations::configured($slug);
        $stale = !$configured && !empty($row['secrets_enc']) && $row['secrets_enc'] !== '{}' && App\Crypto::decrypt((string)$row['secrets_enc']) === null;
        $rows[] = ['slug' => $slug, 'cat' => $cat, 'name' => $name, 'desc' => $desc, 'fields' => $fields, 'row' => $row, 'configured' => $configured, 'stale' => $stale,
            'env' => array_filter($fields, fn($env) => (bool)Env::get($env)), 'logs' => Db::all('SELECT * FROM integration_logs WHERE slug = ? ORDER BY id DESC LIMIT 3', [$slug])];
    }
    // Connected first (with errors at the top of that group), then ones needing attention, then the rest.
    usort($rows, fn($a, $b) => [$a['configured'] ? 0 : ($a['stale'] ? 1 : 2), $a['configured'] && !empty($a['row']['last_error']) ? 0 : 1, $a['name']]
        <=> [$b['configured'] ? 0 : ($b['stale'] ? 1 : 2), $b['configured'] && !empty($b['row']['last_error']) ? 0 : 1, $b['name']]);
    View::page('admin/integrations', ['title' => 'Integrations', 'rows' => $rows]);
}, $P('integrations.read'));
$r->post('/admin/integrations/{slug}', function ($p) {
    if (Request::input('test')) { $res = Integrations::test($p['slug']); Session::flash($res['ok'] ? 'success' : 'error', $res['message']); }
    else {
        Integrations::save($p['slug'], (array)Request::input('secret', []), (array)Request::input('setting', []));
        Session::flash('success', 'Saved (encrypted). Use "Test connection" to verify.');
    }
    View::redirect('/admin/integrations#' . $p['slug']);
}, $P('integrations.write'));

/* Settings, users, security */
$r->get('/admin/settings', function () use ($me) {
    $vals = [];
    foreach (Settings::DEFAULTS as $k => $_) $vals[$k] = Settings::get($k);
    View::page('admin/settings', ['title' => 'Settings', 'vals' => $vals, 'users' => Db::all("SELECT id, email, name, role, status, mfa_enabled, last_login_at FROM users WHERE role <> 'customer' ORDER BY id"),
        'perms' => Db::all('SELECT * FROM role_permissions ORDER BY role, permission'), 'me' => $me(), 'mfa_secret' => $_SESSION['mfa_setup'] ?? null, 'recovery' => (function () { $c = $_SESSION['mfa_recovery_show'] ?? null; unset($_SESSION['mfa_recovery_show']); return $c; })()]);
}, $P('settings.read'));
$r->post('/admin/settings', function () use ($me) {
    $act = Request::str('action', 30);
    if ($act === 'settings') {
        if (!Rbac::can($me(), 'settings.write')) throw new HttpError(403, 'No permission.');
        foreach ((array)Request::input('s', []) as $k => $v) if (array_key_exists($k, Settings::DEFAULTS)) Settings::set($k, mb_substr(trim((string)$v), 0, 2000));
    } elseif ($act === 'mfa_start') {
        $_SESSION['mfa_setup'] ??= Totp::secret(); // keep the same key if the page is reloaded, so a scanned code keeps working
    } elseif ($act === 'mfa_restart') {
        $_SESSION['mfa_setup'] = Totp::secret();
    } elseif ($act === 'mfa_confirm') {
        $sec = $_SESSION['mfa_setup'] ?? throw new UserError('Start two-step setup first.');
        if (!Totp::verify($sec, Request::str('code', 10))) { Session::flash('error', 'That code did not match. Use the newest code in the app (it changes every 30 seconds). If it still fails, check that your phone sets its time automatically, or press "Start again" and scan the new QR code.'); View::redirect('/admin/settings#mfa'); return; }
        Db::update('users', ['mfa_secret_enc' => Crypto::encrypt($sec), 'mfa_enabled' => 1, 'updated_at' => now()], 'id = ?', [$me()['id']]);
        unset($_SESSION['mfa_setup']);
        $_SESSION['mfa_recovery_show'] = App\Auth::newRecoveryCodes((int)$me()['id']);
        Audit::log('user.mfa_enabled', 'user', $me()['id']);
    } elseif ($act === 'mfa_disable') {
        if (!password_verify((string)Request::input('password', ''), (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$me()['id']]))) throw new UserError('Enter your current password to turn off two-step verification.');
        Db::update('users', ['mfa_secret_enc' => null, 'mfa_enabled' => 0, 'updated_at' => now()], 'id = ?', [$me()['id']]);
        Settings::set('mfa.recovery.' . $me()['id'], '');
        Audit::security('mfa.disabled', (string)$me()['id']);
    } elseif ($act === 'user') {
        if ($me()['role'] !== 'owner') throw new HttpError(403, 'Only the owner can manage team accounts.');
        $role = in_array(Request::str('role', 20), ['admin', 'staff'], true) ? Request::str('role', 20) : 'staff';
        $email = strtolower(Request::str('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Valid email required.');
        $pw = rid(9);
        if (Db::val('SELECT 1 FROM users WHERE email = ?', [$email])) Db::update('users', ['role' => $role, 'updated_at' => now()], 'email = ?', [$email]);
        else { Db::insert('users', ['email' => $email, 'name' => Request::str('name', 190) ?: $email, 'password_hash' => Auth::hash($pw), 'role' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $t = Auth::createResetToken($email); Mailer::send('password_reset', $email, ['link' => url('/reset/' . $t)]); }
        Audit::log('user.team_upsert', 'user', $email, ['role' => $role]);
    } elseif ($act === 'perm') {
        if ($me()['role'] !== 'owner') throw new HttpError(403, 'Only the owner can change permissions.');
        $role = Request::str('role', 20); $perm = preg_replace('/[^a-z._]/', '', Request::str('permission', 60));
        if (!in_array($role, ['admin', 'staff'], true)) throw new UserError('Invalid role.');
        if (Request::input('grant')) { if (!Db::val('SELECT 1 FROM role_permissions WHERE role = ? AND permission = ?', [$role, $perm])) Db::insert('role_permissions', ['role' => $role, 'permission' => $perm]); }
        else Db::run('DELETE FROM role_permissions WHERE role = ? AND permission = ?', [$role, $perm]);
        Rbac::flush();
        Audit::log('rbac.change', 'role', $role, ['permission' => $perm, 'grant' => (bool)Request::input('grant')]);
    }
    Settings::flush();
    Session::flash('success', 'Saved.');
    View::redirect('/admin/settings');
}, $P('settings.read'));

$r->get('/admin/audit', function () use ($page) {
    [$per, $off] = $page(100);
    View::page('admin/audit', ['title' => 'Audit log', 'rows' => Db::all("SELECT * FROM audit_log ORDER BY id DESC LIMIT {$per} OFFSET {$off}"), 'sec' => Db::all('SELECT * FROM security_events ORDER BY id DESC LIMIT 50')]);
}, $P('audit.read'));

$r->get('/admin/system', function () {
    View::page('admin/system', ['title' => 'System health', 'cron' => Settings::get('system.cron_last_run'), 'cronStats' => Settings::get('system.cron_last_stats'),
        'webhooks' => Db::all('SELECT * FROM webhook_events ORDER BY id DESC LIMIT 25'), 'emails' => Db::all('SELECT * FROM email_log ORDER BY id DESC LIMIT 30'),
        'wfFailed' => Db::all("SELECT * FROM workflow_runs WHERE status = 'failed' ORDER BY id DESC LIMIT 20"), 'intErrors' => Db::all('SELECT * FROM integration_logs WHERE ok = 0 ORDER BY id DESC LIMIT 20'),
        'backups' => array_map(fn($f) => ['name' => basename($f), 'size' => filesize($f), 'at' => gmdate('Y-m-d H:i', filemtime($f))], array_reverse(glob(STORAGE_ROOT . '/backups/*.gz') ?: [])),
        'logs' => is_file($lf = STORAGE_ROOT . '/logs/app-' . gmdate('Y-m-d') . '.log') ? array_slice(file($lf, FILE_IGNORE_NEW_LINES) ?: [], -40) : [],
        'php' => PHP_VERSION, 'driver' => Db::$driver, 'imap' => function_exists('imap_open'), 'version' => APP_VERSION]);
}, $P('settings.read'));
$r->post('/admin/system/backup', function () { $f = App\Backup::run(); Session::flash('success', 'Backup written: ' . basename($f)); View::redirect('/admin/system'); }, $P('settings.write'));
$r->post('/admin/system/cron', function () { App\Cron::run(25); Session::flash('success', 'Cron cycle executed.'); View::redirect('/admin/system'); }, $P('settings.write'));

/* Government filings */
$r->get('/admin/filings', function () {
    $st = preg_replace('/[^a-z_]/', '', (string)($_GET['status'] ?? ''));
    $modes = [];
    foreach (App\Filings::JURISDICTIONS as $j => $_) $modes[$j] = Settings::get('filing.mode.' . $j, 'auto');
    View::page('admin/filings', ['title' => 'Government filings', 'status' => $st, 'modes' => $modes,
        'rows' => Db::all('SELECT f.*, c.name, c.email FROM filings f JOIN customers c ON c.id = f.customer_id' . ($st ? ' WHERE f.status = ?' : '') . ' ORDER BY f.updated_at DESC LIMIT 200', $st ? [$st] : []),
        'bc' => Integrations::configured('bc_registry')]);
}, $P('projects.read'));
$r->post('/admin/filings/policy', function () {
    foreach ((array)Request::input('mode', []) as $j => $m) if (isset(App\Filings::JURISDICTIONS[$j]) && in_array($m, ['auto', 'approval', 'manual'], true)) Settings::set('filing.mode.' . $j, $m);
    Session::flash('success', 'Filing policy saved.');
    View::redirect('/admin/filings');
}, $P('settings.write'));
$r->get('/admin/filings/{num}', function ($p) {
    $f = Db::one('SELECT * FROM filings WHERE number = ?', [$p['num']]) ?? throw new HttpError(404, 'Filing not found.');
    View::page('admin/filing', ['title' => 'Filing ' . $f['number'], 'f' => $f, 'v' => jdec($f['validation']), 'gates' => App\Filings::gates($f), 'sheet' => App\Filings::sheet($f),
        'c' => Db::one('SELECT * FROM customers WHERE id = ?', [$f['customer_id']]), 'proj' => $f['project_id'] ? Db::one('SELECT * FROM projects WHERE id = ?', [$f['project_id']]) : null,
        'events' => Db::all('SELECT * FROM filing_events WHERE filing_id = ? ORDER BY id DESC', [$f['id']]), 'channel' => App\RegistryConnectors::for($f)]);
}, $P('projects.read'));
$r->post('/admin/filings/{num}', function ($p) use ($me) {
    $f = Db::one('SELECT * FROM filings WHERE number = ?', [$p['num']]) ?? throw new HttpError(404, 'Not found');
    $act = Request::str('action', 20); $who = 'owner:' . $me()['email'];
    if ($act === 'submit') { $res = App\Filings::submit((int)$f['id'], $who, true); Session::flash($res['ok'] ? 'success' : 'error', 'Submit: ' . ($res['status'] ?? '') . ' ' . ($res['reason'] ?? '')); }
    elseif ($act === 'accepted') {
        $doc = !empty($_FILES['file']['name']) ? App\Documents::store($_FILES['file'], (int)$f['customer_id'], $f['project_id'] ? (int)$f['project_id'] : null, $who, 'registry_document') : null;
        if (Request::str('registry_number', 80) === '') throw new UserError('Enter the registry / corporation number issued.');
        App\Filings::recordOutcome($f, true, Request::str('registry_number', 80), null, $who, $doc ? (int)$doc['id'] : null);
        Db::run("UPDATE approvals SET status = 'approved', decided_by = ?, decided_at = ?, decision_note = 'Filed on portal' WHERE status = 'pending' AND subject_type = 'filing' AND subject_id = ?", [$me()['email'], now(), $f['id']]);
        Session::flash('success', 'Recorded. The customer has been notified and the project advanced.');
    } elseif ($act === 'rejected') { App\Filings::recordOutcome($f, false, null, Request::str('reason', 2000) ?: 'Rejected', $who); }
    elseif ($act === 'cancel') { Db::update('filings', ['status' => 'cancelled', 'updated_at' => now()], 'id = ?', [$f['id']]); App\Filings::event((int)$f['id'], 'status', 'cancelled', $who); }
    Audit::log('filing.admin_' . $act, 'filing', $f['number']);
    View::redirect('/admin/filings/' . $f['number']);
}, $P('projects.write'));

/* ── Growth: unfinished forms, blog engine, agent simulator, demo data ── */
$r->get('/admin/drafts', function () {
    $st = in_array($_GET['status'] ?? 'open', ['open', 'followed_up', 'completed', 'dismissed', 'all'], true) ? ($_GET['status'] ?? 'open') : 'open';
    $rows = $st === 'all' ? Db::all('SELECT * FROM form_drafts ORDER BY updated_at DESC LIMIT 200') : Db::all('SELECT * FROM form_drafts WHERE status = ? ORDER BY updated_at DESC LIMIT 200', [$st]);
    $counts = []; foreach (Db::all('SELECT status, COUNT(*) n FROM form_drafts GROUP BY status') as $c) $counts[$c['status']] = (int)$c['n'];
    View::page('admin/drafts', ['title' => 'Unfinished forms', 'rows' => $rows, 'status' => $st, 'counts' => $counts]);
}, $P('crm.read'));
$r->post('/admin/drafts/{id}', function ($p) use ($me) {
    $id = (int)$p['id']; $a = Request::str('action', 20);
    if ($a === 'lead') { $l = App\FormDrafts::toLead($id, $me()['email']); Session::flash('success', 'Lead created. Follow up from the Leads page.'); View::redirect('/admin/leads'); return; }
    if (in_array($a, ['dismiss', 'reopen'], true)) Db::update('form_drafts', ['status' => $a === 'dismiss' ? 'dismissed' : 'open', 'updated_at' => now()], 'id = ?', [$id]);
    if ($a === 'note') Db::update('form_drafts', ['notes' => Request::str('notes', 2000), 'updated_at' => now()], 'id = ?', [$id]);
    Session::flash('success', 'Saved.'); View::redirect('/admin/drafts');
}, $P('crm.write'));

$r->get('/admin/blog', function () {
    View::page('admin/blog', ['title' => 'Blog engine', 'posts' => Db::all("SELECT * FROM content_items WHERE kind = 'blog' ORDER BY id DESC LIMIT 100"),
        'topics' => App\BlogEngine::TOPICS, 'next' => App\BlogEngine::nextTopic(), 'last' => Settings::get('blog.last_generated'),
        'enabled' => Settings::get('blog.enabled', '1') === '1', 'autopublish' => Settings::get('blog.autopublish', '1') === '1',
        'ai' => Integrations::configured('anthropic') || Integrations::configured('openai')]);
}, $P('content.read'));
$r->post('/admin/blog', function () use ($me) {
    $a = Request::str('action', 20);
    if ($a === 'settings') {
        Settings::set('blog.enabled', !empty($_POST['enabled']) ? '1' : '0'); Settings::set('blog.autopublish', !empty($_POST['autopublish']) ? '1' : '0');
        Session::flash('success', 'Blog settings saved.');
    } elseif ($a === 'generate') {
        $ti = ($_POST['topic'] ?? '') === '' ? null : (int)$_POST['topic'];
        $post = App\BlogEngine::generate($ti, $me()['email']);
        Session::flash('success', ($post['status'] === 'published' ? 'Published: ' : 'Ready for review: ') . $post['title']);
    } else {
        $id = (int)Request::str('id', 12);
        if ($a === 'publish') { Db::update('content_items', ['status' => 'published', 'publish_at' => now(), 'updated_at' => now()], "id = ? AND kind = 'blog'", [$id]);
            $sl = Db::val('SELECT slug FROM content_items WHERE id = ?', [$id]); if ($sl) App\Seo::indexNow(['/blog/' . $sl, '/blog']); }
        if ($a === 'unpublish') Db::update('content_items', ['status' => 'review', 'updated_at' => now()], "id = ? AND kind = 'blog'", [$id]);
        if ($a === 'delete') Db::run("DELETE FROM content_items WHERE id = ? AND kind = 'blog'", [$id]);
        Audit::log('blog.' . $a, 'content', (string)$id, ['by' => $me()['email']]);
        Session::flash('success', 'Done.');
    }
    View::redirect('/admin/blog');
}, $P('content.write'));

$r->get('/admin/simulator', function () {
    View::page('admin/simulator', ['title' => 'Agent simulator', 'results' => $_SESSION['sim_results'] ?? null, 'ran_at' => $_SESSION['sim_at'] ?? null, 'agents' => App\Agents::DEFS]);
}, $P('agents.read'));
$r->post('/admin/simulator', function () use ($me) {
    $only = Request::str('agent', 60) ?: null;
    if ($only && !isset(App\Agents::DEFS[$only])) $only = null;
    @set_time_limit(120);
    $_SESSION['sim_results'] = App\Simulator::run($only);
    $_SESSION['sim_at'] = now();
    Audit::log('agents.simulated', 'agent', $only ?? 'all', ['by' => $me()['email'], 'passed' => count(array_filter($_SESSION['sim_results'], fn($x) => $x['ok']))]);
    View::redirect('/admin/simulator');
}, $P('agents.write'));

$r->get('/admin/demo', function () {
    $by = Db::all('SELECT tbl, COUNT(*) n FROM demo_records GROUP BY tbl ORDER BY tbl');
    View::page('admin/demo', ['title' => 'Demo data', 'count' => App\Demo::count(), 'by' => $by]);
}, $P('settings.read'));
$r->post('/admin/demo', function () {
    if (Request::str('action', 10) === 'seed') { $n = App\Demo::seed(); Session::flash('success', 'Demo data loaded: ' . array_sum($n) . ' records.'); }
    else { if (Request::str('confirm', 20) !== 'DELETE') throw new UserError('Type DELETE to confirm.'); $n = App\Demo::purge(); Session::flash('success', "Deleted {$n} demo records. Your real data was not touched."); }
    View::redirect('/admin/demo');
}, $P('settings.write'));

$r->get('/admin/guide', function () {
    View::page('admin/guide', ['title' => 'Agent guide', 'agents' => Db::all('SELECT slug, name, status, autonomy_level, model, tools FROM agents ORDER BY id'),
        'ai' => Integrations::configured('anthropic') || Integrations::configured('openai')]);
}, $P('agents.read'));

$r->get('/admin/security', function () {
    $res = $_SESSION['sec_results'] ?? null; $at = $_SESSION['sec_at'] ?? null;
    if (!$res || strtotime($at . ' UTC') < time() - 600) { $res = App\SecurityCheck::run(); $at = now(); $_SESSION['sec_results'] = $res; $_SESSION['sec_at'] = $at; }
    View::page('admin/security', ['title' => 'Security check', 'results' => $res, 'ran_at' => $at]);
}, $P('settings.read'));
$r->post('/admin/security', function () use ($me) {
    $res = App\SecurityCheck::run(); $_SESSION['sec_results'] = $res; $_SESSION['sec_at'] = now();
    Audit::log('security.check', 'system', null, ['by' => $me()['email'], 'score' => App\SecurityCheck::score($res)]);
    View::redirect('/admin/security');
}, $P('settings.read'));

$r->get('/admin/search', function () {
    $posts = (int)Db::val("SELECT COUNT(*) FROM content_items WHERE kind = 'blog' AND status = 'published'");
    View::page('admin/search', ['title' => 'Search & AI visibility', 'urls' => count(App\Seo::allUrls()), 'posts' => $posts, 'last' => jdec((string)Settings::get('seo.indexnow_last') ?: 'null'),
        'google' => (string)(Integrations::secret('search_console', 'verification') ?: Settings::get('seo.google_verification')), 'bing' => (string)Settings::get('seo.bing_verification'),
        'sameAs' => (string)Settings::get('seo.same_as'), 'key' => App\Seo::indexNowKey(), 'blogOn' => Settings::get('blog.enabled', '1') === '1']);
}, $P('content.read'));
$r->post('/admin/search', function () use ($me) {
    $a = Request::str('action', 20);
    if ($a === 'save') {
        foreach (['google' => 'seo.google_verification', 'bing' => 'seo.bing_verification'] as $f => $k) {
            $v = trim(Request::str($f, 300));
            if (preg_match('/content="([^"]+)"/', $v, $m)) $v = $m[1]; // accept the whole meta tag pasted in
            Settings::set($k, preg_replace('/[^A-Za-z0-9_\-]/', '', $v));
        }
        Settings::set('seo.same_as', implode(',', array_filter(array_map('trim', preg_split('/[\s,]+/', Request::str('same_as', 2000))), fn($u) => str_starts_with($u, 'https://'))));
        Session::flash('success', 'Saved. Verification tags are now on every page.');
    } elseif ($a === 'ping') {
        $res = App\Seo::indexNow(App\Seo::allUrls());
        Audit::log('seo.indexnow', 'system', null, ['by' => $me()['email']] + $res);
        Session::flash($res['ok'] ? 'success' : 'error', $res['ok'] ? "Sent {$res['count']} pages to Bing, Yandex, Seznam and Naver through IndexNow." : ($res['note'] ?? 'IndexNow did not accept the request (code ' . ($res['code'] ?? 0) . '). It works once the site runs on its production domain.'));
    }
    View::redirect('/admin/search');
}, $P('content.write'));

$r->get('/admin/spam', function () {
    View::page('admin/spam', ['title' => 'Spam shield', 's' => App\Spam::stats(7), 'enabled' => Settings::get('spam.enabled', '1') === '1', 'maxLinks' => (int)Settings::get('spam.max_links', '2'), 'blocked' => (string)Settings::get('spam.blocked_ips')]);
}, $P('settings.read'));
$r->post('/admin/spam', function () use ($me) {
    if ($ip = Request::str('block', 45)) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) Settings::set('spam.blocked_ips', implode(',', array_unique(array_merge(App\Spam::blocked(), [$ip]))));
        Session::flash('success', 'Network blocked.');
    } else {
        Settings::set('spam.enabled', !empty($_POST['enabled']) ? '1' : '0');
        Settings::set('spam.max_links', (string)max(0, min(10, (int)Request::input('max_links', 2))));
        Settings::set('spam.blocked_ips', implode(',', array_filter(array_map('trim', explode(',', Request::str('blocked_ips', 4000))), fn($i) => filter_var($i, FILTER_VALIDATE_IP))));
        Session::flash('success', 'Spam shield saved.');
    }
    Audit::log('spam.settings', 'system', null, ['by' => $me()['email']]);
    View::redirect('/admin/spam');
}, $P('settings.write'));

$r->get('/admin/automations', function () {
    $php = (PHP_BINARY && !str_contains(PHP_BINARY, 'fpm')) ? PHP_BINARY : '/usr/bin/php';
    View::page('admin/automations', ['title' => 'Automations', 'cron' => Settings::get('system.cron_last_run'), 'cmd' => $php . ' ' . APP_ROOT . '/cron.php',
        'ai' => Integrations::configured('anthropic') || Integrations::configured('openai')]);
}, $P('dashboard.view'));
$r->post('/admin/automations/run', function () use ($me) {
    @set_time_limit(120);
    $k = Request::str('key', 40);
    if ($k === 'all') { App\Cron::run(40); Session::flash('success', 'Ran everything that was due.'); }
    elseif (isset(App\Automations::JOBS[$k])) { $r = App\Automations::runOne($k, $me()['email']); Session::flash($r['ok'] ? 'success' : 'error', App\Automations::JOBS[$k][0] . ': ' . $r['note']); }
    View::redirect('/admin/automations');
}, $P('settings.write'));
$r->post('/admin/automations/toggle', function () use ($me) {
    $k = Request::str('key', 40);
    if (isset(App\Automations::JOBS[$k]) && App\Automations::JOBS[$k][4]) {
        $on = !App\Automations::enabled($k); Settings::set("auto.{$k}.on", $on ? '1' : '0');
        Audit::log('automation.' . ($on ? 'on' : 'off'), 'system', $k, ['by' => $me()['email']]);
    }
    View::redirect('/admin/automations');
}, $P('settings.write'));

$r->get('/admin/update', fn() => View::page('admin/update', ['title' => 'Install an update', 'why' => App\Updater::supported(), 'backups' => App\Updater::backups()]), $P('settings.write'));
$r->post('/admin/update', function () use ($me) {
    if (($me()['role'] ?? '') !== 'owner') throw new UserError('Only the owner can install updates.');
    @set_time_limit(180);
    if (Request::str('action', 10) === 'rollback') {
        $r = App\Updater::rollback(Request::str('backup', 120), $me()['email']);
        Session::flash('success', "Restored version {$r['version']} ({$r['files']} files).");
    } else {
        $f = $_FILES['package'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) throw new UserError('The upload did not arrive. Check the file is under the size limit and try again.');
        if (empty($_POST['confirm'])) throw new UserError('Tick the box to confirm.');
        $r = App\Updater::apply($f['tmp_name'], $me()['email']);
        Session::flash('success', "Updated from {$r['from']} to {$r['to']} ({$r['files']} files). The previous version is saved for rollback.");
    }
    View::redirect('/admin/update');
}, $P('settings.write'));
