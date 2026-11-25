<section class="section"><div class="wrap" style="max-width:460px"><form class="card" method="post" action="/login/link"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.5rem">Sign in without a password</h1>
  <p class="muted">For team accounts. We'll email you a link that works once, for 15 minutes. If you made sign-in recovery codes, keep one handy: you'll need it after opening the link.</p>
  <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="username"></div>
  <button class="btn btn-primary" style="width:100%">Email me a sign-in link</button>
  <p class="muted" style="margin-top:14px"><a href="/login">Back to sign in</a></p></form></div></section>
