<section class="section tight"><div class="wrap grid g2" style="align-items:start">
  <div><span class="eyebrow">Contact</span><h1 style="margin-top:8px">Talk to the team</h1><p class="lead">Send us a message and a person replies within one business day. For instant answers, use the assistant.</p>
    <p><a href="mailto:<?= e(App\Settings::get('company.email')) ?>"><?= e(App\Settings::get('company.email')) ?></a><br><?= e(App\Settings::get('company.city')) ?></p>
    <button class="btn btn-ghost" type="button" data-chat="">Chat with the assistant</button></div>
  <form class="card" method="post" action="/contact"><?= App\Csrf::field() ?>
    <div class="hp"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="grid g2"><div class="field"><label for="n">Name</label><input id="n" name="name" type="text" required autocomplete="name"></div><div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required autocomplete="email"></div></div>
    <div class="grid g2"><div class="field"><label for="ph">Phone (optional)</label><input id="ph" name="phone" type="tel" autocomplete="tel"></div><div class="field"><label for="bn">Business (optional)</label><input id="bn" name="business_name" type="text"></div></div>
    <div class="field"><label for="msg">How can we help?</label><textarea id="msg" name="message" required></textarea></div>
    <label class="check"><input type="checkbox" name="consent_marketing" value="1"> Send me occasional tips and offers (unsubscribe any time)</label>
    <button class="btn btn-primary" style="margin-top:14px">Send message</button></form>
</div></section>
