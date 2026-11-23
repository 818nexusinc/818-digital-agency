<?php
use App\Catalog;
$svc = Catalog::find('domain_register'); $tlds = $svc['options']['tlds'] ?? [];
$hero = ['tone' => 'teal', 'pills' => ['Domain registration', 'Automatic DNS'], 'title' => 'Claim your name online.', 'sub' => 'Search, register and we connect it to your email and website.',
  'media' => ['img' => 'laptop'], 'chips' => [['globe', '.ca, .com and more', 'Live availability check'], ['shield', 'WHOIS privacy', 'Where the registry allows']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec" style="padding:36px 0 20px"><div class="wrap narrow">
  <div class="dom-box" data-domain-tool data-tlds="<?= e(jenc(array_map('intval', $tlds))) ?>">
    <h3 style="margin:0 0 12px">Search your domain</h3>
    <form class="dom-search" data-dom-form><label class="sr" for="dom-q">Domain name</label><input id="dom-q" type="text" placeholder="mybusiness.ca" autocomplete="off" autocapitalize="none" spellcheck="false"><button class="btn btn-primary" type="submit">Search</button></form>
    <div class="tld-row"><span>Popular extensions:</span><?php foreach ($tlds as $t => $c): ?><button type="button" data-tld="<?= e($t) ?>">.<?= e($t) ?> <small><?= e(money((int)$c)) ?>/yr</small></button><?php endforeach; ?></div>
    <div class="dom-results" data-dom-results aria-live="polite"></div>
    <label class="ck agree" data-dom-agree hidden><input type="checkbox" data-agree><span>I agree to the <a href="/legal/terms" target="_blank" rel="noopener">Service Agreement</a> and <a href="/legal/refunds" target="_blank" rel="noopener">Refund Policy</a>.</span></label>
    <p class="fe" data-dom-err></p>
    <div class="dom-note">After payment your domain is registered and DNS records are set up for the email and website you order with us. WHOIS privacy is included where the registry allows it.</div>
  </div>
</div></section>
<?php $faqs = [
  ['What’s the difference between a .ca and a .com domain?', '.ca is Canada’s domain and tells customers you’re Canadian; it requires a Canadian presence. .com is global. Many businesses register both.'],
  ['How quickly does my domain go live?', 'Usually within minutes to a few hours of payment. DNS changes can take up to 24 hours to reach everyone.'],
  ['Is WHOIS privacy included?', 'Yes, where the registry allows it. .ca hides individual registrants’ details automatically.'],
  ['What is the refund policy?', 'Domain registrations can’t be refunded once registered with the registry, because the registry fee is non-refundable.'],
]; $noRefund = true; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
