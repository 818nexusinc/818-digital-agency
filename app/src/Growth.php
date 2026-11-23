<?php
declare(strict_types=1);
namespace App;

/**
 * Growth tools for the owner:
 *  - ChatLeads:  turns contact details shared in the website chat into leads (works with or without an AI provider).
 *  - FormDrafts: keeps unfinished order forms so the team can follow up.
 *  - BlogEngine: writes a weekly SEO article (AI when connected, a knowledge-base composer otherwise) with social posts and internal links.
 *  - Demo:       loads realistic sample data and removes every sample row in one step.
 *  - Simulator:  runs every AI agent against a scripted model inside a rolled-back transaction to prove each tool works.
 */

final class ChatLeads
{
    /** Pull an email, phone and name out of a visitor message and file a lead once per conversation. */
    public static function fromMessage(array $conv, string $message): ?array
    {
        if (!preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $message, $em)) return null;
        $email = strtolower($em[0]);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return null;
        $phone = preg_match('/(\+?1[\s.-]?)?\(?\d{3}\)?[\s.-]?\d{3}[\s.-]?\d{4}/', $message, $ph) ? trim($ph[0]) : null;
        $name = preg_match("/(?:my name is|i am|i'm|this is|c'est|je m'appelle)\s+([A-Z][\p{L}'-]+(?:\s+[A-Z][\p{L}'-]+)?)/iu", $message, $nm) ? trim($nm[1]) : '';
        if (Db::val("SELECT 1 FROM leads WHERE conversation_id = ?", [$conv['id']])) {
            if ($phone) Db::run('UPDATE customers SET phone = COALESCE(phone, ?) WHERE email = ?', [$phone, $email]);
            return null;
        }
        $transcript = Db::all('SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY id LIMIT 12', [$conv['id']]);
        $summary = mb_substr(implode(' / ', array_map(fn($m) => ($m['role'] === 'user' ? 'Visitor: ' : 'Assistant: ') . str_limit(preg_replace('/\s+/', ' ', $m['content']), 160), $transcript)), 0, 1500);
        $c = Crm::upsertCustomer(array_filter(['email' => $email, 'name' => $name, 'phone' => $phone, 'lead_source' => 'ai_chat']), 'ai_chat');
        $lead = Crm::createLead($c, ['summary' => $summary, 'intent' => 'chat', 'conversation_id' => (int)$conv['id'], 'services' => []], 'ai_chat');
        Db::update('conversations', ['customer_id' => $c['id']], 'id = ?', [$conv['id']]);
        Audit::log('lead.from_chat', 'lead', (string)$lead['id'], ['conversation' => $conv['public_id']]);
        Mailer::notifyOwner('New lead from the website chat', ($name ?: $email) . " left their details in the chat.\n\n" . str_limit($summary, 600), '/admin/leads');
        return $lead;
    }
}

final class FormDrafts
{
    public const FORMS = ['sole_prop', 'partnership', 'corp_named', 'corp_numbered', 'cooperative', 'biz_name', 'nuans', 'bm_annual', 'bm_changes', 'bm_documents',
        'domain_register', 'email_starter', 'email_business', 'email_team', 'website_launch', 'website_grow', 'website_scale', 'maintenance_care', 'maintenance_protect',
        'maintenance_grow', 'web_app', 'bundle'];

    /** Save or update a draft as the visitor moves between steps. Contact fields are lifted out for follow-up. */
    public static function save(array $in): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', (string)($in['token'] ?? ''));
        if (strlen($token) !== 32) $token = bin2hex(random_bytes(16));
        $form = preg_replace('/[^a-z0-9_]/', '', (string)($in['form'] ?? ''));
        if (!in_array($form, self::FORMS, true) && !Catalog::find($form)) $form = 'other';
        if (!RateLimit::hit('draft:' . client_ip(), 120, 3600)) return ['ok' => true, 'token' => $token];
        $data = Wizard::clean(is_array($in['data'] ?? null) ? $in['data'] : []);
        if (strlen(jenc($data)) > 24000) $data = ['note' => 'Form too large to keep.'];
        [$email, $name, $phone] = self::contact($data);
        $row = ['form' => $form, 'path' => mb_substr(preg_replace('/[^\/a-z0-9_-]/i', '', (string)($in['path'] ?? '')), 0, 190), 'step' => max(0, (int)($in['step'] ?? 0)),
            'steps' => max(0, (int)($in['steps'] ?? 0)), 'email' => $email, 'name' => $name, 'phone' => $phone, 'data' => jenc($data), 'updated_at' => now()];
        $ex = Db::one('SELECT id, status FROM form_drafts WHERE token = ?', [$token]);
        if ($ex) {
            if ($ex['status'] === 'open') Db::update('form_drafts', array_filter($row, fn($v) => $v !== null), 'id = ?', [$ex['id']]);
        } else {
            Db::insert('form_drafts', $row + ['token' => $token, 'status' => 'open', 'ip' => client_ip(), 'created_at' => now()]);
        }
        return ['ok' => true, 'token' => $token];
    }

    /** The order went through: the draft is no longer "unfinished". */
    public static function complete(?string $token): void
    {
        $token = preg_replace('/[^a-f0-9]/', '', (string)$token);
        if (strlen($token) === 32) Db::run("UPDATE form_drafts SET status = 'completed', updated_at = ? WHERE token = ? AND status = 'open'", [now(), $token]);
    }

    /** Turn a draft into a CRM lead for follow-up. */
    public static function toLead(int $id, string $by): ?array
    {
        $d = Db::one('SELECT * FROM form_drafts WHERE id = ?', [$id]);
        if (!$d || !$d['email']) throw new UserError('This draft has no email address to follow up with.');
        $c = Crm::upsertCustomer(array_filter(['email' => $d['email'], 'name' => $d['name'], 'phone' => $d['phone']]), 'form_draft');
        $lead = Crm::createLead($c, ['summary' => 'Started the ' . str_replace('_', ' ', $d['form']) . ' form on ' . $d['path'] . ' and stopped at step ' . ($d['step'] + 1) . ' of ' . max(1, $d['steps']) . '.',
            'services' => [$d['form']], 'intent' => 'unfinished_form'], 'form_draft');
        Db::update('form_drafts', ['status' => 'followed_up', 'lead_id' => $lead['id'], 'updated_at' => now()], 'id = ?', [$id]);
        Audit::log('draft.to_lead', 'form_draft', (string)$id, ['by' => $by, 'lead' => $lead['id']]);
        return $lead;
    }

    /** Find an email, name and phone anywhere in the form data (owners, applicant, contact, directors…). */
    public static function contact(array $data): array
    {
        $email = $name = $phone = null;
        $walk = function ($v, $k = '') use (&$walk, &$email, &$name, &$phone) {
            if (is_array($v)) {
                if (!$name && (isset($v['first']) || isset($v['last']))) $name = trim(($v['first'] ?? '') . ' ' . ($v['last'] ?? '')) ?: null;
                foreach ($v as $kk => $vv) $walk($vv, (string)$kk);
                return;
            }
            if (!is_string($v)) return;
            if (!$email && filter_var($v, FILTER_VALIDATE_EMAIL)) $email = strtolower($v);
            if (!$phone && preg_match('/phone|tel/i', $k) && preg_match('/\d{7,}/', preg_replace('/\D/', '', $v))) $phone = mb_substr($v, 0, 40);
            if (!$name && preg_match('/^(name|full_name|contact_name)$/i', $k)) $name = mb_substr($v, 0, 190);
        };
        $walk($data);
        return [$email, $name ? mb_substr($name, 0, 190) : null, $phone];
    }
}

final class BlogEngine
{
    /** Topic → [title, focus keyword, primary service page, related page]. */
    public const TOPICS = [
        ['How to incorporate a business in New Brunswick', 'incorporate in New Brunswick', '/register/corporation', '/register/nuans'],
        ['Sole proprietorship or corporation: which is right for your Canadian business?', 'sole proprietorship vs corporation', '/register', '/register/sole-proprietorship'],
        ['What is a NUANS report and when do you need one?', 'NUANS report', '/register/nuans', '/register/corporation'],
        ['Federal or provincial incorporation in Canada: how to choose', 'federal vs provincial incorporation', '/register/corporation', '/register'],
        ['Annual returns in Canada: deadlines, penalties and how to stay compliant', 'annual return Canada', '/manage-business/annual-returns', '/manage-business'],
        ['.ca or .com: choosing a domain for your Canadian business', '.ca domain', '/domain', '/email'],
        ['Why your business needs email on its own domain', 'business email domain', '/email', '/domain'],
        ['What every small business website needs in 2027', 'small business website', '/website', '/website/maintenance'],
        ['A practical website maintenance checklist for small businesses', 'website maintenance', '/website/maintenance', '/website'],
        ['How to register a business name in your province', 'register a business name', '/register/sole-proprietorship', '/manage-business/name-services'],
        ['Partnership agreements: what to agree on before you register', 'partnership agreement', '/register/partnership', '/register'],
        ['Starting a co-operative in Canada', 'start a co-operative', '/register/cooperative', '/register'],
        ['Changing directors or your registered office: what to file', 'change directors corporation', '/manage-business/corporate-changes', '/manage-business'],
        ['Certificate of status: what it is and when banks ask for it', 'certificate of status', '/manage-business/corporate-documents', '/manage-business'],
        ['Funding programs for small businesses in Atlantic Canada', 'small business funding Atlantic Canada', '/opportunities', '/register'],
        ['Custom web app or off-the-shelf software? How to decide', 'custom web application', '/web-application', '/web-services'],
        ['Numbered or named corporation: the real differences', 'numbered company', '/register/corporation', '/register/nuans'],
        ['Launching a business in one week: registration, domain, email and website', 'launch a business in Canada', '/bundle', '/register'],
    ];

    /** Weekly run from cron: one article every 7 days. */
    public static function weekly(): ?array
    {
        if (Settings::get('blog.enabled', '1') !== '1') return null;
        $last = Settings::get('blog.last_generated');
        if ($last && strtotime($last . ' UTC') > time() - 7 * 86400) return null;
        return self::generate(null, 'cron');
    }

    public static function nextTopic(): array
    {
        $used = jdec(Settings::get('blog.topics_used') ?: '[]');
        foreach (self::TOPICS as $i => $t) if (!in_array($i, $used, true) && !Db::val("SELECT 1 FROM content_items WHERE kind = 'blog' AND keyword = ?", [$t[1]])) return [$i, $t];
        Settings::set('blog.topics_used', '[]');
        $i = (int)(count($used) % count(self::TOPICS));
        return [$i, self::TOPICS[$i]];
    }

    /** Write one article. $topicIndex null = next unused topic. Publishes now or queues for review, per Settings. */
    public static function generate(?int $topicIndex, string $by): array
    {
        [$idx, $t] = $topicIndex !== null && isset(self::TOPICS[$topicIndex]) ? [$topicIndex, self::TOPICS[$topicIndex]] : self::nextTopic();
        [$title, $kw, $primary, $related] = $t;
        $post = self::viaAi($title, $kw, $primary, $related) ?? self::compose($title, $kw, $primary, $related);
        $slug = self::slug($post['title']);
        if (Db::val('SELECT 1 FROM content_items WHERE slug = ?', [$slug])) $slug .= '-' . gmdate('Ymd');
        $publish = Settings::get('blog.autopublish', '1') === '1';
        $meta = ['generator' => $post['generator'], 'topic' => $idx, 'social' => $post['social'], 'links' => [$primary, $related], 'faqs' => $post['faqs'] ?? []];
        $id = Db::insert('content_items', ['kind' => 'blog', 'title' => mb_substr($post['title'], 0, 190), 'slug' => $slug, 'body' => $post['body'], 'excerpt' => mb_substr($post['excerpt'], 0, 400),
            'keyword' => $kw, 'audience' => 'Canadian small business owners', 'meta' => jenc($meta), 'status' => $publish ? 'published' : 'review',
            'seo_score' => Seo::score($post['title'], $post['body'], $kw), 'readability' => Seo::readability($post['body']), 'publish_at' => $publish ? now() : null,
            'version' => 1, 'author' => 'blog_engine', 'created_at' => now(), 'updated_at' => now()]);
        $used = jdec(Settings::get('blog.topics_used') ?: '[]'); $used[] = $idx;
        Settings::set('blog.topics_used', jenc(array_values(array_unique($used))));
        Settings::set('blog.last_generated', now());
        Audit::log('blog.generated', 'content', (string)$id, ['by' => $by, 'generator' => $post['generator'], 'published' => $publish]);
        Mailer::notifyOwner($publish ? 'New article published' : 'New article ready for review', $post['title'], '/admin/blog');
        if ($publish) { try { Seo::indexNow(['/blog/' . $slug, '/blog', '/sitemap.xml', '/llms.txt']); } catch (\Throwable $e) { Log::error('blog.indexnow', ['error' => $e->getMessage()]); } }
        return Db::one('SELECT * FROM content_items WHERE id = ?', [$id]);
    }

    /** Ask the content model for a structured article. Returns null when no provider is connected or the answer is unusable. */
    private static function viaAi(string $title, string $kw, string $primary, string $related): ?array
    {
        $agent = Agents::get('content');
        if (!$agent || !Ai::available(Ai::providerFor((string)$agent['model']))) return null;
        $facts = self::facts($primary) . "\n" . self::facts($related);
        $sys = Agents::BASE_POLICY . "\nYou write SEO articles for 818 Digital Agency's blog. Audience: Canadian small business owners. Plain, helpful, specific. "
            . 'Never invent statistics, prices, laws or testimonials; use only the facts given or widely known general information, and say when rules vary by province.';
        $prompt = "Write an article titled about: {$title}\nFocus keyword: {$kw}\nFacts you may use:\n{$facts}\n\n"
            . "Return ONLY JSON: {\"title\":\"55-65 chars with the keyword\",\"excerpt\":\"150-160 char meta description\",\"body\":\"900-1300 words; paragraphs separated by blank lines; ## for section headings; - for bullet lists; "
            . "include the keyword in the first paragraph and at least one heading; include these internal links in markdown: [link text]({$primary}) and [link text]({$related}); end with a short call to action\","
            . "\"faqs\":[{\"q\":\"\",\"a\":\"\"}],\"social\":{\"linkedin\":\"under 600 chars\",\"x\":\"under 260 chars\",\"facebook\":\"under 400 chars\"}}";
        try {
            $r = Ai::complete($sys, [['role' => 'user', 'content' => $prompt]], [], (string)$agent['model'], $agent['fallback_model'], 4000, 0.6, 90);
        } catch (\Throwable $e) { Log::error('blog.ai_failed', ['error' => $e->getMessage()]); return null; }
        if (!empty($r['offline'])) return null;
        $txt = trim((string)$r['text']); $txt = preg_replace('/^```(?:json)?|```$/m', '', $txt);
        $j = json_decode(trim($txt), true);
        if (!is_array($j) || empty($j['title']) || empty($j['body']) || str_word_count((string)$j['body']) < 400) return null;
        return ['title' => (string)$j['title'], 'excerpt' => (string)($j['excerpt'] ?? ''), 'body' => self::tidy((string)$j['body']), 'faqs' => array_slice((array)($j['faqs'] ?? []), 0, 6),
            'social' => array_map(fn($s) => mb_substr((string)$s, 0, 700), (array)($j['social'] ?? [])) + self::social($title, $primary), 'generator' => 'ai'];
    }

    /** Offline composer: builds the article from the catalog and knowledge base, so the weekly schedule never stalls. */
    private static function compose(string $title, string $kw, string $primary, string $related): array
    {
        $svcP = self::serviceFor($primary); $svcR = self::serviceFor($related);
        $kb = Db::all('SELECT title, body FROM kb_articles WHERE status = ? ORDER BY id', ['published']);
        $pick = array_values(array_filter($kb, fn($a) => self::overlap($a['title'] . ' ' . $a['body'], $kw . ' ' . $title) >= 2));
        $body = "Thinking about " . lcfirst(rtrim($title, '?.')) . "? This guide walks through what matters for {$kw}, in plain language, so you can make the call with confidence and get back to running your business.\n\n";
        $body .= "## The short answer\n\n" . ($svcP ? $svcP['summary'] . ' ' : '') . "Rules and fees can differ by province, so check the details for where you operate before you file.\n\n";
        foreach (array_slice($pick, 0, 3) as $a) $body .= '## ' . $a['title'] . "\n\n" . trim(preg_replace('/\n{3,}/', "\n\n", strip_tags($a['body']))) . "\n\n";
        $body .= "## Step by step\n\n- Decide what you need and gather the details\n- Check names, addresses and people against your registry's rules\n- File, then keep the confirmation and documents somewhere safe\n- Put reminders in place for anything that renews or is due each year\n\n";
        $body .= "## What to have ready\n\n- Your legal name, address and contact details\n- The exact business or corporate name you want, plus a backup\n- The names and addresses of any owners, partners or directors\n- A short description of what the business does\n\n";
        $body .= "## Mistakes we see most often\n\nThe most common problems are small ones: a name that is too close to an existing business, an address the registry will not accept, or a renewal date nobody wrote down. Each one can delay {$kw} by days or weeks. Checking details before you file, and keeping a simple calendar of what is due, avoids almost all of them.\n\n";
        $body .= "## How 818 Digital Agency helps\n\nWe prepare the paperwork from a short online form, check it before it goes to the registry, and keep you updated by email at each step. You can also add a domain, business email and a website when you are ready, so everything lives in one place. A person on our Moncton team reviews anything sensitive.\n\n";
        if ($svcP && ($p = Catalog::fromPrice($svcP)) !== null) $body .= "## What it costs with 818\n\n{$svcP['name']} starts at " . money($p) . ($svcP['category'] === 'registration' ? ', with the government fee included' : '') . ". [See " . mb_strtolower($svcP['name']) . "]({$primary}).\n\n";
        if ($svcR) $body .= "## Related\n\n" . $svcR['summary'] . " [Learn more]({$related}).\n\n";
        $body .= "## Next step\n\nReady to move forward? [Start here]({$primary}) or ask our assistant a question — a person on our Moncton team reviews anything sensitive.";
        return ['title' => $title, 'excerpt' => str_limit('A plain-language guide to ' . $kw . ' for Canadian small business owners: what it is, the steps, costs and what to watch for.', 160),
            'body' => $body, 'faqs' => [], 'social' => self::social($title, $primary), 'generator' => 'composer'];
    }

    public static function social(string $title, string $path): array
    {
        $u = url($path);
        return ['linkedin' => "New guide: {$title}\n\nA plain-language walkthrough for Canadian small business owners, with the steps, costs and what to watch for.\n\n{$u}\n\n#SmallBusiness #Canada #Entrepreneurs",
            'x' => str_limit($title, 180) . " {$u} #SmallBusiness #Canada", 'facebook' => "{$title} — our latest guide for Canadian entrepreneurs. {$u}"];
    }

    private static function facts(string $path): string
    {
        $s = self::serviceFor($path);
        if (!$s) return '';
        $p = Catalog::fromPrice($s);
        return "- {$s['name']}: {$s['summary']}" . ($p !== null ? ' From ' . money($p) . '.' : '') . (!empty($s['timeline']) ? " Typical time: {$s['timeline']}." : '') . " Page: {$path}";
    }

    private static function serviceFor(string $path): ?array
    {
        $map = ['/register/corporation' => 'corp_numbered', '/register/nuans' => 'nuans', '/register/sole-proprietorship' => 'sole_prop', '/register/partnership' => 'partnership', '/register/cooperative' => 'cooperative',
            '/manage-business/annual-returns' => 'bm_annual', '/manage-business/corporate-changes' => 'bm_changes', '/manage-business/corporate-documents' => 'bm_documents', '/manage-business/name-services' => 'biz_name',
            '/domain' => 'domain_register', '/email' => 'email_starter', '/website' => 'website_launch', '/website/maintenance' => 'maintenance_care', '/web-application' => 'web_app'];
        return isset($map[$path]) ? Catalog::find($map[$path]) : null;
    }

    private static function overlap(string $a, string $b): int
    {
        $w = fn($s) => array_unique(array_filter(preg_split('/[^a-z0-9]+/', mb_strtolower($s)), fn($x) => strlen($x) > 3));
        return count(array_intersect($w($a), $w($b)));
    }

    private static function tidy(string $b): string
    {
        $b = strip_tags($b);
        return trim(preg_replace("/\r\n?/", "\n", $b));
    }

    public static function slug(string $t): string
    {
        $s = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t), '-'));
        return mb_substr($s ?: 'article', 0, 120);
    }

    /** RSS for social schedulers (Buffer, Zapier, LinkedIn auto-post). */
    public static function rss(): string
    {
        $items = Db::all("SELECT title, slug, excerpt, publish_at, updated_at FROM content_items WHERE kind = 'blog' AND status = 'published' ORDER BY COALESCE(publish_at, updated_at) DESC LIMIT 30");
        $x = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<rss version=\"2.0\"><channel><title>" . $x(Settings::get('company.name')) . ' blog</title><link>' . $x(url('/blog')) . '</link><description>Guides for Canadian entrepreneurs</description><language>en-ca</language>';
        foreach ($items as $i) $out .= '<item><title>' . $x($i['title']) . '</title><link>' . $x(url('/blog/' . $i['slug'])) . '</link><guid>' . $x(url('/blog/' . $i['slug'])) . '</guid><description>' . $x($i['excerpt']) . '</description><pubDate>' . gmdate('r', strtotime(($i['publish_at'] ?? $i['updated_at']) . ' UTC')) . '</pubDate></item>';
        return $out . '</channel></rss>';
    }
}

final class Demo
{
    /** Register a row as sample data so it can be removed later. */
    private static function mark(string $tbl, int $id): int { Db::insert('demo_records', ['tbl' => $tbl, 'rid' => $id, 'created_at' => now()]); return $id; }
    private static function ins(string $tbl, array $row): int { return self::mark($tbl, Db::insert($tbl, $row)); }

    public static function count(): int { return (int)Db::val('SELECT COUNT(*) FROM demo_records'); }

    /** Load a realistic month of activity. Every inserted row is registered in demo_records. */
    public static function seed(): array
    {
        if (self::count() > 0) throw new UserError('Demo data is already loaded. Delete it first to load a fresh set.');
        $people = [['Amara Okafor', 'Okafor Catering', 'Moncton'], ['Luc Thibodeau', 'Thibodeau Renovations', 'Dieppe'], ['Priya Nair', 'Nair Bookkeeping', 'Halifax'],
            ['Jordan MacLeod', 'MacLeod Outfitters', 'Charlottetown'], ['Sofia Ramirez', 'Ramirez Design Studio', 'Fredericton'], ['Ethan Clarke', 'Clarke Tech Consulting', 'Saint John'],
            ['Mei Chen', 'Harbour Tea Co.', 'Riverview'], ['Noah Gallant', 'Gallant Landscaping', 'Shediac']];
        $n = ['customers' => 0, 'leads' => 0, 'orders' => 0, 'tickets' => 0, 'drafts' => 0, 'posts' => 0, 'conversations' => 0];
        $svc = ['corp_numbered', 'sole_prop', 'website_launch', 'email_starter', 'domain_register', 'bm_annual', 'nuans', 'partnership'];
        foreach ($people as $i => [$name, $biz, $city]) {
            $email = 'demo.' . strtolower(explode(' ', $name)[0]) . '@example.com';
            $at = gmdate('Y-m-d H:i:s', time() - ($i * 3 + 1) * 86400);
            $cid = self::ins('customers', ['name' => $name, 'email' => $email, 'phone' => '506-555-01' . (10 + $i), 'business_name' => $biz, 'province' => 'NB', 'location' => $city . ', NB',
                'status' => $i < 4 ? 'customer' : 'lead', 'lead_source' => ['website', 'ai_chat', 'form_draft', 'referral'][$i % 4], 'created_at' => $at, 'updated_at' => $at]);
            $n['customers']++;
            self::ins('leads', ['customer_id' => $cid, 'source' => ['website', 'ai_chat', 'form_draft', 'referral'][$i % 4], 'intent' => 'demo', 'summary' => "Demo: {$biz} wants help with " . str_replace('_', ' ', $svc[$i]) . '.',
                'services_interest' => jenc([$svc[$i]]), 'score' => 30 + $i * 8, 'status' => $i < 4 ? 'converted' : ['lead', 'qualified', 'proposal_sent', 'lead'][$i % 4],
                'estimated_value_cents' => [44900, 14900, 14900, 999, 999, 14900, 4250, 19900][$i], 'next_follow_up_at' => gmdate('Y-m-d H:i:s', time() + ($i - 4) * 86400), 'created_at' => $at, 'updated_at' => $at]);
            $n['leads']++;
            if ($i < 4) {
                $s = Catalog::find($svc[$i]); $cents = $s ? (int)Catalog::fromPrice($s) : 9900;
                $oid = self::ins('orders', ['number' => 'DEMO-' . strtoupper(rid(5)), 'customer_id' => $cid, 'status' => 'paid', 'currency' => 'CAD', 'subtotal_cents' => $cents, 'discount_cents' => 0,
                    'total_cents' => $cents, 'created_at' => $at, 'updated_at' => $at, 'paid_at' => $at]);
                self::ins('order_items', ['order_id' => $oid, 'service_slug' => $svc[$i], 'name' => $s['name'] ?? $svc[$i], 'qty' => 1, 'unit_cents' => $cents, 'billing' => 'one_time', 'options' => '{}']);
                self::ins('payments', ['order_id' => $oid, 'provider' => 'demo', 'provider_ref' => 'demo_' . rid(8), 'amount_cents' => $cents, 'status' => 'succeeded', 'created_at' => $at]);
                $n['orders']++;
            }
            if ($i % 3 === 1) {
                self::ins('tickets', ['number' => 'DEMO-T' . (100 + $i), 'customer_id' => $cid, 'subject' => 'Demo: question about my ' . str_replace('_', ' ', $svc[$i]), 'status' => 'open', 'priority' => 'normal',
                    'created_at' => $at, 'updated_at' => $at]);
                $n['tickets']++;
            }
            if ($i >= 5) {
                self::ins('form_drafts', ['token' => bin2hex(random_bytes(16)), 'form' => $svc[$i], 'path' => '/register/corporation', 'step' => 2, 'steps' => 8, 'email' => $email, 'name' => $name,
                    'phone' => '506-555-01' . (10 + $i), 'data' => jenc(['entity' => ['proposed_name' => $biz]]), 'status' => 'open', 'ip' => '127.0.0.1', 'created_at' => $at, 'updated_at' => $at]);
                $n['drafts']++;
            }
            if ($i === 2) {
                $cv = self::ins('conversations', ['public_id' => rid(16), 'customer_id' => $cid, 'channel' => 'web', 'agent_slug' => 'sales', 'page_context' => '/register', 'status' => 'open', 'created_at' => $at, 'updated_at' => $at]);
                self::ins('messages', ['conversation_id' => $cv, 'role' => 'user', 'content' => "Hi, I'm {$name}. Should I incorporate or stay a sole proprietor? {$email}", 'meta' => '{}', 'created_at' => $at]);
                self::ins('messages', ['conversation_id' => $cv, 'role' => 'assistant', 'content' => 'Thanks! A sole proprietorship is simplest; incorporating separates your personal assets. I have passed your details to the team.', 'meta' => '{}', 'created_at' => $at]);
                $n['conversations']++;
            }
        }
        foreach ([0, 2, 5] as $k => $ti) {
            [$title, $kw, $p1, $p2] = BlogEngine::TOPICS[$ti];
            $at = gmdate('Y-m-d H:i:s', time() - (21 - $k * 7) * 86400);
            self::ins('content_items', ['kind' => 'blog', 'title' => "[Demo] {$title}", 'slug' => 'demo-' . BlogEngine::slug($title), 'body' => "Sample article for testing the blog layout.\n\n## About {$kw}\n\nThis is demo content. Delete demo data before launch.\n\n[Learn more]({$p1})",
                'excerpt' => 'Demo article for testing. Remove before going live.', 'keyword' => $kw, 'audience' => 'Demo', 'meta' => jenc(['generator' => 'demo', 'social' => BlogEngine::social($title, $p1)]),
                'status' => 'published', 'seo_score' => 50, 'readability' => 60, 'publish_at' => $at, 'version' => 1, 'author' => 'demo', 'created_at' => $at, 'updated_at' => $at]);
            $n['posts']++;
        }
        Audit::log('demo.seeded', 'system', null, $n);
        return $n;
    }

    /** Delete every registered demo row (children first) and the registry itself. */
    public static function purge(): int
    {
        $order = ['messages', 'conversations', 'payments', 'order_items', 'orders', 'tickets', 'form_drafts', 'leads', 'content_items', 'customers'];
        $total = 0;
        $tbls = array_unique(array_merge($order, array_column(Db::all('SELECT DISTINCT tbl FROM demo_records'), 'tbl')));
        foreach ($tbls as $t) {
            if (!preg_match('/^[a-z_]+$/', $t)) continue;
            $ids = array_map('intval', array_column(Db::all('SELECT rid FROM demo_records WHERE tbl = ?', [$t]), 'rid'));
            foreach (array_chunk($ids, 200) as $chunk) {
                $in = implode(',', $chunk);
                if ($t === 'customers') { foreach (['leads', 'orders', 'tickets', 'conversations'] as $dep) Db::run("DELETE FROM {$dep} WHERE customer_id IN ({$in})"); }
                $total += Db::run("DELETE FROM {$t} WHERE id IN ({$in})")->rowCount();
            }
        }
        Db::run('DELETE FROM demo_records');
        Audit::log('demo.purged', 'system', null, ['rows' => $total]);
        return $total;
    }
}

final class Simulator
{
    /**
     * Run one agent (or all) against a scripted model. Each tool the agent may use is called once with sample inputs
     * built from its schema and from sandbox records. Everything happens in a transaction that is rolled back, and outbound email is suppressed.
     * @return array<int, array{agent:string,name:string,ok:bool,tools:array,error:?string,ms:int}>
     */
    public static function run(?string $only = null): array
    {
        $out = [];
        foreach (Agents::DEFS as $slug => $def) {
            if ($only && $only !== $slug) continue;
            $out[] = self::one($slug);
        }
        return $out;
    }

    private static function one(string $slug): array
    {
        $t0 = microtime(true);
        $pdo = Db::pdo();
        $pdo->beginTransaction();
        Mailer::$dryRun = true;
        $toolRes = []; $err = null; $text = '';
        try {
            $agent = Agents::get($slug);
            if (!$agent) throw new \RuntimeException('Agent not found in the registry.');
            // Run with full autonomy inside the sandbox so every tool path executes (approval paths are tested separately below).
            Db::update('agents', ['status' => 'enabled', 'autonomy_level' => 4], 'slug = ?', [$slug]);
            $fx = self::fixtures();
            $defs = Tools::defs();
            $calls = [];
            foreach (Agents::get($slug)['tools'] as $tn) if (isset($defs[$tn])) $calls[] = ['id' => 'sim_' . $tn, 'name' => $tn, 'input' => self::sample($tn, $defs[$tn]['input_schema'] ?? $defs[$tn]['schema'] ?? [], $fx)];
            $step = 0;
            Ai::$mock = function ($system, $messages, $tools) use (&$step, $calls) {
                $base = ['tokens_in' => 0, 'tokens_out' => 0, 'provider' => 'simulator', 'model' => 'simulator', 'stop' => 'end_turn'];
                if ($step++ === 0 && $calls) return $base + ['text' => '', 'tool_calls' => $calls, 'stop' => 'tool_use', 'assistant_blocks' => array_map(fn($c) => ['type' => 'tool_use'] + $c, $calls)];
                return $base + ['text' => 'Simulation complete.', 'tool_calls' => [], 'assistant_blocks' => [['type' => 'text', 'text' => 'Simulation complete.']]];
            };
            $ctx = ['customer_id' => $fx['customer_id'], 'user_id' => $fx['user_id'], 'conversation_id' => $fx['conversation_id'], 'project_id' => $fx['project_id'], 'task_id' => $fx['task_id'], 'channel' => 'simulator'];
            $r = AgentRunner::run($slug, [['role' => 'user', 'content' => 'TASK: simulation run for ' . $slug]], $ctx);
            $text = $r['text'];
            $runTools = jdec((string)Db::val('SELECT tools_used FROM agent_runs WHERE id = ?', [$r['run_id']]) ?: '[]');
            foreach ($calls as $c) $toolRes[$c['name']] = in_array($c['name'], $runTools, true) ? 'ran' : 'skipped';
            // Check the tool results the runner fed back to the model for hard errors.
            foreach (self::$lastResults as $name => $res) if (isset($res['exception'])) { $toolRes[$name] = 'error: ' . $res['exception']; }
            if ($text !== 'Simulation complete.') $err = 'Agent did not finish the scripted conversation.';
        } catch (\Throwable $e) {
            $err = get_class($e) . ': ' . $e->getMessage();
        } finally {
            Ai::$mock = null; Mailer::$dryRun = false; self::$lastResults = [];
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
        $bad = array_filter($toolRes, fn($v) => str_starts_with((string)$v, 'error'));
        return ['agent' => $slug, 'name' => Agents::DEFS[$slug][0], 'ok' => !$err && !$bad, 'tools' => $toolRes, 'error' => $err ?? ($bad ? 'One or more tools failed.' : null),
            'ms' => (int)((microtime(true) - $t0) * 1000)];
    }

    /** Tool exceptions seen during a simulation (filled by Tools::execute when Ai::$mock is active). */
    public static array $lastResults = [];

    /** Sandbox records so tools that need IDs have something real to act on. */
    private static function fixtures(): array
    {
        $now = now();
        $cust = Crm::upsertCustomer(['email' => 'simulator@example.com', 'name' => 'Simulation Customer'], 'simulator');
        $uid = (int)(Db::val("SELECT id FROM users WHERE role = 'owner' ORDER BY id LIMIT 1") ?: 0) ?: null;
        $conv = Db::insert('conversations', ['public_id' => rid(16), 'customer_id' => $cust['id'], 'channel' => 'web', 'agent_slug' => 'sales', 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $order = Db::insert('orders', ['number' => 'SIM-' . rid(4), 'customer_id' => $cust['id'], 'status' => 'paid', 'currency' => 'CAD', 'subtotal_cents' => 14900, 'discount_cents' => 0, 'total_cents' => 14900,
            'created_at' => $now, 'updated_at' => $now, 'paid_at' => $now]);
        $proj = Db::insert('projects', ['number' => 'SIM-P' . rid(3), 'order_id' => $order, 'customer_id' => $cust['id'], 'service_slug' => 'website_launch', 'title' => 'Simulation project', 'status' => 'active',
            'created_at' => $now, 'updated_at' => $now]);
        $ticket = Db::insert('tickets', ['number' => 'SIM-T' . rid(3), 'customer_id' => $cust['id'], 'subject' => 'Simulation ticket', 'status' => 'open', 'priority' => 'normal', 'created_at' => $now, 'updated_at' => $now]);
        $task = Orchestrator::enqueue('qa', 'simulation', 'Simulation task', [], ['project_id' => $proj]);
        $deliv = Db::insert('deliverables', ['project_id' => $proj, 'title' => 'Simulation draft', 'body' => 'Sample body', 'status' => 'draft', 'produced_by' => 'website_dev', 'created_at' => $now, 'updated_at' => $now]);
        $lead = Crm::createLead($cust, ['summary' => 'Simulation lead', 'services' => []], 'simulator');
        $filing = Db::val('SELECT id FROM filings ORDER BY id DESC LIMIT 1');
        return ['customer_id' => (int)$cust['id'], 'user_id' => $uid, 'conversation_id' => $conv, 'project_id' => $proj, 'ticket_id' => $ticket, 'task_id' => $task,
            'deliverable_id' => $deliv, 'lead_id' => (int)$lead['id'], 'filing_id' => $filing ? (int)$filing : 0, 'milestone_id' => (int)(Db::val('SELECT id FROM project_milestones WHERE project_id = ? LIMIT 1', [$proj]) ?: 0)];
    }

    /** Build a plausible input from a tool's JSON schema. */
    private static function sample(string $tool, array $schema, array $fx): array
    {
        $special = [
            'search_catalog' => ['query' => 'incorporate a company in New Brunswick'], 'get_service' => ['slug' => 'corp_numbered'], 'domain_check' => ['domain' => 'example-simulation.ca'],
            'quote_items' => ['items' => [['slug' => 'sole_prop', 'opts' => ['province' => 'NB']]]], 'build_cart' => ['items' => [['slug' => 'domain_register', 'opts' => ['tld' => 'ca', 'domain' => 'simtest.ca']]]],
            'capture_lead' => ['email' => 'simulator@example.com', 'name' => 'Simulation Customer', 'summary' => 'Simulation lead capture'],
            'create_proposal' => ['title' => 'Simulation proposal', 'items' => [['slug' => 'website_launch', 'opts' => []]], 'email' => 'simulator@example.com'],
            'create_task' => ['agent' => 'qa', 'title' => 'Simulation follow-up', 'instructions' => 'Check the simulation deliverable.'],
            'search_knowledge' => ['query' => 'refund policy'], 'remember' => ['fact' => 'Prefers email updates'],
            'create_ticket' => ['subject' => 'Simulation ticket', 'body' => 'Testing ticket creation.'], 'request_human' => ['reason' => 'Simulation handoff'],
            'filing_get' => ['filing_id' => $fx['filing_id']], 'filing_flag' => ['filing_id' => $fx['filing_id'], 'finding' => 'Simulation review note', 'needs_customer_changes' => false],
            'filing_submit' => ['filing_id' => $fx['filing_id']],
            'save_opportunity' => ['name' => 'Simulation opportunity', 'audience' => 'Test', 'problem' => 'Test', 'sources' => ['simulation'], 'monetization' => 'Test', 'confidence' => 'low'],
            'create_content_draft' => ['title' => 'Simulation article', 'body' => 'Draft body for simulation.', 'keyword' => 'simulation', 'kind' => 'blog'],
            'request_approval' => ['what' => 'Simulation approval', 'why' => 'Testing', 'risks' => 'None', 'cost' => 'None'],
        ];
        $in = [];
        foreach (($schema['properties'] ?? []) as $k => $p) {
            $type = $p['type'] ?? 'string';
            $in[$k] = match (true) {
                isset($fx[$k]) => $fx[$k],
                $type === 'integer' || $type === 'number' => 1,
                $type === 'boolean' => false,
                $type === 'array' => [],
                $type === 'object' => [],
                isset($p['enum']) => $p['enum'][0],
                default => 'Simulation ' . str_replace('_', ' ', $k),
            };
        }
        foreach (($special[$tool] ?? []) as $k => $v) if (array_key_exists($k, $schema['properties'] ?? [])) $in[$k] = $v;
        // Email-like fields get a valid test address.
        foreach ($in as $k => $v) if (is_string($v) && str_contains($k, 'email')) $in[$k] = 'simulator@example.com';
        return $in;
    }
}
