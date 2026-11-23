<section class="section tight"><div class="wrap">
  <div class="row spread"><div><span class="eyebrow">Proposal <?= e($p['number']) ?> · v<?= (int)$p['version'] ?></span><h1 style="margin-top:8px"><?= e($p['title']) ?></h1></div><span class="badge <?= $p['status'] === 'accepted' ? 'b-green' : ($p['status'] === 'expired' || $p['status'] === 'superseded' ? 'b-red' : 'b-blue') ?>"><?= e(label($p['status'])) ?></span></div>
  <p class="muted">Prepared for <?= e($cust['business_name'] ?? $cust['name'] ?? 'you') ?> · valid until <?= e(substr((string)$p['expires_at'], 0, 10)) ?></p>
  <?php if ($p['scope']): ?><div class="card flat"><h2 style="font-size:1.1rem">Scope</h2><p><?= nl2br(e($p['scope'])) ?></p></div><?php endif; ?>
  <div class="table-wrap" style="margin-top:18px"><table><thead><tr><th>Item</th><th>Billing</th><th style="text-align:right">Price</th></tr></thead><tbody>
  <?php foreach ($p['items'] as $it): ?><tr><td><b><?= e($it['name']) ?></b></td><td><?= e(label($it['billing'])) ?></td><td style="text-align:right"><?= $it['unit_cents'] === null ? 'Custom quote' . (!empty($it['from']) ? ' (from ' . money((int)$it['from']) . ')' : '') : money((int)$it['unit_cents']) . ($it['setup_cents'] ? ' + ' . money((int)$it['setup_cents']) . ' setup' : '') ?></td></tr><?php endforeach; ?>
  </tbody></table></div>
  <div class="grid g2" style="margin-top:18px">
    <div class="card flat"><div class="row spread"><span>One-time</span><b><?= money((int)$p['one_time_cents']) ?></b></div><div class="row spread"><span>Recurring (per period)</span><b><?= money((int)$p['recurring_cents']) ?></b></div><?php if ($p['timeline']): ?><p class="small muted" style="margin-top:8px">Timeline: <?= e($p['timeline']) ?></p><?php endif; ?></div>
    <div class="card flat small"><p><b>Assumptions.</b> <?= e($p['assumptions']) ?></p><p><b>Exclusions.</b> <?= e($p['exclusions']) ?></p><p class="muted"><?= e($p['terms']) ?></p></div>
  </div>
  <?php if (in_array($p['status'], ['draft', 'sent'], true)): ?>
  <form method="post" action="/proposal/<?= e($p['public_token']) ?>/accept" style="margin-top:22px"><?= App\Csrf::field() ?><button class="btn btn-primary"><?= $p['needs_quote'] ? 'Accept & request final pricing' : 'Accept & continue to checkout' ?></button>
    <button class="btn btn-ghost" type="button" data-chat="I have a question about proposal <?= e($p['number']) ?>">Ask a question</button></form>
  <?php endif; ?>
</div></section>
