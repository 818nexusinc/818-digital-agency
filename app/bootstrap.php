<?php
declare(strict_types=1);

/*
 * 818 Digital Agency — application bootstrap.
 * Lives OUTSIDE public_html. Loaded by public_html/index.php, app/cron.php, bin/* and tests.
 */

define('APP_ROOT', __DIR__);
define('APP_VERSION', '4.12.0');

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $rel = str_replace('\\', '/', substr($class, 4));
    // Several small classes share a file.
    $map = ['Crypto' => 'Security', 'Audit' => 'Security', 'RateLimit' => 'Security', 'Csrf' => 'Security', 'Session' => 'Security',
        'Rbac' => 'Security', 'DbSessionHandler' => 'Security', 'Totp' => 'Security', 'UserError' => 'Auth', 'Request' => 'Http', 'Router' => 'Http', 'View' => 'Http', 'HttpError' => 'Http', 'Redirected' => 'Http',
        'Settings' => 'Integrations', 'HttpFake' => 'Integrations', 'OfflineBrain' => 'Ai', 'AgentRunner' => 'Agents', 'AgentUnavailable' => 'Agents', 'BudgetExceeded' => 'Agents', 'AgentTimeout' => 'Agents', 'Domains' => 'Tools', 'ProviderDown' => 'Orchestrator', 'Backup' => 'Cron', 'Filings' => 'Filing', 'RegistryConnector' => 'Filing', 'RegistryConnectors' => 'Filing', 'AssistedConnector' => 'Filing', 'BcRegistryConnector' => 'Filing', 'Events' => 'Workflows', 'Approvals' => 'Orchestrator', 'Usage' => 'Orchestrator',
        'Kb' => 'Memory', 'Proposals' => 'Commerce', 'Cart' => 'Commerce', 'Orders' => 'Commerce', 'Catalog' => 'Commerce',
        'Projects' => 'Delivery', 'Tickets' => 'Delivery', 'Documents' => 'Delivery', 'Crm' => 'Delivery',
        'ChatLeads' => 'Growth', 'FormDrafts' => 'Growth', 'BlogEngine' => 'Growth', 'Demo' => 'Growth', 'Simulator' => 'Growth'];
    $rel = $map[$rel] ?? $rel;
    $file = APP_ROOT . '/src/' . $rel . '.php';
    if (is_file($file)) require $file;
});

require APP_ROOT . '/src/helpers.php';

App\Env::load(APP_ROOT . '/.env');
// Serverless hosts (Vercel) only allow writes to /tmp; everything durable lives in the database there.
define('ON_VERCEL', (bool)getenv('VERCEL'));
// On Vercel, default APP_URL to the production domain so links, sitemap and emails never say localhost.
if (ON_VERCEL && !getenv('APP_URL') && ($vh = getenv('VERCEL_PROJECT_PRODUCTION_URL') ?: getenv('VERCEL_URL'))) putenv('APP_URL=https://' . $vh);
define('STORAGE_ROOT', App\Env::get('STORAGE_PATH') ?: (ON_VERCEL ? sys_get_temp_dir() . '/818-storage' : APP_ROOT . '/storage'));
foreach (['logs', 'locks', 'cache', 'backups', 'uploads', 'sessions'] as $__d) if (!is_dir(STORAGE_ROOT . '/' . $__d)) @mkdir(STORAGE_ROOT . '/' . $__d, 0750, true);
// The encryption key also lives in storage/keys (never part of a release or an update), so replacing files or the
// .env can't make saved API keys and two-step secrets unreadable. Older keys are kept so old data can still be read.
if (!ON_VERCEL) {
    $__kd = STORAGE_ROOT . '/keys'; if (!is_dir($__kd)) @mkdir($__kd, 0700, true);
    $__kf = $__kd . '/app.key'; $__env = (string)App\Env::get('APP_KEY', ''); $__file = is_file($__kf) ? trim((string)@file_get_contents($__kf)) : '';
    if ($__env === '' && $__file !== '') App\Env::set('APP_KEY', $__file);
    elseif ($__env !== '' && $__file === '') { @file_put_contents($__kf, $__env); @chmod($__kf, 0600); }
    elseif ($__env !== '' && $__file !== '' && $__env !== $__file) { $__old = $__kd . '/old-' . substr(sha1($__file), 0, 10) . '.key'; if (!is_file($__old)) { @file_put_contents($__old, $__file); @chmod($__old, 0600); } @file_put_contents($__kf, $__env); }
    if (!is_file($__kd . '/.htaccess')) @file_put_contents($__kd . '/.htaccess', "Require all denied\n");
    // Same for the whole settings file: keep a private copy, and bring it back if a release or a mistake replaced .env.
    $__ef = APP_ROOT . '/.env'; $__eb = $__kd . '/env.backup'; $__ec = is_file($__ef) ? (string)@file_get_contents($__ef) : '';
    if (str_contains($__ec, 'APP_INSTALLED=1')) { if (!is_file($__eb) || md5_file($__eb) !== md5($__ec)) { @file_put_contents($__eb, $__ec); @chmod($__eb, 0600); } }
    elseif (is_file($__eb) && str_contains((string)@file_get_contents($__eb), 'APP_INSTALLED=1')) {
        if (@file_put_contents($__ef, (string)file_get_contents($__eb)) !== false) @chmod($__ef, 0600);
        App\Env::load($__eb);
        if ((string)App\Env::get('APP_KEY', '') === '' && $__file !== '') App\Env::set('APP_KEY', $__file);
    }
}
date_default_timezone_set('UTC');
ini_set('display_errors', App\Env::bool('APP_DEBUG') ? '1' : '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e): void {
    App\Log::error('uncaught', ['exception' => get_class($e), 'message' => $e->getMessage(), 'file' => $e->getFile() . ':' . $e->getLine()]);
    if (PHP_SAPI === 'cli') { fwrite(STDERR, $e . "\n"); exit(1); }
    if (!headers_sent()) http_response_code(500);
    $cid = App\Log::correlationId();
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Something went wrong. Reference: ' . $cid]);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><body style="font-family:system-ui;padding:3rem">'
            . '<h1>Something went wrong</h1><p>Our team has been notified. Reference: <code>' . htmlspecialchars($cid) . '</code></p><p><a href="/">Back to home</a></p>';
    }
});
