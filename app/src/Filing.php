<?php
declare(strict_types=1);
namespace App;

/**
 * Government Filing engine (Corporate Registry Filing Agent).
 *
 * Pipeline:  customer details → deterministic validation → customer e-authorization (locks a SHA-256 of the data)
 *            → AI review (Filing agent) → automatic submission through a registry connector → status polling
 *            → registry number + documents delivered → project milestones completed.
 *
 * Submission is automatic when ALL gates pass: order paid · validation clean · authorization hash matches current data ·
 * jurisdiction policy = auto · daily cap not exceeded · a live API connector exists for that jurisdiction/filing.
 * Jurisdictions without a government filing API (federal OFC, most provinces) use the "assisted" connector:
 * the package is fully prepared and the filing appears in the owner's one-click filing queue.
 */
final class Filings
{
    public const STATUSES = ['awaiting_details', 'needs_changes', 'awaiting_authorization', 'authorized', 'in_review', 'awaiting_approval',
        'ready_to_file', 'submitted', 'accepted', 'rejected', 'completed', 'cancelled'];

    /** Service slug → filing type. */
    public const TYPES = ['corp_numbered' => 'incorporation', 'corp_named' => 'incorporation', 'sole_prop' => 'sole_prop_registration',
        'partnership' => 'partnership_registration', 'biz_name' => 'business_name_registration', 'cooperative' => 'coop_incorporation',
        'bm_annual' => 'annual_return', 'bm_changes' => 'corporate_change', 'bm_documents' => 'document_order', 'nuans' => 'name_search'];

    public const TYPE_LABELS = ['incorporation' => 'Articles of Incorporation', 'sole_prop_registration' => 'Sole proprietorship registration',
        'partnership_registration' => 'Partnership registration', 'business_name_registration' => 'Business name registration', 'coop_incorporation' => 'Co-operative incorporation',
        'annual_return' => 'Annual return', 'corporate_change' => 'Notice of change', 'document_order' => 'Corporate document order', 'name_search' => 'NUANS name search & reservation'];

    /** Registry facts per jurisdiction. `api` lists filing types that have a live machine channel (when credentials are configured). */
    public const JURISDICTIONS = [
        'FED' => ['Corporations Canada (CBCA)', 'https://ised-isde.canada.ca/site/corporations-canada/en/online-filing-centre', [], 'Online Filing Centre requires an identity-verified ISED account; no public filing API.'],
        'BC' => ['BC Registries — Business Registry', 'https://www.bcregistry.gov.bc.ca', ['incorporation'], 'Business Registry API (Premium account + PAD).'],
        'ON' => ['Ontario Business Registry', 'https://www.ontario.ca/page/ontario-business-registry-partner-portal', [], 'Partner Portal (intermediary) or licensed service-provider API. Company Key required for existing entities.'],
        'AB' => ['Alberta Corporate Registry', 'https://www.alberta.ca/corporate-registry', [], 'Filed through an authorized registry agent.'],
        'SK' => ['Saskatchewan Corporate Registry (ISC)', 'https://www.isc.ca', [], 'Online services portal.'],
        'MB' => ['Manitoba Companies Office', 'https://companiesoffice.gov.mb.ca', [], 'Online services portal.'],
        'QC' => ['Registraire des entreprises du Québec', 'https://www.registreentreprises.gouv.qc.ca', [], 'clicSÉQUR portal; French-language filings.'],
        'NB' => ['Service New Brunswick Corporate Registry', 'https://www.pxw1.snb.ca', [], 'SNB online corporate registry.'],
        'NS' => ['Nova Scotia Registry of Joint Stock Companies', 'https://beta.novascotia.ca/programs-and-services/registry-joint-stock-companies', [], 'Registry of Joint Stock Companies online.'],
        'PE' => ['PEI Corporate Registry', 'https://www.princeedwardisland.ca/en/topic/corporate-registry', [], 'Online corporate registry.'],
        'NL' => ['NL Registry of Companies (CADO)', 'https://cado.eservices.gov.nl.ca', [], 'CADO online registry.'],
    ];

    // ── Lifecycle ─────────────────────────────────────────────────────────

    public static function typeFor(string $slug): ?string { return self::TYPES[$slug] ?? null; }

    /** Create the filing record for a registration/management project (idempotent). */
    public static function openForProject(array $project): ?array
    {
        $type = self::typeFor($project['service_slug']);
        if (!$type) return null;
        if ($f = Db::one('SELECT * FROM filings WHERE project_id = ?', [$project['id']])) return $f;
        $item = Db::one('SELECT options FROM order_items WHERE order_id = ? AND service_slug = ?', [$project['order_id'], $project['service_slug']]);
        $jur = strtoupper((string)(jdec($item['options'] ?? '{}')['province'] ?? 'NB'));
        if (!isset(self::JURISDICTIONS[$jur])) $jur = 'NB';
        $data = ['entity' => ['name_type' => $project['service_slug'] === 'corp_numbered' ? 'numbered' : 'named']];
        $form = jdec($item['options'] ?? '{}')['details'] ?? null;
        $status = 'awaiting_details'; $validation = [];
        if (is_array($form) && $form) {
            $form['contact_email'] = $form['contact_email'] ?? ($form['owners'][0]['email'] ?? $form['directors'][0]['email'] ?? $form['applicant']['email'] ?? '');
            if ($jur !== 'FED' && is_array($form['registered_office'] ?? null) && empty($form['registered_office']['province'])) $form['registered_office']['province'] = $jur;
            // Details typed into the order form become the filing draft; the customer still reviews and signs before anything is filed.
            $data = self::normalize($form + ['entity' => []], ['data' => jenc($data)]);
            $validation = self::validate($jur, $type, $data);
            $status = empty($validation['errors']) ? 'awaiting_authorization' : 'needs_changes';
        }
        $id = Db::insert('filings', ['number' => refno('FIL'), 'project_id' => $project['id'], 'customer_id' => $project['customer_id'], 'jurisdiction' => $jur,
            'filing_type' => $type, 'status' => $status, 'data' => jenc($data), 'validation' => jenc($validation ?: new \stdClass()), 'created_at' => now(), 'updated_at' => now()]);
        self::event($id, 'opened', self::TYPE_LABELS[$type] . ' — ' . self::JURISDICTIONS[$jur][0] . ($form ? ' (details from the order form)' : ''), 'system');
        return Db::one('SELECT * FROM filings WHERE id = ?', [$id]);
    }

    public static function get(int $id): ?array { return Db::one('SELECT * FROM filings WHERE id = ?', [$id]); }

    public static function event(int $filingId, string $kind, string $detail, string $actor): void
    {
        Db::insert('filing_events', ['filing_id' => $filingId, 'kind' => $kind, 'detail' => mb_substr($detail, 0, 4000), 'actor' => $actor, 'created_at' => now()]);
        Db::update('filings', ['updated_at' => now()], 'id = ?', [$filingId]);
    }

    private static function setStatus(int $id, string $status, string $actor, string $note = '', array $extra = []): void
    {
        Db::update('filings', ['status' => $status, 'updated_at' => now()] + $extra, 'id = ?', [$id]);
        self::event($id, 'status', $status . ($note ? ' — ' . $note : ''), $actor);
        $f = self::get($id);
        if ($f['project_id']) {
            $msg = ['needs_changes' => 'We need a few corrections to your filing details.', 'awaiting_authorization' => 'Your filing is ready — please review and authorize it.',
                'submitted' => 'Your filing was submitted to the ' . self::JURISDICTIONS[$f['jurisdiction']][0] . '.', 'ready_to_file' => 'Your filing is fully prepared and queued for submission to the registry.',
                'accepted' => 'The registry accepted your filing.' . ($f['registry_number'] ? ' Registry number: ' . $f['registry_number'] . '.' : ''), 'rejected' => 'The registry returned your filing — our team is correcting it.',
                'completed' => 'Your registration is complete and documents are in your portal.'][$status] ?? null;
            if ($msg) Projects::activity((int)$f['project_id'], 'filing', $msg, 'filing');
        }
    }

    /** Merge customer-submitted details, validate, and move to authorization when clean. Any data change voids a prior authorization. */
    public static function saveDetails(array $f, array $input, string $actor): array
    {
        if (in_array($f['status'], ['submitted', 'accepted', 'completed'], true)) throw new UserError('This filing was already submitted and can no longer be edited.');
        $data = self::normalize($input, $f);
        $v = self::validate($f['jurisdiction'], $f['filing_type'], $data);
        $hash = self::hash($data);
        $status = $v['errors'] ? 'needs_changes' : 'awaiting_authorization';
        $upd = ['data' => jenc($data), 'validation' => jenc($v), 'data_hash' => $hash];
        if ($f['auth_hash'] && $f['auth_hash'] !== $hash) $upd += ['auth_hash' => null, 'authorized_at' => null, 'auth_signer' => null];
        Db::update('filings', $upd, 'id = ?', [$f['id']]);
        self::setStatus((int)$f['id'], $status, $actor, $v['errors'] ? count($v['errors']) . ' issue(s)' : 'validated');
        Audit::log('filing.details', 'filing', $f['number'], ['actor' => $actor, 'errors' => count($v['errors'])]);
        return self::get((int)$f['id']);
    }

    /** Customer e-signature. Records signer, capacity, IP, user agent, time and locks the exact data hash. */
    public static function authorize(array $f, string $signer, string $capacity, bool $attest): array
    {
        if ($f['status'] !== 'awaiting_authorization') throw new UserError('This filing is not ready for authorization.');
        if (!$attest) throw new UserError('Please confirm the declaration to authorize the filing.');
        $signer = trim($signer);
        $names = array_map(fn($p) => mb_strtolower(trim(($p['first'] ?? '') . ' ' . ($p['last'] ?? ''))), self::people(jdec($f['data'])));
        if (mb_strlen($signer) < 3) throw new UserError('Type your full legal name to sign.');
        if ($names && !in_array(mb_strtolower($signer), $names, true)) throw new UserError('The signer must be one of the directors, incorporators or owners listed on the filing.');
        Db::update('filings', ['auth_signer' => $signer, 'auth_capacity' => mb_substr($capacity, 0, 60), 'auth_ip' => client_ip(), 'auth_ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255),
            'auth_hash' => $f['data_hash'], 'authorized_at' => now()], 'id = ?', [$f['id']]);
        self::setStatus((int)$f['id'], 'authorized', 'customer', "signed by {$signer}");
        Audit::log('filing.authorized', 'filing', $f['number'], ['signer' => $signer, 'hash' => $f['data_hash']]);
        if ($f['project_id']) { $m = Db::one("SELECT id FROM project_milestones WHERE project_id = ? AND title = 'Filing details & authorization' AND status = 'pending'", [$f['project_id']]); if ($m) Projects::completeMilestone((int)$m['id'], 'customer'); }
        Events::emit('filing.authorized', ['filing_id' => (int)$f['id'], 'project_id' => $f['project_id'] ? (int)$f['project_id'] : null, 'customer_id' => (int)$f['customer_id']]);
        return self::get((int)$f['id']);
    }

    /**
     * Gate check + submission. Returns ['ok'=>bool,'status'=>..., 'reason'=>...].
     * Called by the Filing agent's filing_submit tool and by the cron fallback.
     */
    public static function submit(int $id, string $actor, bool $ownerApproved = false): array
    {
        $f = self::get($id) ?? throw new UserError('Filing not found.');
        if (!in_array($f['status'], ['authorized', 'in_review', 'awaiting_approval', 'rejected'], true)) return ['ok' => false, 'status' => $f['status'], 'reason' => 'Not in a submittable state.'];
        foreach (self::gates($f) as $gate => [$pass, $why]) if (!$pass) { self::event($id, 'gate_blocked', "{$gate}: {$why}", $actor); return ['ok' => false, 'status' => $f['status'], 'reason' => "{$gate}: {$why}"]; }

        self::milestone($f, 'Filing review & validation');
        $mode = Settings::get('filing.mode.' . $f['jurisdiction'], 'auto');
        if ($mode === 'approval' && !$ownerApproved) {
            Approvals::request('government_filing', 'Approve filing ' . $f['number'] . ' (' . self::TYPE_LABELS[$f['filing_type']] . ', ' . $f['jurisdiction'] . ')',
                ['what' => 'Submit this authorized filing to the ' . self::JURISDICTIONS[$f['jurisdiction']][0] . '.', 'why' => 'Policy for ' . $f['jurisdiction'] . ' requires owner approval.',
                 'risks' => 'Legally binding government filing.', 'evidence' => 'Authorized by ' . $f['auth_signer'] . ' at ' . $f['authorized_at'] . ' (hash ' . substr((string)$f['auth_hash'], 0, 12) . ')'],
                'filing', $id, 'filing', 'high', ['filing_submit' => $id]);
            self::setStatus($id, 'awaiting_approval', $actor, 'policy requires owner approval');
            return ['ok' => true, 'status' => 'awaiting_approval'];
        }
        $conn = RegistryConnectors::for($f);
        Db::run('UPDATE filings SET attempts = attempts + 1, connector = ?, mode = ? WHERE id = ?', [$conn->name(), $conn->automatic() ? 'api' : 'assisted', $id]);
        if (!$conn->automatic()) {
            self::setStatus($id, 'ready_to_file', $actor, 'assisted channel: ' . self::JURISDICTIONS[$f['jurisdiction']][3]);
            Approvals::request('file_on_portal', 'File ' . $f['number'] . ' on the ' . self::JURISDICTIONS[$f['jurisdiction']][0] . ' portal',
                ['what' => 'Package is validated and customer-authorized. Open the filing, copy the prepared fields into the registry portal, then record the registry number.',
                 'why' => self::JURISDICTIONS[$f['jurisdiction']][3], 'impact' => 'Customer is waiting on registration.', 'evidence' => url('/admin/filings/' . $f['number'])], 'filing', $id, 'filing', 'high');
            return ['ok' => true, 'status' => 'ready_to_file'];
        }
        try {
            $r = $conn->submit($f, jdec($f['data']));
            self::setStatus($id, 'submitted', $actor, 'ref ' . $r['ref'], ['submission_ref' => $r['ref'], 'registry_number' => $r['identifier'] ?? null, 'submitted_at' => now(), 'next_check_at' => now(120)]);
            self::milestone($f, 'Submitted to the registry');
            Integrations::record($conn->name(), 'filing.submit', true, $f['number']);
            return ['ok' => true, 'status' => 'submitted', 'ref' => $r['ref']];
        } catch (\Throwable $e) {
            self::event($id, 'submit_error', $e->getMessage(), $actor);
            Integrations::record($conn->name(), 'filing.submit', false, $e->getMessage());
            if ((int)$f['attempts'] + 1 >= 3) Approvals::request('failed_filing', 'Filing ' . $f['number'] . ' could not be submitted', ['what' => 'Check the connector and data, then retry or file on the portal.', 'why' => $e->getMessage()], 'filing', $id, 'filing', 'high');
            throw new \RuntimeException('Registry submission failed: ' . $e->getMessage());
        }
    }

    /** @return array<string,array{0:bool,1:string}> */
    public static function gates(array $f): array
    {
        $paid = $f['project_id'] ? (bool)Db::val("SELECT 1 FROM projects p JOIN orders o ON o.id = p.order_id WHERE p.id = ? AND o.status IN ('paid','processing','completed')", [$f['project_id']]) : false;
        $v = jdec($f['validation']);
        $today = (int)Db::val("SELECT COUNT(*) FROM filings WHERE submitted_at >= ?", [Usage::dayStart()]);
        return [
            'payment' => [$paid, 'order is not paid'],
            'validation' => [empty($v['errors']) && !empty($f['data_hash']), 'validation errors outstanding'],
            'authorization' => [!empty($f['auth_hash']) && hash_equals((string)$f['auth_hash'], (string)$f['data_hash']) && hash_equals((string)$f['data_hash'], self::hash(jdec($f['data']))), 'customer authorization missing or data changed since signing'],
            'policy' => [Settings::get('filing.mode.' . $f['jurisdiction'], 'auto') !== 'manual', 'jurisdiction set to manual'],
            'daily_cap' => [$today < max(1, (int)Settings::get('filing.daily_cap', '25')), 'daily submission cap reached'],
        ];
    }

    /** Poll submitted filings (cron). */
    public static function poll(int $limit = 10): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM filings WHERE status = 'submitted' AND (next_check_at IS NULL OR next_check_at <= ?) LIMIT " . (int)$limit, [now()]) as $f) {
            $n++;
            try {
                $s = RegistryConnectors::for($f)->status($f);
                if ($s['state'] === 'accepted') self::recordOutcome($f, true, $s['identifier'] ?? null, null, 'registry');
                elseif ($s['state'] === 'rejected') self::recordOutcome($f, false, null, $s['reason'] ?? 'Rejected by registry', 'registry');
                else Db::update('filings', ['next_check_at' => now(min(3600, 300 * max(1, (int)$f['attempts'])))], 'id = ?', [$f['id']]);
            } catch (\Throwable $e) {
                self::event((int)$f['id'], 'poll_error', $e->getMessage(), 'system');
                Db::update('filings', ['next_check_at' => now(900)], 'id = ?', [$f['id']]);
            }
        }
        return $n;
    }

    /** Record the registry's decision (from API polling or the owner after a portal filing). */
    public static function recordOutcome(array $f, bool $accepted, ?string $registryNumber, ?string $reason, string $actor, ?int $documentId = null): void
    {
        if ($accepted) {
            self::setStatus((int)$f['id'], 'accepted', $actor, $registryNumber ?: '', ['registry_number' => $registryNumber ?: $f['registry_number'], 'completed_at' => now()]);
            self::milestone($f, 'Submitted to the registry');
            self::milestone($f, 'Registry approval');
            if ($documentId) Db::update('documents', ['project_id' => $f['project_id'], 'customer_id' => $f['customer_id'], 'kind' => 'registry_document'], 'id = ?', [$documentId]);
            self::milestone($f, 'Documents delivered');
            self::setStatus((int)$f['id'], 'completed', $actor);
            if ($f['project_id'] && ($c = Db::one('SELECT * FROM customers WHERE id = ?', [$f['customer_id']]))) {
                $p = Db::one('SELECT * FROM projects WHERE id = ?', [$f['project_id']]);
                Mailer::send('project_update', $c['email'], ['name' => $c['name'], 'project' => $p['title'], 'update' => 'Good news — the registry has accepted your filing' . ($registryNumber ? " (registry number {$registryNumber})" : '') . '. Your documents are available in your portal.',
                    'link' => url('/portal/projects/' . $p['number'])], 'filing-accepted:' . $f['id'], 'filing', (int)$f['id']);
            }
            Events::emit('filing.accepted', ['filing_id' => (int)$f['id'], 'project_id' => $f['project_id']]);
        } else {
            self::setStatus((int)$f['id'], 'rejected', $actor, (string)$reason, ['rejected_reason' => $reason]);
            Orchestrator::enqueue('filing', 'filing_rejection', 'Registry returned ' . $f['number'], ['filing_id' => (int)$f['id'], 'reason' => $reason],
                ['project_id' => $f['project_id'], 'customer_id' => (int)$f['customer_id'], 'priority' => 2, 'dedupe_key' => 'rej:' . $f['id'] . ':' . sha1((string)$reason)]);
        }
        Audit::log('filing.outcome', 'filing', $f['number'], ['accepted' => $accepted, 'registry_number' => $registryNumber, 'actor' => $actor]);
    }

    private static function milestone(array $f, string $title): void
    {
        if (!$f['project_id']) return;
        $m = Db::one("SELECT id FROM project_milestones WHERE project_id = ? AND title = ? AND status = 'pending'", [$f['project_id'], $title]);
        if ($m) Projects::completeMilestone((int)$m['id'], 'filing');
    }

    /** Cron fallback: when no AI provider is available, authorized filings proceed on deterministic checks alone. */
    public static function autoAdvance(): int
    {
        $n = 0;
        $aiLive = Ai::available('anthropic') || Ai::available('openai');
        foreach (Db::all("SELECT * FROM filings WHERE status = 'authorized' AND authorized_at <= ? LIMIT 10", [now($aiLive ? -1800 : 0)]) as $f) {
            try { self::submit((int)$f['id'], 'system'); $n++; } catch (\Throwable $e) { Log::warn('filing.auto_submit', ['filing' => $f['number'], 'error' => $e->getMessage()]); }
        }
        return $n + self::poll();
    }

    // ── Data model & validation ─────────────────────────────────────────

    public static function hash(array $data): string { return hash('sha256', jenc(self::ksortDeep($data))); }

    private static function ksortDeep(array $a): array { foreach ($a as &$v) if (is_array($v)) $v = self::ksortDeep($v); unset($v); if (array_keys($a) !== range(0, count($a) - 1)) ksort($a); return $a; }

    /** Directors, incorporators, owners or partners. */
    public static function people(array $d): array { return array_values(array_filter(array_merge($d['directors'] ?? [], $d['owners'] ?? []), fn($p) => trim(($p['first'] ?? '') . ($p['last'] ?? '')) !== '')); }

    private static function clean(mixed $v, int $max = 190): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }

    private static function address(array $a): array
    {
        return ['street' => self::clean($a['street'] ?? ''), 'city' => self::clean($a['city'] ?? '', 80), 'province' => strtoupper(self::clean($a['province'] ?? '', 3)),
            'postal' => strtoupper(preg_replace('/\s+/', '', self::clean($a['postal'] ?? '', 10))), 'country' => strtoupper(self::clean($a['country'] ?? 'CA', 2)) ?: 'CA'];
    }

    public static function normalize(array $in, array $f): array
    {
        $prev = jdec($f['data']);
        $people = function (array $rows, string $role): array {
            $out = [];
            foreach (array_slice($rows, 0, 10) as $p) {
                if (!is_array($p) || trim(($p['first'] ?? '') . ($p['last'] ?? '')) === '') continue;
                $out[] = ['first' => self::clean($p['first'] ?? '', 80), 'last' => self::clean($p['last'] ?? '', 80), 'email' => strtolower(self::clean($p['email'] ?? '', 190)),
                    'resident_canadian' => !empty($p['resident_canadian']), 'incorporator' => $role === 'director' ? !empty($p['incorporator']) : true, 'address' => self::address((array)($p['address'] ?? []))];
            }
            return $out;
        };
        return [
            'entity' => ['name_type' => in_array($in['entity']['name_type'] ?? '', ['named', 'numbered'], true) ? $in['entity']['name_type'] : ($prev['entity']['name_type'] ?? 'named'),
                'proposed_name' => self::clean($in['entity']['proposed_name'] ?? ''), 'legal_ending' => self::clean($in['entity']['legal_ending'] ?? '', 20),
                'name_reservation' => strtoupper(self::clean($in['entity']['name_reservation'] ?? '', 40)), 'activity' => self::clean($in['entity']['activity'] ?? '', 600),
                'start_date' => self::clean($in['entity']['start_date'] ?? '', 10), 'corporation_number' => strtoupper(self::clean($in['entity']['corporation_number'] ?? '', 40)),
                'company_key' => self::clean($in['entity']['company_key'] ?? '', 40), 'changes' => self::clean($in['entity']['changes'] ?? '', 3000), 'effective_date' => self::clean($in['entity']['effective_date'] ?? '', 10)],
            'registered_office' => self::address((array)($in['registered_office'] ?? [])),
            'records_office' => !empty($in['records_same']) ? self::address((array)($in['registered_office'] ?? [])) : self::address((array)($in['records_office'] ?? [])),
            'contact_email' => strtolower(self::clean($in['contact_email'] ?? '', 190)),
            'directors' => $people((array)($in['directors'] ?? []), 'director'),
            'owners' => $people((array)($in['owners'] ?? []), 'owner'),
            'shares' => ['classes' => self::clean($in['shares']['classes'] ?? 'Common shares, unlimited number, no par value', 600)],
        ];
    }

    /** Deterministic, jurisdiction-aware checks. Errors block submission; warnings are shown to customer and agent. */
    public static function validate(string $jur, string $type, array $d): array
    {
        $e = []; $w = [];
        $postal = fn($p) => (bool)preg_match('/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z]\d[ABCEGHJ-NPRSTV-Z]\d$/', (string)$p);
        $addr = function (array $a, string $label) use (&$e, $postal) {
            foreach (['street' => 'street', 'city' => 'city', 'province' => 'province'] as $k => $n) if (($a[$k] ?? '') === '') $e[] = "{$label}: {$n} is required.";
            if (($a['country'] ?? 'CA') === 'CA' && ($a['postal'] ?? '') !== '' && !$postal($a['postal'])) $e[] = "{$label}: postal code '{$a['postal']}' is not a valid Canadian postal code.";
            if (($a['postal'] ?? '') === '') $e[] = "{$label}: postal code is required.";
        };
        if ($d['contact_email'] === '' || !filter_var($d['contact_email'], FILTER_VALIDATE_EMAIL)) $e[] = 'A valid official contact email is required (registries send notices here).';
        $ent = $d['entity'];
        switch ($type) {
            case 'incorporation':
            case 'coop_incorporation':
                if ($ent['name_type'] === 'named') {
                    if ($ent['proposed_name'] === '') $e[] = 'Enter the proposed corporate name.';
                    if ($ent['legal_ending'] === '' && !preg_match('/\b(inc|ltd|corp|limited|incorporated|corporation|ltée|limitée)\.?$/i', $ent['proposed_name'])) $e[] = 'A named corporation needs a legal ending (Inc., Ltd., Corp., Limited, Incorporated or Corporation).';
                    if ($ent['name_reservation'] === '') $e[] = $jur === 'BC' ? 'BC named companies need an approved Name Request number (NR #).' : 'Named corporations need a NUANS report / name reservation number. Order the NUANS search first.';
                }
                $addr($d['registered_office'], 'Registered office');
                if ($jur !== 'FED' && ($d['registered_office']['province'] ?? '') !== $jur) $e[] = 'The registered office must be located in ' . (Catalog::PROVINCES[$jur] ?? $jur) . ' for a provincial incorporation.';
                if ($jur === 'BC') $addr($d['records_office'], 'Records office');
                if (!$d['directors']) $e[] = 'At least one director is required.';
                foreach ($d['directors'] as $i => $p) { $addr($p['address'], 'Director ' . ($i + 1) . ' address'); }
                if (!array_filter($d['directors'], fn($p) => $p['incorporator'])) $e[] = 'Mark at least one director as an incorporator (the person signing the articles).';
                if ($jur === 'FED' && $d['directors'] && count(array_filter($d['directors'], fn($p) => $p['resident_canadian'])) * 4 < count($d['directors'])) $w[] = 'Federal (CBCA) corporations generally require at least 25% resident Canadian directors — please confirm before filing.';
                if ($d['shares']['classes'] === '') $e[] = 'Describe the share structure.';
                if ($ent['activity'] === '') $w[] = 'Adding a short description of business activities helps with name review and CRA registration.';
                break;
            case 'sole_prop_registration':
            case 'partnership_registration':
            case 'business_name_registration':
                if ($ent['proposed_name'] === '') $e[] = 'Enter the business name to register.';
                if ($ent['activity'] === '') $e[] = 'Describe the business activity.';
                $addr($d['registered_office'], 'Business address');
                $min = $type === 'partnership_registration' ? 2 : 1;
                if (count($d['owners']) < $min) $e[] = $type === 'partnership_registration' ? 'A partnership needs at least two partners.' : 'Add the owner(s).';
                foreach ($d['owners'] as $i => $p) $addr($p['address'], 'Owner ' . ($i + 1) . ' address');
                if (preg_match('/\b(inc|ltd|corp|incorporated|limited|corporation)\.?\b/i', $ent['proposed_name'])) $e[] = 'Unincorporated businesses cannot use Inc., Ltd., Corp. or similar endings.';
                break;
            case 'annual_return':
            case 'corporate_change':
            case 'document_order':
                if ($ent['corporation_number'] === '') $e[] = 'Enter the corporation / registry number.';
                if ($jur === 'ON' && $ent['company_key'] === '') $e[] = 'Ontario requires the Company Key to file on behalf of an existing entity.';
                if ($type === 'corporate_change' && $ent['changes'] === '') $e[] = 'Describe the changes to file (new directors, address, etc.).';
                break;
            case 'name_search':
                if ($ent['proposed_name'] === '') $e[] = 'Enter the name(s) to search.';
                break;
        }
        return ['errors' => array_values(array_unique($e)), 'warnings' => $w, 'checked_at' => now()];
    }

    /** Human-readable filing sheet (used in assisted mode and as the customer's record). */
    public static function sheet(array $f): string
    {
        $d = jdec($f['data']); $ent = $d['entity'] ?? [];
        $a = fn($x) => trim(implode(', ', array_filter([$x['street'] ?? '', $x['city'] ?? '', $x['province'] ?? '', $x['postal'] ?? '', $x['country'] ?? ''])));
        $s = "FILING {$f['number']} — " . self::TYPE_LABELS[$f['filing_type']] . "\nRegistry: " . self::JURISDICTIONS[$f['jurisdiction']][0] . "\nPortal: " . self::JURISDICTIONS[$f['jurisdiction']][1] . "\n\n";
        $s .= "Name type: " . ($ent['name_type'] ?? '') . "\nProposed name: " . trim(($ent['proposed_name'] ?? '') . ' ' . ($ent['legal_ending'] ?? '')) . "\nName reservation: " . ($ent['name_reservation'] ?? '—') . "\n";
        foreach (['activity' => 'Activity', 'corporation_number' => 'Corporation number', 'company_key' => 'Company key', 'start_date' => 'Start date', 'changes' => 'Changes', 'effective_date' => 'Effective date'] as $k => $l) if (!empty($ent[$k])) $s .= "{$l}: " . ($k === 'company_key' ? str_repeat('•', 4) . substr($ent[$k], -2) : $ent[$k]) . "\n";
        $s .= "\nRegistered office / business address: " . $a($d['registered_office'] ?? []) . "\n";
        if ($f['jurisdiction'] === 'BC') $s .= "Records office: " . $a($d['records_office'] ?? []) . "\n";
        $s .= "Official email: " . ($d['contact_email'] ?? '') . "\n\n";
        foreach (['directors' => 'Director', 'owners' => 'Owner/partner'] as $k => $l) foreach ($d[$k] ?? [] as $i => $p) $s .= "{$l} " . ($i + 1) . ": {$p['first']} {$p['last']} — " . $a($p['address']) . ($k === 'directors' ? ($p['resident_canadian'] ? ' · resident Canadian' : '') . ($p['incorporator'] ? ' · incorporator' : '') : '') . "\n";
        if (in_array($f['filing_type'], ['incorporation', 'coop_incorporation'], true)) $s .= "\nShare structure: " . ($d['shares']['classes'] ?? '') . "\n";
        $s .= "\nAuthorized by: " . ($f['auth_signer'] ?: 'NOT YET AUTHORIZED') . ($f['authorized_at'] ? " on {$f['authorized_at']} UTC (data hash " . substr((string)$f['auth_hash'], 0, 16) . ')' : '') . "\n";
        return $s;
    }
}

/** Connector interface for registry channels. */
interface RegistryConnector
{
    public function name(): string;
    public function automatic(): bool;
    /** @return array{ref:string,identifier?:?string} */
    public function submit(array $filing, array $data): array;
    /** @return array{state:string,identifier?:?string,reason?:?string} state: pending|accepted|rejected */
    public function status(array $filing): array;
}

final class RegistryConnectors
{
    public static function for(array $f): RegistryConnector
    {
        $j = Filings::JURISDICTIONS[$f['jurisdiction']] ?? null;
        if ($j && in_array($f['filing_type'], $j[2], true) && $f['jurisdiction'] === 'BC' && Integrations::configured('bc_registry')) return new BcRegistryConnector();
        return new AssistedConnector();
    }
}

/** No machine channel: the package is prepared and routed to the owner's one-click filing queue. */
final class AssistedConnector implements RegistryConnector
{
    public function name(): string { return 'assisted'; }
    public function automatic(): bool { return false; }
    public function submit(array $filing, array $data): array { throw new \LogicException('Assisted filings are submitted on the registry portal.'); }
    public function status(array $filing): array { return ['state' => 'pending']; }
}

/**
 * BC Registries Business Registry API (Premium account, API key + Account-Id).
 * Payload follows the published Business API incorporationApplication filing model. Base URL is configurable
 * (Admin → Integrations → BC Registries) so sandbox and production can be switched without code changes.
 */
final class BcRegistryConnector implements RegistryConnector
{
    public function name(): string { return 'bc_registry'; }
    public function automatic(): bool { return true; }

    private function base(): string { return rtrim((string)Integrations::setting('bc_registry', 'base_url', 'https://bcregistry-prod.apigee.net'), '/'); }

    private function headers(): array
    {
        return ['x-apikey: ' . Integrations::secret('bc_registry', 'api_key'), 'Account-Id: ' . Integrations::secret('bc_registry', 'account_id'), 'Content-Type: application/json', 'Accept: application/json'];
    }

    private static function addr(array $a): array
    {
        return ['streetAddress' => $a['street'] ?? '', 'addressCity' => $a['city'] ?? '', 'addressRegion' => $a['province'] ?? '', 'postalCode' => $a['postal'] ?? '', 'addressCountry' => $a['country'] ?? 'CA', 'deliveryInstructions' => ''];
    }

    public function payload(array $f, array $d): array
    {
        $ent = $d['entity'];
        $named = $ent['name_type'] === 'named';
        $parties = [];
        foreach ($d['directors'] as $p) {
            $roles = [['roleType' => 'Director', 'appointmentDate' => gmdate('Y-m-d')]];
            if ($p['incorporator']) $roles[] = ['roleType' => 'Incorporator', 'appointmentDate' => gmdate('Y-m-d')];
            $parties[] = ['officer' => ['firstName' => $p['first'], 'lastName' => $p['last'], 'partyType' => 'person', 'email' => $p['email'] ?: $d['contact_email']],
                'mailingAddress' => self::addr($p['address']), 'deliveryAddress' => self::addr($p['address']), 'roles' => $roles];
        }
        $ia = ['nameRequest' => ['legalType' => 'BC'] + ($named ? ['nrNumber' => $ent['name_reservation'], 'legalName' => trim($ent['proposed_name'] . ' ' . $ent['legal_ending'])] : []),
            'offices' => ['registeredOffice' => ['mailingAddress' => self::addr($d['registered_office']), 'deliveryAddress' => self::addr($d['registered_office'])],
                'recordsOffice' => ['mailingAddress' => self::addr($d['records_office']), 'deliveryAddress' => self::addr($d['records_office'])]],
            'parties' => $parties,
            'shareStructure' => ['shareClasses' => [['name' => 'Common Shares', 'priority' => 1, 'hasMaximumShares' => false, 'hasParValue' => false, 'hasRightsOrRestrictions' => false, 'series' => []]]],
            'contactPoint' => ['email' => $d['contact_email']], 'incorporationAgreement' => ['agreementType' => 'sample']];
        return ['filing' => ['header' => ['name' => 'incorporationApplication', 'certifiedBy' => $f['auth_signer'], 'date' => gmdate('Y-m-d'), 'accountId' => Integrations::secret('bc_registry', 'account_id')],
            'business' => ['legalType' => 'BC'], 'incorporationApplication' => $ia]];
    }

    public function submit(array $f, array $d): array
    {
        $r = Integrations::http('POST', $this->base() . '/business/api/v2/businesses', $this->headers(), jenc($this->payload($f, $d)), 45);
        if ($r['status'] < 200 || $r['status'] >= 300) {
            $msg = $r['error'] ?: ('HTTP ' . $r['status'] . ' ' . mb_substr(jenc($r['json']['errors'] ?? $r['json']['message'] ?? ''), 0, 300));
            throw new \RuntimeException($msg);
        }
        $h = $r['json']['filing']['header'] ?? [];
        $ident = $r['json']['filing']['business']['identifier'] ?? null;
        if (empty($h['filingId'])) throw new \RuntimeException('Registry response did not include a filing id.');
        return ['ref' => ($ident ? $ident . ':' : '') . $h['filingId'], 'identifier' => $ident];
    }

    public function status(array $f): array
    {
        [$ident, $fid] = array_pad(explode(':', (string)$f['submission_ref'], 2), 2, null);
        if (!$fid) return ['state' => 'pending'];
        $r = Integrations::http('GET', $this->base() . '/business/api/v2/businesses/' . rawurlencode($ident) . '/filings/' . rawurlencode($fid), $this->headers(), null, 20);
        if ($r['status'] !== 200) throw new \RuntimeException('Status check HTTP ' . $r['status']);
        $st = strtoupper((string)($r['json']['filing']['header']['status'] ?? ''));
        return match ($st) {
            'COMPLETED' => ['state' => 'accepted', 'identifier' => $r['json']['filing']['business']['identifier'] ?? $ident],
            'ERROR', 'REJECTED', 'WITHDRAWN', 'CANCELLED' => ['state' => 'rejected', 'reason' => 'Registry status ' . $st . ' ' . mb_substr(jenc($r['json']['errors'] ?? ''), 0, 300)],
            default => ['state' => 'pending'],
        };
    }
}
