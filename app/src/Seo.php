<?php
declare(strict_types=1);
namespace App;

final class Seo
{
    public static function jsonLd(array $data): string
    {
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';
    }

    public static function organization(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'ProfessionalService', 'name' => Settings::get('company.name'), 'legalName' => Settings::get('company.legal'),
            'url' => url('/'), 'email' => Settings::get('company.email'), 'areaServed' => 'CA', 'priceRange' => '$$',
            'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Moncton', 'addressRegion' => 'NB', 'addressCountry' => 'CA'],
            'parentOrganization' => ['@type' => 'Organization', 'name' => '818 Nexus Inc.'],
            'logo' => url('/assets/img/favicon.svg'), 'description' => Settings::get('seo.default_description'),
            'knowsAbout' => ['Business registration in Canada', 'Incorporation', 'NUANS name search', 'Annual returns', 'Corporate changes', 'Domain names', 'Business email', 'Website design', 'Web applications'],
            'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Services', 'itemListElement' => array_map(fn($s) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $s['name'], 'url' => url('/services/' . $s['slug'])],
                'priceCurrency' => 'CAD', 'price' => Catalog::fromPrice($s) !== null ? number_format(Catalog::fromPrice($s) / 100, 2, '.', '') : null], array_slice(Catalog::all(), 0, 30))],
            'sameAs' => array_values(array_unique(array_filter(array_merge(array_map('trim', explode(',', (string)Settings::get('seo.same_as'))), array_column(Social::profiles(), 'url')))))];
    }

    public static function website(): array
    {
        return ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => Settings::get('company.name'), 'url' => url('/'), 'inLanguage' => ['en-CA', 'fr-CA'],
            'publisher' => ['@type' => 'Organization', 'name' => Settings::get('company.legal')]];
    }

    /** AI crawlers we explicitly welcome (search and answer engines). */
    public const AI_BOTS = ['GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot', 'Applebot-Extended',
        'Bingbot', 'DuckAssistBot', 'MistralAI-User', 'cohere-ai', 'Amazonbot', 'meta-externalagent', 'YouBot'];

    public static function robots(): string
    {
        $deny = "Disallow: /admin\nDisallow: /portal\nDisallow: /cart\nDisallow: /checkout\nDisallow: /api/\nDisallow: /classic/\nDisallow: /proposal/\nDisallow: /order/\nDisallow: /login\nDisallow: /signup\n";
        $t = "# " . Settings::get('company.name') . " — search engines and AI assistants are welcome to read and cite our public pages.\n# Plain-language guide for AI: " . url('/llms.txt') . "\n\n";
        $t .= "User-agent: *\nAllow: /\n" . $deny . "\n";
        foreach (self::AI_BOTS as $b) $t .= "User-agent: {$b}\n";
        $t .= "Allow: /\nAllow: /llms.txt\nAllow: /llms-full.txt\nAllow: /services.json\n" . $deny . "\nSitemap: " . url('/sitemap.xml') . "\n";
        return $t;
    }

    /** IndexNow: tell Bing (which feeds Copilot and ChatGPT search), Yandex, Seznam and Naver about new or changed pages immediately. */
    public static function indexNowKey(): string
    {
        $k = (string)Settings::get('seo.indexnow_key');
        if (!preg_match('/^[a-f0-9]{32}$/', $k)) { $k = bin2hex(random_bytes(16)); Settings::set('seo.indexnow_key', $k); }
        return $k;
    }

    public static function indexNow(array $paths): array
    {
        $host = parse_url(url('/'), PHP_URL_HOST);
        if (!$host || str_contains($host, 'localhost') || str_ends_with($host, '.vercel.app') || Env::bool('HTTP_FAKE')) return ['ok' => false, 'note' => 'Skipped: IndexNow needs the production domain.'];
        $urls = array_values(array_unique(array_map(fn($p) => str_starts_with($p, 'http') ? $p : url($p), $paths)));
        $body = json_encode(['host' => $host, 'key' => self::indexNowKey(), 'keyLocation' => url('/' . self::indexNowKey() . '.txt'), 'urlList' => array_slice($urls, 0, 10000)]);
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8, 'ignore_errors' => true, 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $body]]);
        @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
        $code = 0; foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) $code = (int)$m[1];
        $ok = in_array($code, [200, 202], true);
        Settings::set('seo.indexnow_last', jenc(['at' => now(), 'code' => $code, 'count' => count($urls)]));
        Log::write($ok ? 'info' : 'warn', 'seo.indexnow', ['code' => $code, 'count' => count($urls)]);
        return ['ok' => $ok, 'code' => $code, 'count' => count($urls)];
    }

    /** Every public URL in the sitemap, as paths. */
    public static function allUrls(): array
    {
        preg_match_all('#<loc>([^<]+)</loc>#', self::sitemap(), $m);
        return array_map('html_entity_decode', $m[1]);
    }

    public static function service(array $s): array
    {
        $d = ['@context' => 'https://schema.org', '@type' => 'Service', 'name' => $s['name'], 'description' => $s['summary'], 'serviceType' => Catalog::CATEGORIES[$s['category']] ?? $s['category'],
            'provider' => ['@type' => 'Organization', 'name' => Settings::get('company.name'), 'url' => url('/')], 'areaServed' => 'CA', 'url' => url('/services/' . $s['slug'])];
        $from = Catalog::fromPrice($s);
        if ($from !== null) $d['offers'] = ['@type' => 'Offer', 'priceCurrency' => 'CAD', 'price' => number_format($from / 100, 2, '.', ''), 'availability' => 'https://schema.org/InStock'];
        return $d;
    }

    public static function faq(array $faqs): ?array
    {
        if (!$faqs) return null;
        return ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $faqs)];
    }

    public static function breadcrumbs(array $crumbs): array
    {
        $items = []; $i = 1;
        foreach ($crumbs as $name => $path) $items[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $name, 'item' => url($path)];
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public static function sitemap(): string
    {
        $paths = ['/', '/register', '/register/nuans', '/register/sole-proprietorship', '/register/partnership', '/register/corporation', '/register/cooperative', '/manage-business', '/manage-business/annual-returns', '/manage-business/corporate-changes', '/manage-business/corporate-documents', '/manage-business/name-services', '/web-services', '/domain', '/email', '/website', '/website/maintenance', '/web-application', '/bundle', '/opportunities', '/services', '/start', '/about', '/contact', '/faq', '/blog', '/legal/privacy', '/legal/terms', '/legal/refunds', '/legal/cookies', '/legal/ai'];
        foreach (array_keys(Catalog::CATEGORIES) as $c) $paths[] = '/services/category/' . $c;
        foreach (Catalog::all() as $s) $paths[] = '/services/' . $s['slug'];
        $mod = [];
        foreach (Db::all("SELECT slug, COALESCE(updated_at, publish_at) m FROM content_items WHERE status = 'published' AND kind = 'blog'") as $bl) { $paths[] = '/blog/' . $bl['slug']; $mod['/blog/' . $bl['slug']] = substr((string)$bl['m'], 0, 10); }
        $release = gmdate('Y-m-d', (int)(@filemtime(APP_ROOT . '/bootstrap.php') ?: time()));
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach (array_unique($paths) as $p) {
            $u = e(url($p));
            $x .= '  <url><loc>' . $u . '</loc><lastmod>' . ($mod[$p] ?? $release) . '</lastmod>'
                . '<xhtml:link rel="alternate" hreflang="en-CA" href="' . $u . '"/><xhtml:link rel="alternate" hreflang="fr-CA" href="' . $u . (str_contains($p, '?') ? '&amp;' : '?') . 'lang=fr"/>'
                . '<priority>' . ($p === '/' ? '1.0' : (substr_count($p, '/') === 1 ? '0.8' : '0.6')) . "</priority></url>\n";
        }
        return $x . '</urlset>' . "\n";
    }

    public static function llms(): string
    {
        $t = "# " . Settings::get('company.name') . "\n\n> " . Settings::get('seo.default_description') . "\n\n";
        $t .= "Operated by " . Settings::get('company.legal') . ", " . Settings::get('company.city') . ". Prices are in CAD and come from our live catalog (" . url('/services.json') . ").\n";
        $t .= "Contact: " . Settings::get('company.email') . "\n\n";
        foreach (Catalog::CATEGORIES as $cat => $name) {
            $svcs = Catalog::byCategory($cat);
            if (!$svcs) continue;
            $t .= "## {$name}\n";
            foreach ($svcs as $s) $t .= "- [{$s['name']}](" . url('/services/' . $s['slug']) . "): {$s['summary']} — " . Catalog::priceLabel($s) . ($s['timeline'] ? ", typical timeline {$s['timeline']}" : '') . "\n";
            $t .= "\n";
        }
        $posts = Db::all("SELECT title, slug, excerpt FROM content_items WHERE kind = 'blog' AND status = 'published' ORDER BY COALESCE(publish_at, updated_at) DESC LIMIT 25");
        if ($posts) { $t .= "## Guides\n"; foreach ($posts as $p) $t .= "- [{$p['title']}](" . url('/blog/' . $p['slug']) . "): {$p['excerpt']}\n"; $t .= "\n"; }
        $t .= "## Key facts\n- Serves entrepreneurs across Canada: federal (Corporations Canada) and provincial registrations, plus Atlantic Canada focus.\n- Registration prices include the government fee where stated on the service page.\n- Work is prepared with AI assistance and reviewed by people; filings are submitted only with the customer's authorization.\n- Not a law firm and does not give legal or tax advice.\n- Available in English and French (" . url('/?lang=fr') . ").\n- Full detail for AI assistants: " . url('/llms-full.txt') . "\n\n";
        $t .= "## Policies\n- [Privacy](" . url('/legal/privacy') . ")\n- [Terms](" . url('/legal/terms') . ")\n- [Refunds](" . url('/legal/refunds') . ")\n- [How we use AI](" . url('/legal/ai') . ")\n";
        return $t;
    }

    /** Long-form companion to llms.txt: every service with its details, FAQs and knowledge-base answers. */
    public static function llmsFull(): string
    {
        $t = self::llms() . "\n---\n\n# Service details\n\n";
        foreach (Catalog::all() as $s) {
            $t .= "## {$s['name']}\nURL: " . url('/services/' . $s['slug']) . "\nPrice: " . Catalog::priceLabel($s) . "\n" . ($s['timeline'] ? "Typical timeline: {$s['timeline']}\n" : '') . "\n{$s['summary']}\n";
            if (!empty($s['description'])) $t .= "\n" . trim(strip_tags((string)$s['description'])) . "\n";
            $del = is_array($s['deliverables'] ?? null) ? $s['deliverables'] : jdec((string)($s['deliverables'] ?? '[]'));
            if ($del) $t .= "\nIncludes:\n- " . implode("\n- ", array_map('strval', $del)) . "\n";
            $t .= "\n";
        }
        $kb = Db::all("SELECT title, body FROM kb_articles WHERE status = 'published' ORDER BY category, id LIMIT 120");
        if ($kb) { $t .= "# Questions and answers\n\n"; foreach ($kb as $a) $t .= "## {$a['title']}\n" . trim(strip_tags($a['body'])) . "\n\n"; }
        return $t;
    }

    public static function catalogJson(): array
    {
        return ['organization' => Settings::get('company.name'), 'currency' => 'CAD', 'updated' => gmdate('c'), 'services' => array_map(fn($s) => [
            'slug' => $s['slug'], 'name' => $s['name'], 'category' => $s['category'], 'summary' => $s['summary'], 'pricing_model' => $s['pricing_model'],
            'price_from_cad' => Catalog::fromPrice($s) !== null ? Catalog::fromPrice($s) / 100 : null, 'billing' => Catalog::billing($s), 'setup_cad' => $s['setup_cents'] / 100,
            'province_prices_cad' => $s['pricing_model'] === 'per_province' ? array_map(fn($v) => $v === null ? null : $v / 100, $s['province_prices']) : null,
            'timeline' => $s['timeline'], 'deliverables' => $s['deliverables'], 'url' => url('/services/' . $s['slug'])], Catalog::all())];
    }

    /** Heuristic SEO score 0–100 for content drafts. */
    public static function score(string $title, string $body, string $kw): int
    {
        $s = 40; $len = str_word_count(strip_tags($body));
        if ($len >= 600) $s += 15; elseif ($len >= 300) $s += 8;
        if (mb_strlen($title) >= 30 && mb_strlen($title) <= 65) $s += 10;
        if ($kw !== '') { if (stripos($title, $kw) !== false) $s += 15; if (substr_count(mb_strtolower($body), mb_strtolower($kw)) >= 2) $s += 10; }
        if (preg_match('/^#{2,3} /m', $body)) $s += 10;
        return min(100, $s);
    }

    /** Flesch reading ease (approx.). */
    public static function readability(string $body): int
    {
        $text = strip_tags($body);
        $words = max(1, str_word_count($text));
        $sent = max(1, preg_match_all('/[.!?]+/', $text));
        $syll = max(1, preg_match_all('/[aeiouy]+/i', $text));
        return (int)max(0, min(100, round(206.835 - 1.015 * ($words / $sent) - 84.6 * ($syll / $words))));
    }
}
