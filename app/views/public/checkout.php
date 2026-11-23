<section class="section tight"><div class="wrap grid g2" style="align-items:start">
  <form class="card" method="post" action="/checkout"><?= App\Csrf::field() ?>
    <h1 style="font-size:1.6rem">Checkout</h1>
    <?php if (!$user): ?>
      <p class="small muted">Create your account to track your order. Already have one? <a href="/login">Sign in</a>.</p>
      <div class="grid g2"><div class="field"><label for="n">Full name</label><input id="n" name="name" type="text" required autocomplete="name"></div><div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div></div>
      <div class="grid g2"><div class="field"><label for="pw">Password</label><input id="pw" name="password" type="password" required minlength="10" autocomplete="new-password"><div class="hint">10+ characters, letters and numbers.</div></div><div class="field"><label for="ph">Phone</label><input id="ph" name="phone" type="tel" autocomplete="tel"></div></div>
      <div class="field"><label for="bn">Business name (if any)</label><input id="bn" name="business_name" type="text"></div>
    <?php else: ?><p>Signed in as <b><?= e($user['email']) ?></b>.</p><?php endif; ?>
    <div class="field"><label for="notes">Anything we should know? (optional)</label><textarea id="notes" name="notes" style="min-height:70px"></textarea></div>
    <?php if ($stripe): ?><fieldset class="field" style="border:none;padding:0"><legend style="font-weight:600;font-size:.9rem">Payment</legend>
      <label class="check"><input type="radio" name="method" value="card" checked> Card or wallet (secure Stripe Checkout)</label><label class="check"><input type="radio" name="method" value="invoice"> Pay by invoice / e-Transfer</label></fieldset>
    <?php else: ?><div class="alert alert-info">Online card payment isn't switched on yet. Place your order and we'll email an invoice. Work starts once it's paid.</div><input type="hidden" name="method" value="invoice"><?php endif; ?>
    <label class="check"><input type="checkbox" name="agree_terms" value="1" required> I agree to the <a href="/legal/terms" target="_blank">Terms</a> and <a href="/legal/refunds" target="_blank">Refund policy</a>.</label>
    <?php if (!$user): ?><label class="check"><input type="checkbox" name="consent_marketing" value="1"> Send me occasional tips and offers (optional)</label><?php endif; ?>
    <button class="btn btn-primary" style="width:100%;margin-top:16px"><?= $stripe ? 'Continue to secure payment' : 'Place order' ?> · <?= money($t['due_today']) ?></button>
  </form>
  <aside class="card flat"><h2 style="font-size:1.1rem">Order summary</h2>
    <?php foreach ($t['lines'] as $l): ?><div class="row spread small" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><span><?= e($l['name']) ?></span><b><?= money($l['unit_cents'] + $l['setup_cents']) ?></b></div><?php endforeach; ?>
    <div class="row spread" style="margin-top:12px"><span>Due today</span><span class="price"><?= money($t['due_today']) ?></span></div></aside>
</div></section>
