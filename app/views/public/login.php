<section class="auth"><div class="wrap auth-grid">
  <div class="auth-copy"><h1 data-split>Welcome back.</h1><p>Track your filings, documents and orders in one place.</p>
    <ul class="auth-pts"><li>Filing status, live</li><li>Documents ready to download</li><li>Support from a real person</li></ul></div>
  <div class="auth-card">
    <h2>Log in</h2>
    <button type="button" class="btn btn-primary btn-lg auth-btn pk-btn" data-passkey-login hidden>
      <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="8" r="4"/><path d="M10.8 10.8 20 20m-4-4 2-2m-4.5-.5 2-2"/></svg>
      Sign in with a passkey</button>
    <p class="pk-msg" data-passkey-msg role="status" hidden></p>
    <div class="auth-or" data-passkey-or hidden><span>or use your password</span></div>
    <form method="post" action="/login"><?= App\Csrf::field() ?>
      <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="username webauthn"></div>
      <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required autocomplete="current-password"></div>
      <button class="btn btn-lg auth-btn" data-pw-btn>Log in</button>
    </form>
    <div class="auth-links"><a href="/forgot">Forgot password?</a><a href="/login/link">Email me a sign-in link</a></div>
    <div class="auth-links"><span>New here? <a href="/signup">Create an account</a></span></div>
  </div>
</div></section>
