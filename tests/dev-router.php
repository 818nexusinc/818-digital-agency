<?php
// Local dev router for `php -S` (mirrors public_html/.htaccess). Not deployed.
$root = dirname(__DIR__) . '/public_html';
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ((preg_match('#\.(json|log|sql|gz|env|md)$#', $p) && !in_array($p, ['/services.json', '/content.json'], true)) || str_starts_with($p, '/client-docs/') || str_starts_with($p, '/_app/')) { http_response_code(403); exit('Forbidden'); }
if ($p !== '/' && is_file($root . $p) && !str_ends_with($p, '.php')) return false;
if (preg_match('#^/api/[a-z0-9\-]+\.php$#', $p) && is_file($root . $p)) { chdir($root . '/api'); require $root . $p; return true; }
require $root . '/index.php';
