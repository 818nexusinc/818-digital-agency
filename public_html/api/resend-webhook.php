<?php
declare(strict_types=1);
/**
 * Resend webhook receiver (URL unchanged from v2.6: /api/resend-webhook.php).
 * v3.0: signature verification is mandatory; events update the database email log and suppress
 * bounced/complained addresses so they are never emailed again.
 */
$appRoot = is_file(dirname(__DIR__, 2) . '/app/bootstrap.php') ? dirname(__DIR__, 2) . '/app' : dirname(__DIR__) . '/_app';
require $appRoot . '/bootstrap.php';
use App\{Db, Integrations, Audit};
header('Content-Type: application/json');
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); echo '{"error":"Method not allowed"}'; exit; }
$raw = file_get_contents('php://input') ?: '';
$secret = (string)Integrations::secret('resend', 'webhook_secret');
if ($secret === '') { http_response_code(503); echo '{"error":"Webhook secret not configured (Admin -> Integrations -> Resend)"}'; exit; }
$id = $_SERVER['HTTP_SVIX_ID'] ?? ''; $ts = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? ''; $sig = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';
$key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret);
$expected = base64_encode(hash_hmac('sha256', "{$id}.{$ts}.{$raw}", (string)$key, true));
$valid = false;
foreach (explode(' ', $sig) as $s) if (str_starts_with($s, 'v1,') && hash_equals($expected, substr($s, 3))) $valid = true;
if (!$id || !$valid || abs(time() - (int)$ts) > 300) { Audit::security('webhook.resend_bad_signature'); http_response_code(401); echo '{"error":"Invalid signature"}'; exit; }
try { Db::insert('webhook_events', ['provider' => 'resend', 'event_id' => $id, 'type' => 'resend', 'status' => 'received', 'received_at' => now()]); }
catch (\PDOException) { echo '{"ok":true,"duplicate":true}'; exit; }
$evt = json_decode($raw, true) ?: [];
$type = (string)($evt['type'] ?? '');
$data = $evt['data'] ?? [];
$map = ['email.sent' => 'sent', 'email.delivered' => 'delivered', 'email.delivery_delayed' => 'delayed', 'email.bounced' => 'bounced', 'email.complained' => 'complained', 'email.opened' => 'opened', 'email.clicked' => 'clicked'];
$status = $map[$type] ?? null;
if ($status && ($pid = $data['email_id'] ?? null)) Db::update('email_log', ['status' => $status, 'updated_at' => now()], 'provider_id = ?', [(string)$pid]);
if (in_array($type, ['email.bounced', 'email.complained'], true)) {
    foreach ((array)($data['to'] ?? []) as $to) {
        $to = strtolower((string)$to);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) && !Db::val('SELECT 1 FROM email_suppressions WHERE email = ?', [$to])) Db::insert('email_suppressions', ['email' => $to, 'reason' => $status, 'created_at' => now()]);
    }
}
Db::update('webhook_events', ['type' => $type, 'status' => 'processed', 'processed_at' => now()], "provider = 'resend' AND event_id = ?", [$id]);
echo json_encode(['ok' => true, 'type' => $type]);
