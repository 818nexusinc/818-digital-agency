<?php
use App\Settings;
include_once APP_ROOT . '/views/partials/icons.php';
$hero = ['tone' => 'sky', 'pills' => ['Contact us'], 'title' => 'Talk to a real person.', 'sub' => 'Questions about a filing, an order or a quote? We reply within one business day.',
  'media' => ['img' => 'welcome'], 'chips' => [['mail', 'Send us a message', 'Use the form below'], ['clock', 'Mon–Fri', 'Atlantic time']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec" style="padding-top:36px"><div class="wrap contact-grid">
  <div class="side-card"><h3 style="margin:0 0 16px">Our contact information</h3>
    <div class="ci">
      <div><span class="mc-ic tone-blue"><?= nx_icon('building') ?></span><span><small>Address</small><b><?= e(Settings::get('company.city')) ?></b></span></div>
      <div><span class="mc-ic tone-teal"><?= nx_icon('mail') ?></span><span><small>Email</small><b><a href="mailto:<?= e(Settings::get('company.email')) ?>"><?= e(Settings::get('company.email')) ?></a></b></span></div>
      <div><span class="mc-ic tone-violet"><?= nx_icon('calendar') ?></span><span><small>Office hours</small><b>Monday to Friday, 9 AM to 5 PM Atlantic</b></span></div>
    </div>
    <p class="note-box" style="margin-top:18px"><b>Response time:</b> a person replies within one business day. For instant answers, use the assistant.</p>
    <button class="btn btn-ghost btn-sm" type="button" data-chat="">Chat with the assistant</button>
  </div>
  <form class="side-card" method="post" action="/contact"><?= App\Csrf::field() ?>
    <div class="hp"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="grid2">
      <div class="fld"><label for="n">Full name <span class="req">*</span></label><input id="n" name="name" type="text" required autocomplete="name"></div>
      <div class="fld"><label for="em">Email address <span class="req">*</span></label><input id="em" name="email" type="email" required autocomplete="email" placeholder="name@company.ca"></div>
      <div class="fld"><label for="ph">Phone</label><input id="ph" name="phone" type="tel" autocomplete="tel"></div>
      <div class="fld"><label for="sv">Service of interest</label><select id="sv" name="services[]"><option value="">Select a service…</option><option value="registration">Business registration</option><option value="management">Business management</option><option value="domains">Domain</option><option value="email">Business email</option><option value="websites">Website</option><option value="apps">Web application</option><option value="other">Something else</option></select></div>
      <div class="fld wide"><label for="msg">Message <span class="req">*</span></label><textarea id="msg" name="message" required rows="5"></textarea></div>
    </div>
    <label class="ck" style="margin-top:14px"><input type="checkbox" name="consent_marketing" value="1"><span><small>Send me occasional tips and offers (unsubscribe anytime)</small></span></label>
    <button class="btn btn-primary" style="margin-top:14px;width:100%">Send message</button></form>
</div></section>
