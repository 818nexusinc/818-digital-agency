<?php
declare(strict_types=1);

/*
 * 818 Digital Agency — front controller.
 * The application code lives OUTSIDE the web root (../app). If your host only allows public_html,
 * use the fallback layout public_html/_app (protected by its own deny-all .htaccess).
 */
$appRoot = is_file(dirname(__DIR__) . '/app/bootstrap.php') ? dirname(__DIR__) . '/app' : __DIR__ . '/_app';
require $appRoot . '/bootstrap.php';

use App\{Auth, Db, Env, Rbac, Request, Router, View, Csrf, Session};

$nonce = base64_encode(random_bytes(16));
View::$shared['nonce'] = $nonce;
$https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline' https://api.fontshare.com; img-src 'self' data: https:; font-src 'self' data: https://cdn.fontshare.com; connect-src 'self'; frame-ancestors 'self'; form-action 'self' https://checkout.stripe.com; base-uri 'self'; object-src 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.stripe.com")');
header('X-Correlation-Id: ' . App\Log::correlationId());
if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

$path = Request::path();

// Language switch: /any-page?lang=fr|en stores the choice for a year and returns to the same page.
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'fr'], true)) {
    setcookie('lang', $_GET['lang'], ['expires' => time() + 31536000, 'path' => '/', 'secure' => $https, 'httponly' => false, 'samesite' => 'Lax']);
    $q = $_GET; unset($q['lang']);
    header('Location: ' . $path . ($q ? '?' . http_build_query($q) : ''), true, 302); exit;
}

// Legacy SPA archive — owner/admin only. The old bundle (which contains hard-coded credentials) is never public.
if ($path === '/classic' || str_starts_with($path, '/classic/')) {
    Router::guard('legacy.access');
    $legacy = APP_ROOT . '/legacy';
    if (preg_match('#^/classic/assets/(index-[a-f0-9]+\.js)$#', $path, $m) && is_file("{$legacy}/{$m[1]}")) {
        header('Content-Type: application/javascript'); header('Cache-Control: private, max-age=3600');
        readfile("{$legacy}/{$m[1]}"); exit;
    }
    $js = basename((string)(glob("{$legacy}/index-*.js")[0] ?? ''));
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'");
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>818 Classic (archived)</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="/fonts/tabler-icons.css"><script type="module" src="/classic/assets/' . htmlspecialchars($js) . '"></script></head><body>'
        . '<div style="background:#fef3c7;color:#78350f;padding:8px 14px;font:13px system-ui">Archived v2.6 interface (read-only reference). Data shown here lives in this browser only. <a href="/admin">Back to the Control Tower</a></div><div id="root"></div></body></html>';
    exit;
}

$router = (static fn() => require APP_ROOT . '/routes.php')();
View::$shared['user'] = Auth::user();
View::$shared['csrf'] = Csrf::token();
View::$shared['path'] = $path;
$router->dispatch(Request::method(), Request::path());
