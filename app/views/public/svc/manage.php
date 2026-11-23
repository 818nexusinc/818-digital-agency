<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$fromP = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$hero = ['tone' => 'blue', 'pills' => ['Every province + federal', 'Deadline reminders'],
  'title' => 'Stay in good standing. Without the paperwork.', 'sub' => 'Annual returns, corporate changes and official documents, filed for you.',
  'actions' => [['#services', 'View all services', true]],
  'media' => ['img' => 'docs-typing'], 'chips' => [['calendar', 'Never miss a deadline', 'Reminders before it is due'], ['certificate', 'Official documents', 'Ordered from the registry']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$cards = [
  ['/manage-business/annual-returns', 'calendar', 'blue', '#2457d6', 'Compliance', 'Annual Return Services', 'Stay compliant: file your annual return on time and avoid costly penalties.', $fromP('bm_annual'), 'Per annual return filing', ['Corporation annual return', 'Business name renewal', 'Compliance check']],
  ['/manage-business/corporate-changes', 'edit', 'amber', '#b86a00', 'Corporate updates', 'Corporate Changes', 'Update your corporate records: directors, officers, addresses and more.', $fromP('bm_changes'), 'Per corporate change', ['Registered address change', 'Director appointment / resignation', 'Officer change']],
  ['/manage-business/corporate-documents', 'certificate', 'violet', '#6d3fd8', 'Official records', 'Corporate Documents', 'Order official corporate documents directly from the registry.', $fromP('bm_documents'), 'Per document order', ['Certificate of status', 'Corporate profile report', 'Certified copies']],
  ['/manage-business/name-services', 'text', 'teal', '#0f7c74', 'Name services', 'Business Name Management', 'Renew or change your registered business name without losing your history.', $fromP('biz_name'), 'Per renewal or change', ['Business name renewal', 'Business name change']],
];
?>
<section class="feature-row-wrap"><div class="wrap feature-row">
  <?php foreach ([['calendar', 'teal', 'Stay compliant', 'Annual returns filed on time, with reminders before each deadline.'], ['edit', 'blue', 'Keep records current', 'Corporate changes filed correctly with the registry.'], ['certificate', 'violet', 'Official documents', 'Certified copies ordered for banking and legal use.'], ['refresh', 'amber', 'Renewal tracking', 'Business name renewals tracked so they never lapse.']] as [$ic, $tone, $t, $d]): ?>
  <div><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><b><?= e($t) ?></b><p><?= e($d) ?></p></span></div>
  <?php endforeach; ?>
</div></section>
<section class="sec" id="services"><div class="wrap">
  <div class="sec-head center"><h2>What would you like to do?</h2><p>Choose a service to file, update or order. Everything is submitted to the right registry for you.</p></div>
  <div class="hub-grid">
  <?php foreach ($cards as $i => [$href, $ic, $tone, $col, $flag, $t, $d, $price, $sub, $list]): ?>
    <article class="hub-card" data-rv id="mc-<?= $i ?>" style="--tone:<?= $col ?>">
      <div class="hc-top"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="hc-flag"><?= e($flag) ?></span></div>
      <h3><?= e($t) ?></h3><p class="hc-d"><?= e($d) ?></p>
      <p class="hc-price"><b><?= $price !== null ? 'From ' . e(money($price)) : 'Quote' ?></b><small><?= e($sub) ?></small></p>
      <ul class="hc-list"><?php foreach ($list as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul>
      <div class="hc-actions"><a class="btn btn-primary" href="<?= e($href) ?>">Get started</a></div>
    </article>
  <?php endforeach; ?>
  </div>
  <div class="quiz"><h3>Not sure where to start?</h3><p><b>Annual returns</b> if your corporation's anniversary date is coming up. <b>Corporate changes</b> if your address, directors or officers have changed. <b>Documents</b> if a bank or lawyer has asked for a certified record. <b>Name services</b> if your trade name registration is expiring.</p>
    <div class="quiz-grid">
      <?php foreach ([[0, 'calendar', 'blue', 'Anniversary date coming up', 'Annual returns'], [1, 'edit', 'amber', 'Address or director changed', 'Corporate changes'], [2, 'certificate', 'violet', 'Bank needs a document', 'Corporate documents'], [3, 'text', 'teal', 'Trade name expiring', 'Name services']] as [$i, $ic, $tone, $s, $b]): ?>
      <a href="#mc-<?= $i ?>" data-jump="mc-<?= $i ?>"><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span><small><?= e($s) ?></small><b><?= e($b) ?> →</b></span></a>
      <?php endforeach; ?>
    </div></div>
</div></section>
