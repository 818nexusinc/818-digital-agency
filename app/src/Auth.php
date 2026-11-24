<?php
declare(strict_types=1);
namespace App;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function hash(string $pw): string
    {
        return password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT);
    }

    public static function passwordProblem(string $pw): ?string
    {
        if (strlen($pw) < 10) return 'Use at least 10 characters.';
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Include at least one letter and one number.';
        return null;
    }

    public static function user(): ?array
    {
        if (self::$loaded) return self::$user;
        self::$loaded = true;
        if (PHP_SAPI !== 'cli' || isset($_SESSION)) Session::start();
        $id = $_SESSION['uid'] ?? null;
        if (!$id || !empty($_SESSION['mfa_pending'])) return self::$user = null;
        $u = Db::one('SELECT * FROM users WHERE id = ? AND status = ?', [$id, 'active']);
        return self::$user = $u ?: null;
    }

    public static function customer(): ?array
    {
        $u = self::user();
        return $u ? Db::one('SELECT * FROM customers WHERE user_id = ?', [$u['id']]) : null;
    }

    public static function isStaff(?array $u = null): bool
    {
        $u ??= self::user();
        return $u !== null && in_array($u['role'], ['owner', 'admin', 'staff'], true);
    }

    public static function forget(): void { self::$user = null; self::$loaded = false; }

    public static function register(string $name, string $email, string $password, array $extra = []): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Enter a valid email address.');
        if (trim($name) === '') throw new UserError('Enter your name.');
        if ($p = self::passwordProblem($password)) throw new UserError($p);
        if (Db::val('SELECT 1 FROM users WHERE email = ?', [$email])) throw new UserError('An account with that email already exists. Try signing in.');
        return Db::tx(function () use ($name, $email, $password, $extra) {
            $uid = Db::insert('users', ['email' => $email, 'password_hash' => self::hash($password), 'name' => trim($name), 'role' => 'customer',
                'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $cust = Crm::upsertCustomer(['email' => $email, 'name' => trim($name), 'phone' => $extra['phone'] ?? null,
                'business_name' => $extra['business_name'] ?? null, 'lead_source' => 'signup'], 'customer_account');
            Db::update('customers', ['user_id' => $uid, 'consent_marketing' => !empty($extra['consent_marketing']) ? 1 : 0,
                'consent_at' => !empty($extra['consent_marketing']) ? now() : null, 'updated_at' => now()], 'id = ?', [$cust['id']]);
            Audit::log('user.register', 'user', $uid, [], 'user', (string)$uid);
            Events::emit('customer.registered', ['user_id' => $uid, 'customer_id' => $cust['id']]);
            return Db::one('SELECT * FROM users WHERE id = ?', [$uid]);
        });
    }

    /** @return string 'ok' | 'mfa' */
    public static function attempt(string $email, string $password): string
    {
        $email = strtolower(trim($email));
        if (!RateLimit::hit('login:' . client_ip(), 20, 900) || !RateLimit::hit('login:' . $email, 8, 900)) {
            Audit::security('login.rate_limited', $email);
            throw new UserError('Too many sign-in attempts. Please wait 15 minutes and try again.');
        }
        $u = Db::one('SELECT * FROM users WHERE email = ?', [$email]);
        if ($u && $u['locked_until'] && $u['locked_until'] > now()) throw new UserError('This account is temporarily locked. Try again later or reset your password.');
        if (!$u || $u['status'] !== 'active' || !password_verify($password, $u['password_hash'])) {
            if ($u) {
                $f = (int)$u['failed_logins'] + 1;
                Db::update('users', ['failed_logins' => $f, 'locked_until' => $f >= 8 ? now(900) : null], 'id = ?', [$u['id']]);
            }
            Audit::security('login.failed', $email);
            throw new UserError('That email and password combination did not match.');
        }
        if (password_needs_rehash($u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => self::hash($password)], 'id = ?', [$u['id']]);
        }
        Session::start();
        Session::regenerate();
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['role'] = $u['role'];
        // Emergency: MFA_RESET_EMAIL in the server's .env turns two-step off for that one account at its next sign-in.
        if ((int)$u['mfa_enabled'] === 1 && strtolower((string)Env::get('MFA_RESET_EMAIL', '')) === strtolower((string)$u['email'])) {
            Db::update('users', ['mfa_secret_enc' => null, 'mfa_enabled' => 0, 'updated_at' => now()], 'id = ?', [$u['id']]);
            Audit::security('mfa.reset_by_env', (string)$u['email']);
            $u['mfa_enabled'] = 0;
        }
        if ((int)$u['mfa_enabled'] === 1) { $_SESSION['mfa_pending'] = 1; self::forget(); return 'mfa'; }
        self::complete((int)$u['id']);
        return 'ok';
    }

    public static function verifyMfa(string $code): bool
    {
        Session::start();
        $id = $_SESSION['uid'] ?? null;
        if (!$id || empty($_SESSION['mfa_pending'])) return false;
        if (!RateLimit::hit('mfa:' . $id, 6, 600)) throw new UserError('Too many codes tried. Wait 10 minutes.');
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        $secret = Crypto::decrypt($u['mfa_secret_enc'] ?? null);
        $ok = $secret && Totp::verify($secret, $code);
        if (!$ok && self::useRecoveryCode((int)$id, $code)) { $ok = true; Audit::security('mfa.recovery_used', (string)$id); }
        if (!$ok) {
            Audit::security('mfa.failed', (string)$id);
            if (!$secret && $u['mfa_secret_enc']) throw new UserError('Your two-step key can no longer be read on this server (its encryption key changed). Sign in with one of your recovery codes, then set up two-step verification again.');
            return false;
        }
        unset($_SESSION['mfa_pending']);
        self::complete((int)$id);
        return true;
    }

    /** Eight one-time recovery codes. Only hashes are stored; the codes are shown once. */
    public static function newRecoveryCodes(int $id): array
    {
        $codes = []; for ($i = 0; $i < 8; $i++) $codes[] = strtolower(bin2hex(random_bytes(2)) . '-' . bin2hex(random_bytes(2)));
        Settings::set('mfa.recovery.' . $id, jenc(array_map(fn($c) => password_hash($c, PASSWORD_DEFAULT), $codes)));
        return $codes;
    }

    private static function useRecoveryCode(int $id, string $code): bool
    {
        $code = strtolower(trim($code));
        if (!preg_match('/^[a-f0-9]{4}-?[a-f0-9]{4}$/', $code)) return false;
        if (!str_contains($code, '-')) $code = substr($code, 0, 4) . '-' . substr($code, 4);
        $hashes = jdec((string)Settings::get('mfa.recovery.' . $id) ?: '[]');
        foreach ($hashes as $i => $h) if (password_verify($code, (string)$h)) { unset($hashes[$i]); Settings::set('mfa.recovery.' . $id, jenc(array_values($hashes))); return true; }
        return false;
    }

    private static function complete(int $id): void
    {
        Db::update('users', ['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now()], 'id = ?', [$id]);
        self::forget();
        Audit::log('user.login', 'user', $id, [], 'user', (string)$id);
    }

    public static function logout(): void
    {
        Session::start();
        $_SESSION = [];
        if (PHP_SAPI !== 'cli') { session_regenerate_id(true); }
        self::forget();
    }

    public static function loginAs(int $userId): void
    {
        Session::start();
        $_SESSION['uid'] = $userId;
        $_SESSION['role'] = Db::val('SELECT role FROM users WHERE id = ?', [$userId]);
        unset($_SESSION['mfa_pending']);
        self::forget();
    }

    public static function createResetToken(string $email): ?string
    {
        $u = Db::one('SELECT id FROM users WHERE email = ?', [strtolower(trim($email))]);
        if (!$u) return null;
        $token = rid(24);
        Db::insert('password_resets', ['user_id' => $u['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => now(3600), 'created_at' => now()]);
        return $token;
    }

    public static function resetPassword(string $token, string $pw): bool
    {
        $r = Db::one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?', [hash('sha256', $token), now()]);
        if (!$r) return false;
        if ($p = self::passwordProblem($pw)) throw new UserError($p);
        Db::update('users', ['password_hash' => self::hash($pw), 'failed_logins' => 0, 'locked_until' => null, 'updated_at' => now()], 'id = ?', [$r['user_id']]);
        Db::update('password_resets', ['used_at' => now()], 'id = ?', [$r['id']]);
        Audit::log('user.password_reset', 'user', $r['user_id']);
        return true;
    }
}

/** An error whose message is safe to show to the user. */
final class UserError extends \RuntimeException {}
