<section class="section tight"><div class="wrap">
  <h1>Your cart</h1>
  <?php if (!$t['lines']): ?><div class="empty">Your cart is empty. <a href="/services">Browse services</a> or <button class="btn btn-ghost btn-sm" type="button" data-chat="">ask the assistant</button> to build a package for you.</div>
  <?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Service</th><th>Billing</th><th style="text-align:right">Price</th><th></th></tr></thead><tbody>
  <?php foreach ($t['lines'] as $l): ?><tr><td><b><?= e($l['name']) ?></b><?= $l['package'] ? ' <span class="badge b-blue">bundle</span>' : '' ?><?= $l['setup_cents'] ? '<div class="small muted">+ ' . money($l['setup_cents']) . ' one-time setup</div>' : '' ?></td>
    <td><?= e(label($l['billing'])) ?></td><td style="text-align:right"><?= money($l['unit_cents'] * $l['qty']) ?><?= $l['billing'] === 'monthly' ? '/mo' : ($l['billing'] === 'yearly' ? '/yr' : '') ?></td>
    <td><form method="post" action="/cart/remove"><?= App\Csrf::field() ?><input type="hidden" name="index" value="<?= (int)$l['index'] ?>"><button class="btn btn-ghost btn-sm" aria-label="Remove <?= e($l['name']) ?>">Remove</button></form></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="grid g2" style="margin-top:20px;align-items:start">
    <form class="card flat" method="post" action="/cart/coupon"><?= App\Csrf::field() ?><label for="cp">Promo code</label><div class="row"><input id="cp" name="coupon" type="text" value="<?= e($t['coupon'] ?? '') ?>" style="flex:1"><button class="btn btn-ghost">Apply</button></div><?php if ($t['coupon']): ?><p class="small" style="color:var(--green)">Code <?= e($t['coupon']) ?> applied.</p><?php endif; ?></form>
    <div class="card">
      <div class="row spread"><span>One-time services</span><b><?= money($t['one_time']) ?></b></div>
      <?php if ($t['setup']): ?><div class="row spread"><span>Setup fees</span><b><?= money($t['setup']) ?></b></div><?php endif; ?>
      <?php if ($t['recurring_monthly']): ?><div class="row spread"><span>Monthly plans (first month)</span><b><?= money($t['recurring_monthly']) ?></b></div><?php endif; ?>
      <?php if ($t['recurring_yearly']): ?><div class="row spread"><span>Yearly items (first year)</span><b><?= money($t['recurring_yearly']) ?></b></div><?php endif; ?>
      <?php if ($t['discount']): ?><div class="row spread" style="color:var(--green)"><span>Savings</span><b>−<?= money($t['discount']) ?></b></div><?php endif; ?>
      <?php if ($t['tax']): ?><div class="row spread"><span>Tax</span><b><?= money($t['tax']) ?></b></div><?php endif; ?>
      <hr style="border:none;border-top:1px solid var(--line);margin:12px 0"><div class="row spread"><span>Due today</span><span class="price"><?= money($t['due_today']) ?></span></div>
      <?php if ($t['recurring_monthly'] || $t['recurring_yearly']): ?><p class="small muted">Then <?= $t['recurring_monthly'] ? money($t['recurring_monthly']) . '/month' : '' ?><?= $t['recurring_monthly'] && $t['recurring_yearly'] ? ' + ' : '' ?><?= $t['recurring_yearly'] ? money($t['recurring_yearly']) . '/year' : '' ?> until cancelled.</p><?php endif; ?>
      <?php if (App\Settings::get('tax.enabled') !== '1'): ?><p class="small muted">Sales tax, if applicable, is shown on your invoice.</p><?php endif; ?>
      <a class="btn btn-primary" style="width:100%;margin-top:10px" href="/checkout">Continue to checkout</a>
    </div>
  </div>
  <?php endif; ?>
</div></section>
