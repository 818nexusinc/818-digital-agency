<?php
declare(strict_types=1);
namespace App;

/**
 * Passkey sign-in (WebAuthn / FIDO2), with no outside libraries.
 *
 * The private key never leaves the person's device (phone, laptop, security key) and is unlocked with Face ID,
 * a fingerprint or a PIN. The site stores only the public key. Passkeys are bound to this website's address, so they
 * cannot be phished, and there is nothing to guess, so there is nothing to lock out: a passkey sign-in never checks the
 * password lockout. Registration uses attestation "none" (we don't need to know the device maker).
 */
final class Passkeys
{
    /* ── Encoding helpers ── */
    public static function b64u(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }
    public static function unb64u(string $s): string { $r = base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true); if ($r === false) throw new UserError('Malformed passkey data.'); return $r; }

    /** Minimal CBOR decoder (RFC 8949): enough for WebAuthn attestation objects and COSE keys. */
    public static function cbor(string $d, int &$o = 0): mixed
    {
        if ($o >= strlen($d)) throw new UserError('Malformed passkey data.');
        $ib = ord($d[$o++]); $mt = $ib >> 5; $ai = $ib & 31;
        $len = function () use ($d, &$o, $ai): int {
            if ($ai < 24) return $ai;
            $n = [24 => 1, 25 => 2, 26 => 4, 27 => 8][$ai] ?? throw new UserError('Unsupported passkey data.');
            if ($o + $n > strlen($d)) throw new UserError('Malformed passkey data.');
            $v = 0; for ($i = 0; $i < $n; $i++) $v = ($v << 8) | ord($d[$o + $i]); $o += $n; return $v;
        };
        switch ($mt) {
            case 0: return $len();
            case 1: return -1 - $len();
            case 2: case 3: $n = $len(); if ($o + $n > strlen($d)) throw new UserError('Malformed passkey data.'); $v = substr($d, $o, $n); $o += $n; return $v;
            case 4: $n = $len(); $a = []; for ($i = 0; $i < $n; $i++) $a[] = self::cbor($d, $o); return $a;
            case 5: $n = $len(); $m = []; for ($i = 0; $i < $n; $i++) { $k = self::cbor($d, $o); $m[is_scalar($k) ? $k : jenc($k)] = self::cbor($d, $o); } return $m;
            case 7: return match ($ai) { 20 => false, 21 => true, 22, 23 => null, default => throw new UserError('Unsupported passkey data.') };
        }
        throw new UserError('Unsupported passkey data.');
    }

    /* ── Relying party (this website) ── */
    public static function rpId(): string
    {
        $req = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        if ($req === 'localhost') return 'localhost';
        $app = strtolower((string)parse_url((string)Env::get('APP_URL', ''), PHP_URL_HOST));
        $base = preg_replace('/^www\./', '', $app ?: $req);
        return $base ?: 'localhost';
    }

    private static function originOk(string $origin): bool
    {
        $rp = self::rpId();
        if ($rp === 'localhost') return (bool)preg_match('#^https?://localhost(:\d+)?$#', $origin);
        return in_array($origin, ['https://' . $rp, 'https://www.' . $rp], true);
    }

    private static function challenge(string $purpose): string
    {
        Session::start();
        $c = random_bytes(32);
        $_SESSION['wa'] = ['c' => self::b64u($c), 'p' => $purpose, 'exp' => time() + 300];
        return self::b64u($c);
    }

    private static function takeChallenge(string $purpose): string
    {
        Session::start();
        $w = $_SESSION['wa'] ?? null; unset($_SESSION['wa']);
        if (!$w || $w['p'] !== $purpose || $w['exp'] < time()) throw new UserError('This sign-in request expired. Try again.');
        return (string)$w['c'];
    }

    private static function clientData(string $b64, string $type, string $challenge): string
    {
        $raw = self::unb64u($b64); $c = json_decode($raw, true);
        if (!is_array($c) || ($c['type'] ?? '') !== $type) throw new UserError('Unexpected passkey response.');
        if (!hash_equals($challenge, (string)($c['challenge'] ?? ''))) throw new UserError('This passkey response was for a different request. Try again.');
        if (!self::originOk((string)($c['origin'] ?? ''))) { Audit::security('passkey.bad_origin', (string)($c['origin'] ?? '')); throw new UserError('This passkey response came from another website.'); }
        return $raw;
    }

    /** Parse authenticator data. Requires user presence and user verification (Face ID, fingerprint or PIN). */
    private static function authData(string $ad): array
    {
        if (strlen($ad) < 37) throw new UserError('Malformed passkey data.');
        if (!hash_equals(hash('sha256', self::rpId(), true), substr($ad, 0, 32))) throw new UserError('This passkey belongs to a different website address.');
        $flags = ord($ad[32]);
        if (!($flags & 0x01)) throw new UserError('The passkey did not confirm you were present.');
        if (!($flags & 0x04)) throw new UserError('The passkey did not verify you (Face ID, fingerprint or PIN). Try again.');
        return ['flags' => $flags, 'count' => unpack('N', substr($ad, 33, 4))[1], 'rest' => substr($ad, 37), 'backed_up' => (bool)($flags & 0x10)];
    }

    /* ── Public keys ── */
    private static function derLen(int $n): string { if ($n < 128) return chr($n); $b = ''; while ($n > 0) { $b = chr($n & 255) . $b; $n >>= 8; } return chr(0x80 | strlen($b)) . $b; }
    private static function derInt(string $v): string { $v = ltrim($v, "\0"); if ($v === '' || ord($v[0]) & 0x80) $v = "\0" . $v; return "\x02" . self::derLen(strlen($v)) . $v; }
    private static function pem(string $der): string { return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n"; }

    /** COSE key → [alg, stored public key]. Supports ES256 (P-256), EdDSA (Ed25519) and RS256. */
    private static function cose(array $k): array
    {
        $kty = $k[1] ?? null; $alg = $k[3] ?? null;
        if ($kty === 2 && $alg === -7 && ($k[-1] ?? null) === 1 && strlen((string)($k[-2] ?? '')) === 32 && strlen((string)($k[-3] ?? '')) === 32)
            return [-7, self::pem(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . "\x04" . $k[-2] . $k[-3])];
        if ($kty === 1 && $alg === -8 && ($k[-1] ?? null) === 6 && strlen((string)($k[-2] ?? '')) === 32) return [-8, base64_encode($k[-2])];
        if ($kty === 3 && $alg === -257 && isset($k[-1], $k[-2])) {
            $rsa = "\x30" . self::derLen(strlen($s = self::derInt($k[-1]) . self::derInt($k[-2]))) . $s;
            $bits = "\x03" . self::derLen(strlen($rsa) + 1) . "\0" . $rsa;
            $algId = hex2bin('300d06092a864886f70d0101010500');
            return [-257, self::pem("\x30" . self::derLen(strlen($algId . $bits)) . $algId . $bits)];
        }
        throw new UserError('This passkey uses a type of key we do not support. Try another device.');
    }

    private static function verifySig(int $alg, string $pub, string $data, string $sig): bool
    {
        if ($alg === -8) return sodium_crypto_sign_verify_detached($sig, $data, base64_decode($pub));
        $key = openssl_pkey_get_public($pub); if (!$key) return false;
        return openssl_verify($data, $sig, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /* ── Registration ── */

    /** A stable, random user handle (never the email or id). */
    private static function handle(int $uid): string
    {
        $k = 'passkey.handle.' . $uid; $h = Settings::get($k);
        if (!$h) { $h = self::b64u(random_bytes(32)); Settings::set($k, $h); }
        return $h;
    }

    public static function registerOptions(array $user): array
    {
        $exclude = array_map(fn($c) => ['type' => 'public-key', 'id' => $c], array_column(Db::all('SELECT credential_id FROM passkeys WHERE user_id = ?', [$user['id']]), 'credential_id'));
        return ['challenge' => self::challenge('register:' . $user['id']), 'rp' => ['id' => self::rpId(), 'name' => (string)Settings::get('company.name')],
            'user' => ['id' => self::handle((int)$user['id']), 'name' => (string)$user['email'], 'displayName' => (string)($user['name'] ?: $user['email'])],
            'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7], ['type' => 'public-key', 'alg' => -8], ['type' => 'public-key', 'alg' => -257]],
            'timeout' => 120000, 'attestation' => 'none', 'excludeCredentials' => $exclude,
            'authenticatorSelection' => ['residentKey' => 'required', 'requireResidentKey' => true, 'userVerification' => 'required']];
    }

    public static function registerFinish(array $user, array $in, string $name): array
    {
        $chal = self::takeChallenge('register:' . $user['id']);
        $resp = (array)($in['response'] ?? []);
        self::clientData((string)($resp['clientDataJSON'] ?? ''), 'webauthn.create', $chal);
        $o = 0; $att = self::cbor(self::unb64u((string)($resp['attestationObject'] ?? '')), $o);
        if (!is_array($att) || !isset($att['authData'])) throw new UserError('Malformed passkey data.');
        $ad = self::authData((string)$att['authData']);
        if (!($ad['flags'] & 0x40)) throw new UserError('The device did not return a new passkey.');
        $rest = $ad['rest'];
        if (strlen($rest) < 18) throw new UserError('Malformed passkey data.');
        $aaguid = bin2hex(substr($rest, 0, 16)); $idLen = unpack('n', substr($rest, 16, 2))[1];
        $credId = substr($rest, 18, $idLen); $p = 0;
        $coseKey = self::cbor(substr($rest, 18 + $idLen), $p);
        if (!is_array($coseKey)) throw new UserError('Malformed passkey data.');
        [$alg, $pub] = self::cose($coseKey);
        $cid = self::b64u($credId);
        if (!hash_equals($cid, (string)($in['rawId'] ?? $in['id'] ?? ''))) throw new UserError('Passkey id mismatch.');
        if (Db::val('SELECT 1 FROM passkeys WHERE credential_id = ?', [$cid])) throw new UserError('This passkey is already registered.');
        $name = trim(mb_substr($name, 0, 120)) ?: 'Passkey ' . gmdate('Y-m-d');
        $tr = implode(',', array_slice(array_filter(array_map(fn($t) => preg_replace('/[^a-z-]/', '', (string)$t), (array)($resp['transports'] ?? []))), 0, 6));
        $id = Db::insert('passkeys', ['user_id' => $user['id'], 'credential_id' => $cid, 'public_key' => $pub, 'alg' => $alg, 'sign_count' => $ad['count'], 'name' => $name,
            'aaguid' => vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($aaguid, 4)), 'transports' => $tr ?: null, 'backed_up' => $ad['backed_up'] ? 1 : 0, 'created_at' => now()]);
        Audit::security('passkey.added', (string)$user['email'] . ' · ' . $name);
        return ['id' => $id, 'name' => $name];
    }

    /* ── Sign-in ── */

    public static function loginOptions(): array
    {
        return ['challenge' => self::challenge('login'), 'rpId' => self::rpId(), 'timeout' => 120000, 'userVerification' => 'required', 'allowCredentials' => []];
    }

    /** Verify a sign-in. Returns the user id. No password lockout applies; only a per-network limit on failures. */
    public static function loginFinish(array $in): int
    {
        if (!RateLimit::hit('passkey:' . client_ip(), 30, 900)) throw new UserError('Too many attempts from this network. Wait 15 minutes.');
        $chal = self::takeChallenge('login');
        $resp = (array)($in['response'] ?? []);
        $cid = (string)($in['rawId'] ?? $in['id'] ?? '');
        $pk = Db::one('SELECT p.*, u.status, u.email FROM passkeys p JOIN users u ON u.id = p.user_id WHERE p.credential_id = ?', [$cid]);
        if (!$pk) { Audit::security('passkey.unknown', substr($cid, 0, 20)); throw new UserError('This passkey is not registered here. Use another passkey or another way to sign in.'); }
        if ($pk['status'] !== 'active') throw new UserError('This account is not active.');
        $cdRaw = self::clientData((string)($resp['clientDataJSON'] ?? ''), 'webauthn.get', $chal);
        $adRaw = self::unb64u((string)($resp['authenticatorData'] ?? ''));
        $ad = self::authData($adRaw);
        if (!empty($resp['userHandle']) && !hash_equals(self::handle((int)$pk['user_id']), (string)$resp['userHandle'])) throw new UserError('This passkey belongs to a different account.');
        if (!self::verifySig((int)$pk['alg'], (string)$pk['public_key'], $adRaw . hash('sha256', $cdRaw, true), self::unb64u((string)($resp['signature'] ?? '')))) {
            Audit::security('passkey.bad_signature', (string)$pk['email']);
            throw new UserError('The passkey signature could not be verified.');
        }
        // A counter that goes backwards means a copied key: refuse (synced passkeys report 0 and are fine).
        if ($ad['count'] > 0 && (int)$pk['sign_count'] > 0 && $ad['count'] <= (int)$pk['sign_count']) {
            Audit::security('passkey.counter', (string)$pk['email']);
            throw new UserError('This passkey looks copied, so it was refused. Use another passkey and remove this one.');
        }
        Db::update('passkeys', ['sign_count' => $ad['count'], 'last_used_at' => now(), 'backed_up' => $ad['backed_up'] ? 1 : 0], 'id = ?', [$pk['id']]);
        return (int)$pk['user_id'];
    }

    public static function forUser(int $uid): array { return Db::all('SELECT id, name, aaguid, backed_up, created_at, last_used_at FROM passkeys WHERE user_id = ? ORDER BY id', [$uid]); }

    public static function remove(int $uid, int $id): void
    {
        $n = (int)Db::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$uid]);
        if ($n <= 1 && OwnerAccess::passwordOff($uid)) throw new UserError('This is your last passkey and password sign-in is off. Add another passkey or turn password sign-in back on first.');
        Db::run('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [$id, $uid]);
        Audit::security('passkey.removed', (string)$uid);
    }
}
