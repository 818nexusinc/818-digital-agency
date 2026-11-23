<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$svc = Catalog::find('sole_prop'); $pp = $svc['province_prices'] ?? [];
$from = $pp ? min(array_filter($pp, fn($v) => $v !== null)) : null;
$months = ['Dec 31', 'Jan 31', 'Feb 28', 'Mar 31', 'Apr 30', 'May 31', 'Jun 30', 'Jul 31', 'Aug 31', 'Sep 30', 'Oct 31', 'Nov 30'];
$hero = ['tone' => 'teal', 'pills' => ['Simplest structure', $svc['timeline'] ?? '1–3 business days'],
  'title' => 'Go solo. Get registered.', 'sub' => 'The fastest, lowest-cost way to make your business official. Ideal for freelancers and consultants.',
  'media' => ['img' => 'cafe-laptop'], 'chips' => [['clock', $svc['timeline'] ?? '1–3 business days', 'Typical registry time'], ['check', 'Government fee included', 'No surprise costs']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body"><div class="wrap">
<?= wz_open(['Province', 'Owner', 'Business', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <input type="hidden" name="slug" value="sole_prop">
  <?= wz_step(0, 'Province & business name', wz_provinces('province', $pp, 'Select province', false, 'NB')
      . '<div class="grid2">' . wz_field('entity[proposed_name]', 'Proposed business name *', 'text', ['data-rule' => 'bizname', 'placeholder' => 'e.g. Riverview Bookkeeping', 'data-wide' => 1, 'autocomplete' => 'organization']) . '</div>') ?>
  <?= wz_step(1, 'Owner details', '<div class="grid2">'
      . wz_field('owners[0][first]', 'First name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'given-name', 'placeholder' => 'Jane'])
      . wz_field('owners[0][last]', 'Last name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'family-name', 'placeholder' => 'Smith'])
      . wz_field('owners[0][email]', 'Email address *', 'email', ['autocomplete' => 'email', 'placeholder' => 'you@example.ca'])
      . wz_field('contact_phone', 'Phone number *', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel', 'placeholder' => '506 555 0100', 'data-label' => 'Phone'])
      . wz_field('registered_office[street]', 'Street address *', 'text', ['autocomplete' => 'street-address', 'data-wide' => 1, 'data-label' => 'Business address'])
      . wz_field('registered_office[city]', 'City *', 'text', ['autocomplete' => 'address-level2', 'placeholder' => 'Riverview'])
      . wz_field('registered_office[postal]', 'Postal code *', 'text', ['data-rule' => 'postal', 'autocomplete' => 'postal-code', 'placeholder' => 'E1B 3S3'])
      . '</div><input type="hidden" name="owners[0][resident_canadian]" value="1">') ?>
  <?= wz_step(2, 'Business details', '<div class="grid2">'
      . wz_field('entity[activity]', 'Business activity *', 'textarea', ['minlength' => 10, 'data-wide' => 1, 'placeholder' => 'Describe what your business does…'])
      . wz_field('entity[start_date]', 'Start date *', 'date')
      . wz_select('fiscal_year_end', 'Fiscal year end', array_combine($months, $months))
      . '</div>' . wz_addons(['addon_expedited'], 'Optional')) ?>
  <?= wz_review(3, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Registration summary', null, 'Government filing fee included', [['Province', '@province'], ['Delivery', $svc['timeline'] ?? '1–3 business days'], ['Filing', 'Provincial registry'], ['Certificate', 'Digital PDF'], ['Support', 'Email included']],
      ['Name registration with the provincial registry', 'Business confirmation document', 'Set-up guidance and next steps', 'Email support'], 'teal') ?>
  <?php include APP_ROOT . '/views/partials/svc/help.php'; ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What is a sole proprietorship?', 'A business owned and run by one person. There is no legal separation between you and the business: profits are your personal income and you are personally responsible for its debts.'],
  ['Do I need a business licence?', 'Registration gives you the right to use your business name. Your municipality or industry may also require a licence or permit. We include guidance on next steps with your confirmation.'],
  ['Can I use my own name?', 'In most provinces, if you operate under your exact legal name you do not need to register. Any other name, or your name with extra words, must be registered.'],
  ['Sole proprietorship or incorporation?', 'A sole proprietorship is the simplest and cheapest way to start. Incorporating separates your personal assets from the business and can help with taxes as you grow. You can incorporate later.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
