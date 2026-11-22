<?php
declare(strict_types=1);
namespace App;

/** Real metrics computed from the database. No hardcoded or simulated numbers. */
final class Metrics
{
    public static function overview(int $days = 30): array
    {
        $since = Db::since($days);
        $paid = "status IN ('paid','processing','completed')";
        $rev = (int)Db::val("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE {$paid} AND paid_at >= ?", [$since]);
        $refunds = -(int)Db::val("SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE kind = 'refund' AND created_at >= ?", [$since]);
        $leads = (int)Db::val('SELECT COUNT(*) FROM leads WHERE created_at >= ?', [$since]);
        $converted = (int)Db::val("SELECT COUNT(*) FROM leads WHERE created_at >= ? AND status = 'converted'", [$since]);
        $expenses = (int)Db::val('SELECT COALESCE(SUM(amount_cents),0) FROM expenses WHERE incurred_on >= ?', [substr($since, 0, 10)]);
        $aiMicros = (int)Db::val('SELECT COALESCE(SUM(cost_micros),0) FROM ai_usage WHERE created_at >= ?', [$since]);
        $aiCad = (int)round($aiMicros / 10000 * (float)Settings::get('fx.usd_cad', '1.38'));
        return [
            'window_days' => $days,
            'revenue_cents' => $rev, 'revenue' => money($rev), 'revenue_today' => money((int)Db::val("SELECT COALESCE(SUM(total_cents),0) FROM orders WHERE {$paid} AND paid_at >= ?", [Usage::dayStart()])),
            'refunds' => money($refunds),
            'mrr' => money((int)Db::val("SELECT COALESCE(SUM(amount_cents),0) FROM subscriptions WHERE status = 'active'")),
            'orders' => (int)Db::val("SELECT COUNT(*) FROM orders WHERE {$paid} AND paid_at >= ?", [$since]),
            'orders_pending' => (int)Db::val("SELECT COUNT(*) FROM orders WHERE status IN ('pending','awaiting_payment')"),
            'leads' => $leads, 'leads_converted' => $converted, 'conversion_rate' => $leads ? round($converted * 100 / $leads, 1) . '%' : 'n/a',
            'pipeline_value' => money((int)Db::val("SELECT COALESCE(SUM(estimated_value_cents),0) FROM leads WHERE status NOT IN ('converted','closed')")),
            'leads_overdue' => (int)Db::val("SELECT COUNT(*) FROM leads WHERE next_follow_up_at < ? AND status NOT IN ('converted','closed') AND opted_out = 0", [now()]),
            'customers' => (int)Db::val("SELECT COUNT(*) FROM customers WHERE status IN ('customer','active_customer','repeat_customer')"),
            'projects_active' => (int)Db::val("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed','cancelled','failed')"),
            'projects_awaiting_customer' => (int)Db::val("SELECT COUNT(*) FROM projects WHERE status IN ('awaiting_requirements','awaiting_customer')"),
            'projects_overdue' => (int)Db::val("SELECT COUNT(*) FROM projects WHERE deadline < ? AND status NOT IN ('completed','cancelled')", [now()]),
            'projects_completed' => (int)Db::val("SELECT COUNT(*) FROM projects WHERE status = 'completed' AND updated_at >= ?", [$since]),
            'tickets_open' => (int)Db::val("SELECT COUNT(*) FROM tickets WHERE status NOT IN ('resolved','closed')"),
            'approvals_pending' => (int)Db::val("SELECT COUNT(*) FROM approvals WHERE status = 'pending'"),
            'tasks' => [
                'queued' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'queued'"),
                'running' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'running'"),
                'needs_approval' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'needs_approval'"),
                'failed' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'failed' AND updated_at >= ?", [$since]),
                'completed' => (int)Db::val("SELECT COUNT(*) FROM agent_tasks WHERE status = 'completed' AND finished_at >= ?", [$since]),
            ],
            'ai_cost_usd' => '$' . number_format($aiMicros / 1e6, 2), 'ai_cost_today_usd' => '$' . number_format((int)Db::val('SELECT COALESCE(SUM(cost_micros),0) FROM ai_usage WHERE created_at >= ?', [Usage::dayStart()]) / 1e6, 2),
            'ai_tokens' => (int)Db::val('SELECT COALESCE(SUM(tokens_in + tokens_out),0) FROM ai_usage WHERE created_at >= ?', [$since]),
            'expenses' => money($expenses), 'gross_profit_estimate' => money($rev - $refunds - $expenses - $aiCad),
            'top_services' => Db::all("SELECT i.service_slug, COUNT(*) AS sold, SUM(i.unit_cents * i.qty) AS revenue_cents FROM order_items i JOIN orders o ON o.id = i.order_id WHERE o.{$paid} AND o.paid_at >= ? GROUP BY i.service_slug ORDER BY revenue_cents DESC LIMIT 5", [$since]),
            'lead_sources' => Db::all('SELECT source, COUNT(*) AS n FROM leads WHERE created_at >= ? GROUP BY source ORDER BY n DESC', [$since]),
        ];
    }

    public static function agentStats(int $days = 30): array
    {
        $since = Db::since($days);
        $out = [];
        foreach (Db::all('SELECT slug, name, status, autonomy_level, model FROM agents ORDER BY id') as $a) {
            $s = Db::one("SELECT COUNT(*) AS total, SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS done, SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) AS failed,
                SUM(CASE WHEN status IN ('queued','running','blocked') THEN 1 ELSE 0 END) AS open, SUM(CASE WHEN attempts > 1 THEN 1 ELSE 0 END) AS retried, COALESCE(SUM(cost_micros),0) AS cost
                FROM agent_tasks WHERE agent_slug = ? AND created_at >= ?", [$a['slug'], $since]);
            $runs = Db::one('SELECT COUNT(*) AS n, COALESCE(AVG(duration_ms),0) AS avg_ms, COALESCE(SUM(cost_micros),0) AS cost FROM agent_runs WHERE agent_slug = ? AND created_at >= ?', [$a['slug'], $since]);
            $qa = Db::one("SELECT SUM(CASE WHEN qa_status='passed' THEN 1 ELSE 0 END) AS pass, SUM(CASE WHEN qa_status IN ('passed','failed') THEN 1 ELSE 0 END) AS total FROM deliverables WHERE produced_by = ? AND created_at >= ?", [$a['slug'], $since]);
            $total = (int)$s['total'];
            $out[] = $a + ['tasks' => $total, 'completed' => (int)$s['done'], 'failed' => (int)$s['failed'], 'open' => (int)$s['open'],
                'success_rate' => $total ? round((int)$s['done'] * 100 / max(1, (int)$s['done'] + (int)$s['failed'])) . '%' : '—',
                'retry_rate' => $total ? round((int)$s['retried'] * 100 / $total) . '%' : '—', 'runs' => (int)$runs['n'], 'avg_seconds' => round((float)$runs['avg_ms'] / 1000, 1),
                'cost_usd' => '$' . number_format((int)$runs['cost'] / 1e6, 2), 'qa_pass' => (int)($qa['total'] ?? 0) ? round((int)$qa['pass'] * 100 / (int)$qa['total']) . '%' : '—',
                'spent_today_usd' => '$' . number_format(Usage::spentCents($a['slug'], Usage::dayStart()) / 100, 2)];
        }
        return $out;
    }

    public static function dailyReport(): string
    {
        $m = self::overview(1);
        $w = self::overview(7);
        return "Daily summary — " . gmdate('Y-m-d') . "\n\nSales (24h): {$m['revenue']} from {$m['orders']} order(s)\nNew leads (24h): {$m['leads']} · overdue follow-ups: {$m['leads_overdue']}\n"
            . "Projects: {$m['projects_active']} active · {$m['projects_awaiting_customer']} waiting on customers · {$m['projects_overdue']} overdue · {$m['projects_completed']} completed (24h)\n"
            . "Support: {$m['tickets_open']} open tickets\nAI: {$m['tasks']['completed']} tasks completed, {$m['tasks']['failed']} failed, cost {$m['ai_cost_usd']} (24h)\n"
            . "Owner actions required: {$m['approvals_pending']} pending approval(s)\n\n7-day revenue: {$w['revenue']} · MRR: {$w['mrr']} · lead conversion {$w['conversion_rate']}\n";
    }
}
