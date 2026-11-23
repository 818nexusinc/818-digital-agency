<section class="auth"><div class="wrap auth-grid">
  <div class="auth-copy"><h1 data-split>Welcome back.</h1><p>Track your filings, documents and orders in one place.</p>
    <ul class="auth-pts"><li>Filing status, live</li><li>Documents ready to download</li><li>Support from a real person</li></ul></div>
  <form class="auth-card" method="post" action="/login"><?= App\Csrf::field() ?>
    <h2>Log in</h2>
    <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div>
    <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary btn-lg auth-btn">Log in</button>
    <div class="auth-links"><a href="/forgot">Forgot password?</a><span>New here? <a href="/signup">Create an account</a></span></div>
  </form>
</div></section>
