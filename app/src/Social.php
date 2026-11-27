<?php
declare(strict_types=1);
namespace App;

/**
 * Social media accounts. A profile link is enough to show the account on the website (footer icons and the
 * schema.org "sameAs" list search engines and AI assistants read). With an access token, the account can also be
 * tested and — for Facebook Pages, Instagram (with a picture), LinkedIn company pages, X and TikTok (photo or video) — posted to.
 * Posts by agents always go through the owner's approval.
 */
final class Social
{
    /** slug => [label, can post, what the token is] */
    public const NETWORKS = [
        'facebook' => ['Facebook', true, 'Page access token (Meta Business Suite → Page → long-lived token)'],
        'instagram' => ['Instagram', true, 'Instagram Graph API token from the connected Facebook Page'],
        'linkedin' => ['LinkedIn', true, 'Access token with w_organization_social for the company page'],
        'x' => ['X (Twitter)', true, 'OAuth 2.0 user access token with tweet.write'],
        'youtube' => ['YouTube', false, 'API key (read-only: channel details)'],
        'tiktok' => ['TikTok', true, 'Paste the client key and secret from developers.tiktok.com, save, then press Connect TikTok. Tokens refresh automatically. Posts need a picture or video from this website.'],
        'google_business' => ['Google Business Profile', false, 'Profile link only'],
    ];

    private static function token(string $slug): ?string
    {
        if ($slug === 'tiktok') return TikTok::accessToken();
        return Integrations::secret($slug, array_key_first(Integrations::DEFS[$slug][2]));
    }
    private static function set(string $slug, string $k): string { try { return trim((string)Integrations::setting($slug, $k, '')); } catch (\Throwable) { return ''; } }

    /** Profile links for the website, in a stable order. */
    public static function profiles(): array
    {
        $out = [];
        foreach (self::NETWORKS as $slug => [$label]) { $u = self::set($slug, 'profile_url'); if (preg_match('#^https://#', $u)) $out[$slug] = ['label' => $label, 'url' => $u]; }
        return $out;
    }

    /** Whether we can post to this network now, and if not, why. */
    public static function postable(string $slug): array
    {
        if (!(self::NETWORKS[$slug][1] ?? false)) return [false, 'This network does not support posting from here.'];
        if (!self::token($slug)) return [false, 'Add an access token in Integrations.'];
        $need = ['facebook' => 'page_id', 'instagram' => 'ig_user_id', 'linkedin' => 'org_id'][$slug] ?? null;
        if ($need && self::set($slug, $need) === '') return [false, 'Add the ' . str_replace('_', ' ', $need) . ' in Integrations.'];
        return [true, ''];
    }

    public static function test(string $slug): array
    {
        $tok = self::token($slug);
        if (!$tok) return self::set($slug, 'profile_url') !== '' ? ['status' => 200, 'body' => 'profile', 'error' => null, 'json' => ['note' => 'Profile link saved. Add a token to test the connection and post.']] : ['status' => 400, 'body' => '', 'error' => 'Add a profile link or a token.', 'json' => null];
        $g = 'https://graph.facebook.com/v21.0/';
        return match ($slug) {
            'facebook' => Integrations::http('GET', $g . rawurlencode(self::set('facebook', 'page_id') ?: 'me') . '?fields=id,name&access_token=' . rawurlencode($tok), [], null, 15),
            'instagram' => Integrations::http('GET', $g . rawurlencode(self::set('instagram', 'ig_user_id') ?: 'me') . '?fields=id,username&access_token=' . rawurlencode($tok), [], null, 15),
            'linkedin' => Integrations::http('GET', 'https://api.linkedin.com/v2/userinfo', ['Authorization: Bearer ' . $tok], null, 15),
            'x' => Integrations::http('GET', 'https://api.x.com/2/users/me', ['Authorization: Bearer ' . $tok], null, 15),
            'youtube' => Integrations::http('GET', 'https://www.googleapis.com/youtube/v3/channels?part=snippet&id=' . rawurlencode(self::set('youtube', 'channel_id')) . '&key=' . rawurlencode($tok), [], null, 15),
            'tiktok' => Integrations::http('GET', 'https://open.tiktokapis.com/v2/user/info/?fields=open_id,display_name', ['Authorization: Bearer ' . $tok], null, 15),
            default => ['status' => 200, 'body' => 'stored', 'error' => null, 'json' => null],
        };
    }

    /** Publish now. Returns the network's post id. Every attempt is logged. */
    public static function publish(string $slug, string $text, string $link = '', string $image = '', string $by = 'owner', array $opts = []): string
    {
        $slug = strtolower(trim($slug));
        if (!isset(self::NETWORKS[$slug])) throw new UserError('Choose one of: ' . implode(', ', array_keys(array_filter(self::NETWORKS, fn($n) => $n[1]))) . '.');
        [$ok, $why] = self::postable($slug);
        if (!$ok) throw new UserError(self::NETWORKS[$slug][0] . ': ' . $why);
        $text = trim($text); $link = trim($link); $image = trim($image);
        if ($text === '') throw new UserError('Write the post first.');
        foreach ([$link, $image] as $u) if ($u !== '' && !preg_match('#^https://[^\s<>"]+$#', $u)) throw new UserError('Links must start with https://');
        if ($slug === 'x' && mb_strlen($text . ($link ? ' ' . $link : '')) > 280) throw new UserError('Posts on X are limited to 280 characters.');
        if ($slug === 'instagram' && $image === '') throw new UserError('Instagram posts need a picture address (https://…).');
        if ($slug === 'tiktok' && $image === '') throw new UserError('TikTok posts need a picture or video. Upload a picture on the Social page.');
        if ($slug === 'tiktok' && array_key_exists('tiktok_privacy', $opts) && (string)$opts['tiktok_privacy'] === '') throw new UserError('TikTok: choose who can see the post.');
        $tok = (string)self::token($slug); $g = 'https://graph.facebook.com/v21.0/'; $id = '';
        try {
            switch ($slug) {
                case 'facebook':
                    $r = Integrations::http('POST', $g . rawurlencode(self::set('facebook', 'page_id')) . '/feed', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(array_filter(['message' => $text, 'link' => $link, 'access_token' => $tok])), 30);
                    $id = (string)($r['json']['id'] ?? ''); break;
                case 'instagram':
                    $u = rawurlencode(self::set('instagram', 'ig_user_id'));
                    $c = Integrations::http('POST', $g . $u . '/media', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['image_url' => $image, 'caption' => $text . ($link ? "\n\n" . $link : ''), 'access_token' => $tok]), 30);
                    if (empty($c['json']['id'])) { $r = $c; break; }
                    $r = Integrations::http('POST', $g . $u . '/media_publish', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['creation_id' => $c['json']['id'], 'access_token' => $tok]), 30);
                    $id = (string)($r['json']['id'] ?? ''); break;
                case 'linkedin':
                    $ver = preg_match('/^\d{6}$/', self::set('linkedin', 'api_version')) ? self::set('linkedin', 'api_version') : gmdate('Ym', strtotime('-2 months'));
                    $r = Integrations::http('POST', 'https://api.linkedin.com/rest/posts', ['Authorization: Bearer ' . $tok, 'Content-Type: application/json', 'LinkedIn-Version: ' . $ver, 'X-Restli-Protocol-Version: 2.0.0'],
                        jenc(['author' => 'urn:li:organization:' . self::set('linkedin', 'org_id'), 'commentary' => $text . ($link ? "\n\n" . $link : ''), 'visibility' => 'PUBLIC',
                            'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []], 'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false]), 30);
                    $id = (string)($r['json']['id'] ?? ''); if ($id === '' && $r['status'] === 201) $id = 'created'; break;
                case 'x':
                    $r = Integrations::http('POST', 'https://api.x.com/2/tweets', ['Authorization: Bearer ' . $tok, 'Content-Type: application/json'], jenc(['text' => $text . ($link ? ' ' . $link : '')]), 30);
                    $id = (string)($r['json']['data']['id'] ?? ''); break;
                case 'tiktok':
                    $id = TikTok::publish($tok, $text . ($link ? "\n\n" . $link : ''), $image, isset($opts['tiktok_privacy']) && $opts['tiktok_privacy'] !== '' ? (string)$opts['tiktok_privacy'] : null);
                    $r = ['status' => 200];
                    // Photos usually finish in seconds; report a quick failure (e.g. TikTok could not fetch the picture) right away.
                    for ($i = 0; $i < 3 && !Env::bool('HTTP_FAKE'); $i++) {
                        sleep(2);
                        $st = TikTok::status($tok, $id);
                        if ($st['status'] === 'FAILED') throw new UserError('TikTok could not publish the post: ' . ($st['reason'] ?: 'unknown reason') . '.');
                        if (in_array($st['status'], ['PUBLISH_COMPLETE', 'SEND_TO_USER_INBOX'], true)) break;
                    }
                    break;
                default: throw new UserError('Posting is not available for this network.');
            }
            if ($id === '' || ($r['status'] ?? 0) >= 300) {
                $msg = (string)($r['json']['error']['message'] ?? $r['json']['detail'] ?? $r['json']['message'] ?? $r['error'] ?? ('HTTP ' . ($r['status'] ?? 0)));
                throw new UserError(self::NETWORKS[$slug][0] . ' did not accept the post: ' . mb_substr($msg, 0, 200));
            }
            Db::insert('social_posts', ['network' => $slug, 'body' => $text, 'link' => $link ?: null, 'image_url' => $image ?: null, 'status' => 'published', 'external_id' => mb_substr($id, 0, 190), 'created_by' => $by, 'created_at' => now()]);
            Integrations::record($slug, 'post', true, 'Posted ' . $id);
            Audit::log('social.post', 'social', $slug, ['by' => $by, 'id' => $id]);
            return $id;
        } catch (UserError $e) {
            Db::insert('social_posts', ['network' => $slug, 'body' => $text, 'link' => $link ?: null, 'image_url' => $image ?: null, 'status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 500), 'created_by' => $by, 'created_at' => now()]);
            Integrations::record($slug, 'post', false, $e->getMessage());
            throw $e;
        }
    }
}

/**
 * TikTok Login Kit (web) — OAuth 2.0 authorization-code flow.
 * The owner pastes the app's client key/secret, presses "Connect TikTok", approves on tiktok.com, and TikTok
 * redirects back to /admin/integrations/tiktok/callback. Access tokens last ~24h; refresh tokens ~365 days,
 * so accessToken() refreshes shortly before expiry. Tokens are stored encrypted with the other secrets.
 */
final class TikTok
{
    public const SCOPES = 'user.info.basic,video.publish,video.upload';
    private const API = 'https://open.tiktokapis.com/v2/';
    public const PRIVACY = ['PUBLIC_TO_EVERYONE' => 'Everyone', 'MUTUAL_FOLLOW_FRIENDS' => 'Friends', 'FOLLOWER_OF_CREATOR' => 'Followers', 'SELF_ONLY' => 'Only me'];
    private const AUTH_URL = 'https://www.tiktok.com/v2/auth/authorize/';
    private const TOKEN_URL = 'https://open.tiktokapis.com/v2/oauth/token/';
    private const REVOKE_URL = 'https://open.tiktokapis.com/v2/oauth/revoke/';

    public static function redirectUri(): string { return url('/admin/integrations/tiktok/callback'); }

    public static function appConfigured(): bool
    {
        return Integrations::secret('tiktok', 'client_key') !== null && Integrations::secret('tiktok', 'client_secret') !== null;
    }

    /** Whether an OAuth refresh token is stored (i.e. "Connect TikTok" was completed). */
    public static function connected(): bool { return Integrations::secret('tiktok', 'refresh_token') !== null; }

    /** Build the TikTok consent URL and remember a one-time state value in the owner's session. */
    public static function authorizeUrl(): string
    {
        if (!self::appConfigured()) throw new UserError('Add the TikTok client key and client secret, save, then connect.');
        Session::start();
        $state = rid(24);
        $_SESSION['tiktok_oauth_state'] = ['v' => $state, 'at' => time()];
        return self::AUTH_URL . '?' . http_build_query([
            'client_key' => Integrations::secret('tiktok', 'client_key'), 'scope' => self::SCOPES, 'response_type' => 'code',
            'redirect_uri' => self::redirectUri(), 'state' => $state,
        ]);
    }

    /** Handle TikTok's redirect: verify state, exchange the code, store tokens. */
    public static function handleCallback(array $q): void
    {
        Session::start();
        $saved = $_SESSION['tiktok_oauth_state'] ?? null;
        unset($_SESSION['tiktok_oauth_state']);
        if (!empty($q['error'])) throw new UserError('TikTok did not connect: ' . mb_substr((string)($q['error_description'] ?? $q['error']), 0, 160));
        $state = (string)($q['state'] ?? '');
        if (!is_array($saved) || $state === '' || !hash_equals((string)$saved['v'], $state) || time() - (int)$saved['at'] > 900) {
            Audit::security('tiktok.oauth_state_mismatch', 'TikTok callback with a missing, wrong or expired state');
            throw new UserError('That TikTok sign-in link has expired or was not started here. Press Connect TikTok again.');
        }
        $code = (string)($q['code'] ?? '');
        if ($code === '') throw new UserError('TikTok did not return an authorization code. Press Connect TikTok again.');
        $r = self::tokenRequest(['code' => $code, 'grant_type' => 'authorization_code', 'redirect_uri' => self::redirectUri()]);
        self::store($r);
        Integrations::record('tiktok', 'connect', true, 'Connected (scopes: ' . ($r['scope'] ?? self::SCOPES) . ')');
    }

    /** A valid access token, refreshing it if it expires within 10 minutes. Null when not connected. */
    public static function accessToken(): ?string
    {
        $tok = Integrations::secret('tiktok', 'access_token');
        if (!self::connected()) return $tok; // manually pasted token (or none)
        $exp = (int)(Integrations::setting('tiktok', 'token_expires_at', '0') ?? 0);
        if ($tok !== null && $exp > time() + 600) return $tok;
        try {
            $r = self::tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => (string)Integrations::secret('tiktok', 'refresh_token')]);
            self::store($r);
            Integrations::record('tiktok', 'refresh', true, 'Access token refreshed.');
            return (string)$r['access_token'];
        } catch (\Throwable $e) {
            Integrations::record('tiktok', 'refresh', false, 'Refresh failed: ' . $e->getMessage() . ' Press Connect TikTok again.');
            return $tok; // may already be expired; the caller's request will report the failure
        }
    }

    /** Revoke at TikTok (best effort) and forget the stored tokens. Keeps the client key/secret and profile link. */
    public static function disconnect(): void
    {
        $tok = Integrations::secret('tiktok', 'access_token');
        if ($tok !== null && self::appConfigured()) {
            try { Integrations::http('POST', self::REVOKE_URL, ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
                'client_key' => Integrations::secret('tiktok', 'client_key'), 'client_secret' => Integrations::secret('tiktok', 'client_secret'), 'token' => $tok]), 15); } catch (\Throwable) {}
        }
        Integrations::putSecrets('tiktok', ['access_token' => null, 'refresh_token' => null], ['token_expires_at' => null, 'refresh_expires_at' => null, 'open_id' => null]);
        Integrations::record('tiktok', 'disconnect', true, 'Disconnected.');
    }

    /**
     * Post a photo or video. TikTok has no text-only posts and pulls media itself, only from a verified domain
     * (this website). Mode "direct" publishes to the profile; "inbox" sends it to the TikTok app to finish.
     * Returns TikTok's publish_id.
     */
    public static function publish(string $token, string $text, string $mediaUrl, ?string $privacy = null): string
    {
        $mediaUrl = trim($mediaUrl);
        if ($mediaUrl === '') throw new UserError('TikTok posts need a picture or video. Upload a picture on the Social page, or use an address on ' . self::siteHost() . '.');
        if (strtolower((string)parse_url($mediaUrl, PHP_URL_HOST)) !== self::siteHost()) {
            throw new UserError('TikTok only takes pictures and videos from your own website (' . self::siteHost() . '). Upload the picture on the Social page instead of linking to another site.');
        }
        $isVideo = (bool)preg_match('#\.(mp4|mov|webm)(\?|$)#i', $mediaUrl);
        $inbox = Integrations::setting('tiktok', 'post_mode', 'direct') === 'inbox';
        $h = ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8'];
        $text = trim($text);

        if ($inbox) {
            // Sent to the creator's TikTok inbox; they add sound/caption and post from the app.
            [$url, $body] = $isVideo
                ? [self::API . 'post/publish/inbox/video/init/', ['source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $mediaUrl]]]
                : [self::API . 'post/publish/content/init/', ['post_info' => ['title' => mb_substr($text, 0, 90), 'description' => mb_substr($text, 0, 4000)],
                    'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => [$mediaUrl]], 'post_mode' => 'MEDIA_UPLOAD', 'media_type' => 'PHOTO']];
            return self::init($url, $h, $body);
        }

        $creator = self::creatorInfo($h);
        $opts = (array)($creator['privacy_level_options'] ?? []);
        $want = $privacy ?: (string)Integrations::setting('tiktok', 'privacy_level', 'PUBLIC_TO_EVERYONE');
        if (!isset(self::PRIVACY[$want])) $want = 'PUBLIC_TO_EVERYONE';
        if ($opts && !in_array($want, $opts, true)) throw new UserError('TikTok does not allow "' . self::PRIVACY[$want] . '" for this account right now. Allowed: ' . implode(', ', array_map(fn($o) => self::PRIVACY[$o] ?? $o, $opts)) . '.');
        $base = ['privacy_level' => $want, 'disable_comment' => !empty($creator['comment_disabled'])];
        [$url, $body] = $isVideo
            ? [self::API . 'post/publish/video/init/', ['post_info' => $base + ['title' => mb_substr($text, 0, 2200), 'disable_duet' => !empty($creator['duet_disabled']), 'disable_stitch' => !empty($creator['stitch_disabled'])],
                'source_info' => ['source' => 'PULL_FROM_URL', 'video_url' => $mediaUrl]]]
            : [self::API . 'post/publish/content/init/', ['post_info' => $base + ['title' => mb_substr($text, 0, 90), 'description' => mb_substr($text, 0, 4000), 'auto_add_music' => true],
                'source_info' => ['source' => 'PULL_FROM_URL', 'photo_cover_index' => 0, 'photo_images' => [$mediaUrl]], 'post_mode' => 'DIRECT_POST', 'media_type' => 'PHOTO']];
        return self::init($url, $h, $body);
    }

    /** Check a submitted post: PROCESSING_DOWNLOAD / PROCESSING_UPLOAD / SEND_TO_USER_INBOX / PUBLISH_COMPLETE / FAILED. */
    public static function status(string $token, string $publishId): array
    {
        $r = Integrations::http('POST', self::API . 'post/publish/status/fetch/', ['Authorization: Bearer ' . $token, 'Content-Type: application/json; charset=UTF-8'], jenc(['publish_id' => $publishId]), 15);
        $d = (array)($r['json']['data'] ?? []);
        return ['status' => (string)($d['status'] ?? 'UNKNOWN'), 'reason' => (string)($d['fail_reason'] ?? ($r['json']['error']['message'] ?? ''))];
    }

    private static function creatorInfo(array $h): array
    {
        $r = Integrations::http('POST', self::API . 'post/publish/creator_info/query/', $h, '{}', 15);
        self::fail($r, 'TikTok could not check the account before posting');
        return (array)($r['json']['data'] ?? []);
    }

    private static function init(string $url, array $h, array $body): string
    {
        $r = Integrations::http('POST', $url, $h, jenc($body), 30);
        self::fail($r, 'TikTok did not accept the post');
        $id = (string)($r['json']['data']['publish_id'] ?? '');
        if ($id === '') throw new UserError('TikTok did not return a post id.');
        return $id;
    }

    private static function fail(array $r, string $what): void
    {
        $code = (string)($r['json']['error']['code'] ?? '');
        if ($r['status'] === 200 && ($code === '' || $code === 'ok')) return;
        $why = match ($code) {
            'scope_not_authorized', 'scope_permission_missed' => 'TikTok has not given this app posting permission yet. Press Reconnect TikTok on the Integrations page and approve again.',
            'unaudited_client_can_only_post_to_private_accounts' => 'Until TikTok approves (audits) the app, it can only post to a private TikTok account. Switch the account to private while testing, or use the "Send to TikTok inbox" mode.',
            'url_ownership_unverified' => 'TikTok could not confirm this website owns the picture address. Check the domain is verified in the TikTok developer portal.',
            'privacy_level_option_mismatch' => 'That audience is not allowed for this account. Choose another one.',
            'spam_risk_too_many_posts', 'spam_risk_user_banned_from_posting', 'reached_active_user_cap' => 'TikTok is limiting posts from this account right now. Try again later.',
            'access_token_invalid', 'token_expired' => 'The TikTok sign-in has expired. Press Reconnect TikTok on the Integrations page.',
            default => (string)($r['json']['error']['message'] ?? $r['error'] ?? ('HTTP ' . $r['status'])),
        };
        throw new UserError($what . ': ' . mb_substr($why, 0, 300));
    }

    private static function siteHost(): string { return strtolower((string)parse_url(url('/'), PHP_URL_HOST)); }

    private static function tokenRequest(array $params): array
    {
        if (!self::appConfigured()) throw new UserError('Add the TikTok client key and client secret first.');
        $body = http_build_query(['client_key' => Integrations::secret('tiktok', 'client_key'), 'client_secret' => Integrations::secret('tiktok', 'client_secret')] + $params);
        $r = Integrations::http('POST', self::TOKEN_URL, ['Content-Type: application/x-www-form-urlencoded', 'Cache-Control: no-cache'], $body, 20);
        $j = is_array($r['json']) ? $r['json'] : [];
        if ($r['status'] !== 200 || empty($j['access_token'])) {
            $why = (string)($j['error_description'] ?? $j['error'] ?? $r['error'] ?? ('HTTP ' . $r['status']));
            throw new UserError('TikTok refused the token request: ' . mb_substr($why, 0, 160));
        }
        return $j;
    }

    private static function store(array $j): void
    {
        $secrets = ['access_token' => (string)$j['access_token']];
        if (!empty($j['refresh_token'])) $secrets['refresh_token'] = (string)$j['refresh_token'];
        Integrations::putSecrets('tiktok', $secrets, array_filter([
            'token_expires_at' => (string)(time() + max(60, (int)($j['expires_in'] ?? 86400))),
            'refresh_expires_at' => isset($j['refresh_expires_in']) ? (string)(time() + (int)$j['refresh_expires_in']) : null,
            'open_id' => isset($j['open_id']) ? (string)$j['open_id'] : null,
        ], fn($v) => $v !== null));
    }
}

/**
 * Pictures uploaded for social posts, hosted on this website so networks that pull media from a verified domain
 * (TikTok) can fetch them. Re-encoded as JPEG (TikTok photo posts accept JPEG/WebP, not PNG), max 1080×1920.
 */
final class SocialMedia
{
    public const MAX_UPLOAD = 15 * 1024 * 1024;

    /** Store an uploaded picture and return its public https address. */
    public static function store(array $file, string $by): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (!is_uploaded_file($file['tmp_name'] ?? '') && PHP_SAPI !== 'cli')) throw new UserError('The picture did not arrive. Choose it again.');
        if ((int)($file['size'] ?? 0) > self::MAX_UPLOAD) throw new UserError('Use a picture under 15 MB.');
        return self::storeBytes((string)file_get_contents((string)$file['tmp_name']), $by);
    }

    public static function storeBytes(string $raw, string $by): string
    {
        $info = @getimagesizefromstring($raw);
        if (!in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp'], true)) throw new UserError('Use a JPG, PNG or WebP picture.');
        if (!function_exists('imagecreatefromstring') || !function_exists('imagejpeg')) throw new UserError('This server cannot process pictures (PHP GD is missing).');
        $im = @imagecreatefromstring($raw);
        if (!$im) throw new UserError('That picture could not be read.');
        [$w, $h] = [imagesx($im), imagesy($im)];
        $scale = min(1, 1080 / max(1, $w), 1920 / max(1, $h));
        if ($scale < 1) { $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale)); $r2 = imagescale($im, $nw, $nh, IMG_BICUBIC); if ($r2) { imagedestroy($im); $im = $r2; [$w, $h] = [$nw, $nh]; } }
        $bg = imagecreatetruecolor($w, $h); imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255)); imagecopy($bg, $im, 0, 0, 0, 0, $w, $h); imagedestroy($im);
        ob_start(); imagejpeg($bg, null, 88); $out = (string)ob_get_clean(); imagedestroy($bg);
        if ($out === '' || strlen($out) > 8 * 1024 * 1024) throw new UserError('That picture is too large. Export it smaller and try again.');
        $key = sha1(random_bytes(16) . $out);
        Db::insert('social_media', ['skey' => $key, 'mime' => 'image/jpeg', 'data' => base64_encode($out), 'width' => $w, 'height' => $h, 'bytes' => strlen($out), 'created_by' => mb_substr($by, 0, 190), 'created_at' => now()]);
        return url('/social/m/' . $key . '.jpg');
    }

    public static function get(string $key): ?array
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $key)) return null;
        return Db::one('SELECT mime, data, created_at FROM social_media WHERE skey = ?', [$key]) ?: null;
    }
}
