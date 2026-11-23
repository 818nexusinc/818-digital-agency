<?php
declare(strict_types=1);
namespace App;

/**
 * System Security Check: a read-only audit of configuration, accounts, secrets, AI agents, recent activity
 * and what the live website exposes. Never prints a secret, only whether it is set.
 * Each result: [group, title, status pass|warn|fail|info, what we found, how to fix, severity high|medium|low].
 */
final class SecurityCheck
{
    private const WEAK = ['OwnerPass2026', 'password', 'Password1', 'admin123', 'changeme', '818nexus', 'Welcome1', 'Passw0rd!'];

    public static function run(bool $probe = true): array
    {
        $r = [];
        $add = function (string $g, string $t, string $s, string $found, string $fix = '', string $sev = 'medium') use (&$r) { $r[] = compact('g', 't', 's', 'found', 'fix', 'sev'); };

        /* ── Configuration ── */
        $key = (string)Env::get('APP_KEY', '');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('Configuration', 'Encryption key (APP_KEY)', $raw !== false && strlen((string)$raw) >= 32 ? 'pass' : 'fail',
            $key === '' ? 'Not set. Stored API keys and MFA secrets cannot be protected.' : (strlen((string)$raw) >= 32 ? 'Set, 256-bit.' : 'Set but shorter than 32 bytes.'),
            'Set APP_KEY to base64: plus 32 random bytes in Vercel. Never change it once data is stored.', 'high');

        $preview = Db::$driver === 'sqlite' && ON_VERCEL;
        $add('Configuration', 'Permanent database', Db::$driver === 'mysql' ? 'pass' : ($preview ? 'fail' : 'warn'),
            Db::$driver === 'mysql' ? 'MySQL connected.' : ($preview ? 'Preview mode: a temporary database that resets. Orders, leads and accounts are not kept.' : 'SQLite file database (fine for local testing).'),
            'Set DB_HOST, DB_NAME, DB_USER and DB_PASS in Vercel, then run the one-time /setup.', 'high');

        if (Db::$driver === 'mysql') $add('Configuration', 'Encrypted database connection', Db::$tls === 'none' ? 'fail' : 'pass',
            ['verified' => 'TLS with a verified certificate.', 'encrypted' => 'TLS encrypted.', 'none' => 'Not encrypted: data travels to the database in plain text.'][Db::$tls] ?? 'Unknown.',
            'Enable SSL for MySQL on the database server (or ask the host to), then set DB_SSL=require in Vercel.', 'high');
        $url = url('/');
        $add('Configuration', 'HTTPS address', str_starts_with($url, 'https://') ? 'pass' : 'fail', 'Site address: ' . $url, 'Set APP_URL to the https:// address of the production domain.', 'high');
        $add('Configuration', 'Production domain', str_contains($url, '.vercel.app') ? 'warn' : 'pass',
            str_contains($url, '.vercel.app') ? 'The site still answers on its vercel.app address.' : 'Custom domain in use.', 'Add 818digitalagency.com in Vercel → Domains and set APP_URL to it.', 'low');
        $add('Configuration', 'Secure session cookie', Env::bool('SESSION_SECURE') || ON_VERCEL ? 'pass' : 'warn',
            Env::bool('SESSION_SECURE') ? 'Cookies are sent over HTTPS only, HttpOnly, SameSite=Lax.' : 'SESSION_SECURE is not set; cookies are secure only when the request is HTTPS.', 'Set SESSION_SECURE=true.', 'medium');
        $add('Configuration', 'Setup token removed', Env::get('SETUP_TOKEN') ? 'warn' : 'pass',
            Env::get('SETUP_TOKEN') ? 'SETUP_TOKEN is still set, so the installer endpoint is active.' : 'Installer endpoint is closed.', 'Delete SETUP_TOKEN in Vercel after setup and redeploy.', 'medium');
        if (Db::$driver === 'mysql') $add('Configuration', 'Preview password removed', Env::get('PREVIEW_OWNER_PASSWORD') ? 'warn' : 'pass',
            Env::get('PREVIEW_OWNER_PASSWORD') ? 'PREVIEW_OWNER_PASSWORD is still set.' : 'Not set.', 'Delete PREVIEW_OWNER_PASSWORD now that MySQL is connected.', 'low');
        $cs = (string)Env::get('CRON_SECRET', '');
        $add('Configuration', 'Background job secret', strlen($cs) >= 24 ? 'pass' : 'fail',
            $cs === '' ? 'CRON_SECRET is not set, so the daily background job cannot run.' : (strlen($cs) >= 24 ? 'Set.' : 'Set but short.'), 'Set CRON_SECRET to a random string of 32+ characters.', 'high');
        $add('Configuration', 'Error details hidden', in_array(strtolower((string)ini_get('display_errors')), ['', '0', 'off', 'stderr'], true) ? 'pass' : 'fail',
            'display_errors = ' . (ini_get('display_errors') ?: 'off') . '. Visitors see a reference number, not stack traces.', 'Turn display_errors off.', 'high');
        $add('Configuration', 'PHP version', version_compare(PHP_VERSION, '8.2', '>=') ? 'pass' : 'warn', 'PHP ' . PHP_VERSION . '.', 'Use a supported PHP 8.2+ runtime.', 'low');

        /* ── Accounts ── */
        $staff = Db::all("SELECT id, email, role, status, mfa_enabled, password_hash, failed_logins, locked_until, last_login_at FROM users WHERE role IN ('owner','admin','staff')");
        $active = array_filter($staff, fn($u) => $u['status'] === 'active');
        $noMfa = array_filter($active, fn($u) => !(int)$u['mfa_enabled']);
        $ownerNoMfa = array_filter($noMfa, fn($u) => $u['role'] === 'owner');
        $add('Accounts', 'Two-step verification on team accounts', $noMfa ? ($ownerNoMfa ? 'fail' : 'warn') : 'pass',
            $noMfa ? count($noMfa) . ' of ' . count($active) . ' team account(s) sign in with a password only: ' . implode(', ', array_column($noMfa, 'email')) . '.' : 'Every team account uses MFA.',
            'Each person opens Settings & team → Set up MFA and scans the code with an authenticator app.', 'high');
        $weak = [];
        foreach ($active as $u) foreach (self::WEAK as $w) if (password_verify($w, (string)$u['password_hash'])) { $weak[] = $u['email']; break; }
        $add('Accounts', 'No default or common passwords', $weak ? 'fail' : 'pass', $weak ? 'Common or development password on: ' . implode(', ', $weak) . '.' : 'No team account uses a known default password.',
            'Change these passwords now (Account → Password) to a long unique passphrase.', 'high');
        $owners = array_filter($active, fn($u) => $u['role'] === 'owner');
        $add('Accounts', 'Owner accounts', count($owners) === 1 ? 'pass' : (count($owners) === 0 ? 'fail' : 'warn'), count($owners) . ' active owner account(s).',
            'Keep exactly one owner; give other people the admin or staff role.', 'medium');
        $stale = array_filter($active, fn($u) => $u['last_login_at'] && strtotime($u['last_login_at'] . ' UTC') < time() - 90 * 86400);
        $add('Accounts', 'Unused team accounts', $stale ? 'warn' : 'pass', $stale ? count($stale) . ' account(s) have not signed in for 90+ days: ' . implode(', ', array_column($stale, 'email')) . '.' : 'Every team account has been used in the last 90 days.',
            'Disable accounts that are no longer needed.', 'low');
        $locked = array_filter($staff, fn($u) => $u['locked_until'] && strtotime($u['locked_until'] . ' UTC') > time());
        $add('Accounts', 'Locked accounts', $locked ? 'info' : 'pass', $locked ? count($locked) . ' account(s) temporarily locked after failed sign-ins.' : 'None locked.', 'Locks clear automatically; check Activity below for who tried.', 'low');

        /* ── Payments, email and AI keys ── */
        $add('Keys & integrations', 'API keys stored encrypted', 'pass', 'Keys entered in Integrations are encrypted with APP_KEY and never shown again. Keys in Vercel environment variables never reach the browser.', '', 'low');
        if (Integrations::configured('stripe')) $add('Keys & integrations', 'Stripe webhook signature', Integrations::secret('stripe', 'webhook_secret') ? 'pass' : 'fail',
            Integrations::secret('stripe', 'webhook_secret') ? 'Payment notifications are verified.' : 'Stripe is connected but its webhook secret is missing, so payment notifications cannot be verified.', 'Add STRIPE_WEBHOOK_SECRET from Stripe → Developers → Webhooks.', 'high');
        else $add('Keys & integrations', 'Stripe', 'info', 'Not connected. Checkout sends invoice requests.', 'Connect Stripe in Integrations when you are ready to take card payments.', 'low');
        $sk = (string)(Integrations::secret('stripe', 'secret_key') ?? '');
        if ($sk !== '') $add('Keys & integrations', 'Stripe live mode', str_starts_with($sk, 'sk_live_') || str_starts_with($sk, 'rk_live_') ? 'pass' : 'warn',
            str_starts_with($sk, 'sk_live_') || str_starts_with($sk, 'rk_live_') ? 'Live keys in use.' : 'Test keys in use: no real money is collected.', 'Switch to live keys before launch.', 'medium');
        if (Integrations::configured('resend')) $add('Keys & integrations', 'Email webhook signature', Integrations::secret('resend', 'webhook_secret') ? 'pass' : 'warn',
            Integrations::secret('resend', 'webhook_secret') ? 'Bounce and complaint notices are verified.' : 'Resend webhook secret missing.', 'Add RESEND_WEBHOOK_SECRET.', 'medium');
        else $add('Keys & integrations', 'Transactional email', 'warn', 'Resend is not connected, so receipts and notifications are logged but not delivered.', 'Connect Resend in Integrations and verify the sending domain.', 'medium');

        /* ── AI agents ── */
        $agents = Db::all('SELECT slug, name, autonomy_level, status, daily_budget_cents FROM agents');
        $l4 = array_filter($agents, fn($a) => (int)$a['autonomy_level'] >= 4 && $a['status'] === 'enabled');
        $add('AI agents', 'No agent runs high-risk actions unattended', $l4 ? 'warn' : 'pass', $l4 ? 'Level 4 (trusted): ' . implode(', ', array_column($l4, 'name')) . '.' : 'High-risk actions always come to the Action center.',
            'Lower these agents to level 3 in AI workforce → Agents.', 'high');
        $nob = array_filter($agents, fn($a) => (int)($a['daily_budget_cents'] ?? 0) <= 0 && $a['status'] === 'enabled');
        $add('AI agents', 'Spending limits', $nob ? 'warn' : 'pass', $nob ? count($nob) . ' agent(s) have no daily AI budget.' : 'Every agent has a daily AI spending cap.', 'Set a daily budget on each agent.', 'medium');
        $add('AI agents', 'Filing safety limit', (int)Settings::get('filing.daily_cap', '25') <= 50 ? 'pass' : 'warn', 'At most ' . (int)Settings::get('filing.daily_cap', '25') . ' government filings per day.', 'Keep the daily filing cap low (25 or less).', 'medium');
        $add('AI agents', 'Agents cannot move money', 'pass', 'No agent has a payment, refund or transfer tool. Finance can only recommend and request approval.', '', 'low');

        /* ── Activity (last 24 hours) ── */
        $since = gmdate('Y-m-d H:i:s', time() - 86400);
        $ev = []; foreach (Db::all('SELECT kind, COUNT(*) n FROM security_events WHERE created_at >= ? GROUP BY kind', [$since]) as $e) $ev[$e['kind']] = (int)$e['n'];
        $fails = ($ev['login.failed'] ?? 0) + ($ev['mfa.failed'] ?? 0);
        $add('Activity (24 hours)', 'Failed sign-ins', $fails > 25 ? 'warn' : 'pass', $fails . ' failed sign-in or MFA attempt(s); ' . ($ev['login.rate_limited'] ?? 0) . ' blocked by rate limiting.', 'If this keeps rising, check the source IPs in AI workforce → Security events.', 'medium');
        $other = array_diff_key($ev, ['login.failed' => 1, 'mfa.failed' => 1, 'login.rate_limited' => 1]);
        $add('Activity (24 hours)', 'Other security events', array_sum($other) > 50 ? 'warn' : ($other ? 'info' : 'pass'),
            $other ? implode(', ', array_map(fn($k, $n) => "{$k} ×{$n}", array_keys($other), $other)) . '.' : 'None.', 'Review in the audit log.', 'low');
        $demo = Demo::count();
        $add('Activity (24 hours)', 'Demo data', $demo ? 'warn' : 'pass', $demo ? $demo . ' demo records are loaded and visible.' : 'No demo data.', 'Delete it in System → Demo data before launch.', 'low');

        /* ── Live website exposure ── */
        if ($probe && !Env::bool('HTTP_FAKE')) {
            $base = rtrim(url('/'), '/');
            $home = self::fetch($base . '/');
            if ($home === null) $add('Live website', 'Website reachable', 'warn', 'Could not reach ' . $base . ' from the server to test it.', 'Re-run after deploying.', 'low');
            else {
                $h = $home['headers'];
                foreach (['strict-transport-security' => ['HTTPS enforced (HSTS)', 'high'], 'content-security-policy' => ['Content Security Policy', 'high'], 'x-frame-options' => ['Clickjacking protection', 'medium'],
                    'x-content-type-options' => ['MIME sniffing blocked', 'medium'], 'referrer-policy' => ['Referrer policy', 'low'], 'permissions-policy' => ['Camera/mic/location blocked', 'low']] as $hk => [$label, $sev]) {
                    $add('Live website', $label, isset($h[$hk]) ? 'pass' : (str_starts_with($base, 'http://') && $hk === 'strict-transport-security' ? 'info' : 'fail'),
                        isset($h[$hk]) ? 'Header present.' : 'Header missing on the homepage.', 'Security headers are sent by public_html/index.php and vercel.json.', $sev);
                }
                $leaks = [];
                foreach (['/.env', '/app/.env', '/app/bootstrap.php', '/.git/config', '/composer.json', '/app/storage/logs/', '/tests/run.php', '/index.php'] as $p) {
                    $x = self::fetch($base . $p);
                    if ($x && $x['status'] === 200 && preg_match('/APP_KEY=|DB_PASS|<\?php|\[core\]|"require"/', $x['body'])) $leaks[] = $p;
                }
                $add('Live website', 'Private files not downloadable', $leaks ? 'fail' : 'pass', $leaks ? 'Readable: ' . implode(', ', $leaks) : 'Configuration, source code, logs and git data return nothing useful.', 'Block these paths in vercel.json routes.', 'high');
                $cookie = implode("\n", (array)($h['set-cookie'] ?? []));
                if ($cookie !== '') $add('Live website', 'Cookie flags', stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite') !== false && (stripos($cookie, 'secure') !== false || str_starts_with($base, 'http://')) ? 'pass' : 'fail',
                    'Session cookie: ' . (stripos($cookie, 'secure') !== false ? 'Secure, ' : '') . (stripos($cookie, 'httponly') !== false ? 'HttpOnly, ' : '') . (preg_match('/samesite=(\w+)/i', $cookie, $m) ? 'SameSite=' . $m[1] : 'no SameSite'), 'Set SESSION_SECURE=true.', 'medium');
            }
        }
        return $r;
    }

    /** Score 0-100: high-severity failures cost the most. */
    public static function score(array $r): int
    {
        $w = ['high' => 12, 'medium' => 6, 'low' => 2];
        $lost = 0;
        foreach ($r as $x) $lost += $x['s'] === 'fail' ? $w[$x['sev']] : ($x['s'] === 'warn' ? (int)ceil($w[$x['sev']] / 2) : 0);
        return max(0, 100 - $lost);
    }

    private static function fetch(string $url): ?array
    {
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 4, 'ignore_errors' => true, 'follow_location' => 0, 'header' => "User-Agent: 818-security-check\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $body = @file_get_contents($url, false, $ctx, 0, 65536);
        if ($body === false) return null;
        $hdr = []; $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $line, $m)) { $status = (int)$m[1]; $hdr = []; continue; }
            if (str_contains($line, ':')) { [$k, $v] = explode(':', $line, 2); $k = strtolower(trim($k)); $v = trim($v);
                if ($k === 'set-cookie') $hdr[$k][] = $v; else $hdr[$k] = $v; }
        }
        return ['status' => $status, 'headers' => $hdr, 'body' => (string)$body];
    }
}
