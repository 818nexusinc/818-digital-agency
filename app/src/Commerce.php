<?php
declare(strict_types=1);
namespace App;

/** Service catalog. Prices live ONLY here (DB) — pages, cart, proposals and AI all read from it. */
final class Catalog
{
    public const PROVINCES = ['FED' => 'Federal (CBCA)', 'AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick',
        'NL' => 'Newfoundland & Labrador', 'NS' => 'Nova Scotia', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Quebec', 'SK' => 'Saskatchewan'];

    public const CATEGORIES = ['registration' => 'Business registration', 'management' => 'Business management', 'domains' => 'Domains',
        'email' => 'Business email', 'websites' => 'Websites', 'apps' => 'Web applications', 'growth' => 'Brand & growth', 'addon' => 'Add-ons'];

    public static function all(bool $activeOnly = true): array
    {
        $rows = Db::all('SELECT * FROM services ' . ($activeOnly ? "WHERE status = 'active' " : '') . 'ORDER BY sort, id');
        return array_map([self::class, 'hydrate'], $rows);
    }

    public static function find(string $slug, bool $activeOnly = true): ?array
    {
        $r = Db::one('SELECT * FROM services WHERE slug = ?' . ($activeOnly ? " AND status = 'active'" : ''), [$slug]);
        return $r ? self::hydrate($r) : null;
    }

    public static function byCategory(string $cat): array { return array_values(array_filter(self::all(), fn($s) => $s['category'] === $cat)); }

    public static function hydrate(array $r): array
    {
        foreach (['benefits', 'deliverables', 'province_prices', 'options', 'requirements', 'faqs', 'addons', 'human_required'] as $k) $r[$k] = jdec($r[$k] ?? null, []);
        $r['price_cents'] = $r['price_cents'] === null ? null : (int)$r['price_cents'];
        $r['setup_cents'] = (int)$r['setup_cents'];
        return $r;
    }

    /** Lowest advertised price, for "from $X" labels. Null when quote-only with no starting price. */
    public static function fromPrice(array $s): ?int
    {
        if ($s['pricing_model'] === 'per_province') { $p = array_filter($s['province_prices'], fn($v) => $v !== null); return $p ? (int)min($p) : null; }
        if (!empty($s['options']['tlds'])) return (int)min($s['options']['tlds']);
        return $s['price_cents'];
    }

    public static function priceLabel(array $s): string
    {
        $from = self::fromPrice($s);
        if ($from === null) return 'Custom quote';
        $suffix = match (self::billing($s)) { 'monthly' => '/mo', 'yearly' => '/yr', default => '' };
        $prefix = ($s['pricing_model'] === 'per_province' || !empty($s['options']['tlds']) || $s['pricing_model'] === 'quote') ? 'From ' : '';
        return $prefix . money($from) . $suffix . ($s['setup_cents'] ? ' + ' . money($s['setup_cents']) . ' setup' : '');
    }

    public static function billing(array $s): string
    {
        return in_array($s['pricing_model'], ['monthly', 'yearly'], true) ? $s['pricing_model'] : (!empty($s['options']['billing']) ? $s['options']['billing'] : 'one_time');
    }

    /**
     * Authoritative price for a service + options. Returns null if the service must be quoted.
     * @return array{unit_cents:int,setup_cents:int,billing:string,label:string}|null
     */
    public static function price(array $s, array $opts = []): ?array
    {
        $label = $s['name'];
        switch (true) {
            case $s['pricing_model'] === 'quote':
                return null;
            case $s['pricing_model'] === 'per_province':
                $prov = strtoupper((string)($opts['province'] ?? ''));
                if (!array_key_exists($prov, $s['province_prices']) || $s['province_prices'][$prov] === null) {
                    throw new UserError("{$s['name']} is not available for " . (self::PROVINCES[$prov] ?? 'that province') . '. Choose another province.');
                }
                $unit = (int)$s['province_prices'][$prov];
                $label .= ' — ' . self::PROVINCES[$prov];
                break;
            case !empty($s['options']['tlds']):
                $tld = strtolower(ltrim((string)($opts['tld'] ?? 'ca'), '.'));
                if (!isset($s['options']['tlds'][$tld])) throw new UserError('That domain extension is not offered yet.');
                $unit = (int)$s['options']['tlds'][$tld];
                $domain = preg_replace('/[^a-z0-9\-.]/', '', strtolower((string)($opts['domain'] ?? '')));
                $label .= $domain ? " — {$domain}" : " — .{$tld}";
                break;
            default:
                if ($s['price_cents'] === null) return null;
                $unit = $s['price_cents'];
        }
        return ['unit_cents' => $unit, 'setup_cents' => $s['setup_cents'], 'billing' => self::billing($s), 'label' => $label];
    }

    public static function save(array $in, ?string $slug = null): void
    {
        $row = [
            'name' => mb_substr(trim($in['name'] ?? ''), 0, 190), 'category' => array_key_exists($in['category'] ?? '', self::CATEGORIES) ? $in['category'] : 'addon',
            'summary' => mb_substr(trim($in['summary'] ?? ''), 0, 255), 'description' => trim($in['description'] ?? ''),
            'pricing_model' => in_array($in['pricing_model'] ?? '', ['one_time', 'monthly', 'yearly', 'quote', 'per_province'], true) ? $in['pricing_model'] : 'quote',
            'price_cents' => ($in['price'] ?? '') === '' ? null : (int)round((float)$in['price'] * 100),
            'setup_cents' => (int)round((float)($in['setup'] ?? 0) * 100), 'timeline' => mb_substr(trim($in['timeline'] ?? ''), 0, 80),
            'status' => in_array($in['status'] ?? '', ['active', 'hidden', 'retired'], true) ? $in['status'] : 'active',
            'deliverables' => jenc(array_values(array_filter(array_map('trim', explode("\n", (string)($in['deliverables'] ?? '')))))),
            'limitations' => trim($in['limitations'] ?? ''), 'updated_at' => now(),
        ];
        if (isset($in['province_prices']) && is_array($in['province_prices'])) {
            $pp = [];
            foreach (self::PROVINCES as $code => $_) { $v = $in['province_prices'][$code] ?? ''; $pp[$code] = $v === '' ? null : (int)round((float)$v * 100); }
            $row['province_prices'] = jenc($pp);
        }
        if ($row['name'] === '') throw new UserError('Service name is required.');
        if ($slug) {
            $before = Db::one('SELECT price_cents, province_prices, status FROM services WHERE slug = ?', [$slug]);
            Db::update('services', $row, 'slug = ?', [$slug]);
            Audit::log('catalog.update', 'service', $slug, ['before' => $before, 'after' => array_intersect_key($row, $before ?? [])]);
        } else {
            $new = preg_replace('/[^a-z0-9_]/', '_', strtolower($in['slug'] ?? $row['name']));
            Db::insert('services', $row + ['slug' => $new, 'created_at' => now(), 'benefits' => '[]', 'faqs' => '[]', 'options' => '{}', 'province_prices' => $row['province_prices'] ?? '{}', 'requirements' => '[]', 'addons' => '[]', 'human_required' => '[]']);
            Audit::log('catalog.create', 'service', $new);
        }
    }
}

/** Session cart. Stores only references (slug + options); prices are recomputed from the catalog on every read. */
final class Cart
{
    public static function lines(): array { Session::start(); return $_SESSION['cart'] ?? []; }

    public static function setLines(array $lines): void { Session::start(); $_SESSION['cart'] = array_values($lines); }

    public static function add(string $slug, array $opts = [], int $qty = 1, ?string $package = null, ?array $details = null, int $bundlePct = 0): void
    {
        $s = Catalog::find($slug) ?? throw new UserError('That service is not available.');
        $clean = [];
        foreach (['province', 'tld', 'domain', 'note'] as $k) if (isset($opts[$k]) && $opts[$k] !== '') $clean[$k] = mb_substr(preg_replace('/[^\pL\pN .\-_]/u', '', (string)$opts[$k]), 0, 120);
        if (Catalog::price($s, $clean) === null) throw new UserError("{$s['name']} is priced by quote. Request a quote instead and we'll send a proposal.");
        $lines = self::lines();
        if (count($lines) >= 25) throw new UserError('Your cart is full.');
        if ($details) $clean['details'] = $details;
        $line = ['slug' => $slug, 'opts' => $clean, 'qty' => max(1, min(20, $qty)), 'package' => $package];
        if ($bundlePct > 0 && $bundlePct <= 30) $line['bundle_pct'] = $bundlePct; // set only by Wizard::bundle(), never from input
        $lines[] = $line;
        self::setLines($lines);
    }

    public static function addPackage(string $pkgSlug, array $opts): void
    {
        $p = Db::one("SELECT * FROM packages WHERE slug = ? AND status = 'active'", [$pkgSlug]) ?? throw new UserError('Package not found.');
        foreach (jdec($p['items']) as $it) self::add($it['slug'], array_merge($it['opts'] ?? [], $opts), 1, $pkgSlug);
    }

    public static function remove(int $i): void { $l = self::lines(); unset($l[$i]); self::setLines($l); }
    public static function clear(): void { self::setLines([]); Session::start(); unset($_SESSION['coupon']); }

    public static function coupon(): ?string { Session::start(); return $_SESSION['coupon'] ?? null; }
    public static function setCoupon(?string $c): void { Session::start(); $_SESSION['coupon'] = $c ? strtoupper(trim($c)) : null; }

    public static function validCoupon(?string $code): ?array
    {
        if (!$code) return null;
        $c = Db::one('SELECT * FROM coupons WHERE code = ? AND active = 1', [strtoupper(trim($code))]);
        if (!$c) return null;
        if ($c['expires_at'] && $c['expires_at'] < now()) return null;
        if ($c['max_uses'] !== null && (int)$c['uses'] >= (int)$c['max_uses']) return null;
        $c['applies_to'] = jdec($c['applies_to'], ['all']);
        return $c;
    }

    /**
     * Price a list of lines. Package discounts and coupons are applied per line so the
     * amounts sent to Stripe are exactly the amounts shown.
     */
    public static function totals(?array $lines = null, ?string $couponCode = null): array
    {
        $lines ??= self::lines();
        $couponCode ??= self::coupon();
        $coupon = self::validCoupon($couponCode);
        $out = []; $fixedLeft = $coupon && $coupon['type'] === 'fixed' ? (int)$coupon['value_amount'] : 0;
        $sum = ['one_time' => 0, 'recurring_monthly' => 0, 'recurring_yearly' => 0, 'setup' => 0, 'discount' => 0];
        foreach ($lines as $i => $l) {
            $s = Catalog::find($l['slug']);
            if (!$s) continue;
            $p = Catalog::price($s, $l['opts'] ?? []);
            if (!$p) continue;
            $qty = (int)($l['qty'] ?? 1);
            $unit = $p['unit_cents']; $setup = $p['setup_cents']; $disc = 0;
            if (!empty($l['package'])) {
                $pct = (int)Db::val('SELECT discount_pct FROM packages WHERE slug = ?', [$l['package']]);
                $d = intdiv($unit * $pct, 100); $unit -= $d; $disc += $d * $qty;
            } elseif (!empty($l['bundle_pct'])) {
                $d = intdiv($unit * min(30, (int)$l['bundle_pct']), 100); $unit -= $d; $disc += $d * $qty;
            }
            if ($coupon && (in_array('all', $coupon['applies_to'], true) || in_array($s['category'], $coupon['applies_to'], true) || in_array($s['slug'], $coupon['applies_to'], true))) {
                if ($coupon['type'] === 'percent') { $d = intdiv($unit * (int)$coupon['value_amount'], 100); $unit -= $d; $disc += $d * $qty; }
                elseif ($fixedLeft > 0 && $p['billing'] === 'one_time') { $d = min($fixedLeft, $unit); $unit -= $d; $fixedLeft -= $d; $disc += $d; }
            }
            $line = ['index' => $i, 'slug' => $s['slug'], 'name' => $p['label'], 'qty' => $qty, 'unit_cents' => $unit, 'setup_cents' => $setup,
                'billing' => $p['billing'], 'opts' => $l['opts'] ?? [], 'package' => $l['package'] ?? null, 'category' => $s['category']];
            $out[] = $line;
            $sum['discount'] += $disc;
            $sum['setup'] += $setup * $qty;
            match ($p['billing']) {
                'monthly' => $sum['recurring_monthly'] += $unit * $qty,
                'yearly' => $sum['recurring_yearly'] += $unit * $qty,
                default => $sum['one_time'] += $unit * $qty,
            };
        }
        $dueToday = $sum['one_time'] + $sum['setup'] + $sum['recurring_monthly'] + $sum['recurring_yearly'];
        $taxRate = Settings::get('tax.enabled') === '1' ? Settings::int('tax.default_rate_bp') : 0; // basis points
        $tax = intdiv($dueToday * $taxRate, 10000);
        return ['lines' => $out, 'coupon' => $coupon ? $coupon['code'] : null, 'coupon_invalid' => $couponCode && !$coupon] + $sum
            + ['subtotal' => $dueToday + $sum['discount'], 'tax' => $tax, 'due_today' => $dueToday + $tax];
    }
}

final class Proposals
{
    /** Build a proposal from requested items. Quote-only items are flagged, never invented. */
    public static function create(?int $customerId, string $title, array $items, array $meta = [], string $by = 'system'): array
    {
        $priced = []; $one = 0; $rec = 0; $needsQuote = false; $timelines = [];
        foreach (array_slice($items, 0, 30) as $it) {
            $s = Catalog::find((string)($it['slug'] ?? ''));
            if (!$s) continue;
            $p = Catalog::price($s, $it['opts'] ?? []);
            if ($s['timeline']) $timelines[] = $s['name'] . ': ' . $s['timeline'];
            if ($p === null) {
                $needsQuote = true;
                $priced[] = ['slug' => $s['slug'], 'name' => $s['name'], 'opts' => $it['opts'] ?? [], 'unit_cents' => null, 'setup_cents' => 0, 'billing' => 'quote', 'from' => Catalog::fromPrice($s)];
                continue;
            }
            $priced[] = ['slug' => $s['slug'], 'name' => $p['label'], 'opts' => $it['opts'] ?? [], 'unit_cents' => $p['unit_cents'], 'setup_cents' => $p['setup_cents'], 'billing' => $p['billing']];
            if ($p['billing'] === 'one_time') $one += $p['unit_cents']; else $rec += $p['unit_cents'];
            $one += $p['setup_cents'];
        }
        if (!$priced) throw new UserError('No valid services were selected for the proposal.');
        $id = Db::insert('proposals', [
            'number' => refno('PRP'), 'version' => 1, 'customer_id' => $customerId, 'lead_id' => $meta['lead_id'] ?? null, 'status' => 'draft',
            'title' => mb_substr($title, 0, 190), 'scope' => $meta['scope'] ?? null, 'items' => jenc($priced), 'one_time_cents' => $one,
            'recurring_cents' => $rec, 'timeline' => mb_substr(implode('; ', $timelines), 0, 120),
            'assumptions' => $meta['assumptions'] ?? 'Pricing is based on the current 818 Digital Agency catalog. Government filing fees are included where stated in the service description.',
            'exclusions' => $meta['exclusions'] ?? 'Legal or accounting advice; third-party fees not listed; work outside the deliverables described.',
            'terms' => 'Subject to the 818 Digital Agency Terms of Service and Refund Policy.', 'needs_quote' => $needsQuote ? 1 : 0,
            'expires_at' => now(86400 * Settings::int('proposal.valid_days')), 'public_token' => rid(20), 'created_by' => $by,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::log('proposal.create', 'proposal', $id, ['by' => $by, 'needs_quote' => $needsQuote]);
        return self::get($id);
    }

    public static function get(int $id): ?array
    {
        $p = Db::one('SELECT * FROM proposals WHERE id = ?', [$id]);
        if ($p) $p['items'] = jdec($p['items']);
        return $p;
    }

    public static function byToken(string $t): ?array
    {
        $p = Db::one('SELECT * FROM proposals WHERE public_token = ?', [$t]);
        if ($p) $p['items'] = jdec($p['items']);
        return $p;
    }

    public static function send(int $id): void
    {
        $p = self::get($id);
        $c = $p['customer_id'] ? Db::one('SELECT * FROM customers WHERE id = ?', [$p['customer_id']]) : null;
        if (!$c) throw new UserError('Proposal has no customer email.');
        Db::update('proposals', ['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()], 'id = ?', [$id]);
        Mailer::send('proposal', $c['email'], ['name' => $c['name'], 'number' => $p['number'], 'title' => $p['title'],
            'one_time' => money((int)$p['one_time_cents']), 'recurring' => $p['recurring_cents'] ? money((int)$p['recurring_cents']) . ' per period' : 'None',
            'expires' => substr((string)$p['expires_at'], 0, 10), 'link' => url('/proposal/' . $p['public_token'])], 'proposal:' . $id . ':v' . $p['version'], 'proposal', $id);
        if ($p['lead_id']) Db::update('leads', ['status' => 'proposal_sent', 'updated_at' => now()], 'id = ?', [$p['lead_id']]);
        Crm::setStatus((int)$p['customer_id'], 'proposal_sent');
        Events::emit('proposal.sent', ['proposal_id' => $id]);
    }

    /** New version: copies the proposal with edited items, supersedes the old one. */
    public static function revise(int $id, array $items, string $by): array
    {
        $old = self::get($id);
        $new = self::create($old['customer_id'] ? (int)$old['customer_id'] : null, $old['title'], $items, ['lead_id' => $old['lead_id'], 'scope' => $old['scope']], $by);
        $ver = (int)Db::val('SELECT MAX(version) FROM proposals WHERE number = ?', [$old['number']]) + 1;
        Db::update('proposals', ['number' => $old['number'], 'version' => $ver], 'id = ?', [$new['id']]);
        Db::update('proposals', ['status' => 'superseded', 'updated_at' => now()], 'id = ?', [$id]);
        return self::get((int)$new['id']);
    }

    public static function accept(array $p): void
    {
        if (in_array($p['status'], ['accepted', 'superseded', 'declined'], true)) throw new UserError('This proposal can no longer be accepted.');
        if ($p['expires_at'] && $p['expires_at'] < now()) { Db::update('proposals', ['status' => 'expired'], 'id = ?', [$p['id']]); throw new UserError('This proposal has expired. Ask us for an updated one.'); }
        Db::update('proposals', ['status' => 'accepted', 'accepted_at' => now(), 'updated_at' => now()], 'id = ?', [$p['id']]);
        Audit::log('proposal.accept', 'proposal', $p['id']);
        Events::emit('proposal.accepted', ['proposal_id' => $p['id']]);
        if ($p['needs_quote']) {
            Approvals::request('custom_quote', 'Price custom items for proposal ' . $p['number'], [
                'what' => 'Customer accepted a proposal containing quote-only items. Set prices and send a revised version.',
                'why' => 'AI is not allowed to invent prices.', 'impact' => 'Customer is waiting to pay.', 'cost' => 'None'], 'proposal', (int)$p['id'], 'sales', 'medium');
            return;
        }
        Cart::clear();
        foreach ($p['items'] as $it) if ($it['unit_cents'] !== null) Cart::add($it['slug'], $it['opts'] ?? []);
        Session::start(); $_SESSION['proposal_id'] = (int)$p['id'];
    }
}

final class Orders
{
    /** Create a pending order from priced cart totals. */
    public static function createFromTotals(array $t, array $customer, ?int $userId, array $meta = []): array
    {
        if (!$t['lines']) throw new UserError('Your cart is empty.');
        return Db::tx(function () use ($t, $customer, $userId, $meta) {
            $num = refno('ORD');
            $oid = Db::insert('orders', [
                'number' => $num, 'customer_id' => $customer['id'], 'user_id' => $userId, 'status' => 'pending',
                'subtotal_cents' => $t['subtotal'], 'discount_cents' => $t['discount'], 'tax_cents' => $t['tax'], 'total_cents' => $t['due_today'],
                'recurring_cents' => $t['recurring_monthly'] + $t['recurring_yearly'], 'currency' => 'CAD', 'coupon_code' => $t['coupon'],
                'province' => $meta['province'] ?? null, 'proposal_id' => $meta['proposal_id'] ?? null, 'meta' => jenc(array_diff_key($meta, ['province' => 1])),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($t['lines'] as $l) {
                Db::insert('order_items', ['order_id' => $oid, 'service_slug' => $l['slug'], 'name' => $l['name'], 'qty' => $l['qty'],
                    'unit_cents' => $l['unit_cents'], 'setup_cents' => $l['setup_cents'], 'billing' => $l['billing'], 'options' => jenc($l['opts'])]);
            }
            Audit::log('order.create', 'order', $num, ['total' => $t['due_today']]);
            return Db::one('SELECT * FROM orders WHERE id = ?', [$oid]);
        });
    }

    public static function items(int $orderId): array
    {
        return array_map(fn($r) => $r + ['opts' => jdec($r['options'])], Db::all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]));
    }

    /**
     * Mark an order paid. Idempotent: a second call for the same order is a no-op.
     * Emits order.paid which the workflow engine turns into projects + onboarding.
     */
    public static function markPaid(int $orderId, string $provider, string $ref, int $amount, ?string $subscriptionRef = null): bool
    {
        return (bool)Db::tx(function () use ($orderId, $provider, $ref, $amount, $subscriptionRef) {
            $o = Db::one('SELECT * FROM orders WHERE id = ?', [$orderId]);
            if (!$o || $o['status'] === 'paid' || $o['status'] === 'processing' || $o['status'] === 'completed') return false;
            if ($amount < (int)$o['total_cents']) {
                Approvals::request('payment_exception', "Underpayment on {$o['number']}", ['what' => 'Received ' . money($amount) . ' but order total is ' . money((int)$o['total_cents']),
                    'why' => 'Amounts must match before work starts.', 'impact' => 'Order held.', 'cost' => money((int)$o['total_cents'] - $amount)], 'order', $orderId, 'finance', 'high');
                Db::update('orders', ['status' => 'failed', 'updated_at' => now()], 'id = ?', [$orderId]);
                return false;
            }
            Db::update('orders', ['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()], 'id = ?', [$orderId]);
            Db::run("UPDATE approvals SET status = 'approved', decided_by = 'system', decided_at = ?, decision_note = 'Resolved automatically: payment received.' WHERE status = 'pending' AND kind = 'payment_exception' AND subject_type = 'order' AND subject_id = ?", [now(), $orderId]);
            if (!Db::val('SELECT 1 FROM payments WHERE provider_ref = ?', [$ref])) {
                Db::insert('payments', ['order_id' => $orderId, 'provider' => $provider, 'provider_ref' => $ref, 'amount_cents' => $amount, 'status' => 'succeeded', 'created_at' => now()]);
            }
            Db::insert('invoices', ['number' => 'INV-' . substr($o['number'], 4), 'order_id' => $orderId, 'customer_id' => $o['customer_id'], 'amount_cents' => $amount, 'status' => 'paid', 'issued_at' => now()]);
            if ($subscriptionRef || (int)$o['recurring_cents'] > 0) {
                Db::insert('subscriptions', ['customer_id' => $o['customer_id'], 'order_id' => $orderId, 'provider_ref' => $subscriptionRef, 'status' => 'active',
                    'amount_cents' => (int)$o['recurring_cents'], 'current_period_end' => now(86400 * 30), 'created_at' => now(), 'updated_at' => now()]);
            }
            Db::run('UPDATE customers SET lifetime_value_cents = lifetime_value_cents + ?, updated_at = ? WHERE id = ?', [$amount, now(), $o['customer_id']]);
            if ($o['coupon_code']) Db::run('UPDATE coupons SET uses = uses + 1 WHERE code = ?', [$o['coupon_code']]);
            $orders = (int)Db::val("SELECT COUNT(*) FROM orders WHERE customer_id = ? AND status IN ('paid','processing','completed')", [$o['customer_id']]);
            Crm::setStatus((int)$o['customer_id'], $orders > 1 ? 'repeat_customer' : 'active_customer');
            Db::run("UPDATE leads SET status = 'converted', next_follow_up_at = NULL, updated_at = ? WHERE customer_id = ? AND status NOT IN ('converted','closed')", [now(), $o['customer_id']]);
            Audit::log('order.paid', 'order', $o['number'], ['provider' => $provider, 'amount' => $amount], 'system', $provider);
            Events::emit('order.paid', ['order_id' => $orderId]);
            return true;
        });
    }
}
