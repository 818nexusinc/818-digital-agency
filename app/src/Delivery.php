<?php
declare(strict_types=1);
namespace App;

final class Crm
{
    public const STATUSES = ['visitor', 'lead', 'qualified', 'proposal_sent', 'awaiting_decision', 'customer', 'active_customer', 'repeat_customer', 'inactive', 'at_risk', 'closed'];

    public static function upsertCustomer(array $d, string $source = 'website'): array
    {
        $email = strtolower(trim((string)($d['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('A valid email is required.');
        $row = Db::one('SELECT * FROM customers WHERE email = ?', [$email]);
        $fields = [];
        foreach (['name', 'phone', 'business_name', 'industry', 'location', 'province'] as $k) {
            if (!empty($d[$k])) $fields[$k] = mb_substr(trim((string)$d[$k]), 0, $k === 'province' ? 3 : 190);
        }
        if ($row) {
            // Never overwrite existing values with AI-supplied guesses; only fill blanks.
            $fill = array_filter($fields, fn($v, $k) => empty($row[$k]), ARRAY_FILTER_USE_BOTH);
            if ($fill) Db::update('customers', $fill + ['updated_at' => now()], 'id = ?', [$row['id']]);
            return Db::one('SELECT * FROM customers WHERE id = ?', [$row['id']]);
        }
        $id = Db::insert('customers', $fields + ['email' => $email, 'name' => $fields['name'] ?? '', 'lead_source' => $d['lead_source'] ?? $source,
            'status' => 'lead', 'tags' => '[]', 'interests' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('crm.customer_create', 'customer', $id, ['source' => $source]);
        return Db::one('SELECT * FROM customers WHERE id = ?', [$id]);
    }

    public static function setStatus(int $customerId, string $status): void
    {
        if (!in_array($status, self::STATUSES, true)) return;
        $cur = Db::val('SELECT status FROM customers WHERE id = ?', [$customerId]);
        // Don't regress a paying customer back to a pipeline stage.
        $rank = array_flip(self::STATUSES);
        if (in_array($cur, ['active_customer', 'repeat_customer', 'customer'], true) && $rank[$status] < $rank['customer']) return;
        if ($cur === $status) return;
        Db::update('customers', ['status' => $status, 'updated_at' => now()], 'id = ?', [$customerId]);
        Audit::log('crm.status', 'customer', $customerId, ['from' => $cur, 'to' => $status]);
    }

    /** Deterministic lead score (0–100). AI may add a summary but does not set the score. */
    public static function score(array $lead, array $customer): int
    {
        $s = 10;
        if (!empty($customer['phone'])) $s += 10;
        if (!empty($customer['business_name'])) $s += 10;
        if (!empty($lead['budget'])) $s += 15;
        if (preg_match('/asap|urgent|this week|immediately|now/i', (string)($lead['timeline'] ?? ''))) $s += 20;
        $interests = jdec($lead['services_interest'] ?? '[]');
        $s += min(30, 10 * count($interests));
        foreach ($interests as $slug) { $svc = Catalog::find((string)$slug); if ($svc && in_array($svc['category'], ['apps', 'websites'], true)) { $s += 15; break; } }
        return min(100, $s);
    }

    public static function createLead(array $customer, array $d, string $source = 'website'): array
    {
        $interests = array_values(array_filter(array_map('strval', (array)($d['services'] ?? [])), fn($x) => Catalog::find($x) !== null));
        $lead = ['customer_id' => $customer['id'], 'source' => $source, 'intent' => mb_substr((string)($d['intent'] ?? ''), 0, 120),
            'summary' => mb_substr((string)($d['summary'] ?? ''), 0, 4000), 'services_interest' => jenc($interests),
            'budget' => mb_substr((string)($d['budget'] ?? ''), 0, 60) ?: null, 'timeline' => mb_substr((string)($d['timeline'] ?? ''), 0, 60) ?: null,
            'conversation_id' => $d['conversation_id'] ?? null];
        $score = self::score($lead, $customer);
        $value = 0;
        foreach ($interests as $slug) { $svc = Catalog::find($slug); $value += $svc ? (int)(Catalog::fromPrice($svc) ?? 0) : 0; }
        $id = Db::insert('leads', $lead + ['score' => $score, 'urgency' => $score >= 60 ? 'high' : ($score >= 35 ? 'normal' : 'low'),
            'status' => 'lead', 'estimated_value_cents' => $value, 'next_follow_up_at' => now(86400), 'created_at' => now(), 'updated_at' => now()]);
        self::setStatus((int)$customer['id'], $score >= 50 ? 'qualified' : 'lead');
        Db::update('customers', ['last_contact_at' => now()], 'id = ?', [$customer['id']]);
        Audit::log('crm.lead_create', 'lead', $id, ['score' => $score, 'source' => $source]);
        Events::emit('lead.created', ['lead_id' => $id]);
        return Db::one('SELECT * FROM leads WHERE id = ?', [$id]);
    }

    public static function timeline(int $customerId): array
    {
        $items = [];
        foreach (Db::all('SELECT number, status, total_cents, created_at FROM orders WHERE customer_id = ?', [$customerId]) as $o) $items[] = ['at' => $o['created_at'], 'kind' => 'order', 'text' => "Order {$o['number']} · " . label($o['status']) . ' · ' . money((int)$o['total_cents'])];
        foreach (Db::all('SELECT to_email, subject, status, created_at FROM email_log WHERE to_email = (SELECT email FROM customers WHERE id = ?)', [$customerId]) as $m) $items[] = ['at' => $m['created_at'], 'kind' => 'email', 'text' => "Email: {$m['subject']} ({$m['status']})"];
        foreach (Db::all('SELECT number, subject, status, created_at FROM tickets WHERE customer_id = ?', [$customerId]) as $t) $items[] = ['at' => $t['created_at'], 'kind' => 'ticket', 'text' => "Ticket {$t['number']}: {$t['subject']} (" . label($t['status']) . ')'];
        foreach (Db::all('SELECT number, version, status, created_at FROM proposals WHERE customer_id = ?', [$customerId]) as $p) $items[] = ['at' => $p['created_at'], 'kind' => 'proposal', 'text' => "Proposal {$p['number']} v{$p['version']} (" . label($p['status']) . ')'];
        foreach (Db::all("SELECT action, detail, created_at FROM audit_log WHERE subject_type = 'customer' AND subject_id = ?", [(string)$customerId]) as $a) $items[] = ['at' => $a['created_at'], 'kind' => 'crm', 'text' => $a['action'] . ' ' . str_limit((string)$a['detail'], 120)];
        usort($items, fn($a, $b) => strcmp($b['at'], $a['at']));
        return $items;
    }
}

final class Projects
{
    public const STATUSES = ['awaiting_requirements', 'ready', 'planning', 'in_progress', 'ai_working', 'awaiting_customer', 'awaiting_approval', 'qa', 'revision', 'completed', 'on_hold', 'failed', 'cancelled'];

    /** Milestone templates per category: [title, responsible agent or "customer"/"owner"]. */
    public const TEMPLATES = [
        'registration' => [['Filing details & authorization', 'customer'], ['Filing review & validation', 'filing'], ['Submitted to the registry', 'filing'], ['Registry approval', 'registry'], ['Documents delivered', 'filing']],
        'management' => [['Filing details & authorization', 'customer'], ['Filing review & validation', 'filing'], ['Submitted to the registry', 'filing'], ['Registry approval', 'registry'], ['Documents delivered', 'filing']],
        'domains' => [['Domain details confirmed', 'customer'], ['Availability verified', 'concierge'], ['Registration / transfer approved', 'owner'], ['DNS configured', 'project_manager'], ['Access details delivered', 'project_manager']],
        'email' => [['Mailbox plan confirmed', 'customer'], ['Provisioning approved', 'owner'], ['Mailboxes created', 'project_manager'], ['Setup guide delivered', 'support']],
        'websites' => [['Discovery brief received', 'customer'], ['Sitemap & content plan', 'website_dev'], ['Content draft', 'content'], ['Design & build', 'website_dev'], ['Independent QA (links, mobile, accessibility, SEO)', 'qa'], ['Customer preview & approval', 'customer'], ['Launch approved & deployed', 'owner']],
        'apps' => [['Requirements received', 'customer'], ['Technical specification', 'web_app'], ['Architecture & data model', 'web_app'], ['Build sprint', 'web_app'], ['Security & QA review', 'qa'], ['Customer acceptance', 'customer'], ['Release approved', 'owner']],
        'growth' => [['Brief received', 'customer'], ['Strategy & concepts', 'brand'], ['QA review', 'qa'], ['Delivered', 'project_manager']],
        'addon' => [['Fulfilment', 'project_manager']],
    ];

    /** Requirements questions per category (shown in the portal onboarding form). */
    public const QUESTIONS = [
        'registration' => ['legal_name' => 'Proposed business name(s), in order of preference', 'activities' => 'What will the business do?', 'owners' => 'Full names of owners / directors', 'address' => 'Registered address in Canada', 'province' => 'Province of registration (or Federal)'],
        'management' => ['corporation' => 'Corporation name and number', 'change' => 'What needs to be filed or changed?', 'effective' => 'Effective date of the change'],
        'domains' => ['domain' => 'Domain name(s) you want', 'existing' => 'Do you already own it elsewhere? (for transfers)', 'contact' => 'Registrant name and address'],
        'email' => ['domain' => 'Which domain should email use?', 'mailboxes' => 'Mailbox addresses you need (e.g. info@, you@)'],
        'websites' => ['business' => 'Describe your business and customers', 'goals' => 'What should the website achieve?', 'pages' => 'Pages you need', 'style' => 'Websites you like, colours, brand assets', 'content' => 'Do you have text and photos ready?'],
        'apps' => ['problem' => 'What problem should the application solve?', 'users' => 'Who will use it?', 'features' => 'Must-have features', 'integrations' => 'Systems it must connect to', 'deadline' => 'Target launch date'],
        'growth' => ['business' => 'Describe your business', 'goals' => 'Goals for this work', 'audience' => 'Target audience'],
        'addon' => ['notes' => 'Anything we should know?'],
    ];

    public static function createForOrder(int $orderId): array
    {
        $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        $created = [];
        foreach (Orders::items($orderId) as $it) {
            if (Db::val('SELECT 1 FROM projects WHERE order_id = ? AND service_slug = ?', [$orderId, $it['service_slug']])) continue;
            $svc = Catalog::find($it['service_slug'], false);
            $cat = $svc['category'] ?? 'addon';
            $formDetails = $it['opts']['details'] ?? null;
            $hasBrief = is_array($formDetails) && $formDetails && empty($formDetails['for']) && empty($formDetails['bundle']) && !Filings::typeFor($it['service_slug']);
            $pid = Db::insert('projects', ['number' => refno('PRJ'), 'customer_id' => $o['customer_id'], 'order_id' => $orderId, 'service_slug' => $it['service_slug'],
                'title' => $it['name'], 'status' => $hasBrief ? 'ready' : 'awaiting_requirements', 'progress' => 0, 'requirements' => $hasBrief ? jenc(['form' => Wizard::readable($formDetails)]) : '{}', 'created_at' => now(), 'updated_at' => now()]);
            foreach (self::TEMPLATES[$cat] ?? self::TEMPLATES['addon'] as $i => [$title, $who]) {
                Db::insert('project_milestones', ['project_id' => $pid, 'title' => $title, 'status' => 'pending', 'sort' => $i, 'agent_slug' => $who]);
            }
            self::activity($pid, 'created', 'Project created from order ' . $o['number'] . '.', 'system');
            if (Filings::typeFor($it['service_slug'])) Filings::openForProject(Db::one('SELECT * FROM projects WHERE id = ?', [$pid]));
            $created[] = $pid;
        }
        return $created;
    }

    public static function activity(int $projectId, string $kind, string $summary, string $actor, bool $visible = true): void
    {
        Db::insert('project_activity', ['project_id' => $projectId, 'kind' => $kind, 'summary' => mb_substr($summary, 0, 500), 'visible_to_customer' => $visible ? 1 : 0, 'actor' => $actor, 'created_at' => now()]);
        Db::update('projects', ['updated_at' => now()], 'id = ?', [$projectId]);
    }

    public static function setStatus(int $id, string $status, string $actor, ?string $note = null): void
    {
        if (!in_array($status, self::STATUSES, true)) throw new UserError('Invalid status.');
        Db::update('projects', ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
        self::activity($id, 'status', $note ?? ('Status changed to ' . label($status) . '.'), $actor);
        Audit::log('project.status', 'project', $id, ['status' => $status, 'actor' => $actor]);
        if ($status === 'completed') Events::emit('project.completed', ['project_id' => $id]);
    }

    public static function completeMilestone(int $milestoneId, string $actor): void
    {
        $m = Db::one('SELECT * FROM project_milestones WHERE id = ?', [$milestoneId]);
        if (!$m || $m['status'] === 'done') return;
        Db::update('project_milestones', ['status' => 'done', 'completed_at' => now()], 'id = ?', [$milestoneId]);
        self::activity((int)$m['project_id'], 'milestone', $m['title'] . ' — completed.', $actor);
        self::recalc((int)$m['project_id']);
    }

    public static function recalc(int $projectId): void
    {
        $total = (int)Db::val('SELECT COUNT(*) FROM project_milestones WHERE project_id = ?', [$projectId]);
        $done = (int)Db::val("SELECT COUNT(*) FROM project_milestones WHERE project_id = ? AND status = 'done'", [$projectId]);
        $pct = $total ? intdiv($done * 100, $total) : 0;
        Db::update('projects', ['progress' => $pct, 'updated_at' => now()], 'id = ?', [$projectId]);
        if ($total && $done === $total && Db::val('SELECT status FROM projects WHERE id = ?', [$projectId]) !== 'completed') self::setStatus($projectId, 'completed', 'system', 'All milestones complete — final deliverables ready.');
    }

    public static function forCustomer(int $customerId): array
    {
        return Db::all('SELECT * FROM projects WHERE customer_id = ? ORDER BY created_at DESC', [$customerId]);
    }

    public static function submitRequirements(array $project, array $answers): void
    {
        $cat = Catalog::find($project['service_slug'], false)['category'] ?? 'addon';
        $clean = [];
        foreach (self::QUESTIONS[$cat] ?? self::QUESTIONS['addon'] as $k => $_) $clean[$k] = mb_substr(trim((string)($answers[$k] ?? '')), 0, 4000);
        if (!array_filter($clean)) throw new UserError('Please answer at least one question.');
        Db::update('projects', ['requirements' => jenc($clean), 'status' => 'ready', 'updated_at' => now()], 'id = ?', [$project['id']]);
        $first = Db::one("SELECT id FROM project_milestones WHERE project_id = ? AND agent_slug = 'customer' AND status = 'pending' ORDER BY sort LIMIT 1", [$project['id']]);
        if ($first) self::completeMilestone((int)$first['id'], 'customer');
        self::activity((int)$project['id'], 'requirements', 'Requirements received — thank you!', 'customer');
        Events::emit('project.requirements_received', ['project_id' => (int)$project['id']]);
    }
}

final class Tickets
{
    public const CATEGORIES = ['account', 'billing', 'registration', 'domains', 'email', 'website', 'development', 'technical', 'orders', 'refunds', 'general'];

    public static function create(?int $customerId, string $subject, string $body, string $category = 'general', string $authorType = 'customer', string $authorName = 'Customer'): array
    {
        $category = in_array($category, self::CATEGORIES, true) ? $category : 'general';
        $sentiment = preg_match('/angry|terrible|unacceptable|refund|scam|worst|frustrat|furious|cancel/i', $body) ? 'negative' : 'neutral';
        $priority = ($sentiment === 'negative' || in_array($category, ['billing', 'refunds'], true)) ? 'high' : 'normal';
        $id = Db::insert('tickets', ['number' => refno('TKT'), 'customer_id' => $customerId, 'category' => $category, 'priority' => $priority, 'status' => 'open',
            'subject' => mb_substr($subject, 0, 190), 'sentiment' => $sentiment, 'assigned_to' => 'support', 'created_at' => now(), 'updated_at' => now()]);
        self::reply($id, $body, $authorType, $authorName);
        Audit::log('ticket.create', 'ticket', $id, ['category' => $category, 'priority' => $priority]);
        Events::emit('ticket.created', ['ticket_id' => $id]);
        return Db::one('SELECT * FROM tickets WHERE id = ?', [$id]);
    }

    public static function reply(int $ticketId, string $body, string $authorType, string $authorName, bool $internal = false): void
    {
        Db::insert('ticket_messages', ['ticket_id' => $ticketId, 'author_type' => $authorType, 'author_name' => mb_substr($authorName, 0, 120),
            'body' => mb_substr($body, 0, 10000), 'internal' => $internal ? 1 : 0, 'created_at' => now()]);
        $status = match ($authorType) { 'customer' => 'open', 'ai' => 'awaiting_customer', default => 'awaiting_customer' };
        Db::update('tickets', ['status' => $internal ? Db::val('SELECT status FROM tickets WHERE id = ?', [$ticketId]) : $status, 'updated_at' => now()], 'id = ?', [$ticketId]);
    }
}

/** Secure uploads stored outside the web root; served only after an ownership check. */
final class Documents
{
    public const MAX_BYTES = 10 * 1024 * 1024;
    public const ALLOWED = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
        'gif' => 'image/gif', 'txt' => 'text/plain', 'csv' => 'text/csv', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];

    public static function dir(): string { $d = STORAGE_ROOT . '/uploads'; if (!is_dir($d)) mkdir($d, 0750, true); return $d; }

    /** Validate and store an uploaded file ($_FILES entry or ['tmp_name','name','size','error']). */
    public static function store(array $file, ?int $customerId, ?int $projectId, string $by, string $kind = 'file', bool $moveUploaded = true): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new UserError('Upload failed. Please try again.');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) throw new UserError('Files must be under 10 MB.');
        $name = preg_replace('/[^A-Za-z0-9 ._\-]/', '_', basename((string)$file['name']));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) throw new UserError('That file type is not allowed. Use PDF, images, Office documents, TXT or CSV.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: 'application/octet-stream';
        $okMime = self::ALLOWED[$ext] === $mime || ($ext === 'csv' && $mime === 'text/plain') || (in_array($ext, ['docx', 'xlsx', 'pptx'], true) && $mime === 'application/zip');
        if (!$okMime) { Audit::security('upload.mime_mismatch', "{$name} {$mime}"); throw new UserError('The file content does not match its extension.'); }
        $head = (string)file_get_contents($file['tmp_name'], false, null, 0, 1024);
        if (preg_match('/<\?php|<script|<html/i', $head) && !in_array($ext, ['txt', 'csv'], true)) { Audit::security('upload.active_content', $name); throw new UserError('That file was rejected for security reasons.'); }
        if ($scan = Env::get('CLAMSCAN_PATH')) {
            exec(escapeshellcmd($scan) . ' --no-summary ' . escapeshellarg($file['tmp_name']), $_o, $code);
            if ($code === 1) { Audit::security('upload.malware', $name); throw new UserError('That file was rejected by the malware scanner.'); }
        }
        $stored = rid(16) . '.' . $ext;
        if (self::inDb()) {
            $content = (string)file_get_contents($file['tmp_name']);
            $id = Db::insert('documents', ['customer_id' => $customerId, 'project_id' => $projectId, 'kind' => $kind, 'filename' => $name, 'stored_name' => $stored,
                'mime' => self::ALLOWED[$ext], 'size_bytes' => $size, 'sha256' => hash('sha256', $content), 'uploaded_by' => $by, 'created_at' => now()]);
            Db::insert('document_blobs', ['document_id' => $id, 'content' => base64_encode($content)]);
        } else {
        $dest = self::dir() . '/' . $stored;
        $ok = $moveUploaded ? move_uploaded_file($file['tmp_name'], $dest) : copy($file['tmp_name'], $dest);
        if (!$ok) throw new UserError('Could not save the file.');
        @chmod($dest, 0640);
        $id = Db::insert('documents', ['customer_id' => $customerId, 'project_id' => $projectId, 'kind' => $kind, 'filename' => $name, 'stored_name' => $stored,
            'mime' => self::ALLOWED[$ext], 'size_bytes' => $size, 'sha256' => hash_file('sha256', $dest), 'uploaded_by' => $by, 'created_at' => now()]);
        }
        Audit::log('document.upload', 'document', $id, ['name' => $name, 'size' => $size]);
        return Db::one('SELECT * FROM documents WHERE id = ?', [$id]);
    }

    /** Files live in the database on serverless hosts (FILE_STORAGE=db or VERCEL). */
    public static function inDb(): bool { return (Env::get('FILE_STORAGE') ?: (ON_VERCEL ? 'db' : 'disk')) === 'db'; }

    /** Raw bytes of a stored document from whichever backend holds it. */
    public static function content(array $doc): ?string
    {
        $b = Db::val('SELECT content FROM document_blobs WHERE document_id = ?', [$doc['id']]);
        if ($b !== null) return base64_decode((string)$b) ?: null;
        $p = self::dir() . '/' . $doc['stored_name'];
        return is_file($p) ? (string)file_get_contents($p) : null;
    }

    public static function canAccess(array $doc, ?array $user): bool
    {
        if (!$user) return false;
        if (Auth::isStaff($user)) return true;
        $cid = Db::val('SELECT id FROM customers WHERE user_id = ?', [$user['id']]);
        return $cid !== null && (int)$doc['customer_id'] === (int)$cid;
    }
}
