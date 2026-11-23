<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$c = fn($s) => Catalog::price(Catalog::find($s), [])['unit_cents'] ?? 0;
$reg = []; foreach (['sole_prop', 'partnership', 'corp_named', 'corp_numbered'] as $s) $reg[$s] = array_map(fn($v) => $v === null ? null : (int)$v, Catalog::find($s)['province_prices'] ?? []);
$tlds = array_map('intval', Catalog::find('domain_register')['options']['tlds'] ?? []);
$data = ['reg' => $reg, 'bm' => $c('bm_annual'), 'tlds' => $tlds, 'email' => ['email_starter' => $c('email_starter'), 'email_business' => $c('email_business'), 'email_team' => $c('email_team')],
  'website' => ['website_launch' => $c('website_launch'), 'website_grow' => $c('website_grow'), 'website_scale' => $c('website_scale')], 'maint' => ['maintenance_care' => $c('maintenance_care'), 'maintenance_protect' => $c('maintenance_protect'), 'maintenance_grow' => $c('maintenance_grow')],
  'tiers' => App\Wizard::BUNDLE_TIERS];
$provs = ['FED' => 'Federal'] + Catalog::PROVINCES; unset($provs['FED']); $provs = ['FED' => 'Federal'] + $provs;
$pill = fn($name, $val, $label, $checked = false, $dis = false) => '<label class="pill"><input type="radio" name="' . $name . '" value="' . e($val) . '"' . ($checked ? ' checked' : '') . ($dis ? ' disabled' : '') . '><span>' . e($label) . '</span></label>';
$hero = ['landing' => true, 'actions' => [['#start', 'Build your bundle', true], ['/contact', 'Ask a question']], 'tone' => 'amber', 'title' => 'Bundle and save up to 15%.', 'sub' => 'Pick what you need. The more you add, the more you save.',
  'media' => ['img' => 'partners'], 'chips' => [['stack', '2 services: 5% off', 'Up to 15% for 5+'], ['check', 'One dashboard', 'Everything in one place']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="svc-body" id="start"><div class="wrap">
<form class="bun" data-bundle="<?= e(jenc($data)) ?>" novalidate>
  <div class="wz" style="padding:20px 22px">
    <div class="bun-bar"><b>Registration province</b><div class="pillset"><?php foreach ($provs as $k => $n): $has = array_filter(array_column($reg, $k), fn($v) => $v !== null); ?><?= $pill('province', $k, $k, $k === 'NB', !$has) ?><?php endforeach; ?></div></div>
    <div class="bun-bar"><b>Registration type</b><div class="pillset"><?= $pill('reg_type', 'sole_prop', 'Sole proprietorship') . $pill('reg_type', 'partnership', 'Partnership') . $pill('reg_type', 'named', 'Named corporation') . $pill('reg_type', 'numbered', 'Numbered corporation', true) ?></div></div>
    <ul class="tiers" data-tiers><?php foreach (App\Wizard::BUNDLE_TIERS as $n => $p): ?><li data-n="<?= $n ?>"><?= $n ?><?= $n === 5 ? '+' : '' ?> services · <?= $p ?>% off</li><?php endforeach; ?></ul>
    <h3 style="margin:22px 0 4px;font-size:1.1rem">Choose your services</h3>
    <div class="bun-svc">
      <?php $rows = [
        ['registration', 'building', 'amber', 'Business registration', 'Foundational', 'Register or incorporate in your province or federally. Government fees included.', ''],
        ['management', 'calendar', 'blue', 'Business management', 'Stay compliant', 'Your first annual return filed on time, plus deadline reminders.', ''],
        ['domain', 'globe', 'teal', 'Domain registration', '', 'Your domain with WHOIS privacy where allowed, and DNS set up for you.', 'tld'],
        ['email', 'mail', 'cyan', 'Business email', '', 'Professional email at your domain, with webmail and spam filtering.', 'email_plan'],
        ['website', 'layout', 'green', 'Business website', 'Best value', 'A professional website, built and hosted, connected to your domain and email.', 'website_plan'],
        ['maintenance', 'refresh', 'violet', 'Website maintenance', 'Peace of mind', 'Backups, updates, security and content edits for your site.', 'maint_plan'],
      ]; foreach ($rows as [$k, $ic, $tone, $t, $flag, $d, $opt]): ?>
      <div class="bs"><label class="bs-head"><input type="checkbox" name="services[<?= $k ?>]" value="1"<?= in_array($k, ['registration', 'domain'], true) ? ' checked' : '' ?>><span class="mc-ic tone-<?= $tone ?>"><?= nx_icon($ic) ?></span><span class="bs-t"><b><?= e($t) ?></b><?= $flag ? '<em>' . e($flag) . '</em>' : '' ?><p><?= e($d) ?></p></span><span class="bs-p" data-price="<?= $k ?>">—</span></label>
        <?php if ($opt === 'tld'): ?><div class="bs-opts">Extension: <div class="pillset"><?php $i = 0; foreach ($tlds as $t => $cc): ?><?= $pill('tld', $t, '.' . $t . ' ' . money($cc) . '/yr', $i++ === 0) ?><?php endforeach; ?></div></div>
        <?php elseif ($opt === 'email_plan'): ?><div class="bs-opts">Plan: <div class="pillset"><?php foreach ($data['email'] as $s => $cc): ?><?= $pill('email_plan', $s, ucfirst(str_replace('email_', '', $s)) . ' ' . money($cc) . '/mo', $s === 'email_starter') ?><?php endforeach; ?></div></div>
        <?php elseif ($opt === 'website_plan'): ?><div class="bs-opts">Plan: <div class="pillset"><?php foreach ($data['website'] as $s => $cc): ?><?= $pill('website_plan', $s, ucfirst(str_replace('website_', '', $s)) . ' ' . money($cc) . '/mo', $s === 'website_launch') ?><?php endforeach; ?></div></div>
        <?php elseif ($opt === 'maint_plan'): ?><div class="bs-opts">Plan: <div class="pillset"><?php foreach ($data['maint'] as $s => $cc): ?><?= $pill('maint_plan', $s, ucfirst(str_replace('maintenance_', '', $s)) . ' ' . money($cc) . '/mo', $s === 'maintenance_care') ?><?php endforeach; ?></div></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="bun-bar" style="margin-top:14px"><button type="button" class="btn btn-ghost btn-sm" data-all>Select all</button><button type="button" class="btn btn-ghost btn-sm" data-none>Clear all</button></div>
  </div>
  <div class="svc-side">
    <aside class="wz-sum tone-b-amber">
      <p class="ws-k">Your bundle summary</p>
      <ul class="ws-lines" data-b-lines></ul>
      <p class="muted small" data-b-empty>Select services on the left to build your bundle.</p>
      <div class="save-box" data-b-save></div>
      <dl class="ws-rows" data-b-totals></dl>
      <label class="ck agree" style="margin-top:14px"><input type="checkbox" name="agree" value="1"><span>I agree to the <a href="/legal/terms" target="_blank" rel="noopener">Service Agreement</a> and <a href="/legal/refunds" target="_blank" rel="noopener">Refund Policy</a>.</span></label>
      <p class="fe" data-b-err></p>
      <button class="btn btn-primary" type="submit" style="width:100%;margin-top:12px" data-b-submit>Add bundle to cart</button>
      <p class="hint" style="margin-top:10px">Discounts are applied to each service in your cart. Final prices are confirmed at checkout.</p>
    </aside>
  </div>
</form>
</div></section>
<div class="wz-mbar" data-b-mbar><span><small>Bundle total today</small><b data-b-mtotal>—</b></span><button type="button" class="btn btn-primary" data-b-mgo>Add to cart</button></div>
<?php $faqs = [
  ['How does the bundle discount work?', '2 services save 5%, 3 save 10%, 4 save 12% and 5 or more save 15%. The discount is applied to each service in the bundle.'],
  ['What if I already have a domain or email elsewhere?', 'Leave those services unticked. You still get the discount for the number of services you choose.'],
  ['When am I charged for each service?', 'One-time services and the first month of monthly plans are charged at checkout; domains are billed yearly.'],
  ['Can I change or cancel services later?', 'Monthly plans can be changed or cancelled anytime. If you cancel a service, the bundle discount on the remaining services may be recalculated.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
