<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$provs = ['FED' => 'Federal (CBCA)'] + Catalog::PROVINCES; unset($provs['FED']); $provs = ['FED' => 'Federal (CBCA)'] + $provs;
$K = [
  'annual-returns' => ['slug' => 'bm_annual', 'tone' => 'blue', 'icon' => 'calendar', 'title' => 'File your annual return. On time.', 'sub' => 'We prepare it, file it and remind you next year.',
    'opts' => [['corporation', 'Corporation annual return', 'Federal or provincial corporation annual return filing with the registry.'], ['name_renewal', 'Business name renewal', 'Sole proprietorship or partnership registration renewal with the provincial registry.'], ['compliance', 'Compliance check', 'Good-standing check with a short written confirmation.']],
    'incl' => ['Annual return prepared and filed for you', 'Reminder before your next deadline', 'Filing confirmation delivered', 'Federal and every province'],
    'extra' => fn() => wz_field('entity[effective_date]', 'Fiscal year end / anniversary date *', 'date', ['data-label' => 'Anniversary date']) . wz_field('return_year', 'Annual return year *', 'number', ['min' => 2000, 'max' => 2100, 'value' => gmdate('Y')])],
  'corporate-changes' => ['slug' => 'bm_changes', 'tone' => 'orange', 'icon' => 'edit', 'title' => 'Update your corporate records.', 'sub' => 'Address, directors and officers, filed with the registry.',
    'opts' => [['address', 'Registered address change', 'Update your registered office or mailing address with the registry.'], ['directors', 'Director appointment / resignation', 'Appoint or remove a director and update the corporate record.'], ['officers', 'Officer change', 'Update officer information on file with the registry.'], ['other', 'Other corporate change', 'Any other permitted change to your corporate information.']],
    'incl' => ['Notice of change prepared and filed', 'Registered office and mailing address changes', 'Director appointments and resignations', 'Officer changes', 'Federal and every province'],
    'extra' => fn() => '<div data-show="change_type=address" class="wide" style="grid-column:1/-1"><div class="grid2">' . wz_field('registered_office[street]', 'New registered office address *', 'text', ['data-wide' => 1]) . wz_field('registered_office[city]', 'City *', 'text') . wz_field('registered_office[postal]', 'Postal code *', 'text', ['data-rule' => 'postal']) . '</div></div>'
      . '<div data-show="change_type=directors" class="wide" style="grid-column:1/-1"><div class="grid2">' . wz_field('change_director[first]', 'Director first name *', 'text', ['data-rule' => 'person']) . wz_field('change_director[last]', 'Director last name *', 'text', ['data-rule' => 'person']) . wz_select('director_action', 'Appoint or remove? *', ['appoint' => 'Appoint', 'remove' => 'Remove / resignation']) . wz_field('entity[effective_date]', 'Effective date *', 'date') . '</div></div>'
      . '<div data-show="change_type=officers" class="wide" style="grid-column:1/-1"><div class="grid2">' . wz_field('officer_name', 'Officer name *', 'text', ['data-rule' => 'person']) . wz_field('officer_title', 'Title *', 'text', ['placeholder' => 'e.g. Secretary']) . '</div></div>'],
  'corporate-documents' => ['slug' => 'bm_documents', 'tone' => 'violet', 'icon' => 'certificate', 'title' => 'Official documents, fast.', 'sub' => 'Certificates of status, profiles and certified copies.',
    'opts' => [['cert_incorporation', 'Certificate of incorporation', 'Certified copy of your original certificate of incorporation.'], ['cert_status', 'Certificate of status / good standing', 'Official confirmation that your corporation is active and in good standing.'], ['profile', 'Corporate profile report', 'Detailed corporate profile including directors and registered address.'], ['search', 'Corporate search report', 'Search report for due diligence or banking purposes.'], ['articles', 'Certified copy of articles', 'Certified copy of articles of incorporation, amendments or resolutions.']],
    'incl' => ['Ordered directly from the registry', 'Certified copies where available', 'Delivered to your portal', 'Federal and every province'],
    'extra' => fn() => wz_select('copies', 'Number of copies', ['1' => '1', '2' => '2', '3' => '3'])],
  'name-services' => ['slug' => 'biz_name', 'tone' => 'teal', 'icon' => 'text', 'title' => 'Keep your business name.', 'sub' => 'Renew it or change it, filed with your province.', 'province_priced' => true,
    'opts' => [['renewal', 'Business name renewal', 'Renew your registered business name with the provincial registry.'], ['change', 'Business name change', 'Change your registered trade name to a new name with the provincial registry.']],
    'incl' => ['Renewal or change filed for you', 'Confirmation delivered to your portal', 'Renewal reminders set up'],
    'extra' => fn() => wz_field('current_name', 'Current registered business name *', 'text', ['data-rule' => 'bizname']) . '<div data-show="change_type=change">' . wz_field('entity[proposed_name]', 'New business name *', 'text', ['data-rule' => 'bizname']) . '</div>'],
];
$k = $K[$mkind]; $svc = Catalog::find($k['slug']); $cents = empty($k['province_priced']) ? (Catalog::price($svc, [])['unit_cents'] ?? null) : null;
$hero = ['form' => true, 'tone' => $k['tone'], 'icon' => $k['icon'], 'title' => $k['title'], 'sub' => $k['sub'], 'media' => ['img' => ['annual-returns' => 'desk-smile', 'corporate-changes' => 'signing', 'corporate-documents' => 'docs-typing', 'name-services' => 'owner-boxes'][$mkind] ?? 'laptop'], 'chips' => [['shield', 'Filed with the registry', 'Status in your portal'], ['bolt', 'Started immediately', 'As soon as your order is in']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$priceBadge = $cents !== null ? money($cents) : '';
$opts = array_map(fn($o) => [$o[0], $o[1], $o[2], $priceBadge], $k['opts']);
?>
<section class="svc-body" id="start"><div class="wrap">
<?= wz_open(['Choose a service', 'Details', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <input type="hidden" name="slug" value="<?= e($k['slug']) ?>">
  <?= wz_step(0, 'Choose a service', '<p class="muted">Select the service you need. The fields you need appear on the next step.</p>' . wz_cards('change_type', 'Service', $opts, '')
      . (!empty($k['province_priced']) ? wz_provinces('province', $svc['province_prices'] ?? [], 'Province', false, 'NB') : '')) ?>
  <?= wz_step(1, 'Your business details', '<div class="grid2">'
      . wz_field('entity[corporation_number]', 'Corporation / registration number', 'text', ['placeholder' => 'If you have it'])
      . (empty($k['province_priced']) ? wz_select('province', 'Jurisdiction *', $provs) : '')
      . ($mkind !== 'name-services' ? wz_field('entity[proposed_name]', 'Registered business / corporation name *', 'text', ['data-rule' => 'bizname', 'data-wide' => 1, 'data-label' => 'Registered name']) : '')
      . wz_field('directors[0][first]', 'Your first name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'given-name', 'data-label' => 'Contact first name'])
      . wz_field('directors[0][last]', 'Your last name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'family-name', 'data-label' => 'Contact last name'])
      . wz_field('contact_email', 'Contact email *', 'email', ['autocomplete' => 'email'])
      . wz_field('contact_phone', 'Phone number', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel'])
      . ($k['extra'])()
      . wz_field('entity[changes]', 'Additional notes', 'textarea', ['data-wide' => 1, 'placeholder' => 'Anything we should know?', 'data-label' => 'Notes'])
      . '</div>') ?>
  <?= wz_review(2, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary($k['title'], $cents, $cents !== null ? 'One-time · government fees noted at checkout' : 'Government filing fee included', [['Service', '@change_type'], ['Jurisdiction', '@province'], ['Delivery', $svc['timeline'] ?? '']], $k['incl'], $k['tone']) ?>
  <?php include APP_ROOT . '/views/partials/svc/help.php'; ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What is an annual return, and how is it different from a tax return?', 'An annual return confirms your corporation’s basic information (address, directors) with the registry each year. It is separate from your corporate tax return to the CRA.'],
  ['When is my annual return due, and what happens if I miss it?', 'It is usually due around your anniversary date each year. Missing several can lead to penalties and eventually dissolution of the corporation.'],
  ['What corporate changes can you file for me?', 'Registered office and mailing address changes, director appointments and resignations, officer changes and other permitted updates.'],
  ['What official documents can I order?', 'Certificates of status, corporate profile reports, certified copies of articles and certificates, and search reports.'],
  ['How do I renew or change a business name?', 'Choose business name services, tell us the current name and, for a change, the new name. We file the renewal or change with your provincial registry.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
