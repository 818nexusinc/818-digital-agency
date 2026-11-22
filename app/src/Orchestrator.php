<?php
declare(strict_types=1);
namespace App;

/**
 * Durable agent task queue (MySQL/SQLite), processed by cron. Supports priorities, dedupe, dependencies via parent_id,
 * row locks with expiry (crash recovery), exponential backoff retries, timeouts, approvals, cancellation and cost tracking.
 */
final class Orchestrator
{
    /** Task-kind → agent routing used by the Chief Ops classification step. */
    public const ROUTES = ['plan_project' => 'project_manager', 'registration_prep' => 'registration', 'website_build' => 'website_dev', 'app_spec' => 'web_app',
        'brand_concepts' => 'brand', 'seo_audit' => 'seo', 'content_draft' => 'content', 'social_plan' => 'social', 'ticket_first_response' => 'support', 'qa_review' => 'qa',
        'finance_summary' => 'finance', 'growth_review' => 'growth', 'retention_scan' => 'retention', 'research_opportunities' => 'research', 'security_review' => 'security',
        'lead_enrich' => 'sales', 'intake' => 'chief_ops'];

    public static function enqueue(string $agent, string $kind, string $title, array $input = [], array $o = []): int
    {
        if (!Agents::get($agent)) throw new \InvalidArgumentException('Unknown agent ' . $agent);
        if (!empty($o['dedupe_key']) && ($id = Db::val('SELECT id FROM agent_tasks WHERE dedupe_key = ?', [$o['dedupe_key']]))) return (int)$id;
        $a = Agents::get($agent);
        $id = Db::insert('agent_tasks', ['public_id' => substr(rid(8), 0, 16), 'agent_slug' => $agent, 'parent_id' => $o['parent_id'] ?? null, 'workflow_run_id' => $o['workflow_run_id'] ?? null,
            'kind' => $kind, 'title' => mb_substr($title, 0, 190), 'input' => jenc($input), 'status' => 'queued', 'priority' => (int)($o['priority'] ?? 5),
            'attempts' => 0, 'max_attempts' => (int)($o['max_attempts'] ?? $a['max_retries'] ?? 3), 'run_after' => $o['run_after'] ?? now(), 'deadline' => $o['deadline'] ?? null,
            'customer_id' => $o['customer_id'] ?? null, 'project_id' => $o['project_id'] ?? null, 'dedupe_key' => $o['dedupe_key'] ?? null,
            'created_by' => $o['created_by'] ?? 'system', 'created_at' => now(), 'updated_at' => now()]);
        return $id;
    }

    /** Recover tasks whose worker died (lock expired while running). */
    public static function recoverStale(): int
    {
        Db::run("UPDATE agent_tasks SET status = 'queued', updated_at = ? WHERE status = 'blocked' AND run_after <= ?", [now(), now()]);
        return Db::run("UPDATE agent_tasks SET status = 'queued', locked_until = NULL, locked_by = NULL, progress_note = 'Recovered after interruption', updated_at = ? WHERE status = 'running' AND locked_until < ?", [now(), now()])->rowCount();
    }

    /** Claim and process up to $max tasks within $seconds. Returns number processed. */
    public static function work(int $max = 5, int $seconds = 45): int
    {
        self::recoverStale();
        $worker = substr(rid(4), 0, 8) . '@' . getmypid();
        $until = microtime(true) + $seconds;
        $n = 0;
        while ($n < $max && microtime(true) < $until) {
            $cand = Db::one("SELECT t.* FROM agent_tasks t JOIN agents a ON a.slug = t.agent_slug WHERE t.status = 'queued' AND t.run_after <= ? AND a.status = 'enabled'
                AND (t.parent_id IS NULL OR t.kind IN ('qa_review','revision','delegated') OR NOT EXISTS (SELECT 1 FROM agent_tasks p WHERE p.id = t.parent_id AND p.status IN ('queued','running')))
                ORDER BY t.priority, t.id LIMIT 1", [now()]);
            if (!$cand) break;
            $timeout = (int)(Agents::get($cand['agent_slug'])['timeout_seconds'] ?? 90) + 30;
            $claimed = Db::run("UPDATE agent_tasks SET status = 'running', locked_until = ?, locked_by = ?, attempts = attempts + 1, started_at = ?, updated_at = ? WHERE id = ? AND status = 'queued'",
                [now($timeout), $worker, now(), now(), $cand['id']])->rowCount();
            if ($claimed !== 1) continue; // another worker got it
            self::process((int)$cand['id']);
            $n++;
        }
        return $n;
    }

    public static function process(int $taskId): void
    {
        $t = Db::one('SELECT * FROM agent_tasks WHERE id = ?', [$taskId]);
        $input = jdec($t['input']);
        $ctx = ['task_id' => $taskId, 'project_id' => $t['project_id'] ? (int)$t['project_id'] : null, 'customer_id' => $t['customer_id'] ? (int)$t['customer_id'] : null,
            'channel' => 'task', 'depth' => (int)($input['depth'] ?? 0), 'approved_tool' => $input['_approved_tool'] ?? null];
        try {
            if ($t['deadline'] && $t['deadline'] < now()) throw new AgentTimeout('Deadline passed before execution.');
            $msg = self::brief($t, $input);
            $r = AgentRunner::run($t['agent_slug'], [['role' => 'user', 'content' => $msg]], $ctx, self::context($t));
            Db::update('agent_tasks', ['cost_micros' => (int)$t['cost_micros'] + $r['cost_micros'], 'tokens_in' => (int)$t['tokens_in'] + $r['tokens_in'], 'tokens_out' => (int)$t['tokens_out'] + $r['tokens_out']], 'id = ?', [$taskId]);
            if ($r['offline']) throw new ProviderDown('AI provider unavailable');
            if ($r['blocked']) {
                Db::update('agent_tasks', ['status' => 'needs_approval', 'approval_id' => $r['blocked']['approval_id'], 'output' => jenc(['summary' => $r['text']]),
                    'progress_note' => 'Waiting for owner approval', 'locked_until' => null, 'updated_at' => now()], 'id = ?', [$taskId]);
                return;
            }
            Db::update('agent_tasks', ['status' => 'completed', 'output' => jenc(['summary' => $r['text'], 'actions' => $r['actions']]), 'progress_note' => str_limit($r['text'], 250),
                'locked_until' => null, 'error' => null, 'finished_at' => now(), 'updated_at' => now()], 'id = ?', [$taskId]);
            Events::emit('task.completed', ['task_id' => $taskId, 'kind' => $t['kind']]);
        } catch (BudgetExceeded $e) {
            self::hold($taskId, 'blocked', 'Budget limit: ' . $e->getMessage(), 3600);
            Approvals::request('budget', 'AI budget reached for ' . $t['agent_slug'], ['what' => $e->getMessage(), 'why' => 'Cost controls paused this agent\'s work.', 'impact' => 'Tasks wait until budget resets or is raised.'], 'agent', null, 'system', 'medium');
        } catch (AgentUnavailable $e) {
            self::hold($taskId, 'blocked', $e->getMessage(), 1800);
        } catch (\Throwable $e) {
            self::fail($t, $e);
        }
    }

    /** Park a task without consuming a retry attempt (budget pause, agent paused). */
    private static function hold(int $id, string $status, string $note, int $delay): void
    {
        Db::run('UPDATE agent_tasks SET status = ?, progress_note = ?, run_after = ?, locked_until = NULL, locked_by = NULL, attempts = CASE WHEN attempts > 0 THEN attempts - 1 ELSE 0 END, updated_at = ? WHERE id = ?',
            [$status, str_limit($note, 250), now($delay), now(), $id]);
    }

    private static function fail(array $t, \Throwable $e): void
    {
        $attempts = (int)Db::val('SELECT attempts FROM agent_tasks WHERE id = ?', [$t['id']]);
        $msg = mb_substr(get_class($e) . ': ' . $e->getMessage(), 0, 1000);
        if ($attempts < (int)$t['max_attempts']) {
            $delay = (int)min(3600, 60 * (2 ** ($attempts - 1)));
            Db::update('agent_tasks', ['status' => 'queued', 'error' => $msg, 'run_after' => now($delay), 'locked_until' => null, 'locked_by' => null,
                'progress_note' => "Retry {$attempts}/{$t['max_attempts']} scheduled", 'updated_at' => now()], 'id = ?', [$t['id']]);
            Log::warn('task.retry', ['task' => $t['public_id'], 'attempt' => $attempts, 'error' => $msg]);
            return;
        }
        Db::update('agent_tasks', ['status' => 'failed', 'error' => $msg, 'locked_until' => null, 'finished_at' => now(), 'progress_note' => 'Failed after retries — escalated', 'updated_at' => now()], 'id = ?', [$t['id']]);
        Approvals::request('failed_task', 'Agent task failed: ' . $t['title'], ['what' => 'Retry, reassign or handle manually.', 'why' => $msg, 'impact' => $t['project_id'] ? 'Project may be delayed.' : 'Internal work delayed.'], 'agent_task', (int)$t['id'], $t['agent_slug'], 'medium');
        if ($t['project_id']) Projects::activity((int)$t['project_id'], 'issue', 'A step needs attention from our team; we\'re on it.', 'system');
        Log::error('task.failed', ['task' => $t['public_id'], 'error' => $msg]);
    }

    public static function cancel(int $id, string $by): void
    {
        Db::run("UPDATE agent_tasks SET status = 'cancelled', locked_until = NULL, finished_at = ?, updated_at = ? WHERE id = ? AND status IN ('queued','needs_approval','needs_info','blocked','failed')", [now(), now(), $id]);
        Audit::log('task.cancel', 'agent_task', $id, ['by' => $by]);
    }

    public static function retry(int $id, string $by): void
    {
        Db::run("UPDATE agent_tasks SET status = 'queued', attempts = 0, run_after = ?, error = NULL, updated_at = ? WHERE id = ? AND status IN ('failed','cancelled','needs_info')", [now(), now(), $id]);
        Audit::log('task.retry_manual', 'agent_task', $id, ['by' => $by]);
    }

    /** Task brief. Any customer-originated text is wrapped as untrusted. */
    private static function brief(array $t, array $in): string
    {
        $clean = array_diff_key($in, ['depth' => 1, '_approved_tool' => 1]);
        $s = "TASK: {$t['title']}\nKind: {$t['kind']}\nTask id: {$t['public_id']}\n";
        if ($t['project_id']) $s .= "Project id: {$t['project_id']}\n";
        if (isset($clean['deliverable_id'])) {
            $d = Db::one('SELECT id, title, body, produced_by, qa_notes FROM deliverables WHERE id = ?', [(int)$clean['deliverable_id']]);
            if ($d) $s .= "Deliverable #{$d['id']} \"{$d['title']}\" produced by {$d['produced_by']}:\n<untrusted>\n" . mb_substr((string)$d['body'], 0, 20000) . "\n</untrusted>\n";
        }
        $s .= "Input:\n<untrusted>\n" . jenc($clean) . "\n</untrusted>\n";
        if (!empty($in['_approved_tool'])) $s .= "\nThe owner APPROVED your earlier request to run {$in['_approved_tool']}. Proceed with it now.\n";
        $s .= "\nUse your tools to complete the task, then reply with a 1–3 sentence customer-safe summary of what you did.";
        return $s;
    }

    private static function context(array $t): string
    {
        if (!$t['project_id']) return '';
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$t['project_id']]);
        if (!$p) return '';
        $ms = Db::all('SELECT title, status, agent_slug FROM project_milestones WHERE project_id = ? ORDER BY sort', [$p['id']]);
        $svc = Catalog::find($p['service_slug'], false);
        return "Project {$p['number']} (id {$p['id']}): {$p['title']} — status " . label($p['status']) . ", {$p['progress']}%\nService: " . ($svc['name'] ?? $p['service_slug'])
            . ' — deliverables: ' . implode('; ', $svc['deliverables'] ?? []) . "\nMilestones:\n" . implode("\n", array_map(fn($m) => "- [{$m['status']}] {$m['title']} (owner: {$m['agent_slug']})", $ms))
            . "\nCustomer requirements (untrusted):\n<untrusted>" . mb_substr((string)$p['requirements'], 0, 6000) . '</untrusted>';
    }
}

final class ProviderDown extends \RuntimeException {}

/** Human-in-the-loop approval center. */
final class Approvals
{
    public static function request(string $kind, string $title, array $details, ?string $subjectType, ?int $subjectId, string $by, string $risk = 'medium', ?array $payload = null): int
    {
        // Collapse duplicates for the same subject while pending.
        if ($subjectType && $subjectId && ($id = Db::val("SELECT id FROM approvals WHERE status = 'pending' AND kind = ? AND subject_type = ? AND subject_id = ? AND title = ?", [$kind, $subjectType, $subjectId, $title]))) return (int)$id;
        $id = Db::insert('approvals', ['kind' => $kind, 'title' => mb_substr($title, 0, 190), 'details' => jenc($details), 'risk' => $risk, 'status' => 'pending', 'requested_by' => $by,
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'payload' => $payload ? jenc($payload) : null, 'created_at' => now()]);
        Audit::log('approval.request', 'approval', $id, ['kind' => $kind, 'by' => $by], 'agent', $by);
        if ($risk === 'high') Mailer::notifyOwner('Approval needed: ' . $title, (string)($details['what'] ?? ''), '/admin/approvals');
        return $id;
    }

    /** @param string $decision approve|reject|request_info */
    public static function decide(int $id, string $decision, array $user, string $note = '', ?array $modifiedInput = null): void
    {
        $a = Db::one('SELECT * FROM approvals WHERE id = ?', [$id]) ?? throw new UserError('Approval not found.');
        if ($a['status'] !== 'pending') throw new UserError('Already decided.');
        $status = ['approve' => 'approved', 'reject' => 'rejected', 'request_info' => 'changes_requested'][$decision] ?? throw new UserError('Invalid decision.');
        Db::update('approvals', ['status' => $status, 'decided_by' => $user['email'], 'decided_at' => now(), 'decision_note' => mb_substr($note, 0, 2000)], 'id = ?', [$id]);
        Audit::log('approval.' . $decision, 'approval', $id, ['note' => $note, 'modified' => $modifiedInput !== null]);
        $payload = jdec($a['payload'], null);
        $task = $a['subject_type'] === 'agent_task' && $a['subject_id'] ? Db::one('SELECT * FROM agent_tasks WHERE id = ?', [$a['subject_id']]) : null;
        if (!$task) $task = Db::one('SELECT * FROM agent_tasks WHERE approval_id = ?', [$id]);
        if ($decision === 'approve') {
            if ($payload && !empty($payload['filing_submit'])) {
                $res = Filings::submit((int)$payload['filing_submit'], 'owner:' . $user['email'], true);
                Db::update('approvals', ['decision_note' => mb_substr(trim($note . "\nResult: " . jenc($res)), 0, 2000)], 'id = ?', [$id]);
            }
            if ($payload && !empty($payload['tool'])) {
                $agent = Agents::get($payload['agent']);
                $ctx = ($payload['ctx'] ?? []) + ['approved_tool' => $payload['tool'], 'channel' => 'approval'];
                $res = Tools::execute($agent, $payload['tool'], $modifiedInput ?? $payload['input'], $ctx);
                Db::update('approvals', ['decision_note' => mb_substr(trim($note . "\nResult: " . jenc($res)), 0, 2000)], 'id = ?', [$id]);
            }
            if ($task && $task['status'] === 'needs_approval') {
                $in = jdec($task['input']);
                if ($payload && !empty($payload['tool'])) { $in['_approved_result'] = 'executed'; }
                $in['_approved_tool'] = $payload['tool'] ?? 'request_approval';
                $in['owner_note'] = $note;
                Db::update('agent_tasks', ['status' => 'queued', 'input' => jenc($in), 'run_after' => now(), 'progress_note' => 'Approved by owner', 'updated_at' => now()], 'id = ?', [$task['id']]);
            }
            if ($a['subject_type'] === 'project' && $a['subject_id']) {
                Projects::activity((int)$a['subject_id'], 'approval', 'Approved: ' . $a['title'], 'owner');
                $ms = Db::one("SELECT id FROM project_milestones WHERE project_id = ? AND agent_slug = 'owner' AND status = 'pending' ORDER BY sort LIMIT 1", [$a['subject_id']]);
                if ($ms && in_array($a['kind'], ['deployment', 'domain_transfer', 'dns_change', 'publish', 'other'], true)) Projects::completeMilestone((int)$ms['id'], 'owner');
                Db::run("UPDATE projects SET status = 'in_progress', updated_at = ? WHERE id = ? AND status = 'awaiting_approval'", [now(), $a['subject_id']]);
            }
        } elseif ($decision === 'reject') {
            if ($task && in_array($task['status'], ['needs_approval', 'queued'], true)) {
                Db::update('agent_tasks', ['status' => 'cancelled', 'progress_note' => 'Rejected by owner: ' . str_limit($note, 200), 'finished_at' => now(), 'updated_at' => now()], 'id = ?', [$task['id']]);
            }
            if ($a['subject_type'] === 'project' && $a['subject_id']) Db::run("UPDATE projects SET status = 'in_progress', updated_at = ? WHERE id = ? AND status = 'awaiting_approval'", [now(), $a['subject_id']]);
        } else {
            if ($task) Db::update('agent_tasks', ['status' => 'needs_info', 'progress_note' => 'Owner requested more information: ' . str_limit($note, 200), 'updated_at' => now()], 'id = ?', [$task['id']]);
        }
    }
}

/** AI usage, cost accounting and budget enforcement. */
final class Usage
{
    public static function record(string $agent, string $provider, string $model, int $in, int $out, int $micros, ?int $taskId, ?int $convId): void
    {
        Db::insert('ai_usage', ['agent_slug' => $agent, 'task_id' => $taskId, 'conversation_id' => $convId, 'provider' => $provider, 'model' => $model,
            'tokens_in' => $in, 'tokens_out' => $out, 'cost_micros' => $micros, 'created_at' => now()]);
    }

    public static function spentCents(?string $agent, string $since): float
    {
        $sql = 'SELECT COALESCE(SUM(cost_micros),0) FROM ai_usage WHERE created_at >= ?' . ($agent ? ' AND agent_slug = ?' : '');
        return (int)Db::val($sql, $agent ? [$since, $agent] : [$since]) / 10000; // micro-USD → cents
    }

    public static function dayStart(): string { return gmdate('Y-m-d 00:00:00'); }
    public static function monthStart(): string { return gmdate('Y-m-01 00:00:00'); }

    /** Returns a human-readable reason when a budget is exhausted, else null. */
    public static function check(array $agent, ?int $taskId): ?string
    {
        if (self::spentCents(null, self::dayStart()) >= Settings::int('ai.daily_budget_cents')) return 'Platform daily AI budget reached.';
        if (self::spentCents(null, self::monthStart()) >= Settings::int('ai.monthly_budget_cents')) return 'Platform monthly AI budget reached.';
        if (self::spentCents($agent['slug'], self::dayStart()) >= (int)$agent['daily_budget_cents']) return "{$agent['name']} daily budget reached.";
        if (self::spentCents($agent['slug'], self::monthStart()) >= (int)$agent['monthly_budget_cents']) return "{$agent['name']} monthly budget reached.";
        if ($taskId && (int)Db::val('SELECT cost_micros FROM agent_tasks WHERE id = ?', [$taskId]) / 10000 >= (int)$agent['task_budget_cents']) return 'Task budget reached.';
        return null;
    }
}
