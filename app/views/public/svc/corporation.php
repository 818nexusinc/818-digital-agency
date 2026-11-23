<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$named = Catalog::find('corp_named'); $numbered = Catalog::find('corp_numbered');
$by = ['field' => 'slug', 'maps' => ['corp_named' => $named['province_prices'] ?? [], 'corp_numbered' => $numbered['province_prices'] ?? []]];
$provs = ['FED' => 'Federal'] + Catalog::PROVINCES; unset($provs['FED']);
$hero = ['form' => true, 'tone' => 'amber', 'pills' => ['Provincial or federal (CBCA)', 'Named or numbered'],
  'title' => 'Incorporate in minutes. We handle the filing.', 'sub' => 'Answer a few questions. We prepare your articles of incorporation and file them right away.',
  'media' => ['img' => 'signing'], 'chips' => [['shield', 'Limited liability', 'Protect personal assets'], ['bolt', 'Started immediately', 'No extra paperwork']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$director = wz_field('directors[{i}][first]', 'First name *', 'text', ['data-rule' => 'person', 'placeholder' => 'Legal first name'])
  . wz_field('directors[{i}][last]', 'Last name *', 'text', ['data-rule' => 'person', 'placeholder' => 'Legal last name'])
  . wz_field('directors[{i}][email]', 'Email *', 'email')
  . wz_field('directors[{i}][phone]', 'Phone', 'tel', ['data-rule' => 'phone'])
  . wz_field('directors[{i}][address][street]', 'Residential street address *', 'text', ['data-wide' => 1])
  . wz_field('directors[{i}][address][city]', 'City *', 'text')
  . wz_select('directors[{i}][address][province]', 'Province *', $provs)
  . wz_field('directors[{i}][address][postal]', 'Postal code *', 'text', ['data-rule' => 'postal'])
  . wz_select('directors[{i}][role]', 'Officer role', ['none' => 'Director only', 'president' => 'President', 'secretary' => 'Secretary', 'treasurer' => 'Treasurer', 'ceo' => 'CEO'])
  . '<label class="ck wide" style="grid-column:1/-1"><input type="checkbox" name="directors[{i}][resident_canadian]" value="1" data-label="Resident Canadian" data-text="Yes" checked><span><b>Resident Canadian</b><small>Some jurisdictions require a minimum number of resident Canadian directors.</small></span></label>'
  . '<input type="hidden" name="directors[{i}][incorporator]" value="1">';
?>
<section class="svc-body" id="start"><div class="wrap">
<?= wz_open(['Jurisdiction', 'Name', 'Directors', 'Shares', 'Office', 'Add-ons', 'Your details', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <?= wz_step(0, 'Step 1: Choose jurisdiction', '<p class="note-box"><b>Federal (CBCA)</b> lets you operate under your name anywhere in Canada. <b>Provincial</b> is simpler and usually cheaper if you operate in one province.</p>'
      . wz_cards('slug', 'Named or numbered?', [['corp_named', 'Named corporation', 'Your chosen name, e.g. Maple Leaf Consulting Inc. Includes the required NUANS name search.', '', ['data-text' => 'Named', 'checked' => true]], ['corp_numbered', 'Numbered corporation', 'A number assigned by the registry, e.g. 1234567 N.B. Inc. Fastest option; you can add a trade name later.', '', ['data-text' => 'Numbered']]], 'two')
      . wz_provinces('province', [], 'Jurisdiction', true, 'NB', $by)) ?>
  <?= wz_step(1, 'Step 2: Company name', '<div data-show="slug=corp_named"><div class="grid2">'
      . wz_field('entity[proposed_name]', 'Proposed company name *', 'text', ['data-rule' => 'bizname', 'placeholder' => 'Maple Leaf Solutions'])
      . wz_select('entity[legal_ending]', 'Legal ending *', ['Inc.' => 'Inc.', 'Ltd.' => 'Ltd.', 'Corp.' => 'Corp.', 'Incorporated' => 'Incorporated', 'Limited' => 'Limited', 'Corporation' => 'Corporation'])
      . wz_field('backup_names[0]', 'Backup name 1 (recommended)', 'text', ['placeholder' => 'Alternative name'])
      . wz_field('backup_names[1]', 'Backup name 2 (optional)', 'text', ['placeholder' => 'Second alternative'])
      . '</div><p class="note-box" style="margin-top:14px">A good name has three parts: a distinctive word, a descriptive word and a legal ending, e.g. <b>Maple Leaf Consulting Inc.</b> We run the NUANS search and tell you if the name is not available.</p></div>'
      . '<div data-show="slug=corp_numbered"><p class="note-box">The registry assigns your corporation number when it accepts the filing. Nothing else is needed on this step.</p><input type="hidden" name="entity[name_type]" value="numbered"></div>') ?>
  <?= wz_step(2, 'Step 3: Directors & officers', wz_repeat('directors', 'Director', $director, 1, 10, 'Add director')) ?>
  <?= wz_step(3, 'Step 4: Share structure', wz_cards('share_plan', 'Share structure', [['standard', 'Standard share structure (recommended)', 'One class of common shares: unlimited number, voting, eligible for dividends. Right for most new companies.'], ['custom', 'Custom share structure', 'Several classes (e.g. voting and non-voting, preferred). Describe what you need.']], 'two')
      . '<div data-show="share_plan=standard"><input type="hidden" name="shares[classes]" value="Class A common shares, unlimited number, voting, entitled to dividends"></div>'
      . '<div data-show="share_plan=custom"><div class="grid2">' . wz_field('shares[classes]', 'Describe each share class *', 'textarea', ['data-wide' => 1, 'minlength' => 10, 'placeholder' => 'e.g. Class A voting common, unlimited; Class B non-voting common, unlimited']) . '</div></div>') ?>
  <?= wz_step(4, 'Step 5: Registered office', wz_addons(['addon_registered_address'], 'Use our registered address service') . '<div data-show="addons[]=addon_registered_address"><p class="note-box" style="margin-top:12px">We will use our Moncton, NB registered office address and forward your mail. Available for New Brunswick and federal corporations.</p></div><div class="grid2" style="margin-top:14px" data-hide="addons[]=addon_registered_address">'
      . wz_field('registered_office[street]', 'Street address *', 'text', ['data-wide' => 1, 'autocomplete' => 'street-address'], 'No PO boxes. Must be in the province of incorporation (federal: anywhere in Canada).')
      . wz_field('registered_office[city]', 'City *', 'text', ['placeholder' => 'Moncton'])
      . wz_select('registered_office[province]', 'Province *', $provs)
      . wz_field('registered_office[postal]', 'Postal code *', 'text', ['data-rule' => 'postal', 'placeholder' => 'E1C 1A1'])
      . '</div>') ?>
  <?= wz_step(5, 'Step 6: Optional add-ons', wz_addons(['addon_minute_book', 'addon_seal', 'addon_expedited', 'addon_extra_copies'], 'Add-ons') . '<p class="hint" style="margin-top:10px">Guidance on CRA business number, GST/HST and payroll registration is included with every incorporation.</p>') ?>
  <?= wz_step(6, 'Step 7: Your contact details', '<div class="grid2">'
      . wz_field('applicant[first]', 'First name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'given-name'])
      . wz_field('applicant[last]', 'Last name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'family-name'])
      . wz_field('applicant[email]', 'Email address *', 'email', ['autocomplete' => 'email'])
      . wz_field('applicant[phone]', 'Phone number', 'tel', ['data-rule' => 'phone', 'autocomplete' => 'tel'])
      . wz_select('applicant[role]', 'Your role', ['director' => 'Director', 'officer' => 'Officer', 'shareholder' => 'Shareholder', 'agent' => 'Agent / representative'])
      . '</div>') ?>
  <?= wz_review(7, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Registration summary', null, 'Government filing fee included', [['Type', '@slug'], ['Jurisdiction', '@province'], ['Delivery', $named['timeline'] ?? '2–7 business days']],
      ['Articles of incorporation prepared and filed', 'Certificate of incorporation', 'Initial filings (directors, registered office)', 'Guidance on CRA business number'], 'amber') ?>
  <?php include APP_ROOT . '/views/partials/svc/help.php'; ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['Named or numbered corporation: what’s the difference?', 'A named corporation uses a name you choose (and needs a NUANS search in most jurisdictions). A numbered corporation uses the number the registry assigns. Both have the same legal protection; you can register a trade name for a numbered company later.'],
  ['What is a NUANS report?', 'A search of Canadian business names and trademarks that shows whether your proposed name is too similar to an existing one. It is required for named corporations federally and in most provinces, and is included with a named corporation here.'],
  ['Provincial or federal incorporation?', 'Federal (CBCA) protects your name across Canada and suits businesses operating in several provinces. Provincial is often simpler and cheaper if you operate in one province. You may still need to register extra-provincially where you operate.'],
  ['How many directors are required?', 'At least one. Some provinces require a minimum share of directors to be resident Canadians; we check this before filing.'],
  ['How long does incorporation take?', 'Most incorporations complete within the delivery time shown, once your order is paid. Expedited processing is available where the registry allows it.'],
  ['What is a minute book?', 'The binder of your corporation’s records: articles, by-laws, resolutions, registers and share certificates. Corporations are legally required to keep these records.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
