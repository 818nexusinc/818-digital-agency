<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/svc/wz.php';
$plans = [];
foreach (['email_starter' => 'Starter', 'email_business' => 'Business', 'email_team' => 'Team'] as $s => $n) { $x = Catalog::find($s); $p = Catalog::price($x, []); $plans[$s] = [$x, $p['unit_cents'] ?? 0, $n]; }
$dom = Catalog::find('domain_register'); $caCents = (int)($dom['options']['tlds']['ca'] ?? 0);
$hero = ['form' => true, 'tone' => 'orange', 'pills' => ['Your own domain', 'Webmail & mobile'], 'title' => 'Email that looks like a business.', 'sub' => 'Professional mailboxes on your domain, set up for you after payment.',
  'media' => ['img' => 'notes'], 'chips' => [['mail', 'you@yourbusiness.ca', 'On every device'], ['shield', 'Spam filtering', 'Included']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$cardOpts = [];
foreach ($plans as $s => [$x, $c, $n]) $cardOpts[] = [$s, $n, $x['summary'], money($c) . '/mo', ['data-cents' => $c, 'data-line' => 'Email ' . $n, 'data-per' => '/mo', 'data-text' => $n . ' (' . money($c) . '/mo)'] + ($s === 'email_starter' ? ['checked' => true] : [])];
$mailbox = wz_field('mailboxes[{i}][prefix]', 'Address *', 'text', ['data-rule' => 'prefix', 'placeholder' => 'info'], 'Before the @ sign')
  . wz_field('mailboxes[{i}][name]', 'Display name *', 'text', ['placeholder' => 'Jane Smith']);
?>
<section class="svc-body" id="start"><div class="wrap">
<?= wz_open(['Domain', 'Plan', 'Mailboxes', 'Review'], ['data-endpoint' => '/order/add']) ?>
  <?= wz_step(0, 'Do you have a domain name?', '<p class="muted">Professional email needs a domain (e.g. you@yourcompany.ca). If you don’t have one yet, we’ll register it with your email.</p>'
      . wz_cards('have_domain', 'Your domain', [['yes', 'Yes, I have a domain', 'Enter your existing domain and we’ll set up email on it.', '', ['checked' => true]], ['no', 'No, I need a domain', 'We’ll register a .ca domain and set up email on it, all in one order.', '+' . money($caCents) . '/yr']], 'two')
      . '<div class="grid2">' . wz_field('domain', 'Domain name *', 'text', ['data-rule' => 'domain', 'placeholder' => 'yourcompany.ca', 'autocapitalize' => 'none', 'data-wide' => 1]) . '</div>'
      . '<div data-show="have_domain=no"><input type="hidden" name="addons[]" value="domain_register" data-cents="' . $caCents . '" data-line="Domain registration (.ca)" data-per="/yr"><input type="hidden" name="addon_tld" value="ca"></div>', false, 'Next: choose a plan') ?>
  <?= wz_step(1, 'Choose your plan', wz_cards('slug', 'Plan', $cardOpts, 'three')) ?>
  <?= wz_step(2, 'Set up your mailboxes', '<p class="muted">You’ll choose each mailbox password yourself when we send the secure set-up link. We never ask for passwords in this form.</p>' . wz_repeat('mailboxes', 'Mailbox', $mailbox, 1, 20, 'Add mailbox')) ?>
  <?= wz_review(3, 'Add to cart') ?>
<?= wz_close() ?>
<div class="svc-side">
  <?= wz_summary('Email order', null, 'Billed monthly · cancel anytime', [['Domain', '@domain'], ['Plan', '@slug'], ['Set-up', 'Within 1 business day']], ['Webmail and mobile access', 'IMAP / POP3 / SMTP', 'Spam and virus filtering'], 'orange') ?>
</div>
</div></section>
<?= wz_mbar() ?>
<?php $faqs = [
  ['What makes business email different from free Gmail?', 'An address on your own domain looks professional, builds trust and stays yours if you change providers.'],
  ['Do I need a domain name first?', 'Yes, but you don’t need to have one already: choose “No, I need a domain” and we’ll register it with your email.'],
  ['Can I create shared addresses like info@ or support@?', 'Yes. Add them as mailboxes, or ask us to set them up as aliases on the Business and Team plans.'],
  ['What is the refund policy for email subscriptions?', 'Cancel anytime; you won’t be charged for the next month. The current month isn’t refunded once mailboxes are set up.'],
]; $noRefund = true; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
