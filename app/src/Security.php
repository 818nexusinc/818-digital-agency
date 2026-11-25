<?php
declare(strict_types=1);
namespace App;

/** Encryption at rest for secrets (libsodium secretbox keyed by APP_KEY). */
final class Crypto
{
    private static function key(?string $raw = null): string
    {
        $k = $raw ?? (string)Env::get('APP_KEY', '');
        if (str_starts_with($k, 'base64:')) $k = base64_decode(substr($k, 7), true) ?: '';
        if (strlen($k) < 32) throw new \RuntimeException('APP_KEY missing or too short. Run: php bin/setup.php');
        return substr(hash('sha256', $k, true), 0, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    /** The binary key for a raw APP_KEY value (the live one when null). */
    public static function rawKey(?string $raw = null): string { return self::key($raw); }

    /** Earlier keys kept in every safe folder (see Vault), so data saved before a key change can still be read. */
    private static function oldKeys(): array
    {
        static $cache = null; if ($cache !== null) return $cache;
        $o = []; $dirs = [STORAGE_ROOT . '/keys'];
        try { $dirs = array_unique(array_merge($dirs, Vault::dirs())); } catch (\Throwable) {}
        foreach ($dirs as $d) foreach (array_merge(glob($d . '/old-*.key') ?: [], glob($d . '/app.key') ?: []) as $f) { $v = trim((string)@file_get_contents($f)); if ($v !== '') { try { $o[] = self::key($v); } catch (\Throwable) {} } }
        return $cache = array_values(array_unique($o));
    }

    /** Decrypt with one specific raw key (used to find which key a database was encrypted with). */
    public static function decryptWith(string $blob, string $raw): ?string
    {
        if (!str_starts_with($blob, 'v1:')) return null;
        $b = base64_decode(substr($blob, 3), true); if ($b === false || strlen($b) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        try { $p = sodium_crypto_secretbox_open(substr($b, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($b, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), self::key($raw)); } catch (\Throwable) { return null; }
        return $p === false ? null : $p;
    }

    public static function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'v1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, self::key()));
    }

    public static function decrypt(?string $blob): ?string
    {
        if (!$blob || !str_starts_with($blob, 'v1:')) return null;
        $raw = base64_decode(substr($blob, 3), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return null;
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $box = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        foreach (array_merge([self::key()], self::oldKeys()) as $k) { $p = sodium_crypto_secretbox_open($box, $nonce, $k); if ($p !== false) return $p; }
        return null;
    }
}

/** Audit trail for every consequential action (human or AI). */
final class Audit
{
    public static function log(string $action, ?string $subjectType = null, string|int|null $subjectId = null, array $detail = [], ?string $actorType = null, ?string $actorId = null): void
    {
        $u = Auth::user();
        Db::insert('audit_log', [
            'actor_type' => $actorType ?? ($u ? 'user' : (PHP_SAPI === 'cli' ? 'system' : 'guest')),
            'actor_id' => $actorId ?? ($u ? (string)$u['id'] : null),
            'action' => $action, 'subject_type' => $subjectType, 'subject_id' => $subjectId !== null ? (string)$subjectId : null,
            'detail' => $detail ? jenc(Log::redact($detail)) : null, 'ip' => client_ip(),
            'correlation_id' => Log::correlationId(), 'created_at' => now(),
        ]);
    }

    public static function security(string $kind, string $detail = ''): void
    {
        Db::insert('security_events', ['kind' => $kind, 'detail' => substr($detail, 0, 500), 'ip' => client_ip(), 'created_at' => now()]);
        Log::warn('security.' . $kind, ['detail' => $detail]);
    }
}

/** Fixed-window rate limiter persisted in the DB (works across PHP workers). */
final class RateLimit
{
    public static function hit(string $key, int $max, int $windowSeconds): bool
    {
        $now = time();
        $k = substr($key, 0, 190);
        return (bool)Db::tx(function () use ($k, $max, $windowSeconds, $now) {
            $row = Db::one('SELECT id, hits, window_start FROM rate_limits WHERE rkey = ?', [$k]);
            if (!$row) {
                try { Db::insert('rate_limits', ['rkey' => $k, 'hits' => 1, 'window_start' => $now]); } catch (\PDOException) { return true; }
                return true;
            }
            if ($now - (int)$row['window_start'] >= $windowSeconds) {
                Db::update('rate_limits', ['hits' => 1, 'window_start' => $now], 'id = ?', [$row['id']]);
                return true;
            }
            if ((int)$row['hits'] >= $max) return false;
            Db::run('UPDATE rate_limits SET hits = hits + 1 WHERE id = ?', [$row['id']]);
            return true;
        });
    }
}

final class Csrf
{
    public static function token(): string
    {
        Session::start();
        return $_SESSION['_csrf'] ??= rid(24);
    }

    public static function field(): string { return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">'; }

    public static function verify(?string $provided): bool
    {
        Session::start();
        return is_string($provided) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $provided);
    }
}

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        if (PHP_SAPI === 'cli') { if (!isset($_SESSION)) $_SESSION = []; return; }
        if ((Env::get('SESSION_DRIVER') ?: (ON_VERCEL ? 'db' : 'file')) === 'db') session_set_save_handler(new DbSessionHandler(), true);
        else { $dir = STORAGE_ROOT . '/sessions'; if (!is_dir($dir)) @mkdir($dir, 0700, true); session_save_path($dir); }
        session_name('nx818sid');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => Env::bool('SESSION_SECURE') || (($_SERVER['HTTPS'] ?? '') === 'on'),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '28800');
        session_start();
        // Idle timeout: 8h for customers, 2h for staff.
        $idle = in_array($_SESSION['role'] ?? '', ['owner', 'admin', 'staff'], true) ? 7200 : 28800;
        if (isset($_SESSION['_seen']) && time() - $_SESSION['_seen'] > $idle) { $_SESSION = []; session_regenerate_id(true); }
        $_SESSION['_seen'] = time();
    }

    public static function regenerate(): void { if (session_status() === PHP_SESSION_ACTIVE) session_regenerate_id(true); }

    public static function flash(string $type, string $msg): void { self::start(); $_SESSION['_flash'][] = [$type, $msg]; }

    public static function takeFlash(): array { self::start(); $f = $_SESSION['_flash'] ?? []; unset($_SESSION['_flash']); return $f; }
}

/** Role-based access control. Permissions are stored in role_permissions and editable by the owner. */
final class Rbac
{
    public const ROLES = ['owner', 'admin', 'staff', 'customer'];

    public const DEFAULTS = [
        'admin' => ['dashboard.view', 'crm.read', 'crm.write', 'orders.read', 'orders.write', 'projects.read', 'projects.write', 'tickets.read', 'tickets.write',
            'catalog.read', 'catalog.write', 'content.read', 'content.write', 'agents.read', 'agents.write', 'approvals.decide', 'finance.read',
            'integrations.read', 'integrations.write', 'settings.read', 'settings.write', 'audit.read', 'kb.write', 'workflows.write', 'legacy.access'],
        'staff' => ['dashboard.view', 'crm.read', 'crm.write', 'orders.read', 'projects.read', 'projects.write', 'tickets.read', 'tickets.write', 'catalog.read', 'content.read', 'content.write', 'agents.read', 'kb.write'],
        'customer' => ['portal.view'],
    ];

    private static array $cache = [];

    public static function can(?array $user, string $perm): bool
    {
        if (!$user) return false;
        if ($user['role'] === 'owner') return true; // the founder can do everything, still audited
        $role = $user['role'];
        if (!isset(self::$cache[$role])) {
            self::$cache[$role] = array_column(Db::all('SELECT permission FROM role_permissions WHERE role = ?', [$role]), 'permission');
        }
        return in_array($perm, self::$cache[$role], true);
    }

    public static function flush(): void { self::$cache = []; }

    public static function seed(): void
    {
        foreach (self::DEFAULTS as $role => $perms) foreach ($perms as $p) {
            if (!Db::val('SELECT 1 FROM role_permissions WHERE role = ? AND permission = ?', [$role, $p])) Db::insert('role_permissions', ['role' => $role, 'permission' => $p]);
        }
    }
}

/** RFC 6238 TOTP for admin MFA. */
final class Totp
{
    public static function secret(): string
    {
        $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $s = '';
        for ($i = 0; $i < 32; $i++) $s .= $a[random_int(0, 31)];
        return $s;
    }

    private static function b32(string $s): string
    {
        $a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits = ''; $out = '';
        foreach (str_split(strtoupper($s)) as $c) { $p = strpos($a, $c); if ($p === false) continue; $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT); }
        foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $out .= chr(bindec($b));
        return $out;
    }

    public static function code(string $secret, ?int $t = null): string
    {
        $counter = intdiv($t ?? time(), 30);
        $h = hash_hmac('sha1', pack('N*', 0, $counter), self::b32($secret), true);
        $o = ord($h[19]) & 0xf;
        $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
        return str_pad((string)($n % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        // ±60 seconds: phones and servers drift a little.
        foreach ([0, -1, 1, -2, 2] as $w) if (hash_equals(self::code($secret, time() + $w * 30), $code)) return true;
        return false;
    }

    public static function uri(string $secret, string $email): string
    {
        return 'otpauth://totp/' . rawurlencode('818 Digital Agency:' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode('818 Digital Agency');
    }
}

/** Database-backed sessions so logins survive across serverless instances. */
final class DbSessionHandler implements \SessionHandlerInterface
{
    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }
    public function read(string $id): string|false
    {
        try { return (string)(Db::val('SELECT data FROM sessions WHERE sid = ? AND updated_at > ?', [$id, time() - 28800]) ?? ''); }
        catch (\PDOException) { return ''; } // before the first migration (fresh install)
    }
    public function write(string $id, string $data): bool
    {
        try { $this->save($id, $data); } catch (\PDOException) {}
        return true;
    }
    private function save(string $id, string $data): void
    {
        if (Db::val('SELECT 1 FROM sessions WHERE sid = ?', [$id])) Db::update('sessions', ['data' => $data, 'updated_at' => time()], 'sid = ?', [$id]);
        else Db::insert('sessions', ['sid' => $id, 'data' => $data, 'updated_at' => time()]);
    }
    public function destroy(string $id): bool { try { Db::run('DELETE FROM sessions WHERE sid = ?', [$id]); } catch (\PDOException) {} return true; }
    public function gc(int $max): int|false { return Db::run('DELETE FROM sessions WHERE updated_at < ?', [time() - $max])->rowCount(); }
}
