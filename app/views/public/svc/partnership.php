<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$svc = Catalog::find('partnership'); $pp = $svc['province_prices'] ?? [];
$hero = ['form' => true, 'tone' => 'blue', 'pills' => ['2+ partners', $svc['timeline'] ?? '1–3 business days'],
  'title' => 'Build it together.', 'sub' => 'Register a general or limited partnership, with flexible profit sharing between owners.',
  'media' => ['img' => 'handshake'], 'chips' => [['users', 'Two or more owners', 'General or limited'], ['text', 'Agreement template', 'Included with your order']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$partner = wz_field('owners[{i}][first]', 'First name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'given-name'])
  . wz_field('owners[{i}][last]', 'Last name *', 'text', ['data-rule' => 'person', 'autocomplete' => 'family-name'])
  . wz_field('owners[{i}][email]', 'Email *', 'email', ['autocomplete' => 'email'])
  . wz_field('owners[{i}][phone]', 'Phone', 'tel', ['data-rule' => 'phone'])
  . wz_field('owners[{i}][share]', 'Share % *', 'number', ['min' => 1, 'max' => 100, 'step' => 'any', 'placeholder' => '50'])
  . wz_select('owners[{i}][kind]', 'Partner type', ['general' => 'General partner', 'limited' => 'Limited partner'], ['data-show-parent' => 'lp']);
?>
<section class="svc-body" id="start"><div class="wrap">
<?= wz_open(['Province & type', 'Partners', 'Business details', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <input type="hidden" name="slug" value="partnership">
  <?= wz_step(0, 'Province & partnership type', wz_provinces('province', $pp, 'Select province', false, 'NB')
      . wz_cards('partnership_type', 'Partnership type', [['general', 'General partnership', 'No limited liability; all partners take part in running the business.'], ['limited', 'Limited partnership', 'One or more limited partners whose liability is limited to their investment.']], 'two')
      . '<div class="grid2">' . wz_field('entity[proposed_name]', 'Proposed partnership name *', 'text', ['data-rule' => 'bizname', 'placeholder' => 'e.g. Smith & Jones Associates', 'data-wide' => 1]) . '</div>') ?>
  <?= wz_step(1, 'Partners', '<p class="muted">Add every partner. Shares must total 100%.</p>' . str_replace('data-repeat="owners"', 'data-repeat="owners" data-total="share"', wz_repeat('owners', 'Partner', $partner, 2, 10, 'Add partner'))) ?>
  <?= wz_step(2, 'Business details', '<div class="grid2">'
      . wz_field('entity[activity]', 'Business activity *', 'textarea', ['minlength' => 10, 'data-wide' => 1, 'placeholder' => 'Describe what the partnership does…'])
      . wz_field('entity[start_date]', 'Start date *', 'date')
      . wz_field('registered_office[city]', 'City of main office *', 'text', ['autocomplete' => 'address-level2'])
      . wz_field('registered_office[street]', 'Street address *', 'text', ['autocomplete' => 'street-address'])
      . wz_field('registered_office[postal]', 'Postal code *', 'text', ['data-rule' => 'postal', 'autocomplete' => 'postal-code'])
      . '</div>' . wz_addons(['addon_expedited'], 'Optional')) ?>
  <?= wz_review(3, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Registration summary', null, 'Government filing fee included', [['Province', '@province'], ['Type', '@partnership_type'], ['Delivery', $svc['timeline'] ?? '1–3 business days'], ['Registry', 'Provincial']],
      ['Partnership registration with the provincial registry', 'Partnership agreement template', 'Confirmation document', 'Email support throughout'], 'blue') ?>
  <?php include APP_ROOT . '/views/partials/svc/help.php'; ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What is a general partnership?', 'Two or more people or companies who run a business together and share its profits, losses and responsibilities. Each general partner is personally responsible for the partnership’s debts.'],
  ['What is a limited partnership?', 'A partnership with at least one general partner who manages the business and one or more limited partners whose liability is limited to what they invested, as long as they don’t manage the business.'],
  ['Is a partnership agreement required?', 'The registry does not require one, but we strongly recommend it. It sets out each partner’s share, duties and what happens if someone leaves. A template is included.'],
  ['How are profits taxed?', 'The partnership itself doesn’t pay income tax. Each partner reports their share of profit on their personal or corporate return.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
