<?php $pre = preg_replace('/[^a-z0-9_]/', '', (string)($_GET['service'] ?? '')); ?>
<section class="section tight"><div class="wrap" style="max-width:860px">
  <span class="eyebrow">Get started</span><h1 style="margin-top:8px">Tell us what you need</h1>
  <p class="lead">Prefer a conversation? <button class="btn btn-ghost btn-sm" type="button" data-chat="">Use the AI assistant</button>. Or fill this in, and we'll come back with a plan and a written proposal.</p>
  <form class="card" method="post" action="/start" style="margin-top:20px"><?= App\Csrf::field() ?>
    <div class="hp"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="field"><label for="msg">Describe your business and goal</label><textarea id="msg" name="message" required placeholder="e.g. Opening a bakery in Riverview. Need to incorporate, a .ca domain, email for 3 people and a website with online orders."></textarea></div>
    <fieldset class="field" style="border:none;padding:0"><legend style="font-weight:600;font-size:.9rem;margin-bottom:8px">Services you're interested in (optional)</legend>
      <div class="grid g3"><?php foreach ($services as $s): if ($s['category'] === 'addon') continue; ?><label class="check" style="font-weight:500"><input type="checkbox" name="services[]" value="<?= e($s['slug']) ?>"<?= $pre === $s['slug'] ? ' checked' : '' ?>> <?= e($s['name']) ?></label><?php endforeach; ?></div></fieldset>
    <div class="grid g2"><div class="field"><label for="bud">Budget (optional)</label><select id="bud" name="budget"><option value="">Not sure yet</option><option>Under $500</option><option>$500 – $2,000</option><option>$2,000 – $10,000</option><option>$10,000+</option></select></div>
      <div class="field"><label for="tl">Timeline</label><select id="tl" name="timeline"><option value="">Flexible</option><option>ASAP</option><option>This month</option><option>Next 3 months</option></select></div></div>
    <div class="grid g2"><div class="field"><label for="n">Name</label><input id="n" name="name" type="text" required></div><div class="field"><label for="em">Email</label><input id="em" name="email" type="email" required></div></div>
    <div class="grid g2"><div class="field"><label for="bn">Business name</label><input id="bn" name="business_name" type="text"></div><div class="field"><label for="loc">City / province</label><input id="loc" name="location" type="text"></div></div>
    <label class="check"><input type="checkbox" name="consent_marketing" value="1"> Send me occasional tips and offers (optional)</label>
    <button class="btn btn-primary" style="margin-top:16px">Send my request</button>
    <p class="small muted" style="margin-top:10px">We'll use these details to reply about your request. See our <a href="/legal/privacy">privacy policy</a>.</p>
  </form>
</div></section>
