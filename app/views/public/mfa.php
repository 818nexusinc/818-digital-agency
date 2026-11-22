<section class="section"><div class="wrap" style="max-width:440px"><form class="card" method="post" action="/login/mfa"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.5rem">Two-step verification</h1><p class="muted small">Enter the 6-digit code from your authenticator app.</p>
  <div class="field"><label for="c">Code</label><input id="c" name="code" type="text" inputmode="numeric" pattern="[0-9 ]{6,7}" autocomplete="one-time-code" required></div><button class="btn btn-primary" style="width:100%">Verify</button></form></div></section>
