<?php $SL = ['company.name' => 'Business name', 'company.legal' => 'Legal name', 'company.email' => 'Public email', 'company.phone' => 'Public phone', 'company.city' => 'City shown on the site', 'company.tagline' => 'Tagline', 'tax.enabled' => 'Charge sales tax (1 = yes, 0 = no)', 'tax.default_rate_bp' => 'Default tax rate (basis points, 1500 = 15%)', 'owner.notify_email' => 'Send owner alerts to', 'seo.google_verification' => 'Google Search Console verification code', 'seo.bing_verification' => 'Bing Webmaster verification code', 'seo.indexnow_key' => 'IndexNow key (auto)']; ?>
<div class="topbar"><h1>Settings &amp; team</h1></div>
<div class="grid g2" style="align-items:start"><form class="panel" method="post" action="/admin/settings"><?= App\Csrf::field() ?><input type="hidden" name="action" value="settings"><h2>Business settings</h2>
  <?php foreach ($vals as $k => $v): ?><div class="field"><label for="s-<?= e($k) ?>" class="small"><?= e($SL[$k] ?? ucfirst(str_replace(['.', '_'], [' — ', ' '], $k))) ?> <span class="muted" style="font-weight:400">· <?= e($k) ?></span></label><input id="s-<?= e($k) ?>" name="s[<?= e($k) ?>]" type="text" value="<?= e($v) ?>"></div><?php endforeach; ?>
  <p class="hint">Budgets are in USD cents (ai.daily_budget_cents 1500 = $15/day). Tax is in basis points (1500 = 15%). Tax is off by default; confirm your obligations with an accountant. A refund auto-limit of 0 means every refund needs a human.</p><button class="btn btn-primary btn-sm">Save settings</button></form>
<div><div class="panel mfa"><h2>Two-step verification</h2>
  <?php if ($recovery): ?>
    <div class="alert alert-success" role="status"><b>Two-step verification is on.</b> Save these recovery codes somewhere safe (a password manager or printed). Each one signs you in once if you lose your phone. They won't be shown again.</div>
    <ul class="mfa-codes"><?php foreach ($recovery as $c): ?><li><code><?= e($c) ?></code></li><?php endforeach; ?></ul>
  <?php elseif ($mp = App\Mfa::paused((int)$me['id'])): ?>
    <p class="small"><b>Paused for an update</b> on <?= e(substr($mp['at'], 0, 16)) ?> UTC. Turn it back on with your password and a code from the same authenticator app.</p>
    <form method="post" action="/admin/mfa/resume" class="up-inline" id="mfa"><?= App\Csrf::field() ?><input type="hidden" name="back" value="settings"><input type="password" name="password" autocomplete="current-password" required placeholder="Your password" aria-label="Your password"><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required placeholder="6-digit code" aria-label="Code from your app" maxlength="8"><button class="btn btn-primary btn-sm">Turn back on</button></form>
  <?php elseif ($me['mfa_enabled']): ?>
    <p class="small">On for your account. You'll be asked for a 6-digit code from your authenticator app at each sign-in.</p>
    <details class="small"><summary>Turn off or replace</summary>
      <form method="post" action="/admin/settings" class="stack" style="margin-top:8px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mfa_disable">
        <label>Current password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn btn-ghost btn-sm">Turn off two-step verification</button></form></details>
  <?php elseif ($mfa_secret): $uri = App\Totp::uri($mfa_secret, $me['email']); ?>
    <ol class="mfa-steps small">
      <li>Open Google Authenticator, Microsoft Authenticator, 1Password or Authy and choose <b>Scan a QR code</b>.</li>
      <li>Scan this code<span class="mfa-alt">, or tap <a href="<?= e($uri) ?>">open in my authenticator app</a> on this phone</span>.</li>
    </ol>
    <div class="mfa-qr" data-qr="<?= e($uri) ?>" role="img" aria-label="QR code for your authenticator app"></div>
    <details class="small"><summary>Can't scan? Type the key instead</summary><p>Choose "Enter a setup key", type <b>time-based</b>, and enter:</p><p class="mfa-key"><?= e(trim(chunk_split($mfa_secret, 4, ' '))) ?></p></details>
    <form method="post" action="/admin/settings" class="mfa-confirm"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mfa_confirm">
      <label for="mfa-code">3. Enter the 6-digit code the app shows</label>
      <div class="row"><input id="mfa-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" placeholder="123 456" required><button class="btn btn-primary btn-sm">Turn on</button></div></form>
    <p class="small muted">Server time: <span data-server-time="<?= time() ?>"><?= gmdate('H:i:s') ?> UTC</span><span data-clock-check></span></p>
    <form method="post" action="/admin/settings"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mfa_restart"><button class="btn btn-ghost btn-sm">Start again with a new code</button></form>
  <?php else: ?>
    <p class="small">Protects the owner account with a code from your phone in addition to your password. Takes about a minute.</p>
    <form method="post" action="/admin/settings"><?= App\Csrf::field() ?><input type="hidden" name="action" value="mfa_start"><button class="btn btn-primary btn-sm">Set up two-step verification</button></form>
  <?php endif; ?></div>
  <div class="panel"><h2>Team accounts</h2><?php foreach ($users as $u): ?><div class="row spread small" style="padding:5px 0;border-bottom:1px solid var(--line-2)"><span><b><?= e($u['name']) ?></b> <?= e($u['email']) ?></span><span><?= e($u['role']) ?> · MFA <?= $u['mfa_enabled'] ? '✓' : '✗' ?> · last login <?= e(substr((string)$u['last_login_at'], 0, 10) ?: 'never') ?></span></div><?php endforeach; ?>
    <?php if ($me['role'] === 'owner'): ?><form method="post" action="/admin/settings" style="margin-top:12px"><?= App\Csrf::field() ?><input type="hidden" name="action" value="user"><div class="grid g3"><input name="name" type="text" placeholder="Name"><input name="email" type="email" placeholder="Email" required><select name="role"><option>staff</option><option>admin</option></select></div><button class="btn btn-ghost btn-sm" style="margin-top:8px">Invite (sends password link)</button></form><?php endif; ?></div>
  <div class="panel"><h2>Role permissions</h2><p class="small muted">The owner always has full access. Agents' tool permissions are configured per agent and can't be changed by agents.</p>
    <?php $by = []; foreach ($perms as $p) $by[$p['role']][] = $p['permission']; foreach (['admin', 'staff'] as $role): ?><p class="small"><b><?= $role ?>:</b> <?= e(implode(', ', $by[$role] ?? [])) ?></p><?php endforeach; ?>
    <?php if ($me['role'] === 'owner'): ?><form method="post" action="/admin/settings" class="row"><?= App\Csrf::field() ?><input type="hidden" name="action" value="perm"><select name="role" style="width:auto"><option>staff</option><option>admin</option></select><select name="permission" style="width:auto"><?php foreach (App\Rbac::DEFAULTS['admin'] as $p): ?><option><?= e($p) ?></option><?php endforeach; ?></select><label class="check small"><input type="checkbox" name="grant" value="1" checked> grant (uncheck to revoke)</label><button class="btn btn-ghost btn-sm">Apply</button></form><?php endif; ?></div></div></div>
