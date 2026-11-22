<?php
declare(strict_types=1);
namespace App;

final class Events
{
    public static function emit(string $name, array $payload = []): int
    {
        return Db::insert('events', ['name' => $name, 'payload' => jenc($payload), 'status' => 'pending', 'created_at' => now()]);
    }
}

/**
 * Durable workflow engine. Definitions are JSON step lists stored in `workflows` (editable in the admin builder).
 * Step types: action, agent_task, condition, delay, approval, notify, webhook.
 * Each run persists its context and current step, so it resumes after delays, approvals or crashes.
 */
final class Workflows
{
    public const STEP_TYPES = ['action', 'agent_task', 'condition', 'delay', 'approval', 'notify', 'webhook'];

    public const DEFAULTS = [
        'new_lead' => ['New lead', 'lead.created', [
            ['type' => 'action', 'action' => 'load_lead'],
            ['type' => 'notify', 'template' => 'lead_confirmation', 'to' => 'customer', 'dedupe' => 'lead-confirm:{lead_id}'],
            ['type' => 'agent_task', 'agent' => 'sales', 'kind' => 'lead_enrich', 'title' => 'Review lead #{lead_id} and recommend services',
                'brief' => 'Analyse this lead, look up matching catalog services, and write a short recommendation for the owner. Do not contact the customer.'],
            ['type' => 'condition', 'field' => 'lead.score', 'op' => 'gte', 'value' => 60, 'else' => 'stop'],
            ['type' => 'action', 'action' => 'alert_high_value_lead'],
        ]],
        'order_paid' => ['Order paid → projects', 'order.paid', [
            ['type' => 'action', 'action' => 'create_projects_for_order'],
            ['type' => 'action', 'action' => 'send_order_confirmation'],
            ['type' => 'action', 'action' => 'send_onboarding'],
        ]],
        'requirements_received' => ['Requirements received → plan & assign', 'project.requirements_received', [
            ['type' => 'action', 'action' => 'load_project'],
            ['type' => 'agent_task', 'agent' => 'project_manager', 'kind' => 'plan_project', 'title' => 'Plan {project_number}: {project_title}',
                'brief' => 'Requirements are in. Set status to in_progress, assign each pending milestone owned by a specialist agent via create_task (include project_id), and send the customer a short update on next steps.'],
        ]],
        'project_completed' => ['Project completed → feedback & retention', 'project.completed', [
            ['type' => 'action', 'action' => 'load_project'],
            ['type' => 'notify', 'template' => 'feedback_request', 'to' => 'customer', 'dedupe' => 'feedback:{project_id}'],
            ['type' => 'delay', 'seconds' => 604800],
            ['type' => 'agent_task', 'agent' => 'retention', 'kind' => 'retention_scan', 'title' => 'Follow-up ideas after {project_number}',
                'brief' => 'Recommend at most two genuinely relevant next services for this customer, with reasons. Draft only; do not contact them.'],
        ]],
        'ticket_first_response' => ['Support first response', 'ticket.created', [
            ['type' => 'action', 'action' => 'load_ticket'],
            ['type' => 'agent_task', 'agent' => 'support', 'kind' => 'ticket_first_response', 'title' => 'First response: {ticket_number}',
                'brief' => 'Answer ticket id {ticket_id} using the knowledge base via reply_ticket, or escalate_ticket if it is sensitive, angry, billing/refund related, or you are unsure.'],
        ]],
        'proposal_reminder' => ['Proposal reminder', 'proposal.sent', [
            ['type' => 'delay', 'seconds' => 259200],
            ['type' => 'action', 'action' => 'load_proposal'],
            ['type' => 'condition', 'field' => 'proposal.status', 'op' => 'eq', 'value' => 'sent', 'else' => 'stop'],
            ['type' => 'notify', 'template' => 'proposal_reminder', 'to' => 'customer', 'dedupe' => 'prop-remind:{proposal_id}'],
        ]],
        'filing_authorized' => ['Filing authorized → review & submit', 'filing.authorized', [
            ['type' => 'agent_task', 'agent' => 'filing', 'kind' => 'filing_review', 'title' => 'Review & submit filing #{filing_id}',
                'brief' => 'Call filing_get for filing_id {filing_id}. Review it carefully. If it is consistent and complete, call filing_submit with a short review_summary. Otherwise call filing_flag with needs_customer_changes=true and a clear question for the customer.'],
        ]],
        'payment_failed' => ['Payment failed', 'payment.failed', [
            ['type' => 'agent_task', 'agent' => 'finance', 'kind' => 'finance_summary', 'title' => 'Payment failed {order_number}', 'brief' => 'Summarise the failed payment for the owner and recommend next steps. Do not contact the customer or move money.'],
        ]],
    ];

    public static function seed(): void
    {
        foreach (self::DEFAULTS as $slug => [$name, $trigger, $steps]) {
            if (!Db::val('SELECT 1 FROM workflows WHERE slug = ?', [$slug])) Db::insert('workflows', ['slug' => $slug, 'name' => $name, 'trigger_event' => $trigger, 'definition' => jenc(['steps' => $steps]), 'enabled' => 1, 'version' => 1, 'updated_at' => now()]);
        }
    }

    public static function validate(array $def): array
    {
        $errors = [];
        if (!isset($def['steps']) || !is_array($def['steps']) || !$def['steps']) return ['Definition needs a non-empty "steps" array.'];
        foreach ($def['steps'] as $i => $s) {
            $t = $s['type'] ?? '';
            if (!in_array($t, self::STEP_TYPES, true)) $errors[] = "Step {$i}: unknown type '{$t}'.";
            if ($t === 'action' && !method_exists(self::class, 'act_' . ($s['action'] ?? ''))) $errors[] = "Step {$i}: unknown action.";
            if ($t === 'agent_task' && !Agents::get((string)($s['agent'] ?? ''))) $errors[] = "Step {$i}: unknown agent.";
            if ($t === 'notify' && !isset(Mailer::TEMPLATES[$s['template'] ?? ''])) $errors[] = "Step {$i}: unknown email template.";
            if ($t === 'webhook' && !self::safeUrl((string)($s['url'] ?? ''))) $errors[] = "Step {$i}: webhook URL must be public https.";
        }
        return $errors;
    }

    /** Turn pending events into workflow runs (one per matching workflow; unique per event). */
    public static function dispatchEvents(int $limit = 50): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM events WHERE status = 'pending' ORDER BY id LIMIT " . (int)$limit) as $ev) {
            $claimed = Db::run("UPDATE events SET status = 'processing' WHERE id = ? AND status = 'pending'", [$ev['id']])->rowCount();
            if (!$claimed) continue;
            foreach (Db::all('SELECT * FROM workflows WHERE enabled = 1 AND trigger_event = ?', [$ev['name']]) as $wf) {
                try {
                    Db::insert('workflow_runs', ['workflow_slug' => $wf['slug'], 'event_id' => $ev['id'], 'status' => 'running', 'context' => $ev['payload'], 'current_step' => 0,
                        'run_after' => now(), 'attempts' => 0, 'log' => '[]', 'created_at' => now(), 'updated_at' => now()]);
                } catch (\PDOException) { /* already created for this event */ }
            }
            Db::update('events', ['status' => 'processed', 'processed_at' => now()], 'id = ?', [$ev['id']]);
            $n++;
        }
        return $n;
    }

    public static function runDue(int $limit = 20, int $seconds = 20): int
    {
        Db::run("UPDATE workflow_runs SET locked_until = NULL WHERE status = 'running' AND locked_until < ?", [now()]);
        $until = microtime(true) + $seconds; $n = 0;
        foreach (Db::all("SELECT id FROM workflow_runs WHERE status IN ('running','waiting') AND run_after <= ? AND locked_until IS NULL ORDER BY id LIMIT " . (int)$limit, [now()]) as $r) {
            if (microtime(true) > $until) break;
            if (Db::run('UPDATE workflow_runs SET locked_until = ? WHERE id = ? AND locked_until IS NULL', [now(120), $r['id']])->rowCount() !== 1) continue;
            self::advance((int)$r['id']);
            $n++;
        }
        return $n;
    }

    public static function tick(): void { self::dispatchEvents(); self::runDue(); }

    /** Execute steps of a run until it completes, waits or fails. */
    public static function advance(int $runId): void
    {
        $run = Db::one('SELECT * FROM workflow_runs WHERE id = ?', [$runId]);
        $wf = Db::one('SELECT * FROM workflows WHERE slug = ?', [$run['workflow_slug']]);
        $steps = jdec($wf['definition'] ?? '{}')['steps'] ?? [];
        $ctx = jdec($run['context']);
        $log = jdec($run['log']);
        $i = (int)$run['current_step'];
        $status = 'running'; $after = now();
        try {
            for ($guard = 0; $i < count($steps) && $guard < 25; $guard++) {
                $s = $steps[$i];
                $res = self::step($s, $ctx, $runId, $i);
                $log[] = ['step' => $i, 'type' => $s['type'], 'at' => now(), 'result' => $res['note'] ?? 'ok'];
                if (!empty($res['wait'])) { $status = 'waiting'; $after = now((int)$res['wait']); if (!empty($res['advance'])) $i++; break; }
                if (!empty($res['stop'])) { $i = count($steps); break; }
                $i++;
            }
            if ($i >= count($steps)) $status = 'completed';
            Db::update('workflow_runs', ['status' => $status, 'context' => jenc($ctx), 'current_step' => $i, 'run_after' => $after, 'locked_until' => null,
                'log' => jenc(array_slice($log, -50)), 'error' => null, 'updated_at' => now()], 'id = ?', [$runId]);
        } catch (\Throwable $e) {
            $attempts = (int)$run['attempts'] + 1;
            $final = $attempts >= 5;
            Db::update('workflow_runs', ['status' => $final ? 'failed' : 'running', 'attempts' => $attempts, 'run_after' => now(60 * (2 ** $attempts)), 'locked_until' => null,
                'context' => jenc($ctx), 'current_step' => $i, 'error' => mb_substr($e->getMessage(), 0, 1000), 'log' => jenc(array_slice($log, -50)), 'updated_at' => now()], 'id = ?', [$runId]);
            Log::error('workflow.step_failed', ['run' => $runId, 'step' => $i, 'error' => $e->getMessage()]);
            if ($final) Approvals::request('failed_workflow', "Workflow failed: {$wf['name']}", ['what' => 'Inspect and retry the workflow run.', 'why' => $e->getMessage()], 'workflow_run', $runId, 'system', 'medium');
        }
    }

    private static function fill(string $s, array $ctx): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn($m) => (string)($ctx[$m[1]] ?? ''), $s);
    }

    private static function get(array $ctx, string $path): mixed
    {
        foreach (explode('.', $path) as $p) { if (!is_array($ctx) || !array_key_exists($p, $ctx)) return null; $ctx = $ctx[$p]; }
        return $ctx;
    }

    private static function step(array $s, array &$ctx, int $runId, int $i): array
    {
        switch ($s['type']) {
            case 'action':
                $m = 'act_' . $s['action'];
                return self::$m($ctx) ?? [];
            case 'agent_task':
                $targets = !empty($s['for_each']) ? (array)($ctx[$s['for_each']] ?? []) : [null];
                foreach ($targets as $t) {
                    $local = $t ? array_merge($ctx, (array)$t) : $ctx;
                    Orchestrator::enqueue($s['agent'], $s['kind'] ?? 'workflow', self::fill($s['title'] ?? 'Workflow task', $local), ['brief' => self::fill($s['brief'] ?? '', $local)] + array_intersect_key($local, array_flip(['lead_id', 'order_id', 'ticket_id', 'proposal_id', 'order_number', 'filing_id'])),
                        ['workflow_run_id' => $runId, 'priority' => ($s['agent'] === 'filing' ? 2 : 5), 'project_id' => $local['project_id'] ?? null, 'customer_id' => $local['customer_id'] ?? null, 'dedupe_key' => "wf:{$runId}:{$i}:" . ($local['project_id'] ?? '')]);
                }
                return ['note' => 'task queued'];
            case 'condition':
                $v = self::get($ctx, (string)$s['field']);
                $ok = match ($s['op'] ?? 'eq') { 'eq' => $v == $s['value'], 'neq' => $v != $s['value'], 'gte' => $v >= $s['value'], 'lte' => $v <= $s['value'], 'truthy' => (bool)$v, default => false };
                return $ok ? ['note' => 'condition true'] : (($s['else'] ?? 'stop') === 'stop' ? ['stop' => true, 'note' => 'condition false → stop'] : ['note' => 'condition false → continue']);
            case 'delay':
                $key = "_delayed_{$i}";
                if (!empty($ctx[$key])) return ['note' => 'delay elapsed'];
                $ctx[$key] = 1;
                return ['wait' => max(1, (int)$s['seconds']), 'note' => 'waiting ' . (int)$s['seconds'] . 's'];
            case 'approval':
                $key = "_approval_{$i}";
                if (empty($ctx[$key])) {
                    $ctx[$key] = Approvals::request('workflow', self::fill($s['title'] ?? 'Workflow approval', $ctx), ['what' => self::fill($s['what'] ?? '', $ctx), 'why' => 'Workflow approval node'], 'workflow_run', $runId, 'workflow', $s['risk'] ?? 'medium');
                    return ['wait' => 300, 'note' => 'awaiting approval'];
                }
                $st = Db::val('SELECT status FROM approvals WHERE id = ?', [$ctx[$key]]);
                if ($st === 'pending') return ['wait' => 300, 'note' => 'awaiting approval'];
                return $st === 'approved' ? ['note' => 'approved'] : ['stop' => true, 'note' => 'approval ' . $st];
            case 'notify':
                $to = $s['to'] === 'owner' ? Settings::get('owner.notify_email') : ($ctx['customer']['email'] ?? null);
                if (!$to && !empty($ctx['customer_id'])) $to = Db::val('SELECT email FROM customers WHERE id = ?', [$ctx['customer_id']]);
                if (!$to) return ['note' => 'no recipient'];
                $vars = self::vars($ctx);
                $r = Mailer::send($s['template'], $to, $vars, isset($s['dedupe']) ? self::fill($s['dedupe'], $ctx) : "wf:{$runId}:{$i}");
                return ['note' => 'email ' . $r['status']];
            case 'webhook':
                if (!self::safeUrl((string)$s['url'])) throw new \RuntimeException('Unsafe webhook URL');
                $ch = curl_init($s['url']);
                curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => jenc(['event' => $ctx]), CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
                curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
                if ($code < 200 || $code >= 300) throw new \RuntimeException('Webhook returned ' . $code);
                return ['note' => 'webhook ' . $code];
        }
        throw new \RuntimeException('Unknown step type');
    }

    /** SSRF guard for user-defined webhooks: https, public IPs only. */
    public static function safeUrl(string $url): bool
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') return false;
        $host = (string)parse_url($url, PHP_URL_HOST);
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) return false;
        foreach ($ips as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        return true;
    }

    private static function vars(array $ctx): array
    {
        $c = $ctx['customer'] ?? [];
        $p = $ctx['proposal'] ?? [];
        return ['name' => $c['name'] ?? 'there', 'summary' => str_limit((string)($ctx['lead']['summary'] ?? ''), 300), 'project' => $ctx['project_title'] ?? '',
            'number' => $p['number'] ?? '', 'title' => $p['title'] ?? '', 'expires' => substr((string)($p['expires_at'] ?? ''), 0, 10),
            'link' => isset($p['public_token']) ? url('/proposal/' . $p['public_token']) : (isset($ctx['project_number']) ? url('/portal/projects/' . $ctx['project_number'] . (isset($ctx['feedback']) ? '' : '#feedback')) : url('/portal'))];
    }

    // ── Built-in actions ──────────────────────────────────────────────────

    private static function act_load_lead(array &$ctx): array
    {
        $l = Db::one('SELECT * FROM leads WHERE id = ?', [$ctx['lead_id'] ?? 0]) ?? throw new \RuntimeException('Lead not found');
        $ctx['lead'] = ['score' => (int)$l['score'], 'summary' => $l['summary'], 'status' => $l['status']];
        $ctx['customer_id'] = (int)$l['customer_id'];
        $ctx['customer'] = Db::one('SELECT id, name, email FROM customers WHERE id = ?', [$l['customer_id']]);
        return ['note' => 'lead loaded'];
    }

    private static function act_alert_high_value_lead(array &$ctx): array
    {
        Approvals::request('important_lead', 'High-value lead: ' . ($ctx['customer']['name'] ?: $ctx['customer']['email']), ['what' => 'Personally contact this lead.', 'why' => 'Lead score ' . $ctx['lead']['score'],
            'evidence' => str_limit((string)$ctx['lead']['summary'], 400)], 'lead', (int)$ctx['lead_id'], 'sales', 'medium');
        return ['note' => 'owner alerted'];
    }

    private static function act_create_projects_for_order(array &$ctx): array
    {
        $ids = Projects::createForOrder((int)$ctx['order_id']);
        $all = Db::all('SELECT id, number, title FROM projects WHERE order_id = ?', [$ctx['order_id']]);
        $ctx['projects'] = array_map(fn($p) => ['project_id' => (int)$p['id'], 'project_number' => $p['number'], 'project_title' => $p['title']], $all);
        Db::run("UPDATE orders SET status = 'processing', updated_at = ? WHERE id = ? AND status = 'paid'", [now(), $ctx['order_id']]);
        return ['note' => count($ids) . ' project(s) created'];
    }

    private static function act_send_order_confirmation(array &$ctx): array
    {
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$ctx['order_id']]);
        $c = Db::one('SELECT * FROM customers WHERE id = ?', [$o['customer_id']]);
        $items = implode("\n", array_map(fn($i) => '• ' . $i['name'] . ' — ' . money((int)$i['unit_cents']) . ($i['billing'] !== 'one_time' ? ' (' . $i['billing'] . ')' : ''), Orders::items((int)$o['id'])));
        $r = Mailer::send('order_confirmation', $c['email'], ['name' => $c['name'], 'number' => $o['number'], 'total' => money((int)$o['total_cents']), 'items' => $items, 'link' => url('/portal/orders/' . $o['number'])], 'order-confirm:' . $o['id'], 'order', (int)$o['id']);
        Mailer::notifyOwner('New paid order ' . $o['number'], money((int)$o['total_cents']) . " from {$c['email']}\n{$items}", '/admin/orders');
        return ['note' => 'confirmation ' . $r['status']];
    }

    private static function act_send_onboarding(array &$ctx): array
    {
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$ctx['order_id']]);
        $c = Db::one('SELECT * FROM customers WHERE id = ?', [$o['customer_id']]);
        foreach ($ctx['projects'] ?? [] as $p) {
            Mailer::send('onboarding', $c['email'], ['name' => $c['name'], 'project' => $p['project_title'], 'link' => url('/portal/projects/' . $p['project_number'] . '/requirements')], 'onboard:' . $p['project_id'], 'project', $p['project_id']);
            Projects::activity($p['project_id'], 'onboarding', 'Welcome! We sent you a short requirements form to get started.', 'system');
        }
        return ['note' => 'onboarding sent'];
    }

    private static function act_load_project(array &$ctx): array
    {
        $p = Db::one('SELECT * FROM projects WHERE id = ?', [$ctx['project_id'] ?? 0]) ?? throw new \RuntimeException('Project not found');
        $ctx += ['project_number' => $p['number'], 'project_title' => $p['title'], 'customer_id' => (int)$p['customer_id']];
        $ctx['customer'] = Db::one('SELECT id, name, email FROM customers WHERE id = ?', [$p['customer_id']]);
        return ['note' => 'project loaded'];
    }

    private static function act_load_ticket(array &$ctx): array
    {
        $t = Db::one('SELECT * FROM tickets WHERE id = ?', [$ctx['ticket_id'] ?? 0]) ?? throw new \RuntimeException('Ticket not found');
        $ctx['ticket_number'] = $t['number'];
        $ctx['customer_id'] = $t['customer_id'] ? (int)$t['customer_id'] : null;
        return ['note' => 'ticket loaded'];
    }

    private static function act_load_proposal(array &$ctx): array
    {
        $p = Db::one('SELECT id, number, title, status, expires_at, public_token, customer_id FROM proposals WHERE id = ?', [$ctx['proposal_id'] ?? 0]) ?? throw new \RuntimeException('Proposal not found');
        $ctx['proposal'] = $p;
        $ctx['customer_id'] = $p['customer_id'];
        $ctx['customer'] = $p['customer_id'] ? Db::one('SELECT id, name, email FROM customers WHERE id = ?', [$p['customer_id']]) : null;
        return ['note' => 'proposal ' . $p['status']];
    }
}
