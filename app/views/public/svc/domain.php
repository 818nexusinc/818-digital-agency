<?php
use App\Catalog;
$svc = Catalog::find('domain_register'); $tlds = $svc['options']['tlds'] ?? [];
$hero = ['tone' => 'teal', 'pills' => ['Domain registration', 'Automatic DNS'], 'title' => 'Claim your name online.', 'sub' => 'Search, register and we connect it to your email and website.',
  'media' => ['img' => 'laptop'], 'chips' => [['globe', '.ca, .com and more', 'Live availability check'], ['shield', 'WHOIS privacy', 'Where the registry allows']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec" style="padding:36px 0 20px"><div class="wrap narrow">
  <?php include APP_ROOT . '/views/partials/svc/domain_tool.php'; ?>
</div></section>
<?php $faqs = [
  ['What’s the difference between a .ca and a .com domain?', '.ca is Canada’s domain and tells customers you’re Canadian; it requires a Canadian presence. .com is global. Many businesses register both.'],
  ['How quickly does my domain go live?', 'Usually within minutes to a few hours of payment. DNS changes can take up to 24 hours to reach everyone.'],
  ['Is WHOIS privacy included?', 'Yes, where the registry allows it. .ca hides individual registrants’ details automatically.'],
  ['What is the refund policy?', 'Domain registrations can’t be refunded once registered with the registry, because the registry fee is non-refundable.'],
]; $noRefund = true; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
