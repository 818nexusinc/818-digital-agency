<?php
declare(strict_types=1);
namespace App;

/**
 * Site editor. Every public page builds its text through t(), keyed by the English wording.
 * Changes saved in the back office replace that wording on one page (page = its path) or on every page (page = '*'),
 * in English and/or French. Changes live in the database, so installing a new release never wipes them.
 */
final class SiteText
{
    /** Set by the front controller when a signed-in editor opens a page with ?nx_edit=1. */
    public static bool $editing = false;
    private static ?array $over = null;
    /** In an editor preview: edited wording → its original, so the editor keys fields by the original. */
    public static array $from = [];
    private static ?string $page = null;

    public static function key(string $src): string { return sha1($src); }

    public static function pageNow(): ?string { return self::page(); }

    public static function hasEdits(): bool { self::apply('', 'en'); return (bool)self::$over; }

    /** Path of the page being rendered, or null where edits never apply (back office, customer portal, API). */
    private static function page(): ?string
    {
        if (self::$page !== null) return self::$page === '' ? null : self::$page;
        $p = PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_URI']) ? '' : Request::path();
        if ($p === '' || preg_match('#^/(admin|portal|api|cron|webhooks|setup|classic)(/|$)#', $p)) { self::$page = ''; return null; }
        return self::$page = rtrim($p, '/') ?: '/';
    }

    /** Called by t(): the edited wording for this page, or null to keep the default. */
    public static function apply(string $src, string $lang): ?string
    {
        $page = self::page();
        if ($page === null) return null;
        if (self::$over === null) {
            self::$over = [];
            try { foreach (Db::all('SELECT page, skey, lang, value FROM site_text') as $o) self::$over[$o['page']][$o['skey']][$o['lang']] = (string)$o['value']; }
            catch (\Throwable) { self::$over = []; }
        }
        if (!self::$over) return null;
        $k = self::key($src);
        $v = self::$over[$page][$k][$lang] ?? self::$over['*'][$k][$lang] ?? null;
        if ($v !== null && self::$editing) self::$from[trim((string)preg_replace('/\s+/u', ' ', $v))] = $src;
        return $v;
    }

    /** Store what an editor preview showed, so the editor can list it. $sharedFrom marks where the header and footer begin. */
    public static function remember(string $page, array $strings, int $sharedFrom): void
    {
        $strings = array_values(array_filter($strings, fn($s) => preg_match('/\p{L}/u', $s) && mb_strlen($s) <= 4000));
        $row = ['strings' => jenc($strings), 'shared_from' => $sharedFrom, 'updated_at' => now()];
        if (Db::val('SELECT 1 FROM site_pages WHERE page = ?', [$page])) Db::update('site_pages', $row, 'page = ?', [$page]);
        else Db::insert('site_pages', $row + ['page' => $page]);
    }

    /** Fields for one page: [ [src, key, shared, en, fr, frDefault, edited], … ]. */
    public static function fields(string $page): array
    {
        $p = Db::one('SELECT strings, shared_from FROM site_pages WHERE page = ?', [$page]);
        if (!$p) return [];
        $fr = is_file(APP_ROOT . '/i18n/fr.php') ? (require APP_ROOT . '/i18n/fr.php') : [];
        $o = [];
        foreach (Db::all('SELECT page, skey, lang, value FROM site_text WHERE page IN (?, ?)', [$page, '*']) as $r) $o[$r['skey']][$r['page'] === '*' ? 'all' : 'one'][$r['lang']] = $r['value'];
        $out = [];
        foreach (jdec($p['strings']) as $i => $src) {
            $k = self::key($src);
            $scope = isset($o[$k]['one']) ? 'one' : (isset($o[$k]['all']) ? 'all' : null);
            $cur = $scope ? $o[$k][$scope] : [];
            $out[] = ['src' => $src, 'key' => $k, 'shared' => $i >= (int)$p['shared_from'], 'scope' => $scope ?? ($i >= (int)$p['shared_from'] ? 'all' : 'one'),
                'en' => $cur['en'] ?? $src, 'fr' => $cur['fr'] ?? ($fr[$src] ?? ''), 'frDefault' => $fr[$src] ?? '', 'edited' => $scope !== null];
        }
        return $out;
    }

    /** Clean an edit the way the original wording is used: plain text, keeping only the simple tags the original already had. */
    public static function clean(string $src, string $v): string
    {
        $v = trim(str_replace("\r\n", "\n", $v));
        if (str_contains($src, '<')) return strip_tags($v, '<b><strong><i><em><br><span><a>');
        return strip_tags($v);
    }

    /** Placeholders such as %s must survive an edit, or the page would break. */
    public static function placeholdersMatch(string $src, string $v): bool
    {
        $re = '/%(?:\d+\$)?\.?\d*[sdfu]/';
        preg_match_all($re, $src, $a);
        if (!$a[0]) return true;
        preg_match_all($re, $v, $b);
        $sa = $a[0]; $sb = $b[0]; sort($sa); sort($sb);
        return $sa === $sb;
    }

    /**
     * Save edits for one page. $changes: [ ['key' => sha1, 'scope' => 'one'|'all', 'en' => …, 'fr' => …, 'reset' => bool], … ]
     * Returns [saved, problems[]].
     */
    public static function save(string $page, array $changes, string $by): array
    {
        $known = [];
        foreach (jdec((string)Db::val('SELECT strings FROM site_pages WHERE page = ?', [$page])) as $s) $known[self::key($s)] = $s;
        $fr = is_file(APP_ROOT . '/i18n/fr.php') ? (require APP_ROOT . '/i18n/fr.php') : [];
        $saved = 0; $bad = [];
        foreach (array_slice($changes, 0, 2000) as $c) {
            $k = (string)($c['key'] ?? ''); $src = $known[$k] ?? null;
            if ($src === null) continue;
            // One scope per wording: clear both before writing the chosen one.
            Db::run('DELETE FROM site_text WHERE skey = ? AND page IN (?, ?)', [$k, $page, '*']);
            if (!empty($c['reset'])) { $saved++; continue; }
            $scope = ($c['scope'] ?? 'one') === 'all' ? '*' : $page;
            foreach (['en' => $src, 'fr' => $fr[$src] ?? ''] as $lang => $default) {
                $v = self::clean($src, (string)($c[$lang] ?? ''));
                if ($v === '' || $v === $default) continue;
                if (!self::placeholdersMatch($src, $v)) { $bad[] = '"' . str_limit($src, 50) . '" (' . strtoupper($lang) . ') must keep its placeholders, such as %s'; continue; }
                Db::insert('site_text', ['page' => $scope, 'skey' => $k, 'lang' => $lang, 'src' => $src, 'value' => mb_substr($v, 0, 8000), 'updated_by' => $by, 'updated_at' => now()]);
            }
            $saved++;
        }
        self::$over = null;
        if ($saved) Audit::log('site.text', 'page', $page, ['fields' => $saved]);
        return [$saved, $bad];
    }

    /** Pages the editor offers, with the number of edits on each. */
    public static function pages(): array
    {
        $paths = ['/'];
        try { foreach (Seo::allUrls() as $u) { $p = parse_url($u, PHP_URL_PATH) ?: '/'; $paths[] = rtrim($p, '/') ?: '/'; } } catch (\Throwable) {}
        foreach (['/contact', '/login', '/signup', '/cart', '/faq', '/how-it-works', '/about', '/funding'] as $p) $paths[] = $p;
        $paths = array_values(array_unique($paths));
        $counts = [];
        foreach (Db::all('SELECT page, COUNT(DISTINCT skey) n FROM site_text GROUP BY page') as $r) $counts[$r['page']] = (int)$r['n'];
        return ['paths' => $paths, 'counts' => $counts];
    }

    /* ── Brand files (logo) ── */

    public const LOGO_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    public static function asset(string $k): ?array
    {
        static $c = [];
        if (!array_key_exists($k, $c)) { try { $c[$k] = Db::one('SELECT mime, data, updated_at FROM site_assets WHERE akey = ?', [$k]) ?: null; } catch (\Throwable) { $c[$k] = null; } }
        return $c[$k];
    }

    /** Logo slots: 'logo' is the dark logo for light backgrounds, 'logo_light' the white logo for dark backgrounds. */
    public const LOGO_SLOTS = ['dark' => 'logo', 'light' => 'logo_light'];

    /** Short version tag for cache-busting a logo URL, or null when that logo was not uploaded. */
    public static function logoVersion(string $slot = 'any'): ?string
    {
        if ($slot === 'any') return self::logoVersion('dark') ?? self::logoVersion('light');
        $a = self::asset(self::LOGO_SLOTS[$slot] ?? 'logo');
        return $a ? substr(sha1($a['updated_at'] . strlen((string)$a['data'])), 0, 10) : null;
    }

    /** Validate and store an uploaded logo. Throws UserError with a plain reason when the file is not usable. */
    public static function saveLogo(array $file, string $slot = 'dark'): void
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '') && PHP_SAPI !== 'cli') throw new UserError('The file did not arrive. Choose it again and save.');
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 600 * 1024) throw new UserError('Use a logo file under 600 KB.');
        $raw = (string)file_get_contents($file['tmp_name']);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: '';
        if (in_array($mime, ['text/plain', 'text/xml', 'application/xml', 'image/svg'], true) && preg_match('/<svg[\s>]/i', $raw)) $mime = 'image/svg+xml';
        if (!isset(self::LOGO_TYPES[$mime])) throw new UserError('Use a PNG, JPG, WebP or SVG file.');
        if ($mime === 'image/svg+xml') {
            if (preg_match('/<script|<foreignObject|\bon[a-z]+\s*=|javascript:|<!ENTITY|xlink:href\s*=\s*["\'](?!#)|href\s*=\s*["\']\s*(?!#)[a-z]+:/i', $raw)) throw new UserError('This SVG contains scripts or outside links. Export it again as a plain SVG, or use a PNG.');
        } elseif (!@getimagesizefromstring($raw)) throw new UserError('This image could not be read. Export it again and retry.');
        self::putAsset(self::LOGO_SLOTS[$slot] ?? 'logo', $mime, base64_encode($raw));
    }

    public static function putAsset(string $k, string $mime, string $b64): void
    {
        $row = ['mime' => $mime, 'data' => $b64, 'updated_at' => now()];
        if (Db::val('SELECT 1 FROM site_assets WHERE akey = ?', [$k])) Db::update('site_assets', $row, 'akey = ?', [$k]);
        else Db::insert('site_assets', $row + ['akey' => $k]);
        Audit::log('site.asset', 'asset', $k, ['mime' => $mime]);
    }

    public static function removeAsset(string $k): void
    {
        Db::run('DELETE FROM site_assets WHERE akey = ?', [$k]);
        Audit::log('site.asset_removed', 'asset', $k);
    }
}
