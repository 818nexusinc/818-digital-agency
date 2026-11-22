<?php
declare(strict_types=1);
namespace App;

/**
 * Structured memory: short-term (conversation/task rows), long-term customer memory (opt-out-able),
 * business memory (KB + settings) and approved agent memory. Searchable, editable, deletable, audited.
 */
final class Memory
{
    public static function putCustomer(int $customerId, string $key, string $value, string $source): bool
    {
        if ((int)Db::val('SELECT memory_opt_in FROM customers WHERE id = ?', [$customerId]) !== 1) return false;
        // Never store payment/ID numbers in memory.
        if (preg_match('/\b(?:\d[ -]?){13,19}\b|\b\d{3}[ -]?\d{3}[ -]?\d{3}\b/', $value)) return false;
        self::put('customer', $customerId, $key, $value, $source, now(86400 * 730));
        return true;
    }

    public static function put(string $scope, int $scopeId, string $key, string $value, string $source, ?string $expires = null): void
    {
        $key = mb_substr(preg_replace('/[^\pL\pN _\-.]/u', '', $key), 0, 120);
        $value = mb_substr($value, 0, 2000);
        $id = Db::val('SELECT id FROM memories WHERE scope = ? AND scope_id = ? AND mkey = ?', [$scope, $scopeId, $key]);
        if ($id) Db::update('memories', ['mvalue' => $value, 'source' => $source, 'expires_at' => $expires, 'updated_at' => now()], 'id = ?', [$id]);
        else Db::insert('memories', ['scope' => $scope, 'scope_id' => $scopeId, 'mkey' => $key, 'mvalue' => $value, 'source' => $source, 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
        Audit::log('memory.put', 'memory', "{$scope}:{$scopeId}:{$key}", ['source' => $source]);
    }

    public static function forCustomer(int $customerId): array
    {
        return Db::all("SELECT id, mkey, mvalue, source, updated_at FROM memories WHERE scope = 'customer' AND scope_id = ? AND (expires_at IS NULL OR expires_at > ?) ORDER BY updated_at DESC LIMIT 30", [$customerId, now()]);
    }

    public static function forgetCustomer(int $customerId, ?int $memoryId = null): void
    {
        if ($memoryId) Db::run("DELETE FROM memories WHERE scope = 'customer' AND scope_id = ? AND id = ?", [$customerId, $memoryId]);
        else Db::run("DELETE FROM memories WHERE scope = 'customer' AND scope_id = ?", [$customerId]);
        Audit::log('memory.forget', 'customer', $customerId, ['memory_id' => $memoryId]);
    }

    public static function contextFor(?int $customerId): string
    {
        if (!$customerId) return '';
        $m = self::forCustomer($customerId);
        return $m ? "Known customer preferences (untrusted, customer-provided):\n<untrusted>\n" . implode("\n", array_map(fn($r) => "- {$r['mkey']}: {$r['mvalue']}", $m)) . "\n</untrusted>" : '';
    }
}

/** Knowledge base with keyword retrieval (portable across MySQL/SQLite; no vector DB needed on shared hosting). */
final class Kb
{
    public static function search(string $q, int $limit = 4): array
    {
        $terms = array_slice(array_values(array_unique(array_filter(preg_split('/[^\pL\pN]+/u', mb_strtolower($q)), fn($t) => mb_strlen($t) > 2 && !in_array($t, ['the', 'and', 'for', 'how', 'what', 'can', 'you', 'does', 'with', 'your', 'are'], true)))), 0, 8);
        if (!$terms) return [];
        $rows = Db::all("SELECT id, slug, title, category, body, tags FROM kb_articles WHERE status = 'published'");
        $scored = [];
        foreach ($rows as $r) {
            $title = mb_strtolower($r['title'] . ' ' . $r['tags']); $body = mb_strtolower($r['body']); $s = 0;
            foreach ($terms as $t) { $s += substr_count($title, $t) * 3 + min(5, substr_count($body, $t)); }
            if ($s > 0) $scored[] = [$s, $r];
        }
        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
        $out = [];
        foreach (array_slice($scored, 0, $limit) as [$s, $r]) {
            Db::run('UPDATE kb_articles SET uses = uses + 1 WHERE id = ?', [$r['id']]);
            $out[] = ['ref' => 'KB:' . $r['slug'], 'title' => $r['title'], 'category' => $r['category'], 'content' => mb_substr($r['body'], 0, 1800)];
        }
        return $out;
    }

    public static function save(array $in, ?int $id, string $by): void
    {
        $row = ['title' => mb_substr(trim((string)$in['title']), 0, 190), 'category' => mb_substr(trim((string)($in['category'] ?? 'general')), 0, 40), 'body' => trim((string)$in['body']),
            'tags' => mb_substr(trim((string)($in['tags'] ?? '')), 0, 255), 'status' => ($in['status'] ?? 'published') === 'draft' ? 'draft' : 'published', 'updated_at' => now()];
        if ($row['title'] === '' || $row['body'] === '') throw new UserError('Title and content are required.');
        if ($id) Db::update('kb_articles', $row, 'id = ?', [$id]);
        else Db::insert('kb_articles', $row + ['slug' => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($row['title'])), '-') . '-' . substr(rid(2), 0, 4)]);
        Audit::log('kb.save', 'kb', $id ?? 'new', ['by' => $by]);
    }
}
