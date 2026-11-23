<?php
include_once APP_ROOT . '/views/partials/svc/wz.php';
$types = [['portal', 'Client / customer portal', 'Logins, documents, messages and self-service for your customers.'], ['booking', 'Booking & scheduling system', 'Online appointments, calendar sync, reminders and payments.'], ['crm', 'CRM / sales pipeline', 'Leads, deals, follow-ups and reporting in one place.'],
  ['inventory', 'Inventory & stock management', 'Track products and orders, set reorder alerts, sync sales channels.'], ['ecommerce', 'E-commerce store', 'A custom online store with payments and order management.'], ['marketplace', 'Marketplace / multi-vendor', 'Sellers, listings, buyer and seller dashboards, commissions and payouts.'],
  ['membership', 'Membership & subscription site', 'Gated content, plans, member directories and automated billing.'], ['reporting', 'Reporting & analytics dashboard', 'KPIs, charts, custom reports and exports for your data.'], ['workflow', 'Operations / workflow automation', 'Replace spreadsheets and manual steps with an app.'], ['custom', 'Custom / other', 'Have a unique idea? Describe it and we’ll scope it.']];
$hero = ['tone' => 'amber', 'pills' => ['Free consultation', 'Written quote'], 'title' => 'Software built around how you work.', 'sub' => 'Booking systems, client portals, dashboards and internal tools.',
  'media' => ['video' => 'team-laptop'], 'chips' => [['laptop', 'Free consultation', 'Scope it together'], ['text', 'Written quote', 'Fixed before we start']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body"><div class="wrap">
<?= wz_open(['Choose app type', 'Your details', 'Review'], ['data-endpoint' => '/order/quote', 'data-kind' => 'web_app']) ?>
  <?= wz_step(0, 'What type of web application do you need?', '<p class="muted">Pick the closest match. You can describe it in detail on the next step.</p>' . wz_cards('app_type', 'App type', $types), false, 'Continue to details') ?>
  <?= wz_step(1, 'Tell us about your project', '<div class="grid2">'
      . wz_field('contact[name]', 'Full name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'name', 'placeholder' => 'Jane Smith'])
      . wz_field('contact[email]', 'Email address *', 'email', ['autocomplete' => 'email'])
      . wz_field('contact[phone]', 'Phone number', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel'])
      . wz_field('company', 'Company / organization', 'text', ['autocomplete' => 'organization', 'placeholder' => 'Acme Corp'])
      . wz_field('description', 'Describe your project *', 'textarea', ['minlength' => 20, 'data-wide' => 1, 'rows' => 5, 'placeholder' => 'What should it do, who will use it, and what systems should it connect to?'])
      . wz_select('budget', 'Budget range', ['under5k' => 'Under $5,000', '5-15k' => '$5,000 – $15,000', '15-50k' => '$15,000 – $50,000', '50-150k' => '$50,000 – $150,000', '150k+' => '$150,000+', 'unsure' => 'Not sure yet'])
      . wz_select('timeline', 'Timeline', ['asap' => 'As soon as possible', '1-3m' => '1 – 3 months', '3-6m' => '3 – 6 months', '6-12m' => '6 – 12 months', 'none' => 'No rush'])
      . '</div>') ?>
  <?= wz_review(2, 'Request my free consultation', true) ?>
<?= wz_close() ?>
<div class="svc-side"><?= wz_summary('Consultation', null, 'Free · no commitment', [['App type', '@app_type'], ['Reply', 'Within 1 business day']], ['30-minute discovery call', 'Written scope and quote', 'Fixed price or phased options'], 'amber') ?></div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['How much does a custom web application cost?', 'It depends on scope. Small tools start around a few thousand dollars; larger platforms are quoted in phases. You get a written quote before any work starts.'],
  ['How long does it take?', 'Most projects launch a first version in 6 to 12 weeks, then improve in phases.'],
  ['Who owns the code?', 'You do, once the project is paid in full.'],
]; $noRefund = true; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
