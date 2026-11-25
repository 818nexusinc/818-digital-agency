<?php
use App\{Csrf, Users};
$u = $d['u']; $id = (int)$u['id']; $mine = $id === (int)$me['id'];
$t = fn(?string $x) => $x ? substr($x, 0, 16) . ' UTC' : '—';
$btn = function (string $action, string $label, string $cls = '', string $confirm = '') use ($id) {
    return '<form method="post" action="/admin/users/' . $id . '"' . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . Csrf::field() . '<input type="hidden" name="action" value="' . e($action) . '"><button class="btn btn-sm ' . e($cls) . '">' . e($label) . '</button></form>';
};
$tpl = ['password_reset' => 'Password reset', 'account_invite' => 'Choose your password', 'signin_link' => 'Sign-in link'];
$isTeam = $u['role'] !== 'customer';
?>
<div class="db um">
<div class="db-head"><div><h1><?= e($u['name']) ?></h1><p class="db-bc"><?= e($u['email']) ?> · <?= e(Users::ROLE_NAMES[$u['role']] ?? $u['role']) ?> · joined <?= e(substr((string)$u['created_at'], 0, 10)) ?></p></div>
  <a class="btn btn-ghost" href="/admin/users?group=<?= $isTeam ? 'team' : 'customers' ?>">All users</a></div>

<?php if ($mine): ?><div class="alert alert-info">This is your own account. Change how you sign in under <a href="/admin/sign-in">Sign-in &amp; recovery</a>.</div>
<?php elseif (!$can): ?><div class="alert alert-info"><?= $u['role'] === 'owner' ? 'The owner account can only be changed by the owner, from their own Sign-in & recovery page.' : 'Only the owner can manage admin accounts.' ?></div><?php endif; ?>

<div class="um-status">
  <div><span class="small muted">Access</span><b class="<?= $u['status'] === 'active' ? 'ok-t' : 'bad-t' ?>"><?= e(Users::STATUS_NAMES[$u['status']] ?? $u['status']) ?></b></div>
  <div><span class="small muted">Password sign-in</span><b class="<?= $d['locked'] ? 'bad-t' : '' ?>"><?= $d['pwOff'] ? 'Turned off' : ($d['locked'] ? 'Locked until ' . e(substr((string)$u['locked_until'], 11, 5)) . ' UTC' : ((int)$u['failed_logins'] ? (int)$u['failed_logins'] . ' failed tries' : 'OK')) ?></b></div>
  <div><span class="small muted">Two-step</span><b><?= $u['mfa_enabled'] ? 'On' : 'Off' ?></b></div>
  <div><span class="small muted">Passkeys</span><b><?= count($d['passkeys']) ?></b></div>
  <div><span class="small muted">Last sign-in</span><b><?= e($u['last_login_at'] ? substr((string)$u['last_login_at'], 0, 16) : 'Never') ?></b></div>
</div>

<?php if ($can): ?>
<div class="ds-grid">
  <section class="db-card">
    <h2>Help them get in</h2>
    <?php if ($u['status'] !== 'active'): ?><p class="warn-t">Access is suspended. Restore it before sending sign-in emails.</p><?php endif; ?>
    <div class="um-acts">
      <div><?= $btn('reset', 'Send password reset email', 'btn-primary') ?><p class="small muted">A link to choose a new password, valid 1 hour. Older links stop working.</p></div>
      <?php if (!$u['last_login_at']): ?><div><?= $btn('invite', 'Resend invite email') ?><p class="small muted">They haven't signed in yet. Sends the "choose your password" email again, valid 72 hours.</p></div><?php endif; ?>
      <?php if ($isTeam): ?><div><?= $btn('link', 'Send one-time sign-in link') ?><p class="small muted">Signs them in once, within 15 minutes.<?= $d['codesLeft'] ? ' They will also need one of their recovery codes.' : '' ?></p></div><?php endif; ?>
      <?php if ($d['locked'] || (int)$u['failed_logins']): ?><div><?= $btn('unlock', 'Unlock now') ?><p class="small muted">Clears the failed tries so they can sign in straight away.</p></div><?php endif; ?>
    </div>
    <?php if ($d['emails']): ?><h3>Recent emails</h3><ul class="um-log"><?php foreach ($d['emails'] as $m): ?><li><span><?= e($tpl[$m['template']] ?? $m['template']) ?></span><span class="small muted"><?= e($t($m['created_at'])) ?> · <?= e($m['status']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
  </section>

  <section class="db-card">
    <h2>Access</h2>
    <form method="post" action="/admin/users/<?= $id ?>" class="um-row"><?= Csrf::field() ?><input type="hidden" name="action" value="rename"><label>Name<input type="text" name="name" value="<?= e($u['name']) ?>" maxlength="190" required></label><button class="btn btn-sm">Save</button></form>
    <form method="post" action="/admin/users/<?= $id ?>" class="um-row" data-confirm="Change this person's role? They'll be signed out and asked to sign in again."><?= Csrf::field() ?><input type="hidden" name="action" value="role"><label>Role<select name="role"><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= $r === $u['role'] ? ' selected' : '' ?>><?= e(Users::ROLE_NAMES[$r]) ?></option><?php endforeach; ?></select></label><button class="btn btn-sm">Change role</button></form>
    <div class="um-acts">
      <?php if ($u['status'] === 'active'): ?>
        <div><?= $btn('suspend', 'Suspend access', 'btn-danger', 'Suspend ' . $u['name'] . '? They are signed out at once and can\'t sign in until you restore access. Nothing is deleted.') ?><p class="small muted">Blocks sign-in and ends their sessions. Their records stay.</p></div>
      <?php else: ?>
        <div><?= $btn('restore', 'Restore access', 'btn-primary') ?><p class="small muted">They can sign in again with their existing password or passkeys.</p></div>
      <?php endif; ?>
      <div><?= $btn('signout', 'Sign out everywhere', '', 'Sign ' . $u['name'] . ' out on every device?') ?><p class="small muted">Use if a device was lost or shared.</p></div>
    </div>
  </section>

  <section class="db-card">
    <h2>Two-step and passkeys</h2>
    <?php if ($d['passkeys']): ?><ul class="um-log"><?php foreach ($d['passkeys'] as $k): ?><li><span><?= e($k['name']) ?></span><span class="small muted">last used <?= e($t($k['last_used_at'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <div class="um-acts">
      <?php if ($u['mfa_enabled']): ?><div><?= $btn('mfa', 'Turn off two-step', '', 'Turn off two-step verification for ' . $u['name'] . '? Use this only if they lost their phone and recovery codes. They can set it up again after signing in.') ?><p class="small muted">For a lost phone. They set it up again after signing in.</p></div><?php endif; ?>
      <?php if ($d['passkeys'] || $d['pwOff']): ?><div><?= $btn('passkeys', 'Remove passkeys', '', 'Remove all of ' . $u['name'] . '\'s passkeys and turn password sign-in back on? Then send them a password reset.') ?><p class="small muted">Also turns password sign-in back on, so a reset email gets them in.</p></div><?php endif; ?>
      <?php if (!$u['mfa_enabled'] && !$d['passkeys'] && !$d['pwOff']): ?><p class="small muted">Nothing set up. <?= $isTeam ? 'Ask them to add a passkey under Settings → Sign-in & recovery.' : '' ?></p><?php endif; ?>
    </div>
  </section>

  <section class="db-card">
    <h2>History</h2>
    <?php if (!$d['audit'] && !$d['events']): ?><p class="small muted">Nothing recorded yet.</p><?php endif; ?>
    <?php $hist = [];
      foreach ($d['audit'] as $a) $hist[] = [$a['created_at'], ucfirst(str_replace(['user.', '_'], ['', ' '], (string)$a['action'])), $a['actor_name'] ? 'by ' . $a['actor_name'] : ''];
      foreach ($d['events'] as $ev) $hist[] = [$ev['created_at'], ucfirst(str_replace(['.', '_'], ' ', (string)$ev['kind'])), (string)$ev['ip']];
      usort($hist, fn($a, $b) => strcmp((string)$b[0], (string)$a[0])); ?>
    <ul class="um-log">
      <?php foreach (array_slice($hist, 0, 20) as [$at, $what, $who]): ?><li><span><?= e($what) ?><?= $who !== '' ? ' <span class="small muted">' . e($who) . '</span>' : '' ?></span><span class="small muted"><?= e($t($at)) ?></span></li><?php endforeach; ?>
    </ul>
  </section>
</div>
<?php endif; ?>
<?php if ($d['customer']): ?><p style="margin-top:16px"><a class="db-more" href="/admin/customers/<?= (int)$d['customer']['id'] ?>">Open their customer record</a></p><?php endif; ?>
</div>
