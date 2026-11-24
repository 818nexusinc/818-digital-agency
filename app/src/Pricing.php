<?php
declare(strict_types=1);
namespace App;

/**
 * Pricing strategy. For every priced service (and each Atlantic province or domain ending where prices differ):
 * our price = government fee + third-party cost + 818 markup. Fees and competitor prices are only ever entered
 * with a source — by the owner or by the Pricing Agent's web research — and nothing changes a live price until the
 * owner approves a proposal.
 */
final class Pricing
{
    /** Atlantic Canada first, plus federal incorporation where offered. */
    public const REGIONS = ['NB' => 'New Brunswick', 'NS' => 'Nova Scotia', 'PE' => 'Prince Edward Island', 'NL' => 'Newfoundland and Labrador', 'FED' => 'Federal'];
    public const STRATEGIES = ['match' => 'Match the market', 'undercut' => 'Price below competitors', 'premium' => 'Premium (more included)', 'margin' => 'Protect margin', 'bundle' => 'Bundle offer'];

    /** Make sure the Pricing Agent exists on installs that predate it. */
    public static function ensureAgent(): void
    {
        try { if (!Db::val("SELECT 1 FROM agents WHERE slug = 'pricing'")) Agents::seed(); } catch (\Throwable) {}
    }

    private static function arr(mixed $v): array { return is_array($v) ? $v : (array)jdec(is_string($v) ? $v : null, []); }

    /** Price points for one service: [variant, label, price_cents, billing]. */
    public static function variants(array $s): array
    {
        $billing = ['monthly' => 'month', 'yearly' => 'year', 'one_time' => 'one-time', 'per_province' => 'one-time', 'quote' => 'quote'][$s['pricing_model']] ?? 'one-time';
        $pp = self::arr($s['province_prices'] ?? null);
        if ($s['pricing_model'] === 'per_province' || $pp) {
            $out = [];
            foreach (self::REGIONS as $k => $n) if (isset($pp[$k])) $out[] = [$k, $n, (int)$pp[$k], $billing];
            return $out;
        }
        $tlds = self::arr($s['options'] ?? null)['tlds'] ?? null;
        if (is_array($tlds) && $tlds) {
            $out = [];
            foreach ($tlds as $t => $c) $out[] = [(string)$t, '.' . $t, (int)$c, 'year'];
            return $out;
        }
        if ($s['price_cents'] === null || $s['pricing_model'] === 'quote') return [['', 'Quoted', null, 'quote']];
        return [['', '', (int)$s['price_cents'], $billing]];
    }

    /** Every price point with its costs, markup and competitor range. */
    public static function rows(?string $category = null, ?string $slug = null): array
    {
        $costs = []; foreach (Db::all('SELECT * FROM price_costs') as $c) $costs[$c['service_slug']][$c['variant']] = $c;
        $comp = [];
        foreach (Db::all("SELECT * FROM competitor_prices WHERE status = 'active' ORDER BY price_cents") as $c) $comp[$c['service_slug']][$c['variant']][] = $c;
        $open = []; foreach (Db::all("SELECT * FROM price_proposals WHERE status = 'open' ORDER BY id DESC") as $p) $open[$p['service_slug']][$p['variant']][] = $p;
        $sql = "SELECT * FROM services WHERE status = 'active'" . ($category ? ' AND category = ?' : '') . ($slug ? ' AND slug = ?' : '') . ' ORDER BY category, sort, id';
        $out = [];
        foreach (Db::all($sql, array_values(array_filter([$category, $slug]))) as $s) {
            foreach (self::variants($s) as [$v, $vl, $price, $bill]) {
                $c = $costs[$s['slug']][$v] ?? null;
                $gov = $c['gov_fee_cents'] ?? null; $third = $c['third_party_cents'] ?? null;
                $known = $c !== null && ($gov !== null || $third !== null);
                $markup = ($price !== null && $known) ? $price - (int)$gov - (int)$third : null;
                $cs = $comp[$s['slug']][$v] ?? ($v !== '' ? [] : []);
                $prices = array_map(fn($x) => (int)$x['price_cents'], $cs);
                sort($prices);
                $med = $prices ? ($prices[intdiv(count($prices), 2)] + $prices[intdiv(count($prices) - 1, 2)]) / 2 : null;
                $pos = ($price === null || $med === null) ? null : ($price < $med * 0.95 ? 'below' : ($price > $med * 1.05 ? 'above' : 'at'));
                $out[] = ['slug' => $s['slug'], 'name' => $s['name'], 'category' => $s['category'], 'variant' => $v, 'vlabel' => $vl, 'price' => $price, 'billing' => $bill,
                    'gov' => $gov === null ? null : (int)$gov, 'third' => $third === null ? null : (int)$third, 'confirmed' => ($c['status'] ?? '') === 'confirmed', 'cost' => $c,
                    'markup' => $markup, 'markup_pct' => ($markup !== null && $price) ? (int)round($markup / $price * 100) : null,
                    'comp' => $cs, 'low' => $prices[0] ?? null, 'high' => $prices ? end($prices) : null, 'median' => $med === null ? null : (int)round($med), 'position' => $pos,
                    'proposals' => $open[$s['slug']][$v] ?? []];
            }
        }
        return $out;
    }

    public static function summary(array $rows): array
    {
        $priced = array_filter($rows, fn($r) => $r['price'] !== null);
        $withCost = array_filter($priced, fn($r) => $r['markup'] !== null);
        $m = array_filter(array_column($withCost, 'markup_pct'), fn($x) => $x !== null);
        return ['points' => count($priced), 'costed' => count($withCost), 'confirmed' => count(array_filter($priced, fn($r) => $r['confirmed'])),
            'competitors' => (int)Db::val("SELECT COUNT(*) FROM competitor_prices WHERE status = 'active'"),
            'proposals' => (int)Db::val("SELECT COUNT(*) FROM price_proposals WHERE status = 'open'"),
            'avg_markup' => $m ? (int)round(array_sum($m) / count($m)) : null,
            'losing' => count(array_filter($withCost, fn($r) => $r['markup'] < 0)),
            'last_research' => Db::val("SELECT MAX(created_at) FROM competitor_prices WHERE found_by = 'pricing'")];
    }

    private static function service(string $slug): array
    {
        $s = Catalog::find($slug);
        if (!$s) throw new UserError('Unknown service: ' . $slug);
        return $s;
    }

    /** Turn what a person or agent typed ("nb", " NB", ".CA") into the stored option key for this service. */
    public static function normVariant(string $slug, string $v): string
    {
        $v = trim(ltrim(trim($v), '.'));
        if ($v === '') return '';
        $s = Catalog::find($slug);
        if (!$s) return $v;
        if (isset(self::arr($s['province_prices'] ?? null)[strtoupper($v)])) return strtoupper($v);
        if (isset((self::arr($s['options'] ?? null)['tlds'] ?? [])[strtolower($v)])) return strtolower($v);
        return strtoupper($v);
    }

    private static function variantOk(array $s, string $v): bool
    {
        foreach (self::variants($s) as [$k]) if ($k === $v) return true;
        // Costs may be recorded for provinces outside Atlantic Canada too.
        return isset(self::arr($s['province_prices'] ?? null)[$v]);
    }

    private static function cents(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        $n = (float)str_replace([',', '$', ' '], '', (string)$v);
        if ($n < 0 || $n > 1_000_000) throw new UserError('Amounts must be between $0 and $1,000,000.');
        return (int)round($n * 100);
    }

    private static function url(?string $u): ?string
    {
        $u = trim((string)$u);
        if ($u === '') return null;
        if (!preg_match('#^https?://[^\s<>"]+$#i', $u)) throw new UserError('Sources must be web addresses starting with http:// or https://');
        return mb_substr($u, 0, 500);
    }

    /** Record government / third-party cost. Agents' findings stay "unconfirmed" until the owner confirms them. */
    public static function saveCost(string $slug, string $variant, array $in, string $by, bool $owner): void
    {
        $s = self::service($slug);
        if (!self::variantOk($s, $variant)) throw new UserError('That province or option is not sold for ' . $s['name'] . '.');
        $row = ['gov_fee_cents' => self::cents($in['gov_fee'] ?? null), 'third_party_cents' => self::cents($in['third_party'] ?? null), 'source_url' => self::url($in['source_url'] ?? null),
            'notes' => mb_substr(trim((string)($in['notes'] ?? '')), 0, 500) ?: null, 'status' => $owner && !empty($in['confirmed']) ? 'confirmed' : 'unconfirmed', 'updated_by' => $by, 'updated_at' => now()];
        if (!$owner && !$row['source_url']) throw new UserError('Give the official source page for the fee.');
        $ex = Db::one('SELECT id, status FROM price_costs WHERE service_slug = ? AND variant = ?', [$slug, $variant]);
        if ($ex && !$owner && $ex['status'] === 'confirmed') throw new UserError('The owner already confirmed this fee. Suggest a change in a price proposal instead.');
        if ($ex) Db::update('price_costs', $row, 'id = ?', [$ex['id']]); else Db::insert('price_costs', $row + ['service_slug' => $slug, 'variant' => $variant]);
        Audit::log('pricing.cost', 'service', $slug, ['variant' => $variant, 'by' => $by]);
    }

    public static function addCompetitor(string $slug, string $variant, array $in, string $by): int
    {
        $s = self::service($slug);
        if ($variant !== '' && !self::variantOk($s, $variant)) $variant = '';
        $name = mb_substr(trim((string)($in['competitor'] ?? '')), 0, 160);
        $price = self::cents($in['price'] ?? null);
        if ($name === '' || $price === null) throw new UserError('Give the competitor and their price.');
        $url = self::url($in['url'] ?? null);
        if ($by !== 'owner' && !$url) throw new UserError('Give the page where you found the price.');
        $id = Db::insert('competitor_prices', ['service_slug' => $slug, 'variant' => $variant, 'competitor' => $name, 'region' => mb_substr((string)($in['region'] ?? ''), 0, 60) ?: null,
            'price_cents' => $price, 'includes_gov_fee' => isset($in['includes_gov_fee']) && $in['includes_gov_fee'] !== '' ? ((bool)$in['includes_gov_fee'] ? 1 : 0) : null,
            'billing' => mb_substr((string)($in['billing'] ?? ''), 0, 20) ?: null, 'url' => $url, 'notes' => mb_substr(trim((string)($in['notes'] ?? '')), 0, 500) ?: null,
            'found_by' => $by, 'status' => 'active', 'observed_at' => now(), 'created_at' => now()]);
        Audit::log('pricing.competitor', 'service', $slug, ['competitor' => $name, 'by' => $by]);
        return (int)$id;
    }

    public static function propose(string $slug, string $variant, int $proposedCents, string $strategy, string $rationale, string $by): int
    {
        $s = self::service($slug);
        $cur = null; foreach (self::variants($s) as [$k, , $p]) if ($k === $variant) $cur = $p;
        if (!self::variantOk($s, $variant)) throw new UserError('That province or option is not sold for ' . $s['name'] . '.');
        if ($proposedCents <= 0 || $proposedCents > 100_000_000) throw new UserError('Proposed price is out of range.');
        if (trim($rationale) === '') throw new UserError('Explain the reason for the price.');
        Db::run("UPDATE price_proposals SET status = 'replaced', decided_at = ? WHERE service_slug = ? AND variant = ? AND status = 'open'", [now(), $slug, $variant]);
        $id = Db::insert('price_proposals', ['service_slug' => $slug, 'variant' => $variant, 'current_cents' => $cur, 'proposed_cents' => $proposedCents,
            'strategy' => isset(self::STRATEGIES[$strategy]) ? $strategy : 'match', 'rationale' => mb_substr($rationale, 0, 4000), 'status' => 'open', 'created_by' => $by, 'created_at' => now()]);
        Audit::log('pricing.proposed', 'service', $slug, ['variant' => $variant, 'cents' => $proposedCents, 'by' => $by]);
        return (int)$id;
    }

    /** Owner decision. Approving changes the live price on the website. */
    public static function decide(int $id, bool $approve, string $by): array
    {
        $p = Db::one("SELECT * FROM price_proposals WHERE id = ? AND status = 'open'", [$id]);
        if (!$p) throw new UserError('This proposal was already decided.');
        if ($approve) {
            $s = self::service($p['service_slug']); $v = $p['variant']; $c = (int)$p['proposed_cents'];
            $pp = self::arr($s['province_prices'] ?? null); $opt = self::arr($s['options'] ?? null);
            if ($v !== '' && isset($pp[$v])) { $pp[$v] = $c; Db::update('services', ['province_prices' => jenc($pp), 'updated_at' => now()], 'slug = ?', [$s['slug']]); }
            elseif ($v !== '' && isset($opt['tlds'][$v])) { $opt['tlds'][$v] = $c; Db::update('services', ['options' => jenc($opt), 'updated_at' => now()], 'slug = ?', [$s['slug']]); }
            else Db::update('services', ['price_cents' => $c, 'updated_at' => now()], 'slug = ?', [$s['slug']]);
        }
        Db::update('price_proposals', ['status' => $approve ? 'applied' : 'rejected', 'decided_by' => $by, 'decided_at' => now()], 'id = ?', [$id]);
        Audit::log($approve ? 'pricing.applied' : 'pricing.rejected', 'service', $p['service_slug'], ['variant' => $p['variant'], 'from' => $p['current_cents'], 'to' => $p['proposed_cents']]);
        return $p;
    }

    /** Queue a research run for the Pricing Agent. */
    public static function research(?string $slug, string $focus, string $by): int
    {
        self::ensureAgent();
        $scope = $slug ? 'the service "' . $slug . '"' : 'every priced service, starting with registrations and business management';
        $brief = "Research competitor prices in Atlantic Canada (New Brunswick, Nova Scotia, Prince Edward Island, Newfoundland and Labrador) for {$scope}. "
            . 'Use web_search to find (1) the current official government filing fee for each registration or corporate service and province, from the government site, and (2) what other providers in Atlantic Canada charge for the same service. '
            . 'Call pricing_overview first. Save each official fee with save_cost_finding and each competitor price with save_competitor_price, always with the page address. Never guess a number you did not find. '
            . 'Then call propose_price for any price point where we are clearly out of line with the market or below cost, with a short reason. End with a brief summary of what you found.'
            . ($focus !== '' ? ' Owner note: ' . $focus : '');
        return Orchestrator::enqueue('pricing', 'pricing_research', 'Pricing research: ' . ($slug ?: 'all services'), ['brief' => $brief], ['priority' => 4, 'created_by' => $by]);
    }
}
