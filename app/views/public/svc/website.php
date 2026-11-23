<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$dom = Catalog::find('domain_register'); $caCents = (int)($dom['options']['tlds']['ca'] ?? 0);
$feat = ['website_launch' => ['1-page professional website', 'Mobile responsive design', 'Contact form', 'Basic SEO', 'SSL and hosting included', '1 revision round'],
  'website_grow' => ['Up to 5 pages', 'Blog / news section', 'Google Analytics', 'Advanced SEO', 'E-commerce ready', 'Priority support'],
  'website_scale' => ['Unlimited pages', 'Full online store', 'Custom integrations', 'Unlimited revisions', 'Dedicated account manager', 'Priority phone support']];
$cards = '';
foreach ($feat as $s => $list) {
    $x = Catalog::find($s); $p = Catalog::price($x, []); $c = $p['unit_cents'] ?? 0; $setup = $p['setup_cents'] ?? 0; $n = preg_replace('/^Website — /', '', $x['name']);
    $cards .= '<label class="ocard plan">' . ($s === 'website_grow' ? '<span class="oc-flag">Most popular</span>' : '') . '<input type="radio" name="slug" value="' . $s . '" required data-label="Plan" data-text="' . e($n . ' (' . money($c) . '/mo)') . '" data-cents="' . $c . '" data-line="' . e('Website ' . $n) . '" data-per="/mo"' . ($s === 'website_grow' ? ' checked' : '') . '>'
      . '<span class="oc-top"><b>' . e($n) . '</b></span><span class="plan-price"><b>' . e(money($c)) . '</b>/month</span><span class="oc-d">+ ' . e(money($setup)) . ' one-time set-up</span><span class="oc-d">' . e($x['summary']) . '</span><ul class="oc-list">';
    foreach ($list as $li) $cards .= '<li>' . e($li) . '</li>';
    $cards .= '</ul></label>';
}
$pages = ['Home', 'About us', 'Services', 'Contact', 'Our team', 'Gallery / portfolio', 'Testimonials', 'FAQ', 'Blog', 'Pricing', 'Booking', 'Careers'];
$pageChecks = '<div class="chk-grid">'; foreach ($pages as $i => $pg) $pageChecks .= '<label class="ck"><input type="checkbox" name="pages[]" value="' . e($pg) . '" data-label="Page" data-text="' . e($pg) . '"' . ($i < 4 ? ' checked' : '') . '><span><b>' . e($pg) . '</b></span></label>'; $pageChecks .= '</div>';
$hero = ['form' => true, 'tone' => 'sky', 'pills' => ['Mobile first', 'Hosting + SSL included'], 'title' => 'A website that wins you customers.', 'sub' => 'Designed, built, hosted and maintained for you.',
  'media' => ['img' => 'phone-owner'], 'chips' => [['layout', 'Custom design', 'Built around your brand'], ['bolt', 'Hosting + SSL', 'Included from day one']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body" id="start"><div class="wrap">
<?= wz_open(['Plan', 'Domain', 'Brief', 'Pages & features', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <?= wz_step(0, 'Choose your plan', '<fieldset class="cards three" data-label="Plan"><legend class="sr">Plan</legend><div class="card-grid">' . $cards . '</div><p class="fe"></p></fieldset>', false, 'Next: domain') ?>
  <?= wz_step(1, 'Do you have a domain name?', wz_cards('have_domain', 'Your domain', [['yes', 'Yes, I have a domain', 'Enter it and we’ll point it to your new website.', '', ['checked' => true]], ['no', 'No, I need a domain', 'We’ll register a .ca domain and add it to your order.', '+' . money($caCents) . '/yr']], 'two')
      . '<div class="grid2">' . wz_field('domain', 'Domain name *', 'text', ['data-rule' => 'domain', 'placeholder' => 'yourcompany.ca', 'autocapitalize' => 'none', 'data-wide' => 1]) . '</div>'
      . '<div data-show="have_domain=no"><input type="hidden" name="addons[]" value="domain_register" data-cents="' . $caCents . '" data-line="Domain registration (.ca)" data-per="/yr"><input type="hidden" name="addon_tld" value="ca"></div>'
      . wz_addons(['email_starter'], 'Add business email?')) ?>
  <?= wz_step(2, 'Tell us about your business', '<div class="grid2">'
      . wz_field('brief[business_name]', 'Business name *', 'text', ['placeholder' => 'Maple Ridge Consulting', 'autocomplete' => 'organization'])
      . wz_field('brief[industry]', 'Industry *', 'text', ['placeholder' => 'e.g. bakery, accounting, trades'])
      . wz_field('brief[about]', 'What does your business do? *', 'textarea', ['minlength' => 20, 'data-wide' => 1])
      . wz_field('brief[audience]', 'Who are your customers?', 'text', ['data-wide' => 1])
      . wz_field('brief[style]', 'Colours, style or websites you like', 'textarea', ['data-wide' => 1, 'placeholder' => 'e.g. clean and modern, navy and white, like example.com'])
      . wz_field('brief[contact_name]', 'Your name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'name'])
      . wz_field('brief[contact_email]', 'Your email *', 'email', ['autocomplete' => 'email'])
      . '</div>') ?>
  <?= wz_step(3, 'Pages & features', '<p class="wz-sub">Which pages do you need?</p>' . $pageChecks
      . '<div class="grid2" style="margin-top:14px">' . wz_field('brief[custom_pages]', 'Other pages', 'text', ['data-wide' => 1, 'placeholder' => 'Comma-separated, e.g. Menu, Catering'])
      . wz_select('brief[content]', 'Do you have text and photos ready?', ['ready' => 'Yes, ready to send', 'some' => 'Some of it', 'none' => 'No, I need help with content'])
      . wz_select('brief[shop]', 'Selling online?', ['no' => 'No', 'yes' => 'Yes (Grow: up to 50 products, Scale: unlimited)'])
      . '</div>') ?>
  <?= wz_review(4, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Website order', null, 'Monthly plan + one-time set-up at checkout', [['Plan', '@slug'], ['Domain', '@domain']], ['Design and build', 'Hosting and SSL', 'Monthly updates', 'A developer assigned after payment'], 'sky') ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What exactly does the monthly website subscription include?', 'Design, build, hosting, SSL, security updates and the support listed on your plan. You don’t pay separately for hosting.'],
  ['How long does it take to build and launch my website?', 'Launch sites typically take 1–2 weeks, Grow 2–4 weeks and Scale 4–8 weeks, once we have your content.'],
  ['What is the one-time set-up fee for?', 'Discovery, design and the initial build. Your monthly plan then covers hosting, updates and support.'],
  ['Can I update my website content myself?', 'Yes on Grow and Scale, which include an easy content editor. On any plan, you can also send us changes.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
