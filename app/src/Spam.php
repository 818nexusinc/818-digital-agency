<?php
declare(strict_types=1);
namespace App;

/**
 * Spam shield for public forms. Layers, cheapest first:
 *  1. Honeypot field ("website") that people never see.
 *  2. Signed form token issued with the page (HMAC with APP_KEY): proves the form came from our page, and how long ago.
 *  3. Time trap: real people take at least a few seconds to fill a form.
 *  4. Human proof: the browser script answers only after a real keypress, tap or click on the page.
 *  5. Per-network rate limits per form, and repeated identical messages.
 *  6. Content checks: link stuffing, spam phrases, links in name fields, disposable email domains.
 *  7. Optional Cloudflare Turnstile when its keys are set (Integrations).
 * Bots that fail the hidden checks get a normal-looking reply, so they learn nothing. Every block is logged for the owner.
 */
final class Spam
{
    public const MIN_SECONDS = ['contact' => 4, 'start' => 6, 'subscribe' => 2, 'signup' => 3, 'login' => 1, 'forgot' => 2, 'order' => 5, 'draft' => 2, 'chat' => 1];
    public const LIMITS = ['contact' => [5, 3600], 'start' => [5, 3600], 'subscribe' => [5, 3600], 'signup' => [6, 3600], 'login' => [30, 900], 'forgot' => [5, 3600],
        'order' => [40, 3600], 'draft' => [150, 3600], 'chat' => [60, 3600]];
    /** Phrases that only show up in spam and vendor pitches, never in a real customer request. Kept narrow on purpose. */
    private const PHRASES = ['casino', 'viagra', 'cialis', 'crypto signal', 'bitcoin investment', 'forex signal', 'guest post', 'buy followers', 'payday loan', 'porn', 'escort service', 'replica watches',
        'whatsapp me on', 'contact me on telegram', 'increase your sales by', 'unsubscribe from future', 'dear sir/madam', 'we are a leading', 'outsourcing company', 'i can rank your', 'link building package'];
    private const DISPOSABLE = ['mailinator.com', 'guerrillamail.com', 'guerrillamail.info', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com', 'trashmail.com', 'sharklasers.com',
        'getnada.com', 'dispostable.com', 'maildrop.cc', 'fakeinbox.com', 'throwawaymail.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com', 'tempail.com', 'moakt.com', 'spamgourmet.com'];

    /** Signed token for the current page: "time.nonce.signature". */
    public static function token(): string
    {
        $t = (string)time(); $n = bin2hex(random_bytes(6));
        return $t . '.' . $n . '.' . substr(hash_hmac('sha256', $t . '.' . $n, self::key()), 0, 20);
    }

    /** The answer the browser script computes after a real interaction. */
    public static function proof(string $token): string
    {
        return substr(hash('sha256', strrev($token) . '|818'), 0, 16);
    }

    private static function key(): string { return (string)(Env::get('APP_KEY') ?: 'nx818-dev-key') . '|spam'; }

    /**
     * Check a submission. $data holds the visitor's fields (for content checks).
     * Returns null when it passes, or ['reason' => ..., 'silent' => bool] when blocked.
     */
    public static function check(string $form, array $data = []): ?array
    {
        if (Settings::get('spam.enabled', '1') !== '1') return null;
        $ip = client_ip();
        $hdr = fn(string $k) => (string)($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] ?? '');
        $first = function (string $k, string $h) use ($data, $hdr) { foreach ([$_POST[$k] ?? '', $data[$k] ?? '', $hdr($h)] as $v) if (is_string($v) && $v !== '') return $v; return ''; };
        $tok = $first('_ft', 'X-Ft'); $hj = $first('_hj', 'X-Hj');
        $hp = (string)($_POST['website'] ?? $data['website'] ?? '');
        $fail = function (string $reason, bool $silent) use ($form, $ip, $data) {
            self::log($form, $reason, $data);
            return ['reason' => $reason, 'silent' => $silent];
        };

        if (in_array($ip, self::blocked(), true)) return $fail('blocked network', true);
        if (trim($hp) !== '') return $fail('honeypot filled', true);
        if ($form !== 'login') {
            if ($tok === '' || substr_count($tok, '.') !== 2) return $fail('no form token (no JavaScript or a script)', false);
            [$t, $n, $sig] = explode('.', $tok);
            if (!hash_equals(substr(hash_hmac('sha256', $t . '.' . $n, self::key()), 0, 20), $sig)) return $fail('forged form token', true);
            $age = time() - (int)$t;
            if ($age < (self::MIN_SECONDS[$form] ?? 3)) return $fail("submitted {$age}s after the page loaded", true);
            if ($age > 3 * 86400) return $fail('form token expired', false);
            if (!hash_equals(self::proof($tok), $hj)) return $fail('no human interaction on the page', false);
        }
        [$max, $win] = self::LIMITS[$form] ?? [10, 3600];
        if (!RateLimit::hit("spam:{$form}:{$ip}", $max, $win)) return $fail('too many submissions from this network', false);

        // Content checks on free-text and identity fields.
        $text = mb_strtolower(implode(' ', array_map(fn($v) => is_scalar($v) ? (string)$v : '', self::flatten($data))));
        $links = preg_match_all('#(https?://|www\.)\S+#i', $text);
        if (!in_array($form, ['order', 'draft'], true) && $links > (int)Settings::get('spam.max_links', '2')) return $fail("{$links} links in the message", true);
        foreach (['name', 'first', 'last', 'business_name', 'company'] as $f) {
            $v = (string)($data[$f] ?? '');
            if ($v !== '' && (preg_match('#https?://|www\.|\.(com|ru|xyz|top|click)\b#i', $v) || mb_strlen($v) > 120)) return $fail("link or junk in the {$f} field", true);
        }
        foreach (self::PHRASES as $p) if (str_contains($text, $p)) return $fail('spam phrase: "' . $p . '"', true);
        if (preg_match('/[\x{0400}-\x{04FF}]{12,}|[\x{4E00}-\x{9FFF}]{12,}/u', $text) && !preg_match('/[a-zà-ÿ]{12,}/u', $text)) return $fail('message in an unexpected script', true);
        $email = strtolower((string)($data['email'] ?? ''));
        if ($email !== '' && in_array(substr(strrchr($email, '@') ?: '', 1), self::DISPOSABLE, true)) return $fail('disposable email address', false);
        $msg = trim((string)($data['message'] ?? ''));
        if (mb_strlen($msg) > 40 && !RateLimit::hit('spam:same:' . md5($msg), 3, 86400)) return $fail('same message sent repeatedly', true);

        if (($site = Integrations::secret('turnstile', 'secret_key')) && in_array($form, ['contact', 'start', 'signup', 'subscribe'], true)) {
            $resp = (string)($_POST['cf-turnstile-response'] ?? $data['cf-turnstile-response'] ?? '');
            if ($resp === '' || !self::turnstile($site, $resp, $ip)) return $fail('failed the Cloudflare challenge', false);
        }
        return null;
    }

    /** Stop the request: silent blocks pretend to succeed; others ask the person to retry. */
    public static function guard(string $form, array $data = [], ?callable $fakeSuccess = null): void
    {
        $r = self::check($form, $data);
        if (!$r) return;
        if ($r['silent'] && $fakeSuccess) { $fakeSuccess(); exit; }
        throw new UserError($r['reason'] === 'too many submissions from this network' ? 'Too many submissions from your network. Please try again later or email us.'
            : 'We could not verify this submission. Please refresh the page, make sure JavaScript is on, and try again.');
    }

    private static function turnstile(string $secret, string $resp, string $ip): bool
    {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 6, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['secret' => $secret, 'response' => $resp, 'remoteip' => $ip]), 'ignore_errors' => true]]);
        $j = json_decode((string)@file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx), true);
        return !empty($j['success']);
    }

    public static function blocked(): array { return array_values(array_filter(array_map('trim', explode(',', (string)Settings::get('spam.blocked_ips'))))); }

    private static function log(string $form, string $reason, array $data): void
    {
        $who = (string)($data['email'] ?? $data['name'] ?? '');
        try { Audit::security('spam.blocked', mb_substr("{$form}: {$reason}" . ($who !== '' ? " ({$who})" : ''), 0, 480)); } catch (\Throwable) {}
    }

    private static function flatten(array $a): array
    {
        $o = [];
        array_walk_recursive($a, function ($v, $k) use (&$o) { if (!in_array($k, ['_ft', '_hj', 'website', 'password', 'password_confirm', 'cf-turnstile-response'], true)) $o[] = $v; });
        return $o;
    }

    /** Summary for the admin page. */
    public static function stats(int $days = 7): array
    {
        $since = gmdate('Y-m-d H:i:s', time() - $days * 86400);
        $rows = Db::all("SELECT detail, ip, created_at FROM security_events WHERE kind = 'spam.blocked' AND created_at >= ? ORDER BY id DESC LIMIT 500", [$since]);
        $byForm = []; $byReason = []; $byIp = [];
        foreach ($rows as $r) {
            [$f, $why] = array_pad(explode(': ', (string)$r['detail'], 2), 2, '');
            $why = preg_replace('/ \(.*\)$/', '', $why);
            $byForm[$f] = ($byForm[$f] ?? 0) + 1; $byReason[$why] = ($byReason[$why] ?? 0) + 1; $byIp[$r['ip']] = ($byIp[$r['ip']] ?? 0) + 1;
        }
        arsort($byForm); arsort($byReason); arsort($byIp);
        return ['total' => count($rows), 'today' => count(array_filter($rows, fn($r) => $r['created_at'] >= gmdate('Y-m-d H:i:s', time() - 86400))),
            'forms' => $byForm, 'reasons' => array_slice($byReason, 0, 10, true), 'ips' => array_slice($byIp, 0, 10, true), 'recent' => array_slice($rows, 0, 40)];
    }
}
