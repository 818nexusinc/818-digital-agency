<?php
declare(strict_types=1);
/**
 * Guard for the retained v2.6 API endpoints (used only by the archived /classic admin UI).
 * Requires a signed-in owner/admin session with legacy.access and a same-origin request,
 * and supplies credentials from the encrypted Integration Hub instead of the old public api/config.json.
 */
$appRoot = is_file(dirname(__DIR__, 2) . '/app/bootstrap.php') ? dirname(__DIR__, 2) . '/app' : dirname(__DIR__) . '/_app';
require_once $appRoot . '/bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$sfs = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
if (($origin !== '' && strtolower((string)parse_url($origin, PHP_URL_HOST)) !== explode(':', $host)[0]) || ($sfs !== '' && !in_array($sfs, ['same-origin', 'none'], true))) {
    App\Audit::security('legacy.cross_origin', basename($_SERVER['SCRIPT_NAME'] ?? ''));
    http_response_code(403); echo json_encode(['ok' => false, 'error' => 'Cross-origin requests are not allowed.']); exit;
}
$u = App\Auth::user();
if (!$u || !App\Rbac::can($u, 'legacy.access')) {
    App\Audit::security('legacy.unauthenticated', basename($_SERVER['SCRIPT_NAME'] ?? ''));
    http_response_code(401); echo json_encode(['ok' => false, 'error' => 'Sign in to the 818 Control Tower as an administrator.']); exit;
}
foreach (App\Integrations::DEFS as $slug => [$cat, $name, $fields]) {
    foreach ($fields as $f => $env) if (!getenv($env) && ($v = App\Integrations::secret($slug, $f))) putenv("{$env}={$v}");
}
if (!getenv('NX818_IMAP_HOST')) putenv('NX818_IMAP_HOST=' . App\Integrations::setting('webmail', 'imap_host', 'mail.818nexus.ca'));
if (!getenv('NX818_SMTP_HOST')) putenv('NX818_SMTP_HOST=' . App\Integrations::setting('webmail', 'imap_host', 'mail.818nexus.ca'));
if (!getenv('NX818_EMAIL') && ($e = App\Integrations::setting('webmail', 'email'))) putenv('NX818_EMAIL=' . $e);
App\Audit::log('legacy.api', 'endpoint', basename($_SERVER['SCRIPT_NAME'] ?? ''));
