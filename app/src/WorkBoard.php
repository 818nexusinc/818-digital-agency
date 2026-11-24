<?php
declare(strict_types=1);
namespace App;

/** Work, arranged by service: every order, project, filing, lead and unfinished form for a service group or one service. */
final class WorkBoard
{
    public const TYPES = ['order' => 'Orders', 'project' => 'Projects', 'filing' => 'Filings', 'lead' => 'Leads', 'draft' => 'Unfinished forms'];
    private const CLOSED = ['completed', 'cancelled', 'refunded', 'converted', 'closed', 'lost', 'rejected', 'abandoned', 'done'];

    /** Open work per service group, for the menu. */
    public static function openByCategory(): array
    {
        $out = [];
        try {
            foreach (Db::all("SELECT s.category c, COUNT(*) n FROM projects p JOIN services s ON s.slug = p.service_slug WHERE p.status NOT IN ('completed','cancelled') GROUP BY s.category") as $r) $out[$r['c']] = (int)$r['n'];
            foreach (Db::all("SELECT s.category c, COUNT(DISTINCT o.id) n FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN services s ON s.slug = oi.service_slug WHERE o.status IN ('pending','awaiting_payment') GROUP BY s.category") as $r) $out[$r['c']] = ($out[$r['c']] ?? 0) + (int)$r['n'];
        } catch (\Throwable) {}
        return $out;
    }

    /** Services in a group with their open-work counts. */
    public static function services(string $cat): array
    {
        $svc = Db::all("SELECT slug, name FROM services WHERE category = ? AND status = 'active' ORDER BY sort, id", [$cat]);
        $open = array_column(Db::all("SELECT service_slug, COUNT(*) n FROM projects WHERE status NOT IN ('completed','cancelled') GROUP BY service_slug"), 'n', 'service_slug');
        $all = array_column(Db::all('SELECT service_slug, COUNT(DISTINCT order_id) n FROM order_items GROUP BY service_slug'), 'n', 'service_slug');
        foreach ($svc as &$s) { $s['open'] = (int)($open[$s['slug']] ?? 0); $s['orders'] = (int)($all[$s['slug']] ?? 0); }
        return $svc;
    }

    /**
     * Requests for a set of services. Each: [type, ref, title, customer, service, status, cents, at, href, open]
     */
    public static function requests(array $slugs): array
    {
        if (!$slugs) return [];
        $in = implode(',', array_fill(0, count($slugs), '?'));
        $names = array_column(Db::all("SELECT slug, name FROM services WHERE slug IN ({$in})", $slugs), 'name', 'slug');
        $who = fn($r) => trim(($r['cname'] ?? '') ?: ($r['email'] ?? '') ?: 'Visitor') . (!empty($r['business_name']) ? ' · ' . $r['business_name'] : '');
        $out = [];
        foreach (Db::all("SELECT o.number, o.status, o.created_at, oi.unit_cents * oi.qty + oi.setup_cents v, oi.service_slug, oi.name iname, c.name cname, c.email, c.business_name
            FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN customers c ON c.id = o.customer_id WHERE oi.service_slug IN ({$in}) ORDER BY o.id DESC LIMIT 300", $slugs) as $r)
            $out[] = ['order', $r['number'], $r['iname'], $who($r), $r['service_slug'], $r['status'], (int)$r['v'], $r['created_at'], '/admin/orders/' . $r['number']];
        foreach (Db::all("SELECT p.number, p.title, p.status, p.progress, p.deadline, p.created_at, p.service_slug, c.name cname, c.email, c.business_name
            FROM projects p JOIN customers c ON c.id = p.customer_id WHERE p.service_slug IN ({$in}) ORDER BY p.id DESC LIMIT 300", $slugs) as $r)
            $out[] = ['project', $r['number'], $r['title'] . ($r['progress'] ? ' · ' . (int)$r['progress'] . '%' : ''), $who($r), $r['service_slug'], $r['status'], null, $r['created_at'], '/admin/projects/' . $r['number']];
        foreach (Db::all("SELECT f.number, f.filing_type, f.jurisdiction, f.status, f.created_at, p.service_slug, c.name cname, c.email, c.business_name
            FROM filings f JOIN projects p ON p.id = f.project_id JOIN customers c ON c.id = f.customer_id WHERE p.service_slug IN ({$in}) ORDER BY f.id DESC LIMIT 300", $slugs) as $r)
            $out[] = ['filing', $r['number'], label($r['filing_type']) . ' · ' . $r['jurisdiction'], $who($r), $r['service_slug'], $r['status'], null, $r['created_at'], '/admin/filings/' . $r['number']];
        $like = []; $lp = [];
        foreach ($slugs as $s) { $like[] = 'l.services_interest LIKE ?'; $lp[] = '%"' . $s . '"%'; }
        foreach (Db::all('SELECT l.customer_id, l.status, l.summary, l.services_interest, l.estimated_value_cents v, l.created_at, c.name cname, c.email, c.business_name FROM leads l JOIN customers c ON c.id = l.customer_id WHERE ' . implode(' OR ', $like) . ' ORDER BY l.id DESC LIMIT 300', $lp) as $r) {
            $svc = array_values(array_intersect((array)jdec($r['services_interest']), $slugs))[0] ?? $slugs[0];
            $out[] = ['lead', 'View lead', str_limit((string)$r['summary'], 90), $who($r), $svc, $r['status'], (int)$r['v'] ?: null, $r['created_at'], '/admin/customers/' . $r['customer_id']];
        }
        foreach (Db::all("SELECT form, name, email, step, steps, status, updated_at FROM form_drafts WHERE form IN ({$in}) ORDER BY updated_at DESC LIMIT 300", $slugs) as $r)
            $out[] = ['draft', 'View form', 'Stopped at step ' . ((int)$r['step'] + 1) . ' of ' . max(1, (int)$r['steps']), ($r['name'] ?: $r['email'] ?: 'No email left'), $r['form'], $r['status'], null, $r['updated_at'], '/admin/drafts'];
        foreach ($out as &$o) { $o[4] = $names[$o[4]] ?? label($o[4]); $o[9] = !in_array($o[5], self::CLOSED, true); }
        unset($o);
        usort($out, fn($a, $b) => [$b[9], (string)$b[7]] <=> [$a[9], (string)$a[7]]);
        return $out;
    }
}
