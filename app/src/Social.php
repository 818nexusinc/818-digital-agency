<?php
declare(strict_types=1);
namespace App;

/**
 * Social media accounts. A profile link is enough to show the account on the website (footer icons and the
 * schema.org "sameAs" list search engines and AI assistants read). With an access token, the account can also be
 * tested and — for Facebook Pages, Instagram (with a picture), LinkedIn company pages and X — posted to.
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
        'tiktok' => ['TikTok', false, 'Access token (read-only: profile)'],
        'google_business' => ['Google Business Profile', false, 'Profile link only'],
    ];

    private static function token(string $slug): ?string { return Integrations::secret($slug, array_key_first(Integrations::DEFS[$slug][2])); }
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
    public static function publish(string $slug, string $text, string $link = '', string $image = '', string $by = 'owner'): string
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
