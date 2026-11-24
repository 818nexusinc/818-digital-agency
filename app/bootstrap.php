<?php
declare(strict_types=1);

/*
 * 818 Digital Agency — application bootstrap.
 * Lives OUTSIDE public_html. Loaded by public_html/index.php, app/cron.php, bin/* and tests.
 */

define('APP_ROOT', __DIR__);
define('APP_VERSION', '4.7.1');

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
