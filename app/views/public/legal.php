<?php $co = App\Settings::get('company.legal'); $email = App\Settings::get('company.email');
$trust = ['privacy' => ['Privacy', 'What we collect, why, and your choices.'], 'terms' => ['Terms', 'The agreement for using our services.'], 'refunds' => ['Refunds', 'When and how you get your money back.'],
  'cookies' => ['Cookies', 'One essential cookie. No trackers.'], 'ai' => ['How we use AI', 'What our AI does, and what it never does.']];
$summ = $trust[$page][1] ?? ''; ?>
<section class="trust-hero"><div class="wrap">
  <nav class="crumbs" aria-label="<?= e(t('Breadcrumb')) ?>"><a href="/"><?= e(t('Home')) ?></a><span aria-hidden="true">/</span><span><?= e(t('Trust centre')) ?></span><span aria-hidden="true">/</span><b aria-current="page"><?= e(t($trust[$page][0] ?? $title)) ?></b></nav>
  <h1 data-split><?= e($title) ?></h1>
  <p class="th-sub"><?= e(t($summ)) ?></p>
  <nav class="trust-tabs" aria-label="<?= e(t('Trust centre')) ?>"><?php foreach ($trust as $k => [$n, $d]): ?><a href="/legal/<?= $k ?>"<?= $k === $page ? ' aria-current="page"' : '' ?>><?= e(t($n)) ?></a><?php endforeach; ?></nav>
</div></section>
<section class="trust-body"><div class="wrap trust-grid">
  <aside class="toc" aria-label="<?= e(t('On this page')) ?>"><p><?= e(t('On this page')) ?></p><ol data-toc></ol>
    <div class="toc-help"><b><?= e(t('Questions?')) ?></b><a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></div></aside>
  <article class="prose legal" data-toc-src>
  <p class="legal-meta">Operated by <?= e($co) ?>, <?= e(App\Settings::get('company.city')) ?>. Questions: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a>. This document describes how this platform actually works. Have it reviewed by your legal advisor before relying on it.</p>
<?php if ($page === 'privacy'): ?>
<h2>What we collect</h2><ul><li>Contact and business details you give us (name, email, phone, business name, location).</li><li>Order, project, support and payment records. Card details are handled by Stripe and never reach our servers.</li><li>Conversations with our AI assistant, and preferences it saves to help you (only when you're signed in and memory is on).</li><li>Technical data needed for security: IP address, sign-in events and an essential session cookie.</li></ul>
<h2>Why we use it</h2><p>To provide and support the services you request, prepare proposals, keep records required by law, protect accounts against abuse, and, only with your consent, send marketing emails.</p>
<h2>AI processing</h2><p>Messages and project details may be processed by our AI providers (for example Anthropic) to generate responses and drafts. We send only the information a task needs and we do not sell your data.</p>
<h2>Your choices</h2><ul><li>View, export or delete the information our assistant remembers in <a href="/portal/account">Account → Privacy</a>.</li><li>Turn assistant memory off at any time.</li><li>Unsubscribe from marketing email using the link in any marketing message.</li><li>Ask us to access, correct or delete your personal information by emailing <?= e($email) ?>.</li></ul>
<h2>Retention</h2><p>Anonymous chat conversations are deleted after 180 days. Business and financial records are kept as long as the law requires. Saved assistant memories expire after two years unless you delete them sooner.</p>
<h2>Safeguards</h2><p>We use encrypted connections, hashed passwords, encrypted storage for credentials, role-based access, audit logs and multi-factor authentication for staff.</p>
<?php elseif ($page === 'terms'): ?>
<h2>Services</h2><p>We provide the services described on each service page or in an accepted proposal. Registration services prepare and submit filings on your instructions. We are not a law firm and do not provide legal, tax or accounting advice.</p>
<h2>Your responsibilities</h2><p>You confirm that the information you give us is accurate and that you're authorised to act for the business. Filings and domain registrations are made in the names you provide.</p>
<h2>Pricing &amp; payment</h2><p>Prices are in CAD and are shown before you pay. Subscriptions renew automatically until cancelled. Third-party and government fees are passed through as described.</p>
<h2>AI-assisted work</h2><p>Parts of our service are prepared by AI systems and reviewed according to our quality and approval processes. Deliverables are yours once paid for, except third-party components that carry their own licences.</p>
<h2>Liability</h2><p>Our liability for any claim is limited to the amount you paid for the service involved, to the extent the law allows.</p>
<?php elseif ($page === 'refunds'): ?>
<h2>Summary</h2><ul><li>Work not yet started: full refund of our service fee.</li><li>Government filing fees, NUANS reports and domain registry fees: non-refundable once submitted.</li><li>Website and app projects: refund based on milestones not yet completed.</li><li>Subscriptions: cancel any time. The current period runs to its end and isn't refunded unless required by law.</li></ul>
<h2>How to request</h2><p>Open a support request in your <a href="/portal/support">portal</a>. A person reviews every refund request; our AI cannot issue refunds.</p>
<?php elseif ($page === 'cookies'): ?>
<p>We use one essential cookie (<span class="kbd">nx818sid</span>) to keep you signed in, protect forms against forgery and remember your cart. We don't use advertising or cross-site tracking cookies. If we add analytics in future, it will only load after you opt in.</p>
<?php else: ?>
<h2>Where we use AI</h2><ul><li>The website assistant answers questions, recommends services, prices them from our catalog and can create carts, proposals and support requests.</li><li>Agents plan projects, draft deliverables (specifications, copy, checklists), check quality, write first support replies and prepare business reports.</li></ul>
<h2>Limits we enforce in software</h2><ul><li>Prices and policies come only from our catalog and knowledge base.</li><li>Government filings are submitted only after payment, automatic validation and the authorization you give when you submit your order form. Deployments, DNS and domain transfers, refunds and payment changes require human approval.</li><li>Each agent has spending limits and restricted permissions. Agents can't change their own permissions.</li><li>Your data is scoped to your account. Agents can't see other customers' information.</li></ul>
<h2>Your options</h2><p>Ask for a person at any time with "Talk to a person" in the chat or the <a href="/contact">contact form</a>.</p>
<?php endif; ?>
  </article>
</div></section>
