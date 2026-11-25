<?php $uid = (int)App\Db::val('SELECT user_id FROM login_links WHERE token_hash = ?', [hash('sha256', $token)]); $step = App\OwnerAccess::linkSecondStep($uid); ?>
<section class="section"><div class="wrap" style="max-width:460px"><form class="card" method="post" action="/login/link/<?= e($token) ?>"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.5rem">Finish signing in</h1>
  <?php if ($step === 'code'): ?>
    <p class="muted">Enter one of your sign-in recovery codes. Each code works once.</p>
    <div class="field"><label for="rc">Recovery code</label><input id="rc" name="recovery_code" required autocomplete="one-time-code" placeholder="XXXXX-XXXXX" style="font-family:ui-monospace,monospace;letter-spacing:.08em;text-transform:uppercase"></div>
  <?php elseif ($step === 'mfa'): ?>
    <p class="muted">Next you'll be asked for the code from your authenticator app.</p>
  <?php else: ?>
    <p class="muted">Press the button to sign in. This link then stops working.</p>
  <?php endif; ?>
  <button class="btn btn-primary" style="width:100%">Sign in</button></form></div></section>
