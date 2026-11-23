<?php
use App\Catalog;
$fp = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$svc = Catalog::find('domain_register'); $tlds = $svc['options']['tlds'] ?? [];
$hero = ['landing' => true, 'tone' => 'pink', 'pills' => ['Domain', 'Email', 'Website', 'Custom software'],
  'title' => 'Get found. Get customers.', 'sub' => 'Your name online, your inbox and a website that sells. Built and looked after by one Canadian team.',
  'actions' => [['#domain', 'Search a domain', true], ['/website', 'See website plans']],
  'media' => ['video' => 'team-laptop'], 'chips' => [['globe', 'Live domain search', '.ca, .com and more'], ['bolt', 'Hosting + SSL', 'Included with every website']]];
include APP_ROOT . '/views/partials/svc/hero.php';
?>
<section class="sec" id="domain"><div class="wrap">
  <div class="sec-head"><h2>Start with your name.</h2><p>Search it now. We connect it to your email and website.</p></div>
  <?php include APP_ROOT . '/views/partials/svc/domain_tool.php'; ?>
</div></section>
<?php
$cols = [
  ['Claim your name', 'Domain Name', $fp('domain_register'), 'per year', ['.ca, .com and more', 'DNS set up for you', 'WHOIS privacy where allowed'], '/domain', 'Find a domain'],
  ['Look professional', 'Business Email', $fp('email_starter'), 'per month', ['you@yourbusiness.ca', 'Webmail and mobile', 'Spam filtering'], '/email', 'Set up email'],
  ['Win customers online', 'Website', $fp('website_launch'), 'per month', ['Designed for your brand', 'Hosting and SSL included', 'Updates every month'], '/website', 'Choose a plan'],
  ['Already have a site', 'Maintenance', $fp('maintenance_care'), 'per month', ['Daily backups', 'Security patching', 'Content edits'], '/website/maintenance', 'Protect my site'],
  ['Need something custom', 'Web Application', null, 'written quote', ['Portals and dashboards', 'Booking systems', 'Free consultation'], '/web-application', 'Get a quote'],
];
?>
<section class="sec chooser-sec"><div class="wrap">
  <div class="sec-head"><h2>Everything to get online.</h2><p>Pick what you need. Bundle them and save.</p></div>
  <?php $featured = 2; include APP_ROOT . '/views/partials/svc/chooser.php'; ?>
</div></section>
<?php $faqs = [
  ['Do I need a domain name first?', 'No. Choose “No, I need a domain” when you order email or a website and we register it for you.'],
  ['What does the website subscription include?', 'Design, build, hosting, SSL, security updates and the support on your plan. You don’t pay separately for hosting.'],
  ['How long does a website take?', 'Launch sites typically take 1–2 weeks, Grow 2–4 weeks and Scale 4–8 weeks, once we have your content.'],
  ['Can I bundle web services with my registration?', 'Yes. Add two or more services to a bundle and save up to 15% automatically.'],
]; include APP_ROOT . '/views/partials/svc/faq.php'; ?>
