<?php
declare(strict_types=1);
namespace App;

/**
 * Two-step sign-in around updates.
 *
 * Normally the authenticator secret is encrypted with the server's app key. An update must never touch that key, but if
 * it ever did, the secret could no longer be read and the account would be locked. So before an update the owner
 * "pauses" two-step sign-in: the secret is re-encrypted with a key derived from their own password (Argon2id), which
 * does not depend on anything on the server. After the update, their password plus a current code from the same
 * authenticator app restores it — no new QR code, the existing app entry keeps working.
 */
final class Mfa
{
    private static function key(int $uid): string { return 'mfa.paused.' . $uid; }

    public static function paused(int $uid): ?array
    {
        $v = Settings::get(self::key($uid));
        $d = $v ? jdec($v, null) : null;
        return is_array($d) && !empty($d['ct']) ? $d : null;
    }

    private static function forgetPaused(int $uid): void
    {
        Db::run('DELETE FROM settings WHERE skey = ?', [self::key($uid)]);
        Settings::flush();
    }

    private static function derive(string $password, string $salt): string
    {
        return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, $password, $salt, SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    /** Turn two-step sign-in off for an update, keeping the secret sealed with the user's password. */
    public static function pause(array $user, string $password): void
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$user['id']]);
        if (!$u || !password_verify($password, (string)$u['password_hash'])) throw new UserError('That password is not right.');
        if ((int)$u['mfa_enabled'] !== 1) throw new UserError('Two-step sign-in is already off.');
        $secret = Crypto::decrypt($u['mfa_secret_enc'] ?? null);
        if (!$secret) throw new UserError('Your two-step key cannot be read on this server. Turn two-step sign-in off in Settings & team and set it up again after the update.');
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES); $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $k = self::derive($password, $salt);
        $ct = sodium_crypto_secretbox($secret, $nonce, $k);
        sodium_memzero($k);
        Settings::set(self::key((int)$u['id']), jenc(['v' => 1, 'salt' => base64_encode($salt), 'nonce' => base64_encode($nonce), 'ct' => base64_encode($ct), 'at' => now(), 'version' => APP_VERSION]));
        Db::update('users', ['mfa_enabled' => 0, 'mfa_secret_enc' => null, 'updated_at' => now()], 'id = ?', [$u['id']]);
        Audit::security('mfa.paused_for_update', (string)$u['email']);
    }

    /** Turn it back on with the same authenticator entry: password unlocks the secret, a current code proves the app still matches. */
    public static function resume(array $user, string $password, string $code): void
    {
        $uid = (int)$user['id'];
        $p = self::paused($uid);
        if (!$p) throw new UserError('Two-step sign-in is not paused.');
        if (!RateLimit::hit('mfa-resume:' . $uid, 8, 600)) throw new UserError('Too many tries. Wait 10 minutes.');
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        if (!$u || !password_verify($password, (string)$u['password_hash'])) throw new UserError('That password is not right.');
        $k = self::derive($password, base64_decode($p['salt']));
        $secret = sodium_crypto_secretbox_open(base64_decode($p['ct']), base64_decode($p['nonce']), $k);
        sodium_memzero($k);
        if ($secret === false) throw new UserError('This is not the password you had when you paused two-step sign-in. Use that password, or set two-step sign-in up again in Settings & team.');
        $code = preg_replace('/\D/', '', $code);
        if (!Totp::verify($secret, $code)) { Audit::security('mfa.resume_failed', (string)$u['email']); throw new UserError('That code does not match. Use the current code from the same authenticator app, and check your phone\'s clock.'); }
        Db::update('users', ['mfa_secret_enc' => Crypto::encrypt($secret), 'mfa_enabled' => 1, 'updated_at' => now()], 'id = ?', [$uid]);
        // Prove the stored copy reads back with the current key before dropping the paused copy.
        $check = Crypto::decrypt((string)Db::val('SELECT mfa_secret_enc FROM users WHERE id = ?', [$uid]));
        if ($check !== $secret) { Db::update('users', ['mfa_enabled' => 0, 'mfa_secret_enc' => null], 'id = ?', [$uid]); throw new UserError('Could not store your key safely on this server. Two-step sign-in stays paused; nothing was lost. Try again or contact support.'); }
        self::forgetPaused($uid);
        Audit::security('mfa.resumed_after_update', (string)$u['email']);
    }

    /** Team members with two-step sign-in on, and whether their key can still be read. */
    public static function team(): array
    {
        $out = [];
        foreach (Db::all("SELECT id, email, name, role, mfa_enabled, mfa_secret_enc FROM users WHERE role <> 'customer' AND status = 'active' ORDER BY id") as $u) {
            $out[] = ['id' => (int)$u['id'], 'email' => $u['email'], 'name' => $u['name'], 'role' => $u['role'], 'on' => (int)$u['mfa_enabled'] === 1,
                'readable' => (int)$u['mfa_enabled'] !== 1 || Crypto::decrypt($u['mfa_secret_enc'] ?? null) !== null, 'paused' => self::paused((int)$u['id']) !== null];
        }
        return $out;
    }
}
