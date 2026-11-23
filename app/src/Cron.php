<?php
declare(strict_types=1);
namespace App;

/**
 * Single cron entry point (every minute on cPanel). Each cycle is bounded in time and
 * guarded by a lock file so overlapping cron invocations never double-process.
 */
final class Cron
{
    public static function run(int $budgetSeconds = 50): array
    {
        $lock = fopen(STORAGE_ROOT . '/locks/cron.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return ['skipped' => 'another cron cycle is running'];
        $start = microtime(true);
        $stats = [];
        try {
            $stats['events'] = Workflows::dispatchEvents();
            $stats['workflow_steps'] = Workflows::runDue(30, 15);
            $left = (int)max(5, $budgetSeconds - (microtime(true) - $start) - 5);
            $stats['agent_tasks'] = Orchestrator::work(8, $left);
            $stats['events_2'] = Workflows::dispatchEvents();
            $stats['filings'] = Filings::autoAdvance();
            $stats['followups'] = self::followUps();
            $stats['housekeeping'] = self::housekeeping();
            $stats['reports'] = self::reports();
            $b = BlogEngine::weekly(); if ($b) $stats['blog'] = $b['slug'];
        } catch (\Throwable $e) {
            Log::error('cron.failed', ['error' => $e->getMessage()]);
            $stats['error'] = $e->getMessage();
        } finally {
            $stats['seconds'] = round(microtime(true) - $start, 2);
            Settings::set('system.cron_last_run', now());
            Settings::set('system.cron_last_stats', jenc($stats));
            flock($lock, LOCK_UN);
        }
        return $stats;
    }

    /**
     * Lead follow-ups under CASL: only to leads who contacted us within the last 6 months (implied consent)
     * or gave express consent; never to opted-out/suppressed addresses; stop on reply/conversion; capped touches.
     */
    public static function followUps(): int
    {
        $max = max(0, Settings::int('followup.max_touches'));
        $gap = max(1, Settings::int('followup.interval_days'));
        $sent = 0;
        $rows = Db::all("SELECT l.*, c.email, c.name, c.consent_marketing, c.last_contact_at FROM leads l JOIN customers c ON c.id = l.customer_id
            WHERE l.next_follow_up_at IS NOT NULL AND l.next_follow_up_at <= ? AND l.opted_out = 0 AND l.status IN ('lead','qualified','proposal_sent','awaiting_decision') AND l.follow_up_count < ? LIMIT 20", [now(), $max]);
        foreach ($rows as $l) {
            $implied = $l['created_at'] >= now(-86400 * 182);
            $replied = (int)Db::val("SELECT COUNT(*) FROM messages m JOIN conversations cv ON cv.id = m.conversation_id WHERE cv.customer_id = ? AND m.role = 'user' AND m.created_at > ?", [$l['customer_id'], $l['updated_at']])
                + (int)Db::val('SELECT COUNT(*) FROM tickets WHERE customer_id = ? AND created_at > ?', [$l['customer_id'], $l['updated_at']]);
            if ($replied || Mailer::suppressed($l['email']) || (!$implied && (int)$l['consent_marketing'] !== 1)) {
                Db::update('leads', ['next_follow_up_at' => null, 'updated_at' => now()], 'id = ?', [$l['id']]);
                continue;
            }
            $n = (int)$l['follow_up_count'] + 1;
            $interest = implode(', ', array_map(fn($s) => Catalog::find($s)['name'] ?? $s, jdec($l['services_interest'])));
            $book = Integrations::secret('calendly', 'url');
            $body = "Hi " . ($l['name'] ?: 'there') . ",\n\nFollowing up on your request" . ($interest ? " about {$interest}" : '') . ". Do you have any questions I can answer, or would you like a written proposal?\n\n"
                . ($book ? "Book a free 15-minute call: {$book}\n\n" : '') . 'Just reply to this email — a real person reads every reply.';
            $r = Mailer::send('follow_up', $l['email'], ['subject' => 'Following up on your 818 Digital Agency request', 'body' => $body, 'name' => $l['name']], "fu:{$l['id']}:{$n}", 'lead', (int)$l['id']);
            Db::update('leads', ['follow_up_count' => $n, 'next_follow_up_at' => $n >= $max ? null : now(86400 * $gap * $n), 'updated_at' => now()], 'id = ?', [$l['id']]);
            if ($r['ok']) $sent++;
        }
        return $sent;
    }

    public static function housekeeping(): array
    {
        $out = [];
        $out['proposals_expired'] = Db::run("UPDATE proposals SET status = 'expired', updated_at = ? WHERE status IN ('draft','sent') AND expires_at < ?", [now(), now()])->rowCount();
        // Renewal reminders 7 days ahead.
        foreach (Db::all("SELECT s.*, c.email, c.name FROM subscriptions s JOIN customers c ON c.id = s.customer_id WHERE s.status = 'active' AND s.current_period_end BETWEEN ? AND ?", [now(), now(86400 * 7)]) as $s) {
            $svc = Db::val('SELECT name FROM order_items WHERE order_id = ? AND billing <> ? LIMIT 1', [$s['order_id'], 'one_time']) ?: 'Your subscription';
            Mailer::send('renewal_reminder', $s['email'], ['name' => $s['name'], 'service' => $svc, 'date' => substr((string)$s['current_period_end'], 0, 10), 'link' => url('/portal')], 'renew:' . $s['id'] . ':' . substr((string)$s['current_period_end'], 0, 10), 'subscription', (int)$s['id']);
        }
        if ((int)gmdate('i') === 7) { // hourly
            $out['old_anon_conversations'] = Db::run('DELETE FROM conversations WHERE customer_id IS NULL AND user_id IS NULL AND updated_at < ?', [now(-86400 * 180)])->rowCount();
            $out['sessions'] = Db::run('DELETE FROM sessions WHERE updated_at < ?', [time() - 86400])->rowCount();
            $out['rate_limits'] = Db::run('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400])->rowCount();
            Db::run('DELETE FROM memories WHERE expires_at IS NOT NULL AND expires_at < ?', [now()]);
            foreach (glob(STORAGE_ROOT . '/logs/*.log') ?: [] as $f) if (filemtime($f) < time() - 86400 * 30) @unlink($f);
            foreach (glob(STORAGE_ROOT . '/sessions/sess_*') ?: [] as $f) if (filemtime($f) < time() - 86400 * 2) @unlink($f);
        }
        return $out;
    }

    /** Daily (≈8am Atlantic) and weekly (Monday) owner reports. */
    public static function reports(): array
    {
        $out = [];
        $to = Settings::get('owner.notify_email');
        if (!$to) return $out;
        $today = gmdate('Y-m-d');
        if ((int)gmdate('G') >= 12 && Settings::get('report.daily_last') !== $today) {
            Mailer::send('owner_report', $to, ['title' => 'Daily summary ' . $today, 'body' => Metrics::dailyReport() . "\n" . url('/admin')], 'daily:' . $today);
            Settings::set('report.daily_last', $today);
            $out[] = 'daily';
        }
        if (gmdate('N') === '1' && (int)gmdate('G') >= 12 && Settings::get('report.weekly_last') !== $today) {
            $w = Metrics::overview(7);
            $agents = array_filter(Metrics::agentStats(7), fn($a) => $a['tasks'] > 0);
            $body = "Weekly report — week ending {$today}\n\nRevenue: {$w['revenue']} · Orders: {$w['orders']} · MRR: {$w['mrr']}\nLeads: {$w['leads']} (conversion {$w['conversion_rate']}) · pipeline {$w['pipeline_value']}\n"
                . "Projects completed: {$w['projects_completed']} · active: {$w['projects_active']}\nTickets open: {$w['tickets_open']}\nAI cost: {$w['ai_cost_usd']} · estimated gross profit: {$w['gross_profit_estimate']}\n\nAgent performance:\n"
                . implode("\n", array_map(fn($a) => "- {$a['name']}: {$a['completed']} done, {$a['failed']} failed, success {$a['success_rate']}, cost {$a['cost_usd']}", $agents))
                . "\n\nTop services:\n" . implode("\n", array_map(fn($s) => "- {$s['service_slug']}: {$s['sold']} sold, " . money((int)$s['revenue_cents']), $w['top_services'])) . "\n\n" . url('/admin/growth');
            Mailer::send('owner_report', $to, ['title' => 'Weekly report ' . $today, 'body' => $body], 'weekly:' . $today);
            Settings::set('report.weekly_last', $today);
            Orchestrator::enqueue('growth', 'growth_review', 'Weekly growth review ' . $today, ['brief' => 'Review the last 7 days of business_metrics and recommend at most three concrete actions for next week, with the evidence behind each.'], ['dedupe_key' => 'growth:' . $today, 'priority' => 7]);
            $out[] = 'weekly';
        }
        return $out;
    }
}

/** Portable logical backup (works without mysqldump). Writes gzip'd SQL to storage/backups. */
final class Backup
{
    public static function run(): string
    {
        $dir = STORAGE_ROOT . '/backups';
        if (!is_dir($dir)) mkdir($dir, 0750, true);
        $file = $dir . '/backup-' . gmdate('Ymd-His') . '.sql.gz';
        $gz = gzopen($file, 'wb6');
        $pdo = Db::pdo();
        $tables = Db::$driver === 'sqlite' ? array_column(Db::all("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"), 'name') : Db::pdo()->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);
        gzwrite($gz, "-- 818 Digital Agency backup " . gmdate('c') . " driver=" . Db::$driver . "\n");
        foreach ($tables as $t) {
            if (!preg_match('/^[a-z_]+$/', $t)) continue;
            gzwrite($gz, "\n-- table {$t}\nDELETE FROM {$t};\n");
            $st = $pdo->query("SELECT * FROM {$t}");
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string)$v), array_values($row));
                gzwrite($gz, "INSERT INTO {$t} (" . implode(',', array_keys($row)) . ') VALUES (' . implode(',', $vals) . ");\n");
            }
        }
        gzclose($gz);
        // Verify: the archive must decompress and contain every table marker.
        $content = (string)gzdecode((string)file_get_contents($file));
        foreach ($tables as $t) if (!str_contains($content, "-- table {$t}\n")) throw new \RuntimeException('Backup verification failed for ' . $t);
        @chmod($file, 0640);
        foreach (array_slice(array_reverse(glob($dir . '/backup-*.sql.gz') ?: []), 14) as $old) @unlink($old); // keep 14
        Audit::log('backup.created', 'backup', basename($file), ['bytes' => filesize($file)]);
        return $file;
    }
}
