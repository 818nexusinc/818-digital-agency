<section class="section"><div class="wrap" style="max-width:440px"><form class="card" method="post" action="/login/mfa"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.5rem"><?= e(t('Two-step verification')) ?></h1><p class="muted small"><?= e(t('Enter the 6-digit code from your authenticator app.')) ?></p>
  <div class="field"><label for="c"><?= e(t('Code')) ?></label><input id="c" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="9" required autofocus></div>
  <button class="btn btn-primary" style="width:100%"><?= e(t('Verify')) ?></button>
  <details class="small" style="margin-top:14px"><summary><?= e(t('Lost your phone?')) ?></summary><p class="muted"><?= e(t('Type one of your recovery codes (like 1a2b-3c4d) in the box above instead. Each code works once.')) ?></p></details>
</form></div></section>
