<?php
use App\Catalog;
include_once APP_ROOT . '/views/partials/icons.php';
$fromP = function (string $slug) { $s = Catalog::find($slug); return $s ? Catalog::fromPrice($s) : null; };
$hero = ['landing' => true, 'tone' => 'blue', 'pills' => ['Every province + federal', 'Deadline reminders'],
  'title' => 'Stay in good standing. Without the paperwork.', 'sub' => 'Annual returns, corporate changes and official documents, filed for you.',
  'actions' => [['#services', 'View all services', true]],
  'media' => ['img' => 'docs-typing'], 'chips' => [['calendar', 'Never miss a deadline', 'Reminders before it is due'], ['certificate', 'Official documents', 'Ordered from the registry']]];
include APP_ROOT . '/views/partials/svc/hero.php';
$cols = [
  ['Anniversary date coming up', 'Annual Return', $fromP('bm_annual'), 'per filing', ['Prepared and filed for you', 'Reminder next year', 'Every province + federal'], '/manage-business/annual-returns', 'File my return'],
  ['Something changed', 'Corporate Changes', $fromP('bm_changes'), 'per change', ['Registered address', 'Directors and officers', 'Filed with the registry'], '/manage-business/corporate-changes', 'Update records'],
  ['The bank needs proof', 'Corporate Documents', $fromP('bm_documents'), 'per order', ['Certificate of status', 'Corporate profile', 'Certified copies'], '/manage-business/corporate-documents', 'Order documents'],
  ['Name expiring or changing', 'Business Name', null, 'priced by province', ['Renewal or name change', 'Sole props and partnerships', 'Filed with your province'], '/manage-business/name-services', 'Renew or change'],
];
?>
<section class="sec chooser-sec" id="services"><div class="wrap">
  <div class="sec-head"><h2>What do you need?</h2><p>Pick the situation. We handle the filing.</p></div>
  <?php $featured = 0; include APP_ROOT . '/views/partials/svc/chooser.php'; ?>
</div></section>
