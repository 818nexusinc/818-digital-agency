<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$svc = Catalog::find('cooperative');
$hero = ['tone' => 'green', 'icon' => 'network', 'pills' => ['Member-owned', 'Democratic governance', $svc['timeline'] ?? '1–3 weeks', 'Provincial filing'],
  'title' => 'Co-operative Registration', 'sub' => 'Register a co-operative in Canada: a democratic, member-owned business where each member gets one vote regardless of investment.'];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body"><div class="wrap">
<?= wz_open(['Province & type', 'Contact & members', 'Purpose', 'Review'], ['data-endpoint' => '/order/quote', 'data-kind' => 'cooperative']) ?>
  <?= wz_step(0, 'Province & co-operative type', wz_provinces('province', [], 'Select province', false, 'NB')
      . wz_cards('coop_type', 'Co-operative type', [['worker', 'Worker co-op', 'Members are employees who own and govern the business.'], ['consumer', 'Consumer co-op', 'Members are customers who benefit from collective purchasing.'],
          ['producer', 'Producer co-op', 'Members are producers pooling resources to reach markets.'], ['multi', 'Multi-stakeholder', 'Different classes of members with defined roles.']], 'two')
      . '<div class="grid2">' . wz_field('entity[proposed_name]', 'Co-operative name *', 'text', ['data-rule' => 'bizname', 'placeholder' => 'e.g. Atlantic Community Housing Co-operative', 'data-wide' => 1]) . '</div>') ?>
  <?= wz_step(1, 'Contact & membership', '<div class="grid2">'
      . wz_field('contact[name]', 'Primary contact name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'name'])
      . wz_field('contact[email]', 'Email *', 'email', ['autocomplete' => 'email'])
      . wz_field('contact[phone]', 'Phone', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel'])
      . wz_field('members', 'Number of founding members *', 'number', ['min' => 3, 'placeholder' => '3'], 'Minimum 3 founding members required')
      . '</div>') ?>
  <?= wz_step(2, 'Purpose & objects', '<div class="grid2">' . wz_field('purpose', 'Co-operative purpose / objects *', 'textarea', ['minlength' => 20, 'data-wide' => 1, 'rows' => 5], 'This will be included in the articles of incorporation.') . '</div>') ?>
  <?= wz_review(3, 'Request my quote', true) ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Registration summary', null, 'Quoted after we confirm your province’s requirements', [['Province', '@province'], ['Type', '@coop_type'], ['Delivery', $svc['timeline'] ?? '1–3 weeks'], ['Filing', 'Provincial registry']],
      ['Articles of incorporation', 'By-laws template', 'Certificate of incorporation', 'Member register template', 'Email support throughout'], 'green') ?>
  <?php include APP_ROOT . '/views/partials/svc/help.php'; ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What is a co-operative?', 'A business owned and democratically controlled by its members, who share in its benefits. Each member has one vote, whatever they invested.'],
  ['How many members are required?', 'Most provinces require at least three founding members. Some co-op types need more; we confirm your province’s rules with your quote.'],
  ['What types of co-operatives exist?', 'Worker, consumer, producer, housing and multi-stakeholder co-ops are the most common. The type shapes your membership classes and by-laws.'],
  ['Are co-operatives taxed differently?', 'Co-operatives are generally taxed like corporations, but patronage dividends paid to members can often be deducted. Ask an accountant about your situation.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
