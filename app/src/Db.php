<?php
declare(strict_types=1);
namespace App;

use PDO;

/** PDO wrapper: MySQL in production, SQLite for local/dev/tests. All queries are parameterised. */
final class Db
{
    private static ?PDO $pdo = null;
    public static string $driver = 'mysql';

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        $dsn = Env::get('DB_DSN');
        if (!$dsn) {
            $host = Env::get('DB_HOST', 'localhost');
            $name = Env::get('DB_NAME', '');
            $port = (int)Env::get('DB_PORT', '3306') ?: 3306;
            $dsn = $name !== '' ? "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4" : 'sqlite:' . STORAGE_ROOT . '/app.sqlite';
        }
        self::connect($dsn, Env::get('DB_USER'), Env::get('DB_PASS'));
        if (ON_VERCEL && self::$driver === 'sqlite') self::previewInstall();
        else self::upgradeIfNeeded();
        return self::$pdo;
    }

    /** Apply new migration files after a deploy, so a new release never runs against an older schema. Installed databases only. */
    private static function upgradeIfNeeded(): void
    {
        try {
            $files = count(glob(APP_ROOT . '/migrations/*.sql') ?: []);
            $done = (int)self::$pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            if ($done > 0 && $done < $files) { $applied = self::migrate(); if ($applied) Log::write('info', 'db.upgraded', ['migrations' => $applied]); }
        } catch (\Throwable $e) {
            // Not installed yet (no migrations table) or a parallel request already upgraded: carry on.
        }
    }

    /**
     * Preview mode (Vercel with no database configured): build a throwaway SQLite database in /tmp on the
     * first request of each instance, seeded from the real catalog. The owner account exists only when
     * OWNER_EMAIL and PREVIEW_OWNER_PASSWORD are set in Vercel, so no shared default password is ever shipped.
     * Data is not durable here. Connect MySQL (docs/VERCEL.md) for anything real.
     */
    private static function previewInstall(): void
    {
        $has = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetchColumn();
        if ($has && self::$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn()) return;
        $lock = fopen(STORAGE_ROOT . '/preview.lock', 'c'); if ($lock) flock($lock, LOCK_EX);
        try {
            $has = self::$pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='services'")->fetchColumn();
            if (!$has || !self::$pdo->query('SELECT COUNT(*) FROM services')->fetchColumn()) {
                self::migrate();
                Seed::run();
                $email = strtolower(trim((string)Env::get('OWNER_EMAIL', '')));
                $pw = (string)Env::get('PREVIEW_OWNER_PASSWORD', '');
                if ($email !== '' && strlen($pw) >= 12 && !self::val("SELECT 1 FROM users WHERE role = 'owner'")) {
                    self::insert('users', ['email' => $email, 'name' => 'Owner', 'password_hash' => Auth::hash($pw), 'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        } finally { if ($lock) { flock($lock, LOCK_UN); fclose($lock); } }
    }

    /** Encryption of the database connection: 'verified', 'encrypted', 'none' or '' (not MySQL). */
    public static string $tls = '';

    public static function connect(string $dsn, ?string $user = null, ?string $pass = null): void
    {
        $base = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        if (!str_starts_with($dsn, 'mysql:')) { self::$pdo = new PDO($dsn, $user, $pass, $base); }
        else {
            // DB_SSL: "require" (encrypted or fail), "off", or unset = encrypt when the server supports it. DB_SSL_VERIFY=true also checks the certificate.
            $mode = strtolower((string)Env::get('DB_SSL', 'auto'));
            $base[PDO::ATTR_TIMEOUT] = 8;
            $ca = null; foreach (['/etc/ssl/certs/ca-certificates.crt', '/etc/pki/tls/certs/ca-bundle.crt', '/etc/ssl/cert.pem'] as $f) if (is_readable($f)) { $ca = $f; break; }
            $verify = Env::bool('DB_SSL_VERIFY');
            $tlsOpts = $base + [PDO::MYSQL_ATTR_SSL_CA => $ca ?? '', PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $verify];
            if ($mode === 'off') { self::$pdo = new PDO($dsn, $user, $pass, $base); }
            else {
                try { self::$pdo = new PDO($dsn, $user, $pass, $tlsOpts); }
                catch (\PDOException $e) {
                    if ($mode === 'require') throw $e;
                    self::$pdo = new PDO($dsn, $user, $pass, $base);
                }
            }
            try { $c = self::$pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(); self::$tls = !empty($c['Value']) ? ($verify ? 'verified' : 'encrypted') : 'none'; } catch (\Throwable) { self::$tls = 'none'; }
        }
        self::$driver = self::$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (self::$driver === 'sqlite') {
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
    }

    public static function reset(): void { self::$pdo = null; }

    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute(array_values($params) === $params ? $params : $params);
        return $st;
    }

    public static function all(string $sql, array $p = []): array { return self::run($sql, $p)->fetchAll(); }
    public static function one(string $sql, array $p = []): ?array { $r = self::run($sql, $p)->fetch(); return $r === false ? null : $r; }
    public static function val(string $sql, array $p = []): mixed { $r = self::run($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

    /** Insert a row; returns the new id. Column names are whitelisted by regex. */
    public static function insert(string $table, array $row): int
    {
        self::ident($table);
        $cols = array_keys($row);
        array_map([self::class, 'ident'], $cols);
        $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($row));
        return (int)self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $row, string $where, array $params = []): int
    {
        self::ident($table);
        $set = [];
        foreach (array_keys($row) as $c) { self::ident($c); $set[] = "{$c} = ?"; }
        return self::run("UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}", [...array_values($row), ...$params])->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) return $fn();
        $pdo->beginTransaction();
        try { $r = $fn(); $pdo->commit(); return $r; }
        catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
    }

    private static function ident(string $s): void
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/', $s)) throw new \InvalidArgumentException('Bad identifier');
    }

    /** Apply pending migrations from app/migrations (idempotent). */
    public static function migrate(): array
    {
        $pdo = self::pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (version VARCHAR(60) PRIMARY KEY, applied_at VARCHAR(19) NOT NULL)');
        $done = array_column(self::all('SELECT version FROM migrations'), 'version');
        $applied = [];
        $files = glob(APP_ROOT . '/migrations/*.sql');
        sort($files);
        foreach ($files as $f) {
            $v = basename($f, '.sql');
            if (in_array($v, $done, true)) continue;
            $sql = self::expand(file_get_contents($f));
            foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $stmt) {
                if ($stmt === '' || str_starts_with($stmt, '--') && !str_contains($stmt, "\n")) continue;
                $pdo->exec($stmt);
            }
            self::run('INSERT INTO migrations (version, applied_at) VALUES (?, ?)', [$v, now()]);
            $applied[] = $v;
        }
        return $applied;
    }

    public static function expand(string $sql): string
    {
        $mysql = self::$driver === 'mysql';
        return strtr($sql, [
            '{{PK}}' => $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT',
            '{{FK}}' => $mysql ? 'BIGINT UNSIGNED' : 'INTEGER',
            '{{TEXT}}' => $mysql ? 'MEDIUMTEXT' : 'TEXT',
            '{{ENGINE}}' => $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '',
        ]);
    }

    /** Driver-neutral "date >= N days ago" helper value. */
    public static function since(int $days): string { return now(-86400 * $days); }
}
