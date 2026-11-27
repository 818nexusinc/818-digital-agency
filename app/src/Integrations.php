<?php
declare(strict_types=1);
namespace App;

final class Settings
{
    private static array $cache = [];

    public const DEFAULTS = [
        'company.name' => '818 Digital Agency', 'company.legal' => '818 Nexus Inc.', 'company.email' => 'hello@818nexus.ca',
        'company.phone' => '', 'company.city' => 'Moncton, New Brunswick, Canada', 'company.tagline' => 'Building What\'s Next',
        'tax.enabled' => '0', 'tax.default_rate_bp' => '1500', 'currency' => 'CAD',
        'ai.enabled' => '1', 'ai.daily_budget_cents' => '1500', 'ai.monthly_budget_cents' => '30000', 'ai.default_provider' => 'anthropic',
        'ai.chat_rate_per_hour' => '40', 'refund.auto_limit_cents' => '0', 'followup.max_touches' => '3', 'followup.interval_days' => '3',
        'owner.notify_email' => 'hello@818nexus.ca', 'filing.daily_cap' => '25', 'proposal.valid_days' => '30', 'seo.default_description' => 'Register your Canadian business, get a domain, professional email and a website — guided by an AI assistant and delivered by a real team in Moncton, NB.',
    ];

    public static function get(string $k, ?string $default = null): ?string
    {
        if (!array_key_exists($k, self::$cache)) {
            try { self::$cache[$k] = Db::val('SELECT value FROM settings WHERE skey = ?', [$k]); } catch (\PDOException) { self::$cache[$k] = null; }
        }
        return self::$cache[$k] ?? (self::DEFAULTS[$k] ?? $default);
    }

    public static function int(string $k): int { return (int)(self::get($k) ?? 0); }

    public static function set(string $k, string $v): void
    {
        if (Db::val('SELECT 1 FROM settings WHERE skey = ?', [$k])) Db::update('settings', ['value' => $v, 'updated_at' => now()], 'skey = ?', [$k]);
        else Db::insert('settings', ['skey' => $k, 'value' => $v, 'updated_at' => now()]);
        self::$cache[$k] = $v;
        Audit::log('settings.update', 'setting', $k, ['value' => str_contains($k, 'secret') ? '[redacted]' : $v]);
    }

    public static function flush(): void { self::$cache = []; }
}

/**
 * Integration hub: definitions, encrypted credentials, connection status and test hooks.
 * Env vars always take precedence over DB-stored secrets.
 */
final class Integrations
{
    public const DEFS = [
        'anthropic' => ['ai', 'Anthropic Claude', ['api_key' => 'ANTHROPIC_API_KEY'], 'Default AI provider for all agents.'],
        'openai' => ['ai', 'OpenAI', ['api_key' => 'OPENAI_API_KEY'], 'Optional fallback AI provider.'],
        'stripe' => ['payments', 'Stripe', ['secret_key' => 'STRIPE_SECRET_KEY', 'webhook_secret' => 'STRIPE_WEBHOOK_SECRET'], 'Checkout, subscriptions and payment webhooks. Webhook URL: /webhooks/stripe'],
        'resend' => ['email', 'Resend', ['api_key' => 'RESEND_API_KEY', 'webhook_secret' => 'RESEND_WEBHOOK_SECRET'], 'Transactional email. Webhook URL: /api/resend-webhook.php'],
        'twentyi' => ['hosting', '20i Reseller', ['api_key' => 'TWENTYI_API_KEY'], 'Domains, hosting and mailbox provisioning.'],
        'porkbun' => ['domains', 'Porkbun', ['api_key' => 'PORKBUN_API_KEY', 'secret_key' => 'PORKBUN_SECRET_KEY'], 'Domain availability and registration (alternative to 20i).'],
        'webmail' => ['email', 'Company mailbox (IMAP/SMTP)', ['password' => 'NX818_EMAIL_PASS'], 'Admin webmail. Settings: imap_host, email.'],
        'bc_registry' => ['government', 'BC Registries Business API', ['api_key' => 'BC_REGISTRY_API_KEY', 'account_id' => 'BC_REGISTRY_ACCOUNT_ID'], 'Automatic BC incorporation filings. Premium BC Registries account with pre-authorized debit required. Setting base_url: sandbox or production gateway from the BC developer portal.'],
        'google_analytics' => ['marketing', 'Google Analytics 4', ['measurement_id' => 'GA_MEASUREMENT_ID'], 'Loads only after cookie consent.'],
        'search_console' => ['marketing', 'Google Search Console', ['verification' => 'GSC_VERIFICATION'], 'Adds the site-verification meta tag.'],
        'turnstile' => ['security', 'Cloudflare Turnstile', ['site_key' => 'TURNSTILE_SITE_KEY', 'secret_key' => 'TURNSTILE_SECRET_KEY'], 'Optional extra spam check on contact, intake, sign-up and newsletter forms (free at dash.cloudflare.com → Turnstile).'],
        'calendly' => ['productivity', 'Booking (Calendly link)', ['url' => 'CALENDLY_URL'], 'Consultation booking link offered by the Sales agent.'],
        'facebook' => ['social', 'Facebook Page', ['page_token' => 'FACEBOOK_PAGE_TOKEN'], 'Page link shows on the website. With a Page token and Page ID, posts can be published from Marketing → Social media.'],
        'instagram' => ['social', 'Instagram', ['access_token' => 'INSTAGRAM_TOKEN'], 'Profile link shows on the website. With a token and Instagram business account ID, picture posts can be published.'],
        'linkedin' => ['social', 'LinkedIn company page', ['access_token' => 'LINKEDIN_TOKEN'], 'Page link shows on the website. With a token and organization ID, posts can be published.'],
        'x' => ['social', 'X (Twitter)', ['access_token' => 'X_ACCESS_TOKEN'], 'Profile link shows on the website. With a user access token, posts can be published.'],
        'youtube' => ['social', 'YouTube', ['api_key' => 'YOUTUBE_API_KEY'], 'Channel link shows on the website. The API key lets us check the channel.'],
        'tiktok' => ['social', 'TikTok', ['access_token' => 'TIKTOK_TOKEN', 'client_key' => 'TIKTOK_CLIENT_KEY', 'client_secret' => 'TIKTOK_CLIENT_SECRET'], 'Profile link shows on the website. Add the client key and secret from your TikTok developer app, save, then use Connect TikTok — the access token is filled in and refreshed automatically.'],
        'google_business' => ['social', 'Google Business Profile', ['place_id' => 'GOOGLE_PLACE_ID'], 'Profile link shows on the website and helps local search.'],
    ];

    public static function seed(): void
    {
        foreach (self::DEFS as $slug => [$cat, $name]) {
            if (!Db::val('SELECT 1 FROM integrations WHERE slug = ?', [$slug])) {
                Db::insert('integrations', ['slug' => $slug, 'category' => $cat, 'name' => $name, 'enabled' => 0, 'status' => 'not_configured', 'settings' => '{}', 'updated_at' => now()]);
            }
        }
    }

    public static function secret(string $slug, string $field): ?string
    {
        $env = self::DEFS[$slug][2][$field] ?? null;
        if ($env && ($v = Env::get($env))) return $v;
        try { $row = Db::one('SELECT secrets_enc FROM integrations WHERE slug = ?', [$slug]); } catch (\PDOException) { return null; }
        $plain = Crypto::decrypt($row['secrets_enc'] ?? null);
        $all = $plain ? (json_decode($plain, true) ?: []) : [];
        return ($all[$field] ?? '') !== '' ? $all[$field] : null;
    }

    public static function configured(string $slug): bool
    {
        $first = array_key_first(self::DEFS[$slug][2] ?? []);
        if ($first !== null && self::secret($slug, $first) !== null) return true;
        // A social account counts as connected once its profile link is saved.
        if ((self::DEFS[$slug][0] ?? '') === 'social') { try { return preg_match('#^https://#', (string)self::setting($slug, 'profile_url', '')) === 1; } catch (\Throwable) { return false; } }
        return false;
    }

    public static function setting(string $slug, string $k, ?string $default = null): ?string
    {
        $row = Db::one('SELECT settings FROM integrations WHERE slug = ?', [$slug]);
        return jdec($row['settings'] ?? '{}')[$k] ?? $default;
    }

    public static function save(string $slug, array $secrets, array $settings = []): void
    {
        if (!isset(self::DEFS[$slug])) throw new UserError('Unknown integration.');
        $row = Db::one('SELECT * FROM integrations WHERE slug = ?', [$slug]);
        $cur = jdec(Crypto::decrypt($row['secrets_enc'] ?? null) ?? '{}');
        foreach ($secrets as $k => $v) {
            if (!array_key_exists($k, self::DEFS[$slug][2])) continue;
            $v = trim(preg_replace('/[^\x20-\x7E]/', '', (string)$v));
            if ($v !== '') $cur[$k] = $v;
        }
        $s = array_merge(jdec($row['settings'] ?? '{}'), array_map(fn($x) => mb_substr(trim((string)$x), 0, 300), $settings));
        Db::update('integrations', ['secrets_enc' => Crypto::encrypt(jenc($cur)), 'settings' => jenc($s), 'enabled' => 1,
            'status' => 'configured', 'updated_at' => now()], 'slug = ?', [$slug]);
        Audit::log('integration.save', 'integration', $slug, ['fields' => array_keys(array_filter($secrets)), 'settings' => array_keys($settings)]);
    }

    /**
     * Store machine-issued secrets (e.g. OAuth tokens) that are not owner-editable fields.
     * Values are encrypted like every other secret; null removes a key.
     */
    public static function putSecrets(string $slug, array $secrets, array $settings = []): void
    {
        if (!isset(self::DEFS[$slug])) throw new UserError('Unknown integration.');
        $row = Db::one('SELECT * FROM integrations WHERE slug = ?', [$slug]);
        $cur = jdec(Crypto::decrypt($row['secrets_enc'] ?? null) ?? '{}');
        foreach ($secrets as $k => $v) { if ($v === null || $v === '') unset($cur[$k]); else $cur[$k] = (string)$v; }
        $s = jdec($row['settings'] ?? '{}');
        foreach ($settings as $k => $v) { if ($v === null) unset($s[$k]); else $s[$k] = mb_substr((string)$v, 0, 300); }
        Db::update('integrations', ['secrets_enc' => Crypto::encrypt(jenc($cur)), 'settings' => jenc($s), 'enabled' => 1, 'updated_at' => now()], 'slug = ?', [$slug]);
        Audit::log('integration.tokens', 'integration', $slug, ['fields' => array_keys($secrets)]);
    }

    public static function record(string $slug, string $action, bool $ok, string $detail = ''): void
    {
        Db::insert('integration_logs', ['slug' => $slug, 'action' => $action, 'ok' => $ok ? 1 : 0, 'detail' => mb_substr(Log::redact($detail), 0, 500),
            'correlation_id' => Log::correlationId(), 'created_at' => now()]);
        $upd = $ok ? ['status' => 'connected', 'last_ok_at' => now(), 'last_error' => null] : ['status' => 'error', 'last_error' => mb_substr(Log::redact($detail), 0, 1000)];
        Db::update('integrations', $upd + ['updated_at' => now()], 'slug = ?', [$slug]);
    }

    /** Minimal HTTP client used by every adapter. SSRF-safe: only allowlisted API hosts. */
    public static function http(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 30, ?string $basicAuth = null): array
    {
        $host = parse_url($url, PHP_URL_HOST);
        $allow = ['api.anthropic.com', 'api.openai.com', 'api.stripe.com', 'api.resend.com', 'api.20i.com', 'api.porkbun.com', 'rdap.ca.fury.ca', 'rdap.verisign.com', 'rdap.publicinterestregistry.org',
            'graph.facebook.com', 'api.linkedin.com', 'api.x.com', 'www.googleapis.com', 'open.tiktokapis.com'];
        try { $bcHost = (string)parse_url((string)self::setting('bc_registry', 'base_url', ''), PHP_URL_HOST); } catch (\Throwable) { $bcHost = ''; }
        if ($bcHost !== '' && preg_match('/(^|\.)(gov\.bc\.ca|apigee\.net)$/', $bcHost)) $allow[] = $bcHost;
        $allow[] = 'bcregistry-prod.apigee.net'; $allow[] = 'bcregistry-test.apigee.net'; $allow[] = 'bcregistry-sandbox.apigee.net';
        if (!in_array($host, $allow, true) || parse_url($url, PHP_URL_SCHEME) !== 'https') throw new \RuntimeException('Outbound host not allowed: ' . $host);
        if (Env::bool('HTTP_FAKE')) return HttpFake::respond($method, $url, $headers, $body);
        $ch = curl_init($url);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS];
        if ($body !== null) $opts[CURLOPT_POSTFIELDS] = $body;
        if ($basicAuth) $opts[CURLOPT_USERPWD] = $basicAuth;
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return ['status' => $err ? 0 : $code, 'body' => is_string($resp) ? $resp : '', 'error' => $err ?: null, 'json' => is_string($resp) ? json_decode($resp, true) : null];
    }

    /** Test the connection for an integration. Never returns secrets. */
    public static function test(string $slug): array
    {
        if (!self::configured($slug)) return ['ok' => false, 'message' => 'Not connected yet. Add the details below.'];
        $r = match ($slug) {
            'anthropic' => self::http('GET', 'https://api.anthropic.com/v1/models', ['x-api-key: ' . self::secret('anthropic', 'api_key'), 'anthropic-version: 2023-06-01'], null, 15),
            'openai' => self::http('GET', 'https://api.openai.com/v1/models', ['Authorization: Bearer ' . self::secret('openai', 'api_key')], null, 15),
            'stripe' => self::http('GET', 'https://api.stripe.com/v1/balance', [], null, 15, self::secret('stripe', 'secret_key') . ':'),
            'resend' => self::http('GET', 'https://api.resend.com/domains', ['Authorization: Bearer ' . self::secret('resend', 'api_key')], null, 15),
            'twentyi' => self::http('GET', 'https://api.20i.com/package', ['Authorization: Bearer ' . base64_encode(trim((string)self::secret('twentyi', 'api_key')))], null, 15),
            'porkbun' => self::http('POST', 'https://api.porkbun.com/api/json/v3/ping', ['Content-Type: application/json'], jenc(['apikey' => self::secret('porkbun', 'api_key'), 'secretapikey' => self::secret('porkbun', 'secret_key')]), 15),
            'facebook', 'instagram', 'linkedin', 'x', 'youtube', 'tiktok', 'google_business' => Social::test($slug),
            default => ['status' => 200, 'body' => 'stored', 'error' => null],
        };
        $ok = $r['status'] >= 200 && $r['status'] < 300;
        $msg = $ok ? (isset($r['json']['note']) ? (string)$r['json']['note'] : 'Connection OK.') : ('Failed: ' . ($r['error'] ?: 'HTTP ' . $r['status']) . ' ' . mb_substr((string)($r['json']['error']['message'] ?? $r['json']['message'] ?? ''), 0, 160));
        self::record($slug, 'test', $ok, $msg);
        return ['ok' => $ok, 'message' => $msg];
    }
}

/** Test double for outbound HTTP (enabled with HTTP_FAKE=1). */
final class HttpFake
{
    public static array $queue = [];
    public static array $calls = [];

    public static function respond(string $method, string $url, array $headers, ?string $body): array
    {
        self::$calls[] = compact('method', 'url', 'body');
        $r = array_shift(self::$queue) ?? ['status' => 200, 'json' => ['id' => 'fake_' . rid(4)]];
        if (($r['status'] ?? 0) === 0) return ['status' => 0, 'body' => '', 'error' => 'simulated network failure', 'json' => null];
        return ['status' => $r['status'], 'body' => jenc($r['json'] ?? []), 'error' => null, 'json' => $r['json'] ?? []];
    }
}
