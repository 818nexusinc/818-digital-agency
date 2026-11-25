<?php
declare(strict_types=1);
namespace App;

/**
 * User management from the Control Tower: invite team members, change roles and access, unlock accounts,
 * resend password-reset, set-password and sign-in emails, reset two-step and passkeys, and sign people out everywhere.
 *
 * Who can manage whom: the owner manages everyone except their own role and access. Admins (with settings.write)
 * manage staff and customers. Nobody can make a second owner here. Every action is written to the audit log.
 */
final class Users
{
    public const ROLE_NAMES = ['owner' => 'Owner', 'admin' => 'Admin', 'staff' => 'Staff', 'customer' => 'Customer'];
    public const STATUS_NAMES = ['active' => 'Active', 'suspended' => 'Suspended'];

    /** Can $actor manage $target at all? */
    public static function canManage(array $actor, array $target): bool
    {
        if ((int)$actor['id'] === (int)$target['id']) return false;
        if ($target['role'] === 'owner') return false;
        if ($actor['role'] === 'owner') return true;
        return Rbac::can($actor, 'settings.write') && in_array($target['role'], ['staff', 'customer'], true);
    }

    /** Roles $actor may give to someone. */
    public static function assignable(array $actor): array
    {
        return $actor['role'] === 'owner' ? ['admin', 'staff', 'customer'] : ['staff', 'customer'];
    }

    public static function get(int $id): array
    {
        $u = Db::one('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) throw new HttpError(404, 'User not found.');
        return $u;
    }

    private static function guard(array $actor, array $target): void
    {
        if ((int)$actor['id'] === (int)$target['id']) throw new UserError('You can\'t change your own access here. Use Sign-in & recovery for your own account.');
        if (!self::canManage($actor, $target)) throw new HttpError(403, $target['role'] === 'owner' ? 'The owner account can only be changed by the owner, from their own Sign-in & recovery page.' : 'Only the owner can manage admin accounts.');
    }

    /** List with filters: group (team|customers|all), q (name/email), status, locked. */
    public static function search(string $group, string $q, string $status, int $limit = 200): array
    {
        $w = []; $a = [];
        if ($group === 'team') $w[] = "u.role <> 'customer'";
        elseif ($group === 'customers') $w[] = "u.role = 'customer'";
        if ($q !== '') { $w[] = '(LOWER(u.email) LIKE ? OR LOWER(u.name) LIKE ?)'; $a[] = '%' . mb_strtolower($q) . '%'; $a[] = '%' . mb_strtolower($q) . '%'; }
        if ($status === 'locked') { $w[] = 'u.locked_until > ?'; $a[] = now(); }
        elseif (isset(self::STATUS_NAMES[$status])) { $w[] = 'u.status = ?'; $a[] = $status; }
        $rows = Db::all('SELECT u.id, u.email, u.name, u.role, u.status, u.mfa_enabled, u.failed_logins, u.locked_until, u.last_login_at, u.created_at,
                (SELECT COUNT(*) FROM passkeys p WHERE p.user_id = u.id) AS passkeys
            FROM users u' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . "
            ORDER BY CASE u.role WHEN 'owner' THEN 0 WHEN 'admin' THEN 1 WHEN 'staff' THEN 2 ELSE 3 END, u.name, u.id LIMIT " . max(1, min(1000, $limit)), $a);
        foreach ($rows as &$r) { $r['locked'] = $r['locked_until'] && $r['locked_until'] > now(); $r['invited'] = !$r['last_login_at']; }
        return $rows;
    }

    public static function counts(): array
    {
        return ['team' => (int)Db::val("SELECT COUNT(*) FROM users WHERE role <> 'customer'"), 'customers' => (int)Db::val("SELECT COUNT(*) FROM users WHERE role = 'customer'"),
            'locked' => (int)Db::val('SELECT COUNT(*) FROM users WHERE locked_until > ?', [now()]), 'suspended' => (int)Db::val("SELECT COUNT(*) FROM users WHERE status = 'suspended'")];
    }

    /** Everything shown on a person's page. */
    public static function detail(int $id): array
    {
        $u = self::get($id);
        $em = (string)$u['email'];
        return ['u' => $u, 'locked' => $u['locked_until'] && $u['locked_until'] > now(), 'passkeys' => Passkeys::forUser($id),
            'pwOff' => OwnerAccess::passwordOff($id), 'codesLeft' => OwnerAccess::codesLeft($id),
            'customer' => $u['role'] === 'customer' ? Db::one('SELECT * FROM customers WHERE user_id = ?', [$id]) : null,
            'resets' => Db::all('SELECT created_at, expires_at, used_at FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$id]),
            'emails' => Db::all("SELECT template, subject, status, created_at FROM email_log WHERE to_email = ? AND template IN ('password_reset','account_invite','signin_link') ORDER BY id DESC LIMIT 8", [$em]),
            'events' => Db::all('SELECT kind, detail, ip, created_at FROM security_events WHERE detail = ? OR detail LIKE ? OR detail = ? OR detail LIKE ? ORDER BY id DESC LIMIT 15', [$em, $em . ' %', (string)$id, $id . ' %']),
            'audit' => self::withActors(Db::all("SELECT action, actor_type, actor_id, created_at FROM audit_log WHERE subject_type = 'user' AND (subject_id = ? OR subject_id = ?) ORDER BY id DESC LIMIT 15", [(string)$id, $em]))];
    }

    private static function withActors(array $rows): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn($r) => $r['actor_type'] === 'user' && ctype_digit((string)$r['actor_id']) ? (int)$r['actor_id'] : 0, $rows))));
        $names = $ids ? array_column(Db::all('SELECT id, name FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids), 'name', 'id') : [];
        foreach ($rows as &$r) $r['actor_name'] = $r['actor_type'] === 'user' ? ($names[(int)$r['actor_id']] ?? null) : null;
        return $rows;
    }

    /* ── Actions ── */

    /** Invite a team member (or add a customer): creates the account and emails a link to choose a password (valid 72 hours). */
    public static function invite(array $actor, string $name, string $email, string $role): int
    {
        $email = strtolower(trim($email)); $name = trim($name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UserError('Enter a valid email address.');
        if (!in_array($role, self::assignable($actor), true)) throw new UserError('You can\'t give that role.');
        if (Db::val('SELECT 1 FROM users WHERE email = ?', [$email])) throw new UserError('Someone already has an account with that email. Open their page to change their access or resend their email.');
        $id = Db::tx(function () use ($email, $name, $role) {
            $id = Db::insert('users', ['email' => $email, 'name' => $name ?: $email, 'password_hash' => Auth::hash(bin2hex(random_bytes(24))), 'role' => $role, 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            if ($role === 'customer' && !Db::val('SELECT 1 FROM customers WHERE email = ?', [$email]))
                try { Db::insert('customers', ['user_id' => $id, 'email' => $email, 'name' => $name ?: $email, 'created_at' => now(), 'updated_at' => now()]); } catch (\Throwable) {}
            return $id;
        });
        self::sendInvite($actor, $id);
        Audit::log('user.invited', 'user', $id, ['role' => $role, 'email' => $email], 'user', (string)$actor['id']);
        return $id;
    }

    /** Resend the set-your-password email (72 hours). Older unused links stop working. */
    public static function sendInvite(array $actor, int $id): void
    {
        $u = self::get($id);
        if ((int)$actor['id'] !== (int)$id) self::guard($actor, $u);
        $t = self::newResetToken($id, 72 * 3600);
        Mailer::send('account_invite', (string)$u['email'], ['name' => self::first($u), 'link' => url('/reset/' . $t), 'role' => strtolower(self::ROLE_NAMES[$u['role']] ?? $u['role']), 'by' => (string)($actor['name'] ?: $actor['email'])], null, 'user', $id);
        Audit::log('user.invite_sent', 'user', $id, [], 'user', (string)$actor['id']);
    }

    /** Send a password-reset email now (valid 1 hour). Older unused links stop working. */
    public static function sendReset(array $actor, int $id): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if ($u['status'] !== 'active') throw new UserError('This account is suspended. Restore access first.');
        $t = self::newResetToken($id, 3600);
        Mailer::send('password_reset', (string)$u['email'], ['link' => url('/reset/' . $t)], null, 'user', $id);
        Audit::log('user.reset_sent', 'user', $id, [], 'user', (string)$actor['id']);
    }

    /** Email a one-time sign-in link (15 minutes). Team accounts only. */
    public static function sendSigninLink(array $actor, int $id): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if ($u['role'] === 'customer') throw new UserError('Sign-in links are for team accounts. Send a password reset instead.');
        if ($u['status'] !== 'active') throw new UserError('This account is suspended. Restore access first.');
        OwnerAccess::mailLink($u);
        Audit::log('user.signin_link_sent', 'user', $id, [], 'user', (string)$actor['id']);
    }

    /** Clear a password lockout and the failed-attempt limits for this email. */
    public static function unlock(array $actor, int $id): void
    {
        $u = self::get($id); self::guard($actor, $u);
        Db::update('users', ['failed_logins' => 0, 'locked_until' => null, 'updated_at' => now()], 'id = ?', [$id]);
        try { Db::run('DELETE FROM rate_limits WHERE rkey IN (?, ?, ?)', ['login:' . strtolower((string)$u['email']), 'mfa:' . $id, 'recovery:u' . $id]); } catch (\Throwable) {}
        Audit::log('user.unlocked', 'user', $id, [], 'user', (string)$actor['id']);
    }

    public static function setRole(array $actor, int $id, string $role): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if (!in_array($role, self::assignable($actor), true)) throw new UserError('You can\'t give that role.');
        if ($role === $u['role']) return;
        Db::update('users', ['role' => $role, 'updated_at' => now()], 'id = ?', [$id]);
        self::signOutEverywhere($id);
        Audit::log('user.role_changed', 'user', $id, ['from' => $u['role'], 'to' => $role], 'user', (string)$actor['id']);
    }

    public static function setStatus(array $actor, int $id, string $status): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if (!isset(self::STATUS_NAMES[$status])) throw new UserError('Unknown status.');
        Db::update('users', ['status' => $status, 'updated_at' => now()], 'id = ?', [$id]);
        if ($status !== 'active') self::signOutEverywhere($id);
        Audit::log($status === 'active' ? 'user.restored' : 'user.suspended', 'user', $id, [], 'user', (string)$actor['id']);
    }

    public static function rename(array $actor, int $id, string $name): void
    {
        $u = self::get($id); self::guard($actor, $u);
        $name = trim(mb_substr($name, 0, 190)); if ($name === '') throw new UserError('Enter a name.');
        Db::update('users', ['name' => $name, 'updated_at' => now()], 'id = ?', [$id]);
        Audit::log('user.renamed', 'user', $id, [], 'user', (string)$actor['id']);
    }

    /** Turn two-step off (they set it up again) and remove its recovery codes. */
    public static function resetMfa(array $actor, int $id): void
    {
        $u = self::get($id); self::guard($actor, $u);
        Db::update('users', ['mfa_secret_enc' => null, 'mfa_enabled' => 0, 'updated_at' => now()], 'id = ?', [$id]);
        Settings::set('mfa.recovery.' . $id, ''); Settings::set('mfa.paused.' . $id, '');
        Audit::log('user.mfa_reset', 'user', $id, [], 'user', (string)$actor['id']);
        Audit::security('mfa.reset_by_admin', $id . ' · by ' . $actor['email']);
    }

    /** Remove every passkey and turn password sign-in back on, so a reset email gets them in again. */
    public static function resetPasskeys(array $actor, int $id): void
    {
        $u = self::get($id); self::guard($actor, $u);
        Db::run('DELETE FROM passkeys WHERE user_id = ?', [$id]);
        Settings::set('auth.password_off.' . $id, '0'); Settings::set('auth.recovery.' . $id, '');
        self::signOutEverywhere($id);
        Audit::log('user.passkeys_reset', 'user', $id, [], 'user', (string)$actor['id']);
    }

    /** Set a new password for someone (they can be asked to choose their own at next sign-in). */
    public static function setPassword(array $actor, int $id, string $pw, string $confirm, bool $mustChange): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if ($pw !== $confirm) throw new UserError('The two passwords don\'t match.');
        if ($p = Auth::passwordProblem($pw)) throw new UserError($p);
        Db::update('users', ['password_hash' => Auth::hash($pw), 'failed_logins' => 0, 'locked_until' => null, 'updated_at' => now()], 'id = ?', [$id]);
        Db::run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $id]);
        Settings::set('auth.password_off.' . $id, '0');
        Settings::set('auth.must_change.' . $id, $mustChange ? '1' : '');
        self::signOutEverywhere($id);
        Audit::log('user.password_set', 'user', $id, ['must_change' => $mustChange], 'user', (string)$actor['id']);
        Audit::security('password.set_by_admin', $id . ' · by ' . $actor['email']);
    }

    /** Change your own password (current password required, unless they were told to choose a new one after an admin set it). */
    public static function changeOwn(array $me, string $current, string $pw, string $confirm): void
    {
        $id = (int)$me['id'];
        $forced = Settings::get('auth.must_change.' . $id) === '1';
        if (!$forced && !password_verify($current, (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$id]))) {
            if (!RateLimit::hit('pwchange:' . $id, 8, 900)) throw new UserError('Too many tries. Wait 15 minutes.');
            throw new UserError('Your current password is not right.');
        }
        if ($pw !== $confirm) throw new UserError('The two new passwords don\'t match.');
        if ($forced && password_verify($pw, (string)Db::val('SELECT password_hash FROM users WHERE id = ?', [$id]))) throw new UserError('Choose a password different from the one you were given.');
        if ($p = Auth::passwordProblem($pw)) throw new UserError($p);
        Db::update('users', ['password_hash' => Auth::hash($pw), 'updated_at' => now()], 'id = ?', [$id]);
        Settings::set('auth.must_change.' . $id, '');
        self::signOutEverywhere($id);          // other devices sign in again with the new password
        Session::start(); $_SESSION['auth_mt'] = microtime(true) + 0.001; $_SESSION['auth_at'] = time();   // this one stays signed in
        Audit::log('user.password_changed', 'user', $id);
    }

    public static function mustChange(int $id): bool { return Settings::get('auth.must_change.' . $id) === '1'; }

    /**
     * Delete an account permanently. Their orders, invoices, filings and customer record stay (unlinked from the login);
     * passkeys, reset links and notifications go with it. The audit log keeps a record. Confirm by typing their email.
     */
    public static function delete(array $actor, int $id, string $typedEmail): void
    {
        $u = self::get($id); self::guard($actor, $u);
        if (strtolower(trim($typedEmail)) !== strtolower((string)$u['email'])) throw new UserError('Type their email address exactly to confirm the delete.');
        Db::tx(function () use ($id) {
            foreach (['password_resets', 'passkeys', 'login_links', 'notifications'] as $t) try { Db::run("DELETE FROM {$t} WHERE user_id = ?", [$id]); } catch (\Throwable) {}
            foreach (['customers', 'conversations'] as $t) try { Db::run("UPDATE {$t} SET user_id = NULL WHERE user_id = ?", [$id]); } catch (\Throwable) {}
            Db::run('DELETE FROM users WHERE id = ?', [$id]);
            foreach (['auth.epoch.', 'auth.must_change.', 'auth.password_off.', 'auth.recovery.', 'mfa.recovery.', 'mfa.paused.', 'passkey.handle.'] as $k) Db::run('DELETE FROM settings WHERE skey = ?', [$k . $id]);
        });
        Settings::flush();
        Audit::log('user.deleted', 'user', $id, ['email' => $u['email'], 'name' => $u['name'], 'role' => $u['role']], 'user', (string)$actor['id']);
    }

    /** End every signed-in session of this user on their next page load. */
    public static function signOutEverywhere(int $id): void
    {
        Settings::set('auth.epoch.' . $id, sprintf('%.4F', microtime(true)));
    }

    /** A new reset/set-password token; older unused ones are cancelled. */
    private static function newResetToken(int $id, int $ttl): string
    {
        Db::run('UPDATE password_resets SET used_at = ? WHERE user_id = ? AND used_at IS NULL', [now(), $id]);
        $t = rid(24);
        Db::insert('password_resets', ['user_id' => $id, 'token_hash' => hash('sha256', $t), 'expires_at' => now($ttl), 'created_at' => now()]);
        return $t;
    }

    private static function first(array $u): string { $n = trim((string)$u['name']); return $n !== '' && $n !== $u['email'] ? explode(' ', $n)[0] : 'there'; }
}
