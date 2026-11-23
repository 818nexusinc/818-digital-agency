<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
include_once APP_ROOT . '/views/partials/icons.php';
$svc = Catalog::find('nuans'); $p = Catalog::price($svc, []); $cents = $p['unit_cents'] ?? 4250;
$provs = ['FED' => 'Federal (CBCA): all provinces'] + Catalog::PROVINCES; unset($provs['FED']); $provs = ['FED' => 'Federal (CBCA): all provinces'] + $provs;
$hero = ['tone' => 'violet', 'icon' => 'search', 'pills' => ['Official NUANS report', 'Government compatible', $svc['timeline'] ?? 'Same day', 'Valid 90 days'],
  'title' => 'Official NUANS Name Search', 'sub' => 'Get your official NUANS report before incorporating a named corporation. Results are delivered to your portal and by email.'];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec" style="padding:36px 0 10px"><div class="wrap">
  <div class="dom-box" id="nuans-start"><h3 style="margin:0 0 4px">Is your business name available?</h3><p class="muted" style="margin:0 0 14px">Enter the name you want. The official NUANS search compares it against registered corporations, business names and trademarks across Canada.</p>
    <form class="dom-search" data-prefill="entity[proposed_name]"><label class="sr" for="nuans-q">Proposed business name</label><input id="nuans-q" type="text" placeholder="e.g. Atlantic Consulting Group Inc." maxlength="100"><button class="btn btn-primary" type="submit">Order report · <?= e(money($cents)) ?></button></form>
    <p class="hint">We run the official search after you order. A NUANS report shows similar names; it is not a guarantee the registry will approve your name.</p></div>
</div></section>
<section class="sec" style="padding:30px 0"><div class="wrap">
  <div class="sec-head center"><h2>How it works</h2><p>From request to official report.</p></div>
  <div class="steps4">
    <?php foreach ([['edit', 'violet', 'Submit request', 'Enter your proposed names, contact details and business activity. Takes under 2 minutes.'], ['cash', 'blue', 'Pay for the service', 'One-time payment of ' . money($cents) . ' by card, or request an invoice.'], ['search', 'teal', 'We run the search', 'Your search is processed through the official NUANS system.'], ['certificate', 'green', 'Report delivered', 'Your NUANS PDF report is emailed and saved in your portal.']] as $i => [$ic, $tone, $t, $d]): ?>
    <div class="st4"><span class="n"><?= $i + 1 ?></span><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>
<section class="svc-body" id="order"><div class="wrap">
<?= wz_open(['Details', 'Names', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <input type="hidden" name="slug" value="nuans">
  <?= wz_step(0, 'Applicant information', '<p class="muted">Your details for the order confirmation and invoice.</p><div class="grid2">'
      . wz_field('applicant[name]', 'Full legal name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'name'])
      . wz_field('applicant[email]', 'Email address *', 'email', ['autocomplete' => 'email'])
      . wz_field('applicant[phone]', 'Phone number', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel'])
      . wz_field('applicant[company]', 'Company / organization', 'text', ['autocomplete' => 'organization'])
      . '</div>') ?>
  <?= wz_step(1, 'Names to search', '<div class="grid2">'
      . wz_field('entity[proposed_name]', 'Proposed name 1 *', 'text', ['data-rule' => 'bizname', 'data-wide' => 1, 'id' => 'name1', 'data-label' => 'Proposed name 1'])
      . wz_field('names[1]', 'Proposed name 2 (optional)', 'text')
      . wz_field('names[2]', 'Proposed name 3 (optional)', 'text')
      . wz_select('province', 'Province / territory of incorporation *', $provs, ['data-wide' => 1])
      . wz_field('entity[activity]', 'Primary business activity *', 'textarea', ['minlength' => 10, 'data-wide' => 1, 'placeholder' => 'e.g. IT consulting for small businesses'])
      . '</div>') ?>
  <?= wz_review(2, 'Add to cart · ' . money($cents)) ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('NUANS report', $cents, 'One-time · PDF report · valid 90 days', [['Jurisdiction', '@province'], ['Delivery', $svc['timeline'] ?? 'Same day']], ['Official NUANS search report (PDF)', 'Up to 3 proposed names', 'Plain-language summary of conflicts'], 'violet') ?>
</div>
</div></section>
<?= wz_mbar() ?>
<section class="sec alt" style="padding:56px 0"><div class="wrap">
  <div class="sec-head center"><h2>What you need to know</h2></div>
  <div class="hub-grid">
    <?php foreach ([['Administered by ISED Canada', 'NUANS is run by Innovation, Science and Economic Development Canada and is the official pre-incorporation name search in Canada.'], ['Required in several jurisdictions', 'Mandatory for named corporations federally (CBCA) and in Ontario, New Brunswick and other jurisdictions. Some provinces use their own name search instead.'], ['Searches registered names & trademarks', 'The database covers registered corporations, business names and trademarks across Canada.'], ['90-day validity', 'A NUANS report is valid for 90 days. File your incorporation within that window or a new report is needed.'], ['Not a guarantee of approval', 'A clean report helps, but the registry makes the final decision on your name.']] as [$t, $d]): ?>
    <div class="hub-card" style="--tone:#6d3fd8"><h3><?= e($t) ?></h3><p class="hc-d"><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php $faqs = [
  ['What is NUANS?', 'The Newly Upgraded Automated Name Search: a database search that compares a proposed corporate name against existing names and trademarks to identify conflicts.'],
  ['How quickly will I receive my report?', 'Usually the same business day once your order is paid.'],
  ['Can I use this report for my incorporation?', 'Yes. The report is accepted by registries that require NUANS, as long as you file within 90 days and use a name from the report.'],
  ['Do I need NUANS for a sole proprietorship or partnership?', 'No. NUANS is for named corporations. Sole proprietorships and partnerships use the provincial business name registry.'],
  ['What if my preferred name is unavailable?', 'We explain the conflicts in plain language and you can order a new search for another name, or incorporate as a numbered company.'],
  ['How is my information secured?', 'Your details are sent over an encrypted connection and stored in your private portal. We only share what the search requires.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
