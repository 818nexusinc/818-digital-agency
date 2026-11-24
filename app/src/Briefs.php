<?php
declare(strict_types=1);
namespace App;

/**
 * The Overview: what each AI agent did in the last 24 hours, what is queued, and every dated piece of work
 * coming due in the next 1, 3 or 7 days. Everything is read from live records; nothing is estimated.
 */
final class Briefs
{
    private const OPEN = ['queued', 'running', 'needs_approval', 'needs_info', 'blocked'];

    private static function since(int $hours): string { return gmdate('Y-m-d H:i:s', time() - $hours * 3600); }

    /** Team totals for the header row. */
    public static function pulse(): array
    {
        $d = self::since(24); $today = gmdate('Y-m-d 00:00:00');
        $in = "'" . implode("','", self::OPEN) . "'";
        return [
            'agents_on' => (int)Db::val("SELECT COUNT(*) FROM agents WHERE status = 'enabled'"),
            'agents' => (int)Db::val('SELECT COUNT(*) FROM agents'),
            'done' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'completed' AND finished_at >= ?", [$d]),
            'failed' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'failed' AND updated_at >= ?", [$d]),
            'open' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status IN ({$in})"),
            'waiting' => (int)Db::val("SELECT COUNT(*) FROM approvals WHERE status = 'pending'"),
            'runs' => (int)Db::val('SELECT COUNT(*) FROM agent_runs WHERE created_at >= ?', [$d]),
            'spend_cents' => (int)round((int)Db::val('SELECT COALESCE(SUM(cost_micros),0) FROM agent_runs WHERE created_at >= ?', [$today]) / 10000),
        ];
    }

    /** One brief per agent, busiest and most urgent first. */
    public static function team(): array
    {
        $d = self::since(24); $tomorrow = gmdate('Y-m-d H:i:s', time() + 86400);
        $in = "'" . implode("','", self::OPEN) . "'";
        $by = fn(string $sql, array $p = []) => array_column(Db::all($sql, $p), 'n', 'agent_slug');
        $done = $by("SELECT agent_slug, COUNT(*) n FROM agent_tasks WHERE status = 'completed' AND finished_at >= ? GROUP BY agent_slug", [$d]);
        $failed = $by("SELECT agent_slug, COUNT(*) n FROM agent_tasks WHERE status = 'failed' AND updated_at >= ? GROUP BY agent_slug", [$d]);
        $open = $by("SELECT agent_slug, COUNT(*) n FROM agent_tasks WHERE status IN ({$in}) GROUP BY agent_slug");
        $blocked = $by("SELECT agent_slug, COUNT(*) n FROM agent_tasks WHERE status IN ('needs_approval','needs_info','blocked') GROUP BY agent_slug");
        $dueSoon = $by("SELECT agent_slug, COUNT(*) n FROM agent_tasks WHERE status IN ({$in}) AND COALESCE(deadline, run_after) <= ? GROUP BY agent_slug", [$tomorrow]);
        $runs = $by('SELECT agent_slug, COUNT(*) n FROM agent_runs WHERE created_at >= ? GROUP BY agent_slug', [$d]);
        $cost = $by('SELECT agent_slug, COALESCE(SUM(cost_micros),0) n FROM agent_runs WHERE created_at >= ? GROUP BY agent_slug', [$d]);
        $out = [];
        foreach (Db::all('SELECT slug, name, role, status, autonomy_level FROM agents ORDER BY id') as $a) {
            $s = $a['slug'];
            $recent = Db::all("SELECT summary, status, created_at FROM agent_runs WHERE agent_slug = ? AND created_at >= ? AND summary IS NOT NULL AND summary <> '' ORDER BY id DESC LIMIT 2", [$s, $d]);
            $next = Db::one("SELECT id, title, status, COALESCE(deadline, run_after) at FROM agent_tasks WHERE agent_slug = ? AND status IN ({$in}) ORDER BY COALESCE(deadline, run_after) LIMIT 1", [$s]);
            $b = ['slug' => $s, 'name' => preg_replace('/^AI /', '', $a['name']), 'role' => $a['role'], 'on' => $a['status'] === 'enabled', 'level' => (int)$a['autonomy_level'],
                'done' => (int)($done[$s] ?? 0), 'failed' => (int)($failed[$s] ?? 0), 'open' => (int)($open[$s] ?? 0), 'blocked' => (int)($blocked[$s] ?? 0),
                'due' => (int)($dueSoon[$s] ?? 0), 'runs' => (int)($runs[$s] ?? 0), 'cents' => (int)round((int)($cost[$s] ?? 0) / 10000), 'recent' => $recent, 'next' => $next];
            $b['line'] = self::line($b);
            $b['state'] = !$b['on'] ? 'off' : ($b['failed'] || $b['blocked'] ? 'attn' : ($b['open'] || $b['done'] || $b['runs'] ? 'busy' : 'idle'));
            $out[] = $b;
        }
        $rank = ['attn' => 0, 'busy' => 1, 'idle' => 2, 'off' => 3];
        usort($out, fn($x, $y) => [$rank[$x['state']], -($x['done'] + $x['open'])] <=> [$rank[$y['state']], -($y['done'] + $y['open'])]);
        return $out;
    }

    /** A plain one-line brief built from the agent's own numbers. */
    private static function line(array $b): string
    {
        if (!$b['on']) return 'Switched off.';
        $p = [];
        if ($b['done']) $p[] = 'Finished ' . $b['done'] . ' task' . ($b['done'] === 1 ? '' : 's');
        if ($b['failed']) $p[] = $b['failed'] . ' failed';
        if ($b['blocked']) $p[] = $b['blocked'] . ' waiting on you';
        if ($b['open'] - $b['blocked'] > 0) $p[] = ($b['open'] - $b['blocked']) . ' in the queue';
        if (!$p) return $b['runs'] ? 'Answered ' . $b['runs'] . ' request' . ($b['runs'] === 1 ? '' : 's') . ' in the last 24 hours.' : 'Nothing in the last 24 hours and nothing queued.';
        return implode(', ', $p) . '.';
    }

    /**
     * Dated work coming due within $days days (and anything already overdue), oldest first.
     * Each item: [at, agent_slug, kind, title, detail, href, overdue]
     */
    public static function due(int $days): array
    {
        $end = gmdate('Y-m-d H:i:s', time() + $days * 86400); $now = now();
        $in = "'" . implode("','", self::OPEN) . "'";
        $i = [];
        foreach (Db::all("SELECT id, agent_slug, title, status, kind, COALESCE(deadline, run_after) at FROM agent_tasks WHERE status IN ({$in}) AND COALESCE(deadline, run_after) <= ? ORDER BY at LIMIT 60", [$end]) as $t)
            $i[] = [$t['at'], $t['agent_slug'], 'Task', $t['title'], label($t['status']), '/admin/tasks/' . $t['id']];
        foreach (Db::all("SELECT m.title, m.due_at at, m.agent_slug, p.id pid, p.number, p.title ptitle FROM project_milestones m JOIN projects p ON p.id = m.project_id WHERE m.status <> 'done' AND m.due_at IS NOT NULL AND m.due_at <= ? AND p.status NOT IN ('completed','cancelled') ORDER BY m.due_at LIMIT 40", [$end]) as $m)
            $i[] = [$m['at'], $m['agent_slug'] ?: 'project_manager', 'Milestone', $m['title'], $m['number'] . ' · ' . $m['ptitle'], '/admin/projects/' . $m['number']];
        foreach (Db::all("SELECT id, number, title, deadline at FROM projects WHERE deadline IS NOT NULL AND deadline <= ? AND status NOT IN ('completed','cancelled') ORDER BY deadline LIMIT 30", [$end]) as $p)
            $i[] = [$p['at'], 'project_manager', 'Project deadline', $p['title'], $p['number'], '/admin/projects/' . $p['number']];
        foreach (Db::all("SELECT l.customer_id, l.next_follow_up_at at, l.follow_up_count, c.name, c.email, c.business_name FROM leads l JOIN customers c ON c.id = l.customer_id WHERE l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at <= ? AND l.status NOT IN ('converted','closed','lost') AND l.opted_out = 0 ORDER BY l.next_follow_up_at LIMIT 40", [$end]) as $l)
            $i[] = [$l['at'], 'sales', 'Follow-up', 'Follow up with ' . ($l['name'] ?: $l['email']), ($l['business_name'] ?: 'Lead') . ' · touch ' . ((int)$l['follow_up_count'] + 1), '/admin/customers/' . $l['customer_id']];
        foreach (Db::all("SELECT id, number, filing_type, jurisdiction, status, next_check_at at FROM filings WHERE next_check_at IS NOT NULL AND next_check_at <= ? AND status NOT IN ('completed','rejected','cancelled') ORDER BY next_check_at LIMIT 30", [$end]) as $f)
            $i[] = [$f['at'], 'filing', 'Registry check', 'Check ' . $f['number'] . ' with the registry', label($f['filing_type']) . ' · ' . $f['jurisdiction'] . ' · ' . label($f['status']), '/admin/filings/' . $f['number']];
        foreach (Db::all("SELECT id, title, kind, publish_at at FROM content_items WHERE status = 'scheduled' AND publish_at IS NOT NULL AND publish_at <= ? ORDER BY publish_at LIMIT 20", [$end]) as $c)
            $i[] = [$c['at'], 'content', 'Publish', $c['title'], label($c['kind']), '/admin/content?edit=' . $c['id']];
        foreach ($i as &$x) $x[6] = $x[0] < $now;
        unset($x);
        usort($i, fn($a, $b) => strcmp((string)$a[0], (string)$b[0]));
        return $i;
    }
}
