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
            'parentOrganization' => ['@type' => 'Organization', 'name' => '818 Nexus Inc.']];
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
        foreach (Db::all("SELECT slug, updated_at FROM content_items WHERE status = 'published' AND kind = 'blog'") as $b) $paths[] = '/blog/' . $b['slug'];
        $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_unique($paths) as $p) $x .= '  <url><loc>' . e(url($p)) . "</loc></url>\n";
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
        $t .= "## Policies\n- [Privacy](" . url('/legal/privacy') . ")\n- [Terms](" . url('/legal/terms') . ")\n- [Refunds](" . url('/legal/refunds') . ")\n- [How we use AI](" . url('/legal/ai') . ")\n";
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
