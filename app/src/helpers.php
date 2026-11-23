<?php
declare(strict_types=1);

/** HTML-escape for output. */
function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/** Current UTC timestamp in DB format. */
function now(int $offsetSeconds = 0): string { return gmdate('Y-m-d H:i:s', time() + $offsetSeconds); }

/** Format integer cents as CAD. */
function money(?int $cents, bool $showFree = false): string {
    if ($cents === null) return t('Quote');
    if ($cents === 0 && $showFree) return t('Included');
    $d = ($cents % 100 === 0) ? 0 : 2;
    return lang() === 'fr' ? number_format($cents / 100, $d, ',', "\u{00A0}") . "\u{00A0}\$" : '$' . number_format($cents / 100, $d);
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

/** French pass over a rendered HTML page: swaps text nodes and UI attributes that match the dictionary or a pattern. */
function tr_html(string $html): string
{
    if (lang() !== 'fr' || $html === '') return $html;
    foreach (headers_list() as $h) if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) return $html;
    static $pat = null;
    $pat ??= is_file(APP_ROOT . '/i18n/fr_patterns.php') ? (require APP_ROOT . '/i18n/fr_patterns.php') : [];
    $one = function (string $raw) use ($pat): ?string {
        $s = trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($s === '' || !preg_match('/\p{L}{2}/u', $s)) return null;
        $f = t($s);
        if ($f === $s) { foreach ($pat as $re => $to) { if (preg_match($re, $s)) { $f = preg_replace_callback($re, fn($m) => preg_replace_callback('/\{(\d)\}/', fn($k) => t($m[(int)$k[1]] ?? ''), $to), $s); break; } } }
        if ($f === $s) return null;
        preg_match('/^\s*/u', $raw, $a); preg_match('/\s*$/u', $raw, $b);
        return $a[0] . htmlspecialchars($f, ENT_QUOTES | ENT_HTML5, 'UTF-8', false) . $b[0];
    };
    $parts = preg_split('/(<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => &$p) {
        if ($i % 2) continue;
        $p = preg_replace_callback('/>([^<>]+)</u', fn($m) => '>' . ($one($m[1]) ?? $m[1]) . '<', $p);
        $p = preg_replace_callback('/\b(placeholder|aria-label|title|alt|data-label|data-confirm|data-text|data-line)="([^"]*)"/u', fn($m) => $m[1] . '="' . ($one($m[2]) ?? $m[2]) . '"', $p);
    }
    return implode('', $parts);
}
/** Strings the browser scripts need, as JSON for <html data-i18n>. */
function js_dict(): string
{
    if (lang() !== 'fr') return '';
    $d = is_file(APP_ROOT . '/i18n/fr_js.php') ? (require APP_ROOT . '/i18n/fr_js.php') : [];
    return jenc($d);
}

/* ── Business pillars: each main business has its own colour, used on the homepage, in its submenu and on its pages. */
const PILLARS = [
    'register' => ['name' => 'Register Business', 'home' => '/register', 'acc' => '#2b3fd1', 'soft' => '#eef0fe', 'deep' => '#141b52', 'ink' => '#0e1433'],
    'manage'   => ['name' => 'Manage Business', 'home' => '/manage-business', 'acc' => '#0b7a62', 'soft' => '#e6f4ef', 'deep' => '#0b3b31', 'ink' => '#082a23'],
    'web'      => ['name' => 'Web Services', 'home' => '/website', 'acc' => '#b42a6a', 'soft' => '#fbeaf2', 'deep' => '#3d0f29', 'ink' => '#2a0a1c'],
    'bundle'   => ['name' => 'Bundle & Save', 'home' => '/bundle', 'acc' => '#9a5b00', 'soft' => '#fff2d9', 'deep' => '#3e2600', 'ink' => '#2b1a00'],
    'opps'     => ['name' => 'Opportunities', 'home' => '/opportunities', 'acc' => '#c21f32', 'soft' => '#fdecee', 'deep' => '#4a0c15', 'ink' => '#33080e'],
    'brand'    => ['name' => '818 Digital Agency', 'home' => '/', 'acc' => '#1e44c8', 'soft' => '#eef2fd', 'deep' => '#0d1117', 'ink' => '#0d1117'],
];
function pillar(string $path): string
{
    return match (true) {
        str_starts_with($path, '/register') => 'register',
        str_starts_with($path, '/manage-business') => 'manage',
        in_array(strtok($path, '/') ?: '', ['domain', 'email', 'website', 'web-application'], true) => 'web',
        $path === '/bundle' => 'bundle',
        in_array($path, ['/opportunities', '/funding'], true) => 'opps',
        default => 'brand',
    };
}
function pillar_css(string $p): string
{
    $c = PILLARS[$p] ?? PILLARS['brand'];
    return "--acc:{$c['acc']};--acc-soft:{$c['soft']};--acc-deep:{$c['deep']};--acc-ink:{$c['ink']}";
}
