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
    if (\App\SiteText::$editing) return 'en';
    if ($l === null) { $c = $_COOKIE['lang'] ?? ''; $l = in_array($c, ['en', 'fr'], true) ? $c : 'en'; }
    return $l;
}
/** Translate a UI string. English is the key; missing French entries fall back to English. */
function t(string $s): string
{
    if ($s === '') return $s;
    $l = lang();
    if (($o = \App\SiteText::apply($s, $l)) !== null) return $o;
    if ($l !== 'fr') return $s;
    static $d = null;
    $d ??= is_file(APP_ROOT . '/i18n/fr.php') ? (require APP_ROOT . '/i18n/fr.php') : [];
    return $d[$s] ?? $s;
}
/** Translate, then format with sprintf (for strings with prices or names). */
function tf(string $s, mixed ...$a): string { try { return vsprintf(t($s), $a); } catch (\ValueError) { return vsprintf($s, $a); } }

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
/**
 * English pass over a rendered public page for the Site editor: applies saved wording to text that is written straight into
 * the templates (header, footer, buttons), and, in an editor preview, records every piece of text in page order.
 */
function site_html(string $html): string
{
    if ($html === '' || lang() === 'fr') return $html;
    foreach (headers_list() as $h) if (stripos($h, 'content-type:') === 0 && stripos($h, 'text/html') === false) return $html;
    $page = \App\SiteText::pageNow();
    if ($page === null || stripos($html, '<body') === false) return $html;
    $rec = \App\SiteText::$editing && http_response_code() === 200;
    if (!$rec && !\App\SiteText::hasEdits()) return $html;
    $main = []; $shared = []; $inMain = false;
    $one = function (string $raw, bool $record = true) use (&$main, &$shared, &$inMain, $rec): ?string {
        $s = trim(preg_replace('/\s+/u', ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($s === '' || !preg_match('/\p{L}/u', $s)) return null;
        if ($rec && $record) { $o = \App\SiteText::$from[$s] ?? $s; if ($inMain) $main[$o] = true; else $shared[$o] = true; }
        $f = \App\SiteText::apply($s, 'en');
        if ($f === null || $f === $s) return null;
        preg_match('/^\s*/u', $raw, $a); preg_match('/\s*$/u', $raw, $b);
        return $a[0] . htmlspecialchars($f, ENT_QUOTES | ENT_HTML5, 'UTF-8', false) . $b[0];
    };
    [$head, $body] = preg_split('/(?=<body\b)/i', $html, 2) + [1 => ''];
    $parts = preg_split('/(<script\b.*?<\/script>|<style\b.*?<\/style>|<textarea\b.*?<\/textarea>|<svg\b.*?<\/svg>)/is', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($parts as $i => &$p) {
        if ($i % 2) continue;
        // Walk tags and text in order so we know whether we are inside <main>.
        $p = preg_replace_callback('/(<main\b[^>]*>)|(<\/main>)|<([a-zA-Z][^\s>\/]*)([^>]*)>|(<!--.*?-->|<[\/!?][^>]*>)|([^<]+)/us', function ($m) use ($one, &$inMain) {
            if (!empty($m[1])) { $inMain = true; return $m[1]; }
            if (!empty($m[2])) { $inMain = false; return $m[2]; }
            if (isset($m[5]) && $m[5] !== '') return $m[5];
            if (isset($m[6]) && $m[6] !== '') return $one($m[6]) ?? $m[6];
            if (empty($m[3])) return $m[0];
            $attrs = preg_replace_callback('/\b(placeholder|aria-label|title|alt)="([^"]*)"/u', fn($a) => $a[1] . '="' . ($one($a[2]) ?? $a[2]) . '"', $m[4]);
            return '<' . $m[3] . $attrs . '>';
        }, $p);
    }
    unset($p);
    if ($rec) { try { \App\SiteText::remember($page, array_merge(array_keys($main), array_keys(array_diff_key($shared, $main))), count($main)); } catch (\Throwable) {} }
    return $head . implode('', $parts);
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
    'register' => ['name' => 'Register Business', 'home' => '/register', 'acc' => '#4f46e5', 'soft' => '#eef2ff', 'deep' => '#4f46e5', 'ink' => '#312e81', 'tone' => 'dark'],
    'manage'   => ['name' => 'Manage Business', 'home' => '/manage-business', 'acc' => '#047857', 'soft' => '#e7f8f0', 'deep' => '#059669', 'ink' => '#064e3b', 'tone' => 'light'],
    'web'      => ['name' => 'Web Services', 'home' => '/web-services', 'acc' => '#be185d', 'soft' => '#fcf1f6', 'deep' => '#be185d', 'ink' => '#701a43', 'tone' => 'light'],
    'bundle'   => ['name' => 'Bundle & Save', 'home' => '/bundle', 'acc' => '#c2410c', 'soft' => '#fff4ea', 'deep' => '#ea580c', 'ink' => '#7c2d12', 'tone' => 'light'],
    'opps'     => ['name' => 'Opportunities', 'home' => '/opportunities', 'acc' => '#be123c', 'soft' => '#fff3f4', 'deep' => '#be123c', 'ink' => '#7f1d2d', 'tone' => 'light'],
    'brand'    => ['name' => '818 Digital Agency', 'home' => '/', 'acc' => '#1e44c8', 'soft' => '#eef2fd', 'deep' => '#0f172a', 'ink' => '#0f172a', 'tone' => 'dark'],
];
function pillar(string $path): string
{
    return match (true) {
        str_starts_with($path, '/register') => 'register',
        str_starts_with($path, '/manage-business') => 'manage',
        in_array(strtok($path, '/') ?: '', ['domain', 'email', 'website', 'web-application', 'web-services'], true) => 'web',
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
