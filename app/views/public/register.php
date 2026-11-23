<section class="auth"><div class="wrap auth-grid">
  <div class="auth-copy"><h1 data-split>Your business starts here.</h1><p>One account for registration, filings, your website and every document.</p>
    <ul class="auth-pts"><li>Free to create</li><li>Orders and filings tracked live</li><li>Canadian team, real support</li></ul></div>
  <form class="auth-card" method="post" action="/signup"><?= App\Csrf::field() ?>
    <h2>Create your account</h2>
    <div class="field"><label for="n">Full name</label><input id="n" name="name" type="text" required autocomplete="name"></div>
    <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div>
    <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required minlength="10" autocomplete="new-password"><div class="hint">At least 10 characters with letters and numbers.</div></div>
    <div class="field"><label for="bn">Business name (optional)</label><input id="bn" name="business_name" type="text"></div>
    <label class="check"><input type="checkbox" name="consent_marketing" value="1"> Send me occasional tips and offers</label>
    <button class="btn btn-primary btn-lg auth-btn">Create account</button>
    <div class="auth-links"><span>Already registered? <a href="/login">Log in</a></span></div>
  </form>
</div></section>
