<section class="section"><div class="wrap center" style="max-width:720px">
  <div class="svc-icon" style="margin:0 auto 16px;background:var(--green-soft);color:var(--green)">✓</div>
  <h1><?= $invoice ? 'Order received' : 'Thank you, payment is processing' ?></h1>
  <p class="lead" style="margin:0 auto">Order <b><?= e($o['number']) ?></b>. <?= $invoice ? "We'll email your invoice shortly. Work starts as soon as it's paid." : "We've sent a confirmation email. Your projects open automatically once Stripe confirms the payment, usually within a minute." ?></p>
  <div class="row" style="justify-content:center;margin-top:24px"><a class="btn btn-primary" href="/portal/orders/<?= e($o['number']) ?>">Track your order</a><a class="btn btn-ghost" href="/portal">Go to my portal</a></div>
</div></section>
