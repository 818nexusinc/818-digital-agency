<?php
declare(strict_types=1);

/** HTML-escape for output. */
function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Current UTC timestamp in DB format. */
function now(int $offsetSeconds = 0): string { return gmdate('Y-m-d H:i:s', time() + $offsetSeconds); }

/** Format integer cents as CAD. */
function money(?int $cents, bool $showFree = false): string {
    if ($cents === null) return 'Quote';
    if ($cents === 0 && $showFree) return 'Included';
    return '$' . number_format($cents / 100, ($cents % 100 === 0) ? 0 : 2);
}

function jenc(mixed $v): string { return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
function jdec(?string $s, mixed $default = []): mixed {
    if ($s === null || $s === '') return $default;
    $d = json_decode($s, true);
    return $d === null ? $default : $d;
}

/** Random URL-safe id. */
function rid(int $bytes = 16): string { return bin2hex(random_bytes($bytes)); }

/** Human-friendly reference number, e.g. ORD-7K3QX9. */
function refno(string $prefix): string {
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    return $prefix . '-' . $s;
}

function url(string $path = '/'): string { return rtrim(App\Env::get('APP_URL', 'http://localhost:8000'), '/') . $path; }

function str_limit(string $s, int $n): string { return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; }

function client_ip(): string
{
    // Behind Vercel's edge the client address arrives in x-real-ip (set by the platform, not the client).
    $ip = (defined('ON_VERCEL') && ON_VERCEL && !empty($_SERVER['HTTP_X_REAL_IP'])) ? $_SERVER['HTTP_X_REAL_IP'] : ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    return substr((string)$ip, 0, 45);
}

function label(string $status): string { return ucwords(str_replace('_', ' ', $status)); }

/* ── Language (EN / FR) ─────────────────────────────────────────────── */
function lang(): string
{
    static $l = null;
    if ($l === null) { $c = $_COOKIE['lang'] ?? ''; $l = in_array($c, ['en', 'fr'], true) ? $c : 'en'; }
    return $l;
}
/** Translate a UI string. English is the key; missing French entries fall back to English. */
function t(string $s): string
{
    if ($s === '' || lang() !== 'fr') return $s;
    static $d = null;
    $d ??= is_file(APP_ROOT . '/i18n/fr.php') ? (require APP_ROOT . '/i18n/fr.php') : [];
    return $d[$s] ?? $s;
}
/** Translate, then format with sprintf (for strings with prices or names). */
function tf(string $s, mixed ...$a): string { return vsprintf(t($s), $a); }
