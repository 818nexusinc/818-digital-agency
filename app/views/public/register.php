<section class="section"><div class="wrap" style="max-width:480px"><form class="card" method="post" action="/register"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.6rem">Create your account</h1>
  <div class="field"><label for="n">Full name</label><input id="n" name="name" type="text" required autocomplete="name"></div>
  <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required minlength="10" autocomplete="new-password"><div class="hint">At least 10 characters with letters and numbers.</div></div>
  <div class="field"><label for="bn">Business name (optional)</label><input id="bn" name="business_name" type="text"></div>
  <label class="check"><input type="checkbox" name="consent_marketing" value="1"> Send me occasional tips and offers</label>
  <button class="btn btn-primary" style="width:100%;margin-top:14px">Create account</button>
  <p class="small" style="margin-top:14px">Already registered? <a href="/login">Sign in</a></p>
</form></div></section>
