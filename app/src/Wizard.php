<?php
declare(strict_types=1);
namespace App;

/**
 * Order intake for the step-by-step service forms (same flows as the original 818digitalagency.com).
 * The browser sends what the customer chose and typed; prices, discounts and what may be combined are decided here.
 * Details travel with the cart line → order item → project/filing, so customers never re-type them after paying.
 */
final class Wizard
{
    /** Service → add-ons that may be ordered with it from its form. */
    public const ADDONS = [
        'corp_named' => ['addon_minute_book', 'addon_seal', 'addon_registered_address', 'addon_expedited', 'addon_extra_copies'],
        'corp_numbered' => ['addon_minute_book', 'addon_seal', 'addon_registered_address', 'addon_expedited', 'addon_extra_copies'],
        'sole_prop' => ['addon_expedited'], 'partnership' => ['addon_expedited'],
        'website_launch' => ['domain_register', 'email_starter'], 'website_grow' => ['domain_register', 'email_starter'], 'website_scale' => ['domain_register', 'email_starter'],
        'email_starter' => ['domain_register'], 'email_business' => ['domain_register'], 'email_team' => ['domain_register'],
    ];
    /** Services orderable from a form (others are quote-only and go through quote()). */
    public const ORDERABLE = ['sole_prop', 'partnership', 'corp_named', 'corp_numbered', 'biz_name', 'nuans', 'bm_annual', 'bm_changes', 'bm_documents',
        'domain_register', 'email_starter', 'email_business', 'email_team', 'website_launch', 'website_grow', 'website_scale', 'maintenance_care', 'maintenance_protect', 'maintenance_grow'];
    /** Bundle discount by number of services, as on the original bundle page. */
    public const BUNDLE_TIERS = [2 => 5, 3 => 10, 4 => 12, 5 => 15];

    /** Keep only plain strings/numbers/bools, shallow nesting and bounded size. */
    public static function clean(mixed $v, int $depth = 0): mixed
    {
        if ($depth > 4) return null;
        if (is_array($v)) {
            $out = [];
            foreach (array_slice($v, 0, 40, true) as $k => $x) {
                $k = is_int($k) ? $k : mb_substr(preg_replace('/[^a-z0-9_]/i', '', (string)$k), 0, 40);
                if ($k === '' || in_array($k, ['password', 'pass', 'card', 'card_number', 'sin', 'cvv'], true)) continue;
                $c = self::clean($x, $depth + 1);
                if ($c !== null && $c !== '' && $c !== []) $out[$k] = $c;
            }
            return $out;
        }
        if (is_bool($v) || is_int($v) || is_float($v)) return $v;
        if (is_string($v)) return mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v)), 0, 2000);
        return null;
    }

    private static function details(mixed $d): array
    {
        $d = self::clean(is_array($d) ? $d : []);
        if (strlen(jenc($d)) > 24000) throw new UserError('That form is too large. Shorten the descriptions and try again.');
        return $d;
    }

    /** Add one service (plus allowed add-ons) from a form. */
    public static function add(array $in): array
    {
        $slug = (string)($in['slug'] ?? '');
        if (!in_array($slug, self::ORDERABLE, true)) throw new UserError('That service can’t be ordered from this form.');
        if (empty($in['agree'])) throw new UserError('Please read and accept the Service Agreement to continue.');
        $opts = ['province' => (string)($in['province'] ?? ''), 'tld' => (string)($in['tld'] ?? ''), 'domain' => (string)($in['domain'] ?? '')];
        $details = self::details($in['details'] ?? []);
        Cart::add($slug, $opts, 1, null, $details);
        $added = 1;
        foreach (array_unique(array_filter((array)($in['addons'] ?? []), 'is_string')) as $a) {
            if (!in_array($a, self::ADDONS[$slug] ?? [], true)) continue;
            $aopts = $a === 'domain_register' ? ['tld' => (string)($in['addon_tld'] ?? $in['tld'] ?? 'ca'), 'domain' => (string)($in['addon_domain'] ?? $in['domain'] ?? '')] : ['province' => $opts['province']];
            Cart::add($a, $aopts, 1, null, ['for' => $slug]);
            $added++;
        }
        Audit::log('cart.form_add', 'service', $slug, ['addons' => $added - 1]);
        return ['ok' => true, 'count' => count(Cart::lines()), 'redirect' => '/cart'];
    }

    /** Bundle builder: discount tier is decided here from the number of services. */
    public static function bundle(array $in): array
    {
        if (empty($in['agree'])) throw new UserError('Please read and accept the Service Agreement to continue.');
        $prov = strtoupper((string)($in['province'] ?? 'NB'));
        $reg = ['sole_prop' => 'sole_prop', 'partnership' => 'partnership', 'named' => 'corp_named', 'numbered' => 'corp_numbered'][(string)($in['reg_type'] ?? 'numbered')] ?? 'corp_numbered';
        $pick = (array)($in['services'] ?? []);
        $lines = [];
        if (!empty($pick['registration'])) $lines[] = [$reg, ['province' => $prov]];
        if (!empty($pick['management'])) $lines[] = ['bm_annual', ['province' => $prov]];
        if (!empty($pick['domain'])) $lines[] = ['domain_register', ['tld' => (string)($in['tld'] ?? 'ca'), 'domain' => (string)($in['domain'] ?? '')]];
        if (!empty($pick['email'])) $lines[] = [in_array($in['email_plan'] ?? '', ['email_starter', 'email_business', 'email_team'], true) ? $in['email_plan'] : 'email_starter', []];
        if (!empty($pick['website'])) $lines[] = [in_array($in['website_plan'] ?? '', ['website_launch', 'website_grow', 'website_scale'], true) ? $in['website_plan'] : 'website_launch', []];
        if (!empty($pick['maintenance'])) $lines[] = [in_array($in['maint_plan'] ?? '', ['maintenance_care', 'maintenance_protect', 'maintenance_grow'], true) ? $in['maint_plan'] : 'maintenance_care', []];
        if (!$lines) throw new UserError('Please select at least one service.');
        $pct = self::bundlePct(count($lines));
        foreach ($lines as [$slug, $opts]) Cart::add($slug, $opts, 1, null, ['bundle' => true], $pct);
        Audit::log('cart.bundle_add', 'bundle', null, ['services' => count($lines), 'pct' => $pct]);
        return ['ok' => true, 'count' => count(Cart::lines()), 'redirect' => '/cart', 'discount_pct' => $pct];
    }

    public static function bundlePct(int $n): int { $p = 0; foreach (self::BUNDLE_TIERS as $min => $pct) if ($n >= $min) $p = $pct; return $p; }

    /** Quote-priced forms (co-operative, web application): create a lead with everything the customer entered. */
    public static function quote(array $in): array
    {
        if (!empty($in['website'])) throw new UserError('Submission rejected.'); // honeypot
        if (!RateLimit::hit('wizard-quote:' . client_ip(), 6, 3600)) throw new UserError('Too many requests from this network. Please email us instead.');
        $kind = in_array($in['kind'] ?? '', ['cooperative', 'web_app', 'maintenance'], true) ? $in['kind'] : 'web_app';
        $d = self::details($in['details'] ?? []);
        $email = strtolower(trim((string)($d['contact']['email'] ?? $d['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Enter a valid email address.');
        $name = (string)($d['contact']['name'] ?? $d['name'] ?? '');
        $c = Crm::upsertCustomer(['email' => $email, 'name' => $name, 'phone' => (string)($d['contact']['phone'] ?? $d['phone'] ?? ''), 'business_name' => (string)($d['entity']['proposed_name'] ?? $d['company'] ?? '')], 'service_form');
        $label = ['cooperative' => 'Co-operative registration', 'web_app' => 'Custom web application', 'maintenance' => 'Website maintenance'][$kind];
        $summary = $label . "\n\n" . self::readable($d);
        $lead = Crm::createLead($c, ['summary' => mb_substr($summary, 0, 4000), 'services' => [$kind === 'cooperative' ? 'cooperative' : 'web_app'], 'budget' => (string)($d['budget'] ?? ''), 'timeline' => (string)($d['timeline'] ?? ''), 'intent' => 'quote'], 'service_form');
        Mailer::send('lead_confirmation', $email, ['name' => $name ?: 'there', 'summary' => str_limit($summary, 300)], 'lead-confirm:' . $lead['id'], 'lead', (int)$lead['id']);
        return ['ok' => true, 'reference' => $lead['number'] ?? ('L-' . $lead['id'])];
    }

    /** Flatten details into "Label: value" lines for people to read. */
    public static function readable(array $d, string $prefix = ''): string
    {
        $out = [];
        foreach ($d as $k => $v) {
            $label = trim($prefix . ' ' . (is_int($k) ? '#' . ($k + 1) : ucfirst(str_replace('_', ' ', (string)$k))));
            if (is_array($v)) $out[] = self::readable($v, $label);
            elseif ($v !== '' && $v !== null) $out[] = $label . ': ' . (is_bool($v) ? ($v ? 'Yes' : 'No') : $v);
        }
        return implode("\n", array_filter($out));
    }
}
