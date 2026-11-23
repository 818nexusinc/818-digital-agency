<?php /* $faqs = [[question, answer], …]; $faqTitle optional. Answers are plain text. */ ?>
<section class="sec sec-faq"><div class="wrap narrow">
  <div class="sec-head center"><h2><?= e($faqTitle ?? 'Common questions') ?></h2></div>
  <div class="faq-l"><?php foreach ($faqs as [$q, $a]): ?><details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details><?php endforeach; ?></div>
  <?php if (!isset($noRefund)): ?><div class="refund-note"><b>Refund policy</b><p>You can cancel and get a full refund any time before your request is submitted for processing. Once it has been submitted to the registry or service provider, the service has started and is non-refundable. If we can't complete your request because of a problem on our end, we will refund it or provide an appropriate alternative. <a href="/legal/refunds">Full refund policy</a></p></div><?php endif; ?>
</div></section>
