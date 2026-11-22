<section class="section"><div class="wrap" style="max-width:440px"><form class="card" method="post" action="/login"><?= App\Csrf::field() ?>
  <h1 style="font-size:1.6rem">Sign in</h1>
  <div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div>
  <div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required autocomplete="current-password"></div>
  <button class="btn btn-primary" style="width:100%">Sign in</button>
  <p class="small" style="margin-top:14px"><a href="/forgot">Forgot password?</a> · New here? <a href="/register">Create an account</a></p>
</form></div></section>
