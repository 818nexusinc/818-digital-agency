<?php
use App\Csrf;
$n = count($keys);
$ready = $n >= 1 && $codesLeft >= 1;
$when = fn(?string $d) => $d ? substr($d, 0, 16) . ' UTC' : 'Never';
?>
<div class="db ds si">
<div class="db-head"><div><h1>Sign-in &amp; recovery</h1><p class="db-bc">Sign in with Face ID, a fingerprint, a PIN or a security key instead of a password. Nothing to guess, nothing to phish, no lockouts</p></div></div>

<?php if ($codes): ?>
<section class="db-card ds-code" aria-labelledby="rc-h">
  <h2 id="rc-h">Your sign-in recovery codes</h2>
  <p>Print these or save them in your password manager <b>now</b>. They are shown only once. If you ever lose every passkey, open an emailed sign-in link and enter one of these codes. Each works once. Your old codes stopped working.</p>
  <ol class="si-codes" data-code><?php foreach ($codes as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ol>
  <div class="ds-acts"><button type="button" class="btn btn-primary btn-sm" data-copy-code>Copy</button><button type="button" class="btn btn-sm" data-print>Print</button></div>
</section>
<?php endif; ?>

<div class="si-steps">
  <div class="si-step <?= $n ? 'done' : '' ?>"><b><?= $n ? '✓' : '1' ?></b><span>Add a passkey on your phone</span></div>
  <div class="si-step <?= $n >= 2 ? 'done' : '' ?>"><b><?= $n >= 2 ? '✓' : '2' ?></b><span>Add a second one (laptop or security key)</span></div>
  <div class="si-step <?= $codesLeft ? 'done' : '' ?>"><b><?= $codesLeft ? '✓' : '3' ?></b><span>Make recovery codes</span></div>
  <div class="si-step <?= $pwOff ? 'done' : '' ?>"><b><?= $pwOff ? '✓' : '4' ?></b><span>Turn off password sign-in</span></div>
</div>

<div class="ds-grid">
  <section class="db-card">
    <h2>Your passkeys</h2>
    <?php if ($keys): ?>
      <table class="tbl si-tbl"><thead><tr><th>Name</th><th>Added</th><th>Last used</th><th></th></tr></thead><tbody>
      <?php foreach ($keys as $k): ?><tr>
        <td><b><?= e($k['name']) ?></b><?= $k['backed_up'] ? '<span class="small muted"> · synced to your cloud account</span>' : '' ?></td>
        <td><?= e($when($k['created_at'])) ?></td><td><?= e($when($k['last_used_at'])) ?></td>
        <td><form method="post" data-confirm="Remove the passkey &quot;<?= e($k['name']) ?>&quot;? That device will no longer sign you in."><?= Csrf::field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= (int)$k['id'] ?>"><button class="btn btn-sm btn-ghost">Remove</button></form></td>
      </tr><?php endforeach; ?></tbody></table>
    <?php else: ?><p class="warn-t"><b>No passkeys yet.</b> Add one on the device you use most. Your phone is best: it can also sign you in on any computer by scanning a QR code.</p><?php endif; ?>
    <div class="si-add">
      <label>Name for this passkey<input type="text" data-passkey-name maxlength="120" placeholder="e.g. My iPhone, Office laptop, YubiKey"></label>
      <button type="button" class="btn btn-primary" data-passkey-add>Add a passkey</button>
    </div>
    <p class="pk-msg" data-passkey-add-msg role="status" hidden></p>
    <p class="small warn-t" data-passkey-unsupported>This browser can't make passkeys. Use a current version of Safari, Chrome, Edge or Firefox.</p>
    <p class="small muted">Passkeys are tied to <b><?= e($rp) ?></b>. The private key stays on your device; this site keeps only the public half, so even a copy of the database can't be used to sign in.<?= $recent ? '' : ' You\'ll be asked to sign in again before adding one.' ?></p>
  </section>

  <section class="db-card">
    <h2>If you lose your devices</h2>
    <p>Ask for an emailed sign-in link on the sign-in page, open it, then enter one of your recovery codes. Both are needed, so someone who gets into your email alone can't get in.</p>
    <?php if ($codesLeft): ?><p class="ok-t"><b><?= (int)$codesLeft ?> of 10 recovery codes left.</b></p><?php else: ?><p class="warn-t"><b>No recovery codes yet.</b></p><?php endif; ?>
    <form method="post" data-confirm="<?= $codesLeft ? 'Make new recovery codes? Your old codes will stop working.' : 'Make your recovery codes now? They are shown once.' ?>"><?= Csrf::field() ?><input type="hidden" name="action" value="codes"><button class="btn <?= $codesLeft ? '' : 'btn-primary' ?>"><?= $codesLeft ? 'Make new codes' : 'Make recovery codes' ?></button></form>
    <h3>No lockouts</h3>
    <p class="small">Wrong passwords can lock password sign-in for 15 minutes. Passkeys, email links and recovery codes never lock your account: they are only slowed down if a single network keeps failing.</p>
  </section>

  <section class="db-card">
    <h2>Password sign-in</h2>
    <?php if ($pwOff): ?>
      <p class="ok-t"><b>Off.</b> Nobody can sign in to your account with a password, even one that leaked.</p>
      <form method="post"><?= Csrf::field() ?><input type="hidden" name="action" value="password_on"><button class="btn">Turn password sign-in back on</button></form>
    <?php else: ?>
      <p><b>On.</b> Once you have a passkey and recovery codes, turn it off so a stolen or guessed password is useless.</p>
      <form method="post" data-confirm="Turn off password sign-in for your account? You'll sign in with a passkey, or an email link plus a recovery code."><?= Csrf::field() ?><input type="hidden" name="action" value="password_off"><button class="btn btn-primary" <?= $ready ? '' : 'disabled' ?>>Turn off password sign-in</button></form>
      <?php if (!$ready): ?><p class="small muted">Available after you add a passkey and make recovery codes.</p><?php endif; ?>
    <?php endif; ?>
    <?php if (!$pwOff): ?><p><a class="btn btn-sm" href="/account/password">Change my password</a></p><?php endif; ?>
    <p class="small muted">Two-step codes (authenticator app) still work for password sign-in. A passkey already counts as two steps: your device plus your face, fingerprint or PIN.</p>
  </section>
</div>
</div>
