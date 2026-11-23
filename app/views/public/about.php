<?php
include_once APP_ROOT . '/views/partials/icons.php';
$hero = ['tone' => 'rose', 'icon' => 'building', 'pills' => ['Our story'], 'title' => 'Built for Canadian Entrepreneurs', 'sub' => '818 Digital Agency was founded in Moncton, New Brunswick with a single mission: make it effortless for Canadians to start and grow a business.'];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="stats" aria-label="Facts">
  <div><span class="mc-ic tone-blue"><?= nx_icon('building') ?></span><b><?= count(App\Filings::JURISDICTIONS) ?></b><span>Registries we file with</span></div>
  <div><span class="mc-ic tone-violet"><?= nx_icon('stack') ?></span><b>6</b><span>Services, one dashboard</span></div>
  <div><span class="mc-ic tone-teal"><?= nx_icon('globe') ?></span><b>Moncton, NB</b><span>Atlantic Canada team</span></div>
  <div><span class="mc-ic tone-amber"><?= nx_icon('certificate') ?></span><b>818 Nexus Inc.</b><span>Registered Canadian corporation</span></div>
</section>
<section class="sec"><div class="wrap about-grid">
  <?php foreach ([['globe', 'blue', 'Our mission', 'We believe every Canadian deserves access to professional business services. From St. John’s to Victoria, we bring the same quality, speed and care to every customer.'],
    ['stack', 'violet', 'What we do', 'We combine business registration and management with domains, email, websites and custom software, in one account with clear prices.'],
    ['users', 'teal', 'How we work', 'Our own AI platform handles routine work around the clock, and our team in Atlantic Canada reviews quality, pricing and every sensitive decision. Nothing is filed without your signature.'],
    ['certificate', 'green', 'Trust & privacy', '818 Digital Agency is part of ' . App\Settings::get('company.legal') . ', a registered Canadian corporation. We handle personal information under Canadian privacy law and never store card details.']] as [$ic, $tone, $t, $d]): ?>
  <div><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></span></div>
  <?php endforeach; ?>
  <p style="margin-top:10px">Our sister products are <b>CONARO</b>, a services marketplace, and the <b>CONARO AI Business Workforce</b>.</p>
  <div class="hero-actions"><a class="btn btn-primary" href="/register">Start your business today</a><a class="btn btn-ghost" href="/contact">Contact us</a></div>
</div></section>
