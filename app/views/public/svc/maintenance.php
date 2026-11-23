<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$feat = ['maintenance_care' => ['Daily backups', 'Plugin and core updates', 'SSL certificate renewal', 'Uptime monitoring', '1 hour of content edits per month', 'Monthly performance report'],
  'maintenance_protect' => ['Everything in Care', 'Priority security scanning and malware removal', 'Monthly database optimisation', 'Speed optimisation', '2 hours of content edits per month', 'Priority email and chat support'],
  'maintenance_grow' => ['Everything in Protect', 'Weekly security scans', '4 hours of content edits per month', 'One landing page per quarter', 'Monthly SEO health check', 'Dedicated account manager']];
$cards = '';
foreach ($feat as $s => $list) { $x = Catalog::find($s); $c = Catalog::price($x, [])['unit_cents'] ?? 0; $n = preg_replace('/^Website /', '', $x['name']);
  $cards .= '<label class="ocard plan">' . ($s === 'maintenance_protect' ? '<span class="oc-flag">Most popular</span>' : '') . '<input type="radio" name="slug" value="' . $s . '" required data-label="Plan" data-text="' . e($n) . '" data-cents="' . $c . '" data-line="' . e($n) . '" data-per="/mo"' . ($s === 'maintenance_care' ? ' checked' : '') . '><span class="oc-top"><b>' . e($n) . '</b></span><span class="plan-price"><b>' . e(money($c)) . '</b>/month</span><ul class="oc-list">';
  foreach ($list as $li) $cards .= '<li>' . e($li) . '</li>'; $cards .= '</ul></label>'; }
$hero = ['tone' => 'violet', 'icon' => 'refresh', 'pills' => ['Daily backups', 'Uptime monitoring', 'Security patching', 'Monthly reports'], 'title' => 'Website Maintenance Plans', 'sub' => 'Ongoing care for your existing website: backups, updates, security patches and content edits. Cancel anytime.'];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body"><div class="wrap">
<?= wz_open(['Plan', 'Your website', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <?= wz_step(0, 'Choose a plan', '<fieldset class="cards three" data-label="Plan"><legend class="sr">Plan</legend><div class="card-grid">' . $cards . '</div><p class="fe"></p></fieldset>') ?>
  <?= wz_step(1, 'About your website', '<div class="grid2">'
      . wz_field('site[url]', 'Website address *', 'url', ['placeholder' => 'https://yourcompany.ca', 'data-wide' => 1])
      . wz_select('site[platform]', 'Platform *', ['wordpress' => 'WordPress', 'woocommerce' => 'WooCommerce', 'shopify' => 'Shopify', 'wix' => 'Wix / Squarespace', 'custom' => 'Custom / other', 'unknown' => 'Not sure'])
      . wz_select('site[built_by_us]', 'Did 818 build it?', ['no' => 'No', 'yes' => 'Yes'])
      . wz_field('site[contact_name]', 'Your name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'name'])
      . wz_field('site[contact_email]', 'Your email *', 'email', ['autocomplete' => 'email'])
      . wz_field('site[notes]', 'Anything we should know?', 'textarea', ['data-wide' => 1, 'placeholder' => 'Known issues, access details are shared securely later'])
      . '</div>') ?>
  <?= wz_review(2, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side"><?= wz_summary('Maintenance plan', null, 'Billed monthly · cancel anytime', [['Plan', '@slug']], ['Activated after checkout', 'Onboarding call to collect access securely'], 'violet') ?></div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['Do I need a plan if 818 built my website?', 'Website subscriptions already include hosting and updates. A maintenance plan adds extra edits, security and reporting.'],
  ['Can I add a plan to a website not built by 818?', 'Yes. We support most WordPress, WooCommerce and custom sites; we review your site before activating.'],
  ['What happens if my site goes down?', 'Uptime monitoring alerts us and we start restoring it, from backup if needed.'],
  ['How do content edits work?', 'Send changes by email or through your portal. Unused hours don’t roll over.'],
  ['Can I cancel anytime?', 'Yes. Your plan runs to the end of the month you’ve paid for.'],
]; $noRefund = true; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
