<?php
declare(strict_types=1);
namespace App;

/**
 * Agent tool registry. Every tool declares a risk level; Tools::execute() enforces
 *  (1) the agent's tool permissions, (2) the agent's autonomy level, (3) trusted server context
 *  (customer scoping comes from the session — never from model input).
 *
 * Risk ladder vs autonomy:  read: L1+ · low: L2+ · medium: L3+ · high: L4 · critical: always owner approval.
 */
final class Tools
{
    private const LEVEL_FOR_RISK = ['read' => 1, 'low' => 2, 'medium' => 3, 'high' => 4, 'critical' => 99];

    /** Which agents each orchestrating agent may assign work to. */
    private const DELEGATION = [
        'chief_ops' => ['sales', 'concierge', 'registration', 'website_dev', 'web_app', 'brand', 'seo', 'content', 'social', 'support', 'project_manager', 'qa', 'finance', 'growth', 'retention', 'research', 'security', 'pricing'],
        'project_manager' => ['registration', 'website_dev', 'web_app', 'brand', 'content', 'seo', 'qa', 'support'],
        'owner_assistant' => ['chief_ops', 'research', 'growth', 'content', 'seo', 'finance', 'retention', 'security', 'pricing'],
        'registration' => ['qa'], 'website_dev' => ['qa', 'content'], 'web_app' => ['qa'],
    ];

    private static ?array $defs = null;

    public static function defs(): array
    {
        if (self::$defs) return self::$defs;
        $obj = fn(array $props, array $req = []) => ['type' => 'object', 'properties' => $props, 'required' => $req];
        $str = fn(string $d) => ['type' => 'string', 'description' => $d];
        $items = ['type' => 'array', 'description' => 'Services: [{slug, opts:{province?, tld?, domain?}}]', 'items' => ['type' => 'object', 'properties' => [
            'slug' => ['type' => 'string'], 'opts' => ['type' => 'object', 'properties' => ['province' => ['type' => 'string'], 'tld' => ['type' => 'string'], 'domain' => ['type' => 'string']]]], 'required' => ['slug']]];
        $list = [
            ['search_catalog', 'Search the official service catalog (names, prices, timelines). Use before recommending anything.', $obj(['query' => $str('What the customer needs')], ['query']), 'read', [self::class, 'searchCatalog']],
            ['get_service', 'Full details for one service: deliverables, pricing model, province prices, requirements, limitations.', $obj(['slug' => $str('Service slug')], ['slug']), 'read', [self::class, 'getService']],
            ['quote_items', 'Price a set of services with the official pricing engine (returns one-time, recurring, discounts and quote-only items).', $obj(['items' => $items, 'coupon' => $str('Optional coupon code')], ['items']), 'read', [self::class, 'quoteItems']],
            ['build_cart', "Add services to the visitor's cart so they can review and check out. Only when the visitor agrees.", $obj(['items' => $items], ['items']), 'low', [self::class, 'buildCart']],
            ['capture_lead', 'Save the visitor as a lead once they give an email. Ask before saving. consent_marketing only if they explicitly agree to marketing emails.',
                $obj(['name' => $str('Name'), 'email' => $str('Email'), 'phone' => $str('Phone'), 'business_name' => $str('Business'), 'industry' => $str('Industry'), 'location' => $str('City/province'),
                    'summary' => $str('Needs summary'), 'services' => ['type' => 'array', 'items' => ['type' => 'string']], 'budget' => $str('Budget'), 'timeline' => $str('Timeline'),
                    'consent_marketing' => ['type' => 'boolean']], ['email', 'summary']), 'low', [self::class, 'captureLead']],
            ['create_proposal', 'Create a written proposal from catalog services for the current lead/customer; returns a link they can review and accept.', $obj(['title' => $str('Proposal title'), 'items' => $items, 'scope' => $str('Scope summary')], ['title', 'items']), 'low', [self::class, 'createProposal']],
            ['my_orders', "List the signed-in customer's orders. Returns nothing for visitors.", $obj([]), 'read', [self::class, 'myOrders']],
            ['my_projects', "List the signed-in customer's projects with status and recent activity.", $obj([]), 'read', [self::class, 'myProjects']],
            ['create_ticket', 'Open a support request for the customer.', $obj(['subject' => $str('Subject'), 'body' => $str('Details'), 'category' => $str(implode('|', Tickets::CATEGORIES))], ['subject', 'body']), 'low', [self::class, 'createTicket']],
            ['search_knowledge', 'Search approved company knowledge: policies, FAQs, SOPs, how-tos.', $obj(['query' => $str('Question')], ['query']), 'read', [self::class, 'searchKnowledge']],
            ['domain_check', 'Check whether a domain name is registered (RDAP / registrar). Result may be "unknown".', $obj(['domain' => $str('e.g. mybusiness.ca')], ['domain']), 'read', [self::class, 'domainCheck']],
            ['remember', 'Store a durable customer preference or business fact (only for signed-in customers who allow memory).', $obj(['key' => $str('short key'), 'value' => $str('fact')], ['key', 'value']), 'low', [self::class, 'remember']],
            ['request_human', 'Hand the conversation to a human team member.', $obj(['reason' => $str('Why')], ['reason']), 'low', [self::class, 'requestHuman']],
            ['create_task', 'Assign a task to another agent.', $obj(['agent' => $str('Agent slug'), 'title' => $str('Task title'), 'brief' => $str('Instructions'), 'project_id' => ['type' => 'integer'], 'priority' => ['type' => 'integer']], ['agent', 'title', 'brief']), 'low', [self::class, 'createTask']],
            ['list_open_tasks', 'List open agent tasks (optionally for one project).', $obj(['project_id' => ['type' => 'integer']]), 'read', [self::class, 'listOpenTasks']],
            ['update_project', 'Post a progress note on a project, optionally completing a milestone assigned to you or changing working status.', $obj(['project_id' => ['type' => 'integer'], 'note' => $str('Customer-safe progress summary'),
                'milestone' => $str('Exact milestone title to complete'), 'status' => $str('planning|in_progress|ai_working|qa|revision|awaiting_customer')], ['project_id', 'note']), 'low', [self::class, 'updateProject']],
            ['draft_deliverable', 'Save a draft deliverable (spec, copy, plan, checklist) for a project. QA is scheduled automatically.', $obj(['project_id' => ['type' => 'integer'], 'title' => $str('Title'), 'body' => $str('Markdown content')], ['project_id', 'title', 'body']), 'low', [self::class, 'draftDeliverable']],
            ['record_qa', 'Record an independent QA result for a deliverable.', $obj(['deliverable_id' => ['type' => 'integer'], 'pass' => ['type' => 'boolean'], 'notes' => $str('Specific findings')], ['deliverable_id', 'pass', 'notes']), 'low', [self::class, 'recordQa']],
            ['send_customer_update', 'Email the customer a short factual project update and log it on the timeline.', $obj(['project_id' => ['type' => 'integer'], 'update' => $str('Message')], ['project_id', 'update']), 'medium', [self::class, 'sendCustomerUpdate']],
            ['reply_ticket', 'Reply to a support ticket.', $obj(['ticket_id' => ['type' => 'integer'], 'body' => $str('Reply')], ['ticket_id', 'body']), 'medium', [self::class, 'replyTicket']],
            ['escalate_ticket', 'Escalate a ticket to a human with a reason.', $obj(['ticket_id' => ['type' => 'integer'], 'reason' => $str('Reason')], ['ticket_id', 'reason']), 'low', [self::class, 'escalateTicket']],
            ['request_approval', 'Ask the owner to approve a consequential action.', $obj(['kind' => $str('government_filing|deployment|domain_transfer|dns_change|refund|price_change|publish|other'), 'title' => $str('Title'),
                'what' => $str('What you want to do'), 'why' => $str('Why'), 'risks' => $str('Risks'), 'cost' => $str('Cost'), 'impact' => $str('Customer impact'), 'project_id' => ['type' => 'integer']], ['kind', 'title', 'what', 'why']), 'low', [self::class, 'requestApproval']],
            ['business_metrics', 'Live business metrics: revenue, orders, leads, projects, tickets, AI usage.', $obj(['days' => ['type' => 'integer', 'description' => 'Look-back window, default 30']]), 'read', [self::class, 'businessMetrics']],
            ['list_leads', 'List recent or overdue leads with score and status.', $obj(['filter' => $str('new|overdue|high|all')]), 'read', [self::class, 'listLeads']],
            ['create_content_draft', 'Save a content draft (blog, page, social, newsletter) for human review.', $obj(['kind' => $str('blog|page|social|newsletter|ad|case_study'), 'title' => $str('Title'), 'body' => $str('Content'), 'keyword' => $str('Primary keyword')], ['kind', 'title', 'body']), 'low', [self::class, 'createContentDraft']],
            ['save_opportunity', 'Save a researched business opportunity (cite sources; label assumptions).', $obj(['name' => $str('Name'), 'audience' => $str('Audience'), 'problem' => $str('Problem'), 'evidence' => $str('Evidence'),
                'sources' => ['type' => 'array', 'items' => ['type' => 'string']], 'monetization' => $str('How it makes money'), 'risks' => $str('Risks'), 'confidence' => $str('low|medium|high'), 'score' => ['type' => 'integer'], 'recommendation' => $str('Recommendation')], ['name', 'problem', 'confidence']), 'low', [self::class, 'saveOpportunity']],
            ['filing_get', 'Read a registry filing: data, validation results, authorization, gates and history.', $obj(['filing_id' => ['type' => 'integer']], ['filing_id']), 'read', [self::class, 'filingGet']],
            ['filing_flag', 'Record a review finding on a filing. If needs_customer_changes is true the customer is asked to correct details (and re-authorize).', $obj(['filing_id' => ['type' => 'integer'], 'message_to_customer' => $str('Plain-language request'), 'internal_note' => $str('Note for the team'), 'needs_customer_changes' => ['type' => 'boolean']], ['filing_id', 'internal_note']), 'low', [self::class, 'filingFlag']],
            ['filing_submit', 'Submit an authorized filing to the government registry (automatic via API where available, otherwise queued for portal filing). Gates are enforced by the platform.', $obj(['filing_id' => ['type' => 'integer'], 'review_summary' => $str('What you checked')], ['filing_id', 'review_summary']), 'medium', [self::class, 'filingSubmit']],
            ['security_events', 'Recent security events (failed logins, denied access, upload rejections).', $obj([]), 'read', [self::class, 'securityEvents']],
            ['publish_social_post', 'Publish a post to a connected social account (facebook, instagram, linkedin, x). Always needs the owner\'s approval.',
                $obj(['network' => $str('facebook|instagram|linkedin|x'), 'text' => $str('Post text'), 'link' => $str('Optional https link'), 'image_url' => $str('Picture address (required for Instagram)')], ['network', 'text']), 'high', [self::class, 'publishSocialPost']],
            ['our_filing_forms', 'Our website order forms for registry services: each service, the jurisdictions we offer it in, and every step and field we ask the customer for (with options). Optional slug.', $obj(['slug' => $str('Service slug, e.g. corp_numbered')]), 'read', [self::class, 'ourFilingForms']],
            ['save_form_finding', 'Record where one of our forms does not collect what a government registry requires, or asks for it differently. Cite the official page. The owner reviews every finding.',
                $obj(['slug' => $str('Our service slug'), 'jurisdiction' => $str('FED, NB, NS, PE, NL, ON, … or ALL'), 'field' => $str('Field or step concerned'), 'issue' => $str('missing_field|outdated|wrong_format|fee_change|document_needed|other'),
                    'detail' => $str('What the registry requires, in plain words'), 'suggestion' => $str('What to change in our form'), 'source_url' => $str('Official government page'), 'severity' => $str('high|medium|low')], ['slug', 'issue', 'detail', 'suggestion', 'source_url']), 'low', [self::class, 'saveFormFinding']],
            ['web_search', 'Search the web (runs on the AI provider; Anthropic only). Use it to find official fees and competitor prices.', $obj(['query' => $str('Search terms')], ['query']), 'read', [self::class, 'webSearchLocal']],
            ['pricing_overview', 'Our price points (per Atlantic province or domain ending), known government and third-party costs, markup, competitor prices on file and open proposals. Optional slug or category.', $obj(['slug' => $str('Service slug'), 'category' => $str(implode('|', array_keys(Catalog::CATEGORIES)))]), 'read', [self::class, 'pricingOverview']],
            ['save_cost_finding', 'Record an official government filing fee and/or third-party cost for a service price point, with the official source page. Saved as unconfirmed for the owner to check.',
                $obj(['slug' => $str('Service slug'), 'variant' => $str('Province code (NB, NS, PE, NL, FED, ...), domain ending (ca, com), or empty'), 'gov_fee' => $str('Government fee in CAD, e.g. 112.00'),
                    'third_party' => $str('Other cost in CAD, e.g. wholesale domain cost'), 'source_url' => $str('Official page address'), 'notes' => $str('What the fee covers')], ['slug', 'source_url']), 'low', [self::class, 'saveCostFinding']],
            ['save_competitor_price', 'Record a competitor\'s price for one of our services, with the page where it is published.',
                $obj(['slug' => $str('Our service slug'), 'variant' => $str('Province code or domain ending it compares to, or empty'), 'competitor' => $str('Business name'), 'region' => $str('Where they operate, e.g. Halifax, NS'),
                    'price' => $str('Their price in CAD'), 'includes_gov_fee' => ['type' => 'boolean', 'description' => 'Whether their price includes the government fee'], 'billing' => $str('one-time|month|year'),
                    'url' => $str('Page address'), 'notes' => $str('What is included')], ['slug', 'competitor', 'price', 'url']), 'low', [self::class, 'saveCompetitorPrice']],
            ['propose_price', 'Propose a new price for one price point. The owner approves or rejects it; nothing changes on the website until then.',
                $obj(['slug' => $str('Service slug'), 'variant' => $str('Province code or domain ending, or empty'), 'price' => $str('Proposed price in CAD'), 'strategy' => $str(implode('|', array_keys(Pricing::STRATEGIES))),
                    'rationale' => $str('Why, citing the competitor prices and costs on file')], ['slug', 'price', 'rationale']), 'low', [self::class, 'proposePrice']],
        ];
        $out = [];
        foreach ($list as [$n, $d, $s, $risk, $h]) $out[$n] = ['name' => $n, 'description' => $d, 'schema' => $s, 'risk' => $risk, 'handler' => $h];
        return self::$defs = $out;
    }

    public static function execute(array $agent, string $name, array $input, array &$ctx): array
    {
        $def = self::defs()[$name] ?? null;
        if (!$def || !in_array($name, $agent['tools'], true)) {
            Audit::security('agent.tool_denied', "{$agent['slug']} -> {$name}");
            return ['error' => 'Tool not permitted for this agent.'];
        }
        $need = self::LEVEL_FOR_RISK[$def['risk']];
        $level = (int)$agent['autonomy_level'];
        if (!empty($ctx['approved_tool']) && $ctx['approved_tool'] === $name) $need = 0; // owner already approved this exact call
        if ($level < $need) {
            if ($level <= 1) return ['status' => 'not_executed', 'note' => 'Assist mode: this is a recommendation only. Describe it to a human.'];
            $apId = Approvals::request('agent_action', "{$agent['name']} wants to run {$name}", [
                'what' => "Run {$name} with: " . str_limit(jenc($input), 400), 'why' => 'Above the agent\'s autonomy level (' . $level . ').', 'risks' => 'Risk level: ' . $def['risk'],
                'cost' => 'None', 'impact' => 'Customer-visible action', 'evidence' => 'Task ' . ($ctx['task_id'] ?? 'chat')], 'agent_task', $ctx['task_id'] ?? null, $agent['slug'], $def['risk'] === 'critical' ? 'high' : 'medium',
                ['tool' => $name, 'agent' => $agent['slug'], 'input' => $input, 'ctx' => array_intersect_key($ctx, array_flip(['customer_id', 'project_id', 'task_id']))]);
            return ['status' => 'needs_approval', 'approval_id' => $apId, 'note' => 'Queued for owner approval.', '_blocked' => ['approval_id' => $apId, 'tool' => $name]];
        }
        try {
            $t0 = microtime(true);
            $res = ($def['handler'])($input, $ctx, $agent);
            if (Ai::$mock) Simulator::$trace[] = ['tool' => $name, 'input' => $input, 'result' => $res, 'ms' => (int)((microtime(true) - $t0) * 1000)];
            Audit::log('agent.tool', 'tool', $name, ['agent' => $agent['slug'], 'risk' => $def['risk'], 'task' => $ctx['task_id'] ?? null], 'agent', $agent['slug']);
            return $res;
        } catch (UserError $e) {
            if (Ai::$mock) Simulator::$trace[] = ['tool' => $name, 'input' => $input, 'result' => ['error' => $e->getMessage()], 'ms' => 0];
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            if (Ai::$mock) Simulator::$lastResults[$name] = ['exception' => get_class($e) . ': ' . $e->getMessage()];
            Log::error('tool.failed', ['tool' => $name, 'error' => $e->getMessage()]);
            return ['error' => 'Tool failed. Reference ' . Log::correlationId()];
        }
    }

    // ── Handlers ───────────────────────────────────────────────────────────────

    private static function serviceSummary(array $s): array
    {
        return ['slug' => $s['slug'], 'name' => $s['name'], 'category' => $s['category'], 'price' => Catalog::priceLabel($s), 'timeline' => $s['timeline'], 'summary' => $s['summary'],
            'quote_only' => $s['pricing_model'] === 'quote'];
    }

    public static function searchCatalog(array $in): array
    {
        $q = mb_strtolower((string)($in['query'] ?? ''));
        $syn = ['incorporat' => 'corp', 'company' => 'corp', 'register' => 'registration', 'start' => 'registration', 'site' => 'website', 'web' => 'website', 'shop' => 'ecommerce', 'store' => 'ecommerce',
            'mail' => 'email', 'logo' => 'brand', 'name' => 'nuans', 'google' => 'gbp', 'seo' => 'seo', 'book' => 'booking', 'app' => 'app', 'annual' => 'annual', 'director' => 'changes', 'domain' => 'domain'];
        $terms = array_filter(preg_split('/[^\pL\pN]+/u', $q), fn($t) => mb_strlen($t) > 2);
        foreach ($terms as $t) foreach ($syn as $k => $v) if (str_starts_with($t, $k)) $terms[] = $v;
        $scored = [];
        foreach (Catalog::all() as $s) {
            $hay = mb_strtolower($s['slug'] . ' ' . $s['name'] . ' ' . $s['summary'] . ' ' . $s['category'] . ' ' . $s['description']);
            $sc = 0;
            foreach (array_unique($terms) as $t) if (str_contains($hay, $t)) $sc += str_contains(mb_strtolower($s['name'] . ' ' . $s['slug']), $t) ? 3 : 1;
            if ($sc) $scored[] = [$sc, $s];
        }
        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
        $res = array_map(fn($x) => self::serviceSummary($x[1]), array_slice($scored, 0, 8));
        if (!$res) $res = array_map([self::class, 'serviceSummary'], array_slice(Catalog::all(), 0, 8));
        return ['services' => $res, 'note' => 'Prices in CAD from the official catalog.'];
    }

    public static function getService(array $in): array
    {
        $s = Catalog::find((string)($in['slug'] ?? '')) ?? throw new UserError('Unknown service slug.');
        return self::serviceSummary($s) + ['description' => $s['description'], 'deliverables' => $s['deliverables'], 'pricing_model' => $s['pricing_model'],
            'province_prices' => $s['pricing_model'] === 'per_province' ? array_map(fn($v) => $v === null ? 'not offered' : money($v), $s['province_prices']) : null,
            'tld_prices' => !empty($s['options']['tlds']) ? array_map('money', $s['options']['tlds']) : null, 'setup' => $s['setup_cents'] ? money($s['setup_cents']) : null,
            'requirements' => $s['requirements'], 'limitations' => $s['limitations'], 'human_involvement' => $s['human_required']];
    }

    private static function lines(array $items): array
    {
        $out = [];
        foreach (array_slice($items, 0, 20) as $it) if (is_array($it) && !empty($it['slug'])) $out[] = ['slug' => (string)$it['slug'], 'opts' => (array)($it['opts'] ?? []), 'qty' => 1];
        return $out;
    }

    public static function quoteItems(array $in): array
    {
        $lines = self::lines((array)($in['items'] ?? [])); $quoteOnly = []; $priceable = []; $errors = [];
        foreach ($lines as $l) {
            $s = Catalog::find($l['slug']);
            if (!$s) { $errors[] = "Unknown service {$l['slug']}"; continue; }
            try { Catalog::price($s, $l['opts']) === null ? $quoteOnly[] = $s['name'] : $priceable[] = $l; } catch (UserError $e) { $errors[] = $e->getMessage(); }
        }
        $t = Cart::totals($priceable, $in['coupon'] ?? null);
        return ['lines' => array_map(fn($l) => ['name' => $l['name'], 'price' => money($l['unit_cents']) . match ($l['billing']) { 'monthly' => '/mo', 'yearly' => '/yr', default => '' }, 'setup' => $l['setup_cents'] ? money($l['setup_cents']) : null], $t['lines']),
            'one_time_total' => money($t['one_time'] + $t['setup']), 'monthly_total' => money($t['recurring_monthly']), 'yearly_total' => money($t['recurring_yearly']),
            'discount' => money($t['discount']), 'due_today' => money($t['due_today']), 'quote_only_items' => $quoteOnly, 'errors' => $errors,
            'note' => $quoteOnly ? 'Quote-only items need a custom proposal; do not estimate their price.' : 'Official catalog pricing.'];
    }

    public static function buildCart(array $in, array $ctx): array
    {
        if (($ctx['channel'] ?? '') !== 'web') return ['error' => 'Cart is only available in website chat.'];
        $added = []; $errors = [];
        foreach (self::lines((array)($in['items'] ?? [])) as $l) {
            try { Cart::add($l['slug'], $l['opts']); $added[] = $l['slug']; } catch (UserError $e) { $errors[] = $e->getMessage(); }
        }
        $t = Cart::totals();
        return ['added' => $added, 'errors' => $errors, 'cart_due_today' => money($t['due_today']), '_action' => ['type' => 'cart', 'count' => count(Cart::lines()), 'url' => '/cart']];
    }

    public static function captureLead(array $in, array &$ctx): array
    {
        $email = strtolower(trim((string)($in['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Ask the visitor for a valid email address first.'];
        if (!RateLimit::hit('lead:' . client_ip(), 10, 3600)) return ['error' => 'Too many submissions.'];
        $c = Crm::upsertCustomer($in + ['lead_source' => 'ai_chat'], 'ai_chat');
        if (!empty($in['consent_marketing'])) Db::update('customers', ['consent_marketing' => 1, 'consent_at' => now()], 'id = ?', [$c['id']]);
        $lead = Crm::createLead($c, ['summary' => $in['summary'] ?? '', 'services' => $in['services'] ?? [], 'budget' => $in['budget'] ?? null, 'timeline' => $in['timeline'] ?? null,
            'intent' => 'chat', 'conversation_id' => $ctx['conversation_id'] ?? null], 'ai_chat');
        if (!empty($ctx['conversation_id'])) Db::update('conversations', ['customer_id' => $c['id']], 'id = ?', [$ctx['conversation_id']]);
        $ctx['customer_id'] = (int)$c['id'];
        $ctx['lead_id'] = (int)$lead['id'];
        Mailer::send('lead_confirmation', $email, ['name' => $c['name'] ?: 'there', 'summary' => str_limit((string)($in['summary'] ?? ''), 300)], 'lead-confirm:' . $lead['id'], 'lead', (int)$lead['id']);
        return ['saved' => true, 'lead_number' => $lead['id'], 'score' => (int)$lead['score'], 'note' => 'Confirmation email sent.', '_action' => ['type' => 'lead']];
    }

    public static function createProposal(array $in, array $ctx): array
    {
        $cid = $ctx['customer_id'] ?? null;
        if (!$cid) return ['error' => 'Capture the lead (email) before creating a proposal.'];
        $p = Proposals::create((int)$cid, (string)($in['title'] ?? 'Proposal'), self::lines((array)($in['items'] ?? [])), ['scope' => $in['scope'] ?? null, 'lead_id' => $ctx['lead_id'] ?? null], 'agent');
        Db::update('proposals', ['status' => 'sent', 'sent_at' => now()], 'id = ?', [$p['id']]);
        return ['proposal' => $p['number'], 'one_time' => money((int)$p['one_time_cents']), 'recurring' => money((int)$p['recurring_cents']), 'needs_quote' => (bool)$p['needs_quote'],
            'url' => '/proposal/' . $p['public_token'], '_action' => ['type' => 'proposal', 'url' => '/proposal/' . $p['public_token'], 'number' => $p['number']]];
    }

    public static function myOrders(array $in, array $ctx): array
    {
        if (empty($ctx['customer_id']) || empty($ctx['user_id'])) return ['orders' => [], 'note' => 'Visitor is not signed in. Ask them to sign in to see orders.'];
        $rows = Db::all('SELECT number, status, total_cents, created_at, paid_at FROM orders WHERE customer_id = ? ORDER BY created_at DESC LIMIT 10', [$ctx['customer_id']]);
        return ['orders' => array_map(fn($o) => ['number' => $o['number'], 'status' => label($o['status']), 'total' => money((int)$o['total_cents']), 'date' => substr($o['created_at'], 0, 10)], $rows)];
    }

    public static function myProjects(array $in, array $ctx): array
    {
        if (empty($ctx['customer_id']) || empty($ctx['user_id'])) return ['projects' => [], 'note' => 'Visitor is not signed in.'];
        $out = [];
        foreach (Db::all('SELECT id, number, title, status, progress FROM projects WHERE customer_id = ? ORDER BY updated_at DESC LIMIT 10', [$ctx['customer_id']]) as $p) {
            $act = Db::all('SELECT summary, created_at FROM project_activity WHERE project_id = ? AND visible_to_customer = 1 ORDER BY id DESC LIMIT 3', [$p['id']]);
            $out[] = ['number' => $p['number'], 'title' => $p['title'], 'status' => label($p['status']), 'progress' => $p['progress'] . '%', 'recent' => array_column($act, 'summary'), 'url' => '/portal/projects/' . $p['number']];
        }
        return ['projects' => $out];
    }

    public static function createTicket(array $in, array $ctx): array
    {
        if (!RateLimit::hit('ticket:' . client_ip(), 10, 3600)) return ['error' => 'Too many tickets.'];
        $t = Tickets::create($ctx['customer_id'] ?? null, (string)$in['subject'], (string)$in['body'], (string)($in['category'] ?? 'general'), 'ai', 'AI Concierge (on behalf of customer)');
        return ['ticket' => $t['number'], 'priority' => $t['priority'], '_action' => ['type' => 'ticket', 'number' => $t['number']]];
    }

    public static function searchKnowledge(array $in): array { return ['articles' => Kb::search((string)($in['query'] ?? ''), 4)]; }

    public static function domainCheck(array $in): array
    {
        $d = strtolower(trim((string)($in['domain'] ?? '')));
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9\-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $d)) return ['error' => 'Invalid domain name.'];
        return Domains::check($d);
    }

    public static function remember(array $in, array $ctx): array
    {
        if (empty($ctx['customer_id']) || empty($ctx['user_id'])) return ['saved' => false, 'note' => 'Memory is only kept for signed-in customers.'];
        if (!Memory::putCustomer((int)$ctx['customer_id'], (string)$in['key'], (string)$in['value'], 'concierge')) return ['saved' => false, 'note' => 'Customer has turned memory off.'];
        return ['saved' => true];
    }

    public static function requestHuman(array $in, array $ctx): array
    {
        if (!empty($ctx['conversation_id'])) Db::update('conversations', ['handoff_requested' => 1, 'updated_at' => now()], 'id = ?', [$ctx['conversation_id']]);
        $t = Tickets::create($ctx['customer_id'] ?? null, 'Chat handoff: ' . str_limit((string)$in['reason'], 120), 'Customer asked for a human in website chat. Reason: ' . $in['reason'] . "\nConversation #" . ($ctx['conversation_id'] ?? '-'), 'general', 'ai', 'AI Concierge');
        Mailer::notifyOwner('Chat handoff requested', 'Reason: ' . $in['reason'] . "\nTicket " . $t['number'], '/admin/tickets');
        return ['handoff' => true, 'ticket' => $t['number'], 'note' => 'A team member will reply by email, usually within one business day.', '_action' => ['type' => 'handoff', 'ticket' => $t['number']]];
    }

    public static function createTask(array $in, array $ctx, array $agent): array
    {
        $target = (string)($in['agent'] ?? '');
        if (!in_array($target, self::DELEGATION[$agent['slug']] ?? [], true)) return ['error' => "You may not assign work to {$target}."];
        $depth = (int)($ctx['depth'] ?? 0);
        if ($depth >= 3) return ['error' => 'Delegation depth limit reached; escalate instead.'];
        $id = Orchestrator::enqueue($target, 'delegated', (string)$in['title'], ['brief' => (string)$in['brief'], 'depth' => $depth + 1], [
            'parent_id' => $ctx['task_id'] ?? null, 'project_id' => $in['project_id'] ?? $ctx['project_id'] ?? null, 'customer_id' => $ctx['customer_id'] ?? null,
            'priority' => (int)($in['priority'] ?? 5), 'created_by' => $agent['slug'], 'dedupe_key' => 'del:' . sha1($target . ($in['project_id'] ?? '') . mb_strtolower((string)$in['title']))]);
        return ['task_id' => $id, 'assigned_to' => $target];
    }

    public static function listOpenTasks(array $in): array
    {
        $p = isset($in['project_id']) ? (int)$in['project_id'] : null;
        $rows = Db::all("SELECT public_id, agent_slug, title, status, progress_note, created_at FROM agent_tasks WHERE status NOT IN ('completed','cancelled')" . ($p ? ' AND project_id = ?' : '') . ' ORDER BY priority, created_at LIMIT 25', $p ? [$p] : []);
        return ['tasks' => $rows];
    }

    private static function project(int $id): array { return Db::one('SELECT * FROM projects WHERE id = ?', [$id]) ?? throw new UserError('Project not found.'); }

    public static function updateProject(array $in, array $ctx, array $agent): array
    {
        $p = self::project((int)$in['project_id']);
        if (!empty($ctx['project_id']) && (int)$ctx['project_id'] !== (int)$p['id'] && $agent['slug'] !== 'project_manager') return ['error' => 'You can only update the project for your task.'];
        Projects::activity((int)$p['id'], 'agent_update', str_limit((string)$in['note'], 480), $agent['slug']);
        $res = ['ok' => true];
        if (!empty($in['milestone'])) {
            $m = Db::one('SELECT * FROM project_milestones WHERE project_id = ? AND title = ?', [$p['id'], $in['milestone']]);
            if (!$m) $res['milestone'] = 'not found';
            elseif ($m['agent_slug'] !== $agent['slug'] && $agent['slug'] !== 'project_manager') $res['milestone'] = 'not yours — only ' . $m['agent_slug'] . ' can complete it';
            elseif (in_array($m['agent_slug'], ['owner', 'customer'], true)) $res['milestone'] = 'requires owner/customer';
            else { Projects::completeMilestone((int)$m['id'], $agent['slug']); $res['milestone'] = 'completed'; }
        }
        if (!empty($in['status']) && in_array($in['status'], ['planning', 'in_progress', 'ai_working', 'qa', 'revision', 'awaiting_customer'], true)) {
            Db::update('projects', ['status' => $in['status'], 'updated_at' => now()], 'id = ?', [$p['id']]);
        }
        return $res;
    }

    public static function draftDeliverable(array $in, array $ctx, array $agent): array
    {
        $p = self::project((int)$in['project_id']);
        $id = Db::insert('deliverables', ['project_id' => $p['id'], 'title' => mb_substr((string)$in['title'], 0, 190), 'body' => mb_substr((string)$in['body'], 0, 60000),
            'status' => 'draft', 'qa_status' => 'pending', 'produced_by' => $agent['slug'], 'created_at' => now(), 'updated_at' => now()]);
        Projects::activity((int)$p['id'], 'deliverable', 'Draft ready for quality review: ' . $in['title'], $agent['slug']);
        Orchestrator::enqueue('qa', 'qa_review', 'QA: ' . $in['title'], ['deliverable_id' => $id], ['project_id' => $p['id'], 'customer_id' => $p['customer_id'], 'parent_id' => $ctx['task_id'] ?? null, 'dedupe_key' => 'qa:' . $id, 'created_by' => $agent['slug']]);
        return ['deliverable_id' => $id, 'qa' => 'scheduled'];
    }

    public static function recordQa(array $in, array $ctx, array $agent): array
    {
        $d = Db::one('SELECT * FROM deliverables WHERE id = ?', [(int)$in['deliverable_id']]) ?? throw new UserError('Deliverable not found.');
        if ($d['produced_by'] === $agent['slug']) return ['error' => 'QA must be independent of the producing agent.'];
        $pass = (bool)$in['pass'];
        Db::update('deliverables', ['qa_status' => $pass ? 'passed' : 'failed', 'qa_notes' => mb_substr((string)$in['notes'], 0, 8000), 'status' => $pass ? 'ready' : 'revision', 'updated_at' => now()], 'id = ?', [$d['id']]);
        Projects::activity((int)$d['project_id'], 'qa', ($pass ? 'Quality review passed: ' : 'Quality review requested changes: ') . $d['title'], 'qa');
        if (!$pass && $d['produced_by']) {
            Orchestrator::enqueue($d['produced_by'], 'revision', 'Revise: ' . $d['title'], ['deliverable_id' => $d['id'], 'qa_notes' => (string)$in['notes']], ['project_id' => $d['project_id'], 'dedupe_key' => 'rev:' . $d['id'] . ':' . substr(sha1((string)$in['notes']), 0, 8), 'created_by' => 'qa']);
        }
        return ['recorded' => true, 'result' => $pass ? 'passed' : 'failed'];
    }

    public static function sendCustomerUpdate(array $in, array $ctx, array $agent): array
    {
        $p = self::project((int)$in['project_id']);
        $c = Db::one('SELECT * FROM customers WHERE id = ?', [$p['customer_id']]);
        $msg = str_limit(trim((string)$in['update']), 1200);
        Projects::activity((int)$p['id'], 'customer_update', str_limit($msg, 480), $agent['slug']);
        $r = Mailer::send('project_update', $c['email'], ['name' => $c['name'], 'project' => $p['title'], 'update' => $msg, 'link' => url('/portal/projects/' . $p['number'])], 'pu:' . $p['id'] . ':' . sha1($msg), 'project', (int)$p['id']);
        return ['sent' => $r['ok'], 'status' => $r['status']];
    }

    public static function replyTicket(array $in, array $ctx, array $agent): array
    {
        $t = Db::one('SELECT * FROM tickets WHERE id = ?', [(int)$in['ticket_id']]) ?? throw new UserError('Ticket not found.');
        if ($t['priority'] === 'high' || in_array($t['category'], ['refunds', 'billing'], true)) {
            Db::update('tickets', ['assigned_to' => 'human', 'updated_at' => now()], 'id = ?', [$t['id']]);
            return ['error' => 'Sensitive ticket — must be handled by a human. It has been escalated.'];
        }
        Tickets::reply((int)$t['id'], (string)$in['body'], 'ai', 'AI Support (818 Digital Agency)');
        if ($t['customer_id'] && ($c = Db::one('SELECT * FROM customers WHERE id = ?', [$t['customer_id']]))) {
            Mailer::send('ticket_update', $c['email'], ['name' => $c['name'], 'number' => $t['number'], 'subject' => $t['subject'], 'update' => (string)$in['body'], 'link' => url('/portal/support/' . $t['number'])], 'tr:' . $t['id'] . ':' . sha1((string)$in['body']), 'ticket', (int)$t['id']);
        }
        return ['replied' => true];
    }

    public static function escalateTicket(array $in): array
    {
        $t = Db::one('SELECT * FROM tickets WHERE id = ?', [(int)$in['ticket_id']]) ?? throw new UserError('Ticket not found.');
        Db::update('tickets', ['assigned_to' => 'human', 'priority' => 'high', 'updated_at' => now()], 'id = ?', [$t['id']]);
        Tickets::reply((int)$t['id'], 'Escalated to the team: ' . $in['reason'], 'ai', 'AI Support', true);
        Approvals::request('escalation', 'Customer escalation: ' . $t['subject'], ['what' => 'Respond to ticket ' . $t['number'], 'why' => (string)$in['reason'], 'impact' => 'Customer waiting'], 'ticket', (int)$t['id'], 'support', 'medium');
        return ['escalated' => true];
    }

    public static function requestApproval(array $in, array $ctx, array $agent): array
    {
        $id = Approvals::request(preg_replace('/[^a-z_]/', '', (string)$in['kind']) ?: 'other', (string)$in['title'], array_intersect_key($in, array_flip(['what', 'why', 'risks', 'cost', 'impact'])) + ['evidence' => 'Task ' . ($ctx['task_id'] ?? '-')],
            isset($in['project_id']) ? 'project' : 'agent_task', isset($in['project_id']) ? (int)$in['project_id'] : ($ctx['task_id'] ?? null), $agent['slug'],
            in_array($in['kind'], ['government_filing', 'domain_transfer', 'dns_change', 'refund', 'deployment'], true) ? 'high' : 'medium');
        if (!empty($in['project_id'])) Db::update('projects', ['status' => 'awaiting_approval', 'updated_at' => now()], 'id = ?', [(int)$in['project_id']]);
        return ['approval_id' => $id, 'status' => 'pending', '_blocked' => ['approval_id' => $id, 'tool' => 'request_approval']];
    }

    public static function businessMetrics(array $in): array { return Metrics::overview(max(1, min(365, (int)($in['days'] ?? 30)))); }

    public static function listLeads(array $in): array
    {
        $f = (string)($in['filter'] ?? 'new'); if (!in_array($f, ['new', 'overdue', 'high', 'all'], true)) $f = 'new';
        $where = match ($f) { 'overdue' => "l.next_follow_up_at < ? AND l.status NOT IN ('converted','closed')", 'high' => "l.score >= 60 AND l.status NOT IN ('converted','closed')", 'all' => '1=1', default => 'l.created_at >= ?' };
        $p = match ($f) { 'overdue' => [now()], 'new' => [Db::since(7)], default => [] };
        $rows = Db::all("SELECT l.id, c.name, c.business_name, l.score, l.status, l.urgency, l.summary, l.created_at FROM leads l JOIN customers c ON c.id = l.customer_id WHERE {$where} ORDER BY l.score DESC LIMIT 20", $p);
        return ['leads' => array_map(fn($r) => $r + ['summary' => str_limit((string)$r['summary'], 160)], $rows)];
    }

    public static function createContentDraft(array $in, array $ctx, array $agent): array
    {
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower((string)$in['title'])), '-') . '-' . substr(rid(2), 0, 4);
        $id = Db::insert('content_items', ['kind' => preg_replace('/[^a-z_]/', '', (string)$in['kind']) ?: 'blog', 'title' => mb_substr((string)$in['title'], 0, 190), 'slug' => mb_substr($slug, 0, 160),
            'body' => mb_substr((string)$in['body'], 0, 60000), 'keyword' => mb_substr((string)($in['keyword'] ?? ''), 0, 120), 'status' => 'review', 'seo_score' => Seo::score((string)$in['title'], (string)$in['body'], (string)($in['keyword'] ?? '')),
            'readability' => Seo::readability((string)$in['body']), 'author' => $agent['slug'], 'meta' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        return ['content_id' => $id, 'status' => 'review', 'note' => 'Awaiting human approval before publishing.'];
    }

    public static function saveOpportunity(array $in): array
    {
        $id = Db::insert('opportunities', ['name' => mb_substr((string)$in['name'], 0, 190), 'audience' => mb_substr((string)($in['audience'] ?? ''), 0, 190), 'problem' => (string)$in['problem'],
            'evidence' => (string)($in['evidence'] ?? ''), 'sources' => jenc(array_slice((array)($in['sources'] ?? []), 0, 10)), 'monetization' => (string)($in['monetization'] ?? ''),
            'risks' => (string)($in['risks'] ?? ''), 'confidence' => in_array($in['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $in['confidence'] : 'low',
            'score' => max(0, min(100, (int)($in['score'] ?? 0))), 'recommendation' => (string)($in['recommendation'] ?? ''), 'status' => 'new', 'created_at' => now()]);
        return ['opportunity_id' => $id];
    }

    private static function filingFor(array $in, array $ctx): array
    {
        $f = Filings::get((int)($in['filing_id'] ?? 0)) ?? throw new UserError('Filing not found.');
        if (!empty($ctx['project_id']) && (int)$f['project_id'] !== (int)$ctx['project_id']) throw new UserError('That filing belongs to a different project.');
        return $f;
    }

    public static function filingGet(array $in, array $ctx): array
    {
        $f = self::filingFor($in, $ctx);
        $d = jdec($f['data']);
        if (!empty($d['entity']['company_key'])) $d['entity']['company_key'] = '[on file]';
        return ['number' => $f['number'], 'jurisdiction' => $f['jurisdiction'], 'registry' => Filings::JURISDICTIONS[$f['jurisdiction']][0], 'type' => Filings::TYPE_LABELS[$f['filing_type']],
            'status' => $f['status'], 'data_untrusted' => $d, 'validation' => jdec($f['validation']), 'authorized_by' => $f['auth_signer'], 'authorized_at' => $f['authorized_at'],
            'gates' => array_map(fn($g) => $g[0] ? 'pass' : 'FAIL: ' . $g[1], Filings::gates($f)), 'channel' => RegistryConnectors::for($f)->automatic() ? 'automatic API' : 'assisted portal filing',
            'rejected_reason' => $f['rejected_reason'], 'history' => array_column(Db::all('SELECT kind, detail FROM filing_events WHERE filing_id = ? ORDER BY id DESC LIMIT 10', [$f['id']]), 'detail')];
    }

    public static function filingFlag(array $in, array $ctx, array $agent): array
    {
        $f = self::filingFor($in, $ctx);
        Filings::event((int)$f['id'], 'agent_review', (string)$in['internal_note'], $agent['slug']);
        if (!empty($in['needs_customer_changes'])) {
            if (in_array($f['status'], ['submitted', 'accepted', 'completed'], true)) return ['error' => 'Already submitted.'];
            $v = jdec($f['validation']); $v['errors'][] = 'Review: ' . str_limit((string)($in['message_to_customer'] ?? $in['internal_note']), 400);
            Db::update('filings', ['status' => 'needs_changes', 'validation' => jenc($v), 'auth_hash' => null, 'authorized_at' => null, 'updated_at' => now()], 'id = ?', [$f['id']]);
            Filings::event((int)$f['id'], 'status', 'needs_changes — agent review', $agent['slug']);
            $c = Db::one('SELECT * FROM customers WHERE id = ?', [$f['customer_id']]); $p = Db::one('SELECT * FROM projects WHERE id = ?', [$f['project_id']]);
            if ($c && $p) {
                Projects::activity((int)$p['id'], 'filing', 'We need a quick correction before filing: ' . str_limit((string)($in['message_to_customer'] ?? ''), 300), 'filing');
                Mailer::send('approval_request', $c['email'], ['name' => $c['name'], 'project' => $p['title'], 'update' => (string)($in['message_to_customer'] ?? 'Please review your filing details.'), 'link' => url('/portal/projects/' . $p['number'] . '/filing')], 'fil-flag:' . $f['id'] . ':' . sha1((string)$in['internal_note']), 'filing', (int)$f['id']);
            }
            return ['status' => 'needs_changes', 'customer_notified' => true];
        }
        return ['noted' => true];
    }

    public static function filingSubmit(array $in, array $ctx, array $agent): array
    {
        $f = self::filingFor($in, $ctx);
        Filings::event((int)$f['id'], 'agent_review', 'Reviewed: ' . str_limit((string)$in['review_summary'], 1500), $agent['slug']);
        try { return Filings::submit((int)$f['id'], $agent['slug']); }
        catch (\RuntimeException $e) { return ['ok' => false, 'error' => $e->getMessage(), 'note' => 'Will retry automatically.']; }
    }

    /** Server-side tool: the provider runs the search. Reaching this handler means the provider cannot search. */
    public static function webSearchLocal(array $in): array { return ['error' => 'Web search needs the Anthropic connection. Work from the information already on file, or ask the owner.']; }

    public static function pricingOverview(array $in): array
    {
        $rows = Pricing::rows(($in['category'] ?? '') ?: null, ($in['slug'] ?? '') ?: null);
        $money = fn(?int $c) => $c === null ? null : number_format($c / 100, 2, '.', '');
        return ['currency' => 'CAD', 'price_points' => array_map(fn($r) => ['slug' => $r['slug'], 'service' => $r['name'], 'variant' => $r['variant'], 'variant_name' => $r['vlabel'], 'our_price' => $money($r['price']),
            'billing' => $r['billing'], 'gov_fee' => $money($r['gov']), 'third_party' => $money($r['third']), 'fee_confirmed_by_owner' => $r['confirmed'], 'markup' => $money($r['markup']),
            'competitors' => array_map(fn($c) => ['name' => $c['competitor'], 'price' => $money((int)$c['price_cents']), 'includes_gov_fee' => $c['includes_gov_fee'] === null ? null : (bool)$c['includes_gov_fee'], 'url' => $c['url']], $r['comp']),
            'open_proposal' => $r['proposals'] ? $money((int)$r['proposals'][0]['proposed_cents']) : null], array_slice($rows, 0, 80))];
    }

    public static function saveCostFinding(array $in, array $ctx, array $agent): array
    {
        Pricing::saveCost((string)$in['slug'], Pricing::normVariant((string)$in['slug'], (string)($in['variant'] ?? '')), $in, $agent['slug'], false);
        return ['saved' => true, 'status' => 'unconfirmed — the owner will check it'];
    }

    public static function saveCompetitorPrice(array $in, array $ctx, array $agent): array
    {
        return ['saved' => true, 'id' => Pricing::addCompetitor((string)$in['slug'], Pricing::normVariant((string)$in['slug'], (string)($in['variant'] ?? '')), $in, $agent['slug'])];
    }

    public static function proposePrice(array $in, array $ctx, array $agent): array
    {
        $c = (int)round((float)str_replace([',', '$'], '', (string)$in['price']) * 100);
        $id = Pricing::propose((string)$in['slug'], Pricing::normVariant((string)$in['slug'], (string)($in['variant'] ?? '')), $c, (string)($in['strategy'] ?? 'match'), (string)$in['rationale'], $agent['slug']);
        return ['proposal_id' => $id, 'status' => 'waiting for the owner'];
    }

    public static function publishSocialPost(array $in, array $ctx, array $agent): array
    {
        $id = Social::publish((string)$in['network'], (string)$in['text'], (string)($in['link'] ?? ''), (string)($in['image_url'] ?? ''), $agent['slug']);
        return ['published' => true, 'post_id' => $id];
    }

    public static function ourFilingForms(array $in): array
    {
        $out = [];
        foreach (Filings::TYPES as $slug => $type) {
            if (!empty($in['slug']) && $in['slug'] !== $slug) continue;
            $svc = Catalog::find($slug); if (!$svc) continue;
            $form = FormSchema::formFor($slug);
            $fields = [];
            foreach ($form['fields'] ?? [] as $path => [$label, $step, $opts]) $fields[] = ['step' => $form['steps'][$step] ?? null, 'field' => $path, 'label' => $label] + ($opts ? ['options' => array_values(array_slice($opts, 0, 20))] : []);
            $out[] = ['slug' => $slug, 'service' => $svc['name'], 'filing_type' => Filings::TYPE_LABELS[$type] ?? $type,
                'jurisdictions' => array_keys(is_array($svc['province_prices']) ? $svc['province_prices'] : []) ?: ['as chosen on the form'],
                'registries' => array_map(fn($j) => Filings::JURISDICTIONS[$j][0] ?? $j, array_keys(is_array($svc['province_prices']) ? $svc['province_prices'] : [])),
                'steps' => array_values($form['steps'] ?? []), 'fields' => $fields];
        }
        return ['forms' => $out, 'open_findings' => Db::all("SELECT service_slug, jurisdiction, field, issue FROM form_findings WHERE status = 'open'")];
    }

    public static function saveFormFinding(array $in, array $ctx, array $agent): array
    {
        $slug = (string)$in['slug'];
        if (!isset(Filings::TYPES[$slug])) return ['error' => 'Not a registry service: ' . $slug];
        $url = trim((string)$in['source_url']);
        if (!preg_match('#^https://[^\s<>"]+$#', $url)) return ['error' => 'Give the official page address (https://…).'];
        $jur = strtoupper(mb_substr(trim((string)($in['jurisdiction'] ?? '')), 0, 10));
        $field = mb_substr(trim((string)($in['field'] ?? '')), 0, 190) ?: null;
        if (Db::val("SELECT 1 FROM form_findings WHERE service_slug = ? AND jurisdiction = ? AND COALESCE(field,'') = ? AND status = 'open'", [$slug, $jur, (string)$field])) return ['saved' => false, 'note' => 'Already recorded and waiting for the owner.'];
        $id = Db::insert('form_findings', ['service_slug' => $slug, 'jurisdiction' => $jur, 'field' => $field,
            'issue' => in_array($in['issue'] ?? '', ['missing_field', 'outdated', 'wrong_format', 'fee_change', 'document_needed', 'other'], true) ? $in['issue'] : 'other',
            'detail' => mb_substr((string)$in['detail'], 0, 4000), 'suggestion' => mb_substr((string)$in['suggestion'], 0, 4000), 'source_url' => mb_substr($url, 0, 500),
            'severity' => in_array($in['severity'] ?? '', ['high', 'medium', 'low'], true) ? $in['severity'] : 'medium', 'status' => 'open', 'found_by' => $agent['slug'], 'created_at' => now()]);
        return ['saved' => true, 'id' => $id];
    }

    public static function securityEvents(): array
    {
        return ['events' => Db::all('SELECT kind, detail, ip, created_at FROM security_events ORDER BY id DESC LIMIT 30')];
    }
}

/** Domain availability via public RDAP (no credentials) or the configured registrar. Never fakes availability. */
final class Domains
{
    private const RDAP = ['ca' => 'https://rdap.ca.fury.ca/rdap/domain/', 'com' => 'https://rdap.verisign.com/com/v1/domain/', 'net' => 'https://rdap.verisign.com/net/v1/domain/',
        'org' => 'https://rdap.publicinterestregistry.org/rdap/domain/'];

    public static function check(string $domain): array
    {
        $tld = substr(strrchr($domain, '.'), 1);
        $svc = Catalog::find('domain_register');
        $price = isset($svc['options']['tlds'][$tld]) ? money($svc['options']['tlds'][$tld]) . '/yr' : null;
        if (isset(self::RDAP[$tld])) {
            try {
                $r = Integrations::http('GET', self::RDAP[$tld] . rawurlencode($domain), ['Accept: application/rdap+json'], null, 8);
                if ($r['status'] === 404) return ['domain' => $domain, 'status' => 'available', 'price' => $price, 'source' => 'RDAP registry lookup'];
                if ($r['status'] === 200) return ['domain' => $domain, 'status' => 'registered', 'price' => $price, 'source' => 'RDAP registry lookup', 'note' => 'Already registered — suggest alternatives or a transfer if they own it.'];
            } catch (\Throwable $e) { Log::warn('rdap.failed', ['domain' => $domain, 'error' => $e->getMessage()]); }
        }
        return ['domain' => $domain, 'status' => 'unknown', 'price' => $price, 'note' => 'Could not verify automatically; we will confirm availability before purchase.'];
    }
}
