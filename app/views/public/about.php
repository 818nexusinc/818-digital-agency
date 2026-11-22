<?php $ph = ['img' => 'peggys-cove', 'title' => 'From idea to open for business', 'lead' => 'An Atlantic Canadian agency for registered, professional, online businesses.']; include APP_ROOT . '/views/partials/photo-head.php'; ?>
<section class="section tight"><div class="wrap prose">
  <p>818 Digital Agency is part of <b><?= e(App\Settings::get('company.legal')) ?></b>, based in <?= e(App\Settings::get('company.city')) ?>. Our sister products are CONARO, a services marketplace, and the CONARO AI Business Workforce.</p>
  <p>We run the agency on our own AI platform: specialised agents handle routine work around the clock, and the founder supervises quality, pricing and every sensitive decision. That lets us keep prices transparent and turnaround fast without cutting corners on accountability.</p>
  <h2>Contact</h2><p><a href="mailto:<?= e(App\Settings::get('company.email')) ?>"><?= e(App\Settings::get('company.email')) ?></a> · <a href="/contact">Contact form</a></p>
</div></section>
