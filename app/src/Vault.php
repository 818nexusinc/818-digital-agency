<?php
declare(strict_types=1);
namespace App;

/**
 * Keeps the site's encryption key, its settings file and configuration backups safe from anything that happens to the
 * website folder (a release extracted over it, a folder deleted by mistake, a host "reset").
 *
 *  1. Copies everywhere: the key and .env are kept in storage/keys AND in a private folder outside the web root
 *     (on 20i: the account home, next to public_html). Every request brings missing copies back.
 *  2. Fingerprint: the database remembers which key encrypted its secrets. If the live key is different, the right one
 *     is found among the copies and put back automatically. If none matches, the owner is asked for the recovery kit
 *     instead of the site quietly treating every integration as empty.
 *  3. Recovery kit: a code the owner keeps. It seals the key and settings file (Argon2id) inside the database, so even
 *     a brand-new server can be put back exactly as it was.
 *  4. Configuration snapshots: settings, integrations, site edits, logos, pictures, agents and pricing, encrypted,
 *     daily and before every update, stored in every safe folder. Restore merges them back in one click.
 */
final class Vault
{
    /** Tables (and their unique key) that hold configuration rather than business records. */
    public const CONFIG = ['settings' => ['skey'], 'integrations' => ['slug'], 'site_text' => ['page', 'skey', 'lang'], 'site_assets' => ['akey'], 'site_images' => ['skey'],
        'agents' => ['slug'], 'price_costs' => ['service_slug', 'variant'], 'workflows' => ['slug'], 'passkeys' => ['credential_id']];
    private const SKIP_SETTINGS = '/^(mfa\.paused\.|sim\.report\.|system\.cron_last_run|auto\.[a-z_]+\.(last|sig)|recovery\.kit)/';

    /** Folders that hold copies, most private first. */
    public static function dirs(): array
    {
        $out = [];
        if ($p = Env::get('PRIVATE_DIR')) $out[] = rtrim((string)$p, '/');
        $web = basename(APP_ROOT) === '_app' ? dirname(APP_ROOT) : dirname(APP_ROOT) . '/public_html';
        $home = dirname($web);
        if ($home && $home !== '/' && is_dir($home) && is_writable($home)) $out[] = $home . '/818-private';
        $out[] = STORAGE_ROOT . '/keys';
        $ok = [];
        foreach (array_unique($out) as $d) {
            if (!is_dir($d)) @mkdir($d, 0700, true);
            if (is_dir($d) && is_writable($d)) {
                if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
                $ok[] = $d;
            }
        }
        return $ok;
    }

    private static function put(string $f, string $data): void { if (!is_file($f) || (string)@file_get_contents($f) !== $data) { @file_put_contents($f, $data, LOCK_EX); @chmod($f, 0600); } }

    /**
     * Run on every request before anything reads the key: bring back .env and the key from any copy, and write the
     * live ones to every safe folder. Never throws.
     */
    public static function boot(): void
    {
        if (ON_VERCEL) return;
        try {
            $dirs = self::dirs(); if (!$dirs) return;
            $envFile = APP_ROOT . '/.env';
            $env = is_file($envFile) ? (string)@file_get_contents($envFile) : '';
            // Restore the settings file from the newest good copy if it is missing or not finished.
            if (!str_contains($env, 'APP_INSTALLED=1')) {
                $best = null; $bestT = 0;
                foreach ($dirs as $d) { $f = $d . '/env.backup'; if (is_file($f) && str_contains((string)@file_get_contents($f), 'APP_INSTALLED=1') && filemtime($f) > $bestT) { $best = $f; $bestT = filemtime($f); } }
                if ($best) { $env = (string)file_get_contents($best); if (@file_put_contents($envFile, $env) !== false) @chmod($envFile, 0600); Env::load($best); }
            }
            $live = (string)Env::get('APP_KEY', '');
            if ($live === '') foreach ($dirs as $d) { $k = is_file($d . '/app.key') ? trim((string)@file_get_contents($d . '/app.key')) : ''; if ($k !== '') { $live = $k; Env::set('APP_KEY', $k); break; } }
            if ($live === '') return;
            foreach ($dirs as $d) {
                $kf = $d . '/app.key'; $cur = is_file($kf) ? trim((string)@file_get_contents($kf)) : '';
                if ($cur !== '' && $cur !== $live) self::put($d . '/old-' . substr(sha1($cur), 0, 10) . '.key', $cur);
                self::put($kf, $live);
                if (str_contains($env, 'APP_INSTALLED=1')) self::put($d . '/env.backup', $env);
            }
        } catch (\Throwable) {}
    }

    /** Every key we know of: the live one first, then copies and earlier keys from all safe folders. */
    public static function candidateKeys(): array
    {
        $k = [(string)Env::get('APP_KEY', '')];
        foreach (self::dirs() as $d) { foreach (array_merge(glob($d . '/app.key') ?: [], glob($d . '/old-*.key') ?: []) as $f) $k[] = trim((string)@file_get_contents($f)); }
        return array_values(array_unique(array_filter($k)));
    }

    public static function fingerprint(?string $raw = null): string { return substr(hash('sha256', 'nx818-fp|' . Crypto::rawKey($raw)), 0, 24); }

    /** Make the live key the one in use everywhere (the .env line and every copy). */
    private static function adopt(string $key): void
    {
        Env::set('APP_KEY', $key);
        $envFile = APP_ROOT . '/.env';
        if (is_file($envFile)) {
            $e = (string)file_get_contents($envFile);
            $e = preg_match('/^APP_KEY=.*$/m', $e) ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $e) : "APP_KEY={$key}\n" . $e;
            @file_put_contents($envFile, $e, LOCK_EX);
        }
        self::boot();
    }

    /**
     * Compare the live key with the one the database was encrypted with. 'ok' | 'healed' | 'mismatch' | 'new'.
     * When healed, every saved integration and two-step secret is readable again without anyone doing anything.
     */
    public static function verify(): string
    {
        static $done = null; if ($done !== null) return $done;
        try {
            $want = (string)(Db::val('SELECT value FROM settings WHERE skey = ?', ['crypto.key_fp']) ?? '');
            $have = self::fingerprint();
            if ($want === '') {
                // First run with this feature. Only record the key if it actually reads what is stored (or nothing is stored yet).
                $sample = Db::val("SELECT secrets_enc FROM integrations WHERE secrets_enc IS NOT NULL AND secrets_enc LIKE 'v1:%' LIMIT 1");
                if (!$sample || Crypto::decryptWith((string)$sample, (string)Env::get('APP_KEY')) !== null) { self::setFp($have); return $done = 'new'; }
                foreach (self::candidateKeys() as $k) if (Crypto::decryptWith((string)$sample, $k) !== null) { self::adopt($k); self::setFp(self::fingerprint($k)); Log::write('warning', 'vault.key_healed', []); return $done = 'healed'; }
                return $done = 'mismatch';
            }
            if (hash_equals($want, $have)) return $done = 'ok';
            foreach (self::candidateKeys() as $k) if (hash_equals($want, self::fingerprint($k))) { self::adopt($k); Log::write('warning', 'vault.key_healed', []); Audit::security('vault.key_restored', 'from safe copy'); return $done = 'healed'; }
            return $done = 'mismatch';
        } catch (\Throwable) { return $done = 'ok'; }
    }

    private static function setFp(string $fp): void
    {
        if (Db::val('SELECT 1 FROM settings WHERE skey = ?', ['crypto.key_fp'])) Db::update('settings', ['value' => $fp, 'updated_at' => now()], 'skey = ?', ['crypto.key_fp']);
        else Db::insert('settings', ['skey' => 'crypto.key_fp', 'value' => $fp, 'updated_at' => now()]);
    }

    /* ── Recovery kit ─────────────────────────────────────────────────────────── */

    private static function derive(string $code, string $salt): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
        return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $code, $salt, SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    /** Create (or replace) the recovery kit. Returns the code — shown once, never stored. */
    public static function createKit(string $by): string
    {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $raw = '';
        for ($i = 0; $i < 25; $i++) $raw .= $alpha[random_int(0, 31)];
        $code = implode('-', str_split($raw, 5));
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES); $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $k = self::derive($code, $salt);
        $env = is_file(APP_ROOT . '/.env') ? (string)file_get_contents(APP_ROOT . '/.env') : '';
        $ct = sodium_crypto_secretbox(jenc(['app_key' => (string)Env::get('APP_KEY'), 'env' => $env, 'at' => now()]), $nonce, $k);
        sodium_memzero($k);
        $kit = jenc(['v' => 1, 'salt' => base64_encode($salt), 'nonce' => base64_encode($nonce), 'ct' => base64_encode($ct), 'at' => now(), 'by' => $by, 'fp' => self::fingerprint()]);
        if (Db::val('SELECT 1 FROM settings WHERE skey = ?', ['recovery.kit'])) Db::update('settings', ['value' => $kit, 'updated_at' => now()], 'skey = ?', ['recovery.kit']);
        else Db::insert('settings', ['skey' => 'recovery.kit', 'value' => $kit, 'updated_at' => now()]);
        foreach (self::dirs() as $d) self::put($d . '/recovery-kit.json', $kit);
        Settings::flush();
        Audit::security('vault.kit_created', $by);
        return $code;
    }

    public static function kitInfo(): ?array { $v = Settings::get('recovery.kit'); $d = $v ? jdec($v, null) : null; return is_array($d) ? $d : null; }

    /** Open a kit (from the database or a kit file) with its code. */
    public static function openKit(string $code, ?array $kit = null): array
    {
        $kit ??= self::kitInfo();
        if (!$kit) throw new UserError('No recovery kit has been made yet.');
        if (!RateLimit::hit('vault-kit:' . client_ip(), 10, 3600)) throw new UserError('Too many tries. Wait an hour.');
        $k = self::derive($code, base64_decode($kit['salt']));
        $p = sodium_crypto_secretbox_open(base64_decode($kit['ct']), base64_decode($kit['nonce']), $k);
        sodium_memzero($k);
        if ($p === false) throw new UserError('That recovery code does not match this kit.');
        return jdec($p);
    }

    /** Owner typed the recovery code after a key mismatch: put the original key back. */
    public static function restoreWithKit(string $code, string $by): void
    {
        $d = self::openKit($code);
        if (empty($d['app_key'])) throw new UserError('The kit has no key in it.');
        self::adopt((string)$d['app_key']);
        self::setFp(self::fingerprint((string)$d['app_key']));
        Audit::security('vault.key_restored', 'recovery kit by ' . $by);
    }

    /* ── Configuration snapshots ──────────────────────────────────────────────── */

    private static function tableRows(string $t): array
    {
        try { $rows = Db::all('SELECT * FROM ' . $t); } catch (\Throwable) { return []; }
        foreach ($rows as &$r) unset($r['id']);
        if ($t === 'settings') $rows = array_values(array_filter($rows, fn($r) => !preg_match(self::SKIP_SETTINGS, (string)$r['skey'])));
        return $rows;
    }

    /** Write an encrypted snapshot to every safe folder. Returns the file name. */
    public static function snapshot(string $reason, string $by = 'system'): string
    {
        $data = ['v' => 1, 'at' => now(), 'reason' => $reason, 'by' => $by, 'version' => APP_VERSION, 'fp' => self::fingerprint(), 'tables' => []];
        foreach (self::CONFIG as $t => $_) $data['tables'][$t] = self::tableRows($t);
        $json = jenc($data);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $blob = 'NX818SNAP1:' . base64_encode($nonce . sodium_crypto_secretbox(gzencode($json, 6), $nonce, Crypto::rawKey()));
        $name = 'config-' . gmdate('Ymd-His') . '-' . preg_replace('/[^a-z0-9]+/', '-', strtolower($reason)) . '.snap';
        foreach (self::dirs() as $d) {
            $sd = $d . '/snapshots'; if (!is_dir($sd)) @mkdir($sd, 0700, true);
            self::put($sd . '/' . $name, $blob);
            $all = glob($sd . '/config-*.snap') ?: []; rsort($all);
            foreach (array_slice($all, 30) as $old) @unlink($old);
        }
        Settings::set('vault.last_snapshot', jenc(['at' => now(), 'reason' => $reason, 'name' => $name, 'rows' => array_sum(array_map('count', $data['tables']))]));
        return $name;
    }

    /** Snapshots found in any safe folder, newest first: [name, at, reason, size, path]. */
    public static function snapshots(): array
    {
        $seen = [];
        foreach (self::dirs() as $d) foreach (glob($d . '/snapshots/config-*.snap') ?: [] as $f) {
            $n = basename($f); if (isset($seen[$n])) continue;
            preg_match('/^config-(\d{8})-(\d{6})-(.*)\.snap$/', $n, $m);
            $seen[$n] = ['name' => $n, 'at' => $m ? substr($m[1], 0, 4) . '-' . substr($m[1], 4, 2) . '-' . substr($m[1], 6, 2) . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) : '', 'reason' => $m ? str_replace('-', ' ', $m[3]) : '', 'size' => filesize($f), 'path' => $f, 'outside' => !str_starts_with($f, STORAGE_ROOT)];
        }
        krsort($seen);
        return array_values($seen);
    }

    private static function readSnapshot(string $name): array
    {
        foreach (self::snapshots() as $s) if ($s['name'] === basename($name)) {
            $blob = (string)file_get_contents($s['path']);
            if (!str_starts_with($blob, 'NX818SNAP1:')) break;
            $raw = base64_decode(substr($blob, 11), true) ?: '';
            $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $box = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            foreach (self::candidateKeys() as $k) { $p = sodium_crypto_secretbox_open($box, $nonce, Crypto::rawKey($k)); if ($p !== false) return jdec((string)gzdecode($p)); }
            throw new UserError('This backup was made with an encryption key this server does not have. Restore the key with your recovery kit first.');
        }
        throw new UserError('Backup not found.');
    }

    /** What a snapshot contains, for the confirmation screen. */
    public static function describe(string $name): array
    {
        $d = self::readSnapshot($name);
        return ['at' => $d['at'] ?? '', 'version' => $d['version'] ?? '', 'reason' => $d['reason'] ?? '', 'counts' => array_map('count', $d['tables'] ?? [])];
    }

    /**
     * Put configuration back from a snapshot. Rows are matched on their natural key: existing ones are updated to the
     * saved values, missing ones are added; nothing that is not in the snapshot is deleted. A snapshot of the current
     * state is taken first, so a restore can itself be undone.
     */
    public static function restore(string $name, string $by): array
    {
        $d = self::readSnapshot($name);
        self::snapshot('before restore', $by);
        $n = [];
        $pdo = Db::pdo(); $pdo->beginTransaction();
        try {
            foreach (self::CONFIG as $t => $keys) {
                $rows = $d['tables'][$t] ?? []; $n[$t] = 0;
                if (!$rows) continue;
                try { $cols = array_keys(Db::one('SELECT * FROM ' . $t . ' LIMIT 1') ?: $rows[0]); } catch (\Throwable) { continue; }
                foreach ($rows as $r) {
                    $r = array_intersect_key($r, array_flip(array_merge($cols, array_keys($rows[0]))));
                    if ($t === 'settings' && preg_match(self::SKIP_SETTINGS, (string)($r['skey'] ?? ''))) continue;
                    if ($t === 'settings' && ($r['skey'] ?? '') === 'crypto.key_fp') continue;
                    $where = implode(' AND ', array_map(fn($k) => "{$k} = ?", $keys)); $wv = array_map(fn($k) => $r[$k] ?? '', $keys);
                    if (Db::val("SELECT 1 FROM {$t} WHERE {$where}", $wv)) Db::update($t, array_diff_key($r, array_flip($keys)), $where, $wv); else Db::insert($t, $r);
                    $n[$t]++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw new UserError('Restore stopped and nothing was changed: ' . $e->getMessage()); }
        Settings::flush();
        Audit::log('vault.restored', 'snapshot', $name, ['by' => $by, 'rows' => $n]);
        return $n;
    }

    /** A plain summary for the Data safety page and the security check. */
    public static function status(): array
    {
        $dirs = self::dirs();
        $outside = array_values(array_filter($dirs, fn($d) => !str_starts_with($d, APP_ROOT) && !str_starts_with($d, STORAGE_ROOT)));
        return ['key' => self::verify(), 'dirs' => $dirs, 'outside' => $outside, 'kit' => self::kitInfo(), 'last' => jdec((string)Settings::get('vault.last_snapshot') ?: 'null'), 'snapshots' => count(self::snapshots())];
    }
}
