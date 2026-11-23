<?php
include_once APP_ROOT . '/views/partials/icons.php';
$hero = ['tone' => 'rose', 'pills' => ['Moncton, New Brunswick'], 'title' => 'We make starting a business feel simple.', 'sub' => 'Registration, compliance and your online presence, handled by one Canadian team.',
  'media' => ['img' => 'owners-open'], 'chips' => [['building', 'Canadian-owned', 'Built in New Brunswick'], ['users', 'Real people', 'Reply within one business day']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec about2"><div class="wrap">
  <p class="ab-big" data-rv><?= e(t('From St. John’s to Victoria, every Canadian deserves a business that is easy to start and easy to run.')) ?></p>
  <div class="ab-cols">
    <?php foreach ([['Our mission', 'Make professional business services simple, fast and fairly priced for every Canadian.'],
      ['What we do', 'Registration, compliance, domains, email, websites and custom software. One account, clear prices.'],
      ['How we work', 'Our own AI platform handles routine work around the clock. Our Atlantic Canada team reviews quality, pricing and every sensitive decision.']] as $i => [$tt, $d]): ?>
      <div class="ab-col" data-rv><p class="ab-n"><?= sprintf('%02d', $i + 1) ?></p><h3><?= e(t($tt)) ?></h3><p><?= e(t($d)) ?></p></div>
    <?php endforeach; ?>
  </div>
  <dl class="ab-facts" data-rv>
    <div><dt><?= e(t('Registries we file with')) ?></dt><dd><?= count(App\Filings::JURISDICTIONS) ?></dd></div>
    <div><dt><?= e(t('Based in')) ?></dt><dd>Moncton, NB</dd></div>
    <div><dt><?= e(t('Operated by')) ?></dt><dd><?= e(App\Settings::get('company.legal')) ?></dd></div>
    <div><dt><?= e(t('Sister products')) ?></dt><dd>CONARO</dd></div>
  </dl>
</div></section>
