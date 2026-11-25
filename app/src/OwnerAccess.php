<?php
declare(strict_types=1);
namespace App;

/**
 * Lockout-free ways back in for the owner and team, alongside passkeys: one-time recovery codes and emailed one-time
 * links. Password sign-in can be switched off once a passkey and recovery codes exist.
 */
final class OwnerAccess
{
    public static function passwordOff(int $uid): bool { return Settings::get('auth.password_off.' . $uid) === '1'; }

    public static function setPasswordOff(int $uid, bool $off): void
    {
        if ($off) {
            if ((int)Db::val('SELECT COUNT(*) FROM passkeys WHERE user_id = ?', [$uid]) < 1) throw new UserError('Add a passkey first.');
            if (self::codesLeft($uid) < 1) throw new UserError('Make your sign-in recovery codes first, so you always have a way back in.');
        }
        Settings::set('auth.password_off.' . $uid, $off ? '1' : '0');
        Audit::security($off ? 'auth.password_off' : 'auth.password_on', (string)$uid);
    }

    /** Ten one-time recovery codes (shown once; only hashes are stored). */
    public static function newCodes(int $uid): array
    {
        $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $codes = [];
        for ($i = 0; $i < 10; $i++) { $c = ''; for ($j = 0; $j < 10; $j++) $c .= $alpha[random_int(0, 31)]; $codes[] = substr($c, 0, 5) . '-' . substr($c, 5); }
        Settings::set('auth.recovery.' . $uid, jenc(array_map(fn($c) => password_hash(str_replace('-', '', $c), PASSWORD_DEFAULT), $codes)));
        Audit::security('auth.recovery_codes_made', (string)$uid);
        return $codes;
    }

    public static function codesLeft(int $uid): int { return count(jdec((string)Settings::get('auth.recovery.' . $uid) ?: '[]')); }

    /** Check (and use up) one sign-in recovery code. Never locks the account; limited per network and per account. */
    public static function useCode(int $uid, string $code): bool
    {
        $code = strtoupper((string)preg_replace('/[^A-Za-z0-9]/', '', $code));
        if (!RateLimit::hit('recovery:' . client_ip(), 10, 3600) || !RateLimit::hit('recovery:u' . $uid, 10, 3600)) throw new UserError('Too many tries. Wait an hour and try again.');
        $hashes = jdec((string)Settings::get('auth.recovery.' . $uid) ?: '[]');
        foreach ($hashes as $i => $h) if (password_verify($code, (string)$h)) {
            unset($hashes[$i]); Settings::set('auth.recovery.' . $uid, jenc(array_values($hashes)));
            Audit::security('auth.recovery_code_used', $uid . ' · ' . count($hashes) . ' left');
            return true;
        }
        Audit::security('auth.recovery_code_failed', (string)$uid);
        return false;
    }

    /** What the second step after an email link needs: a recovery code (if set up), the authenticator code, or nothing. */
    public static function linkSecondStep(int $uid): string
    {
        if (self::codesLeft($uid) > 0) return 'code';
        return (int)Db::val('SELECT mfa_enabled FROM users WHERE id = ?', [$uid]) === 1 ? 'mfa' : 'none';
    }

    /** Email a one-time sign-in link (15 minutes). Same answer whether or not the email exists. */
    public static function sendLink(string $email): void
    {
        $email = strtolower(trim($email));
        if (!RateLimit::hit('signin-link:' . client_ip(), 5, 3600) || !RateLimit::hit('signin-link:' . $email, 3, 3600)) return;
        $u = Db::one("SELECT id, email, status FROM users WHERE email = ? AND role <> 'customer'", [$email]);
        if (!$u || $u['status'] !== 'active') { Audit::security('auth.link_unknown', $email); return; }
        self::mailLink($u);
        Audit::security('auth.link_sent', $email);
    }

    /** Create and email a one-time sign-in link (15 minutes) for this user row. */
    public static function mailLink(array $u): void
    {
        $t = Passkeys::b64u(random_bytes(32));
        Db::insert('login_links', ['user_id' => $u['id'], 'token_hash' => hash('sha256', $t), 'expires_at' => now(900), 'ip' => client_ip(), 'created_at' => now()]);
        Mailer::send('signin_link', (string)$u['email'], ['link' => url('/login/link/' . $t), 'ip' => client_ip(), 'at' => substr(now(), 0, 16)], null, 'user', (int)$u['id']);
    }

    public static function linkValid(string $t): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{40,60}$/', $t) && (bool)Db::val('SELECT 1 FROM login_links WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $t), now()]);
    }

    public static function useLink(string $t): int
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,60}$/', $t)) throw new UserError('This sign-in link is not valid.');
        $row = Db::one('SELECT * FROM login_links WHERE token_hash = ?', [hash('sha256', $t)]);
        if (!$row || $row['used_at'] || $row['expires_at'] <= now()) throw new UserError('This sign-in link has expired or was already used. Ask for a new one.');
        $upd = Db::run('UPDATE login_links SET used_at = ? WHERE id = ? AND used_at IS NULL', [now(), $row['id']]);
        if ($upd->rowCount() !== 1) throw new UserError('This sign-in link was already used.');
        if (Db::val('SELECT status FROM users WHERE id = ?', [$row['user_id']]) !== 'active') throw new UserError('This account is not active.');
        return (int)$row['user_id'];
    }
}
