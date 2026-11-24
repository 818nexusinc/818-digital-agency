<?php
/*
 * Site logo. Uploaded logos (Site editor → Logo): a dark logo for light backgrounds and a white logo for dark ones.
 * Both are output; the page shows the one that suits the colour behind it (data-bg, set by app.js from the actual
 * background, or fixed by the caller with $logoBg = 'dark' | 'light'). With only one uploaded, a one-colour version
 * of it stands in on the other background. Without uploads: "The Link" mark (8 · 1 · 8 rings, the 1 as connector).
 */
$__d = App\SiteText::logoVersion('dark'); $__l = App\SiteText::logoVersion('light');
$__bg = in_array($logoBg ?? null, ['dark', 'light'], true) ? $logoBg : null;
if ($__d || $__l):
  $__alt = e(App\Settings::get('company.name')); ?>
<span class="nx-logo<?= $__d && $__l ? ' has-both' : ($__d ? ' only-dark' : ' only-light') ?>"<?= $__bg ? ' data-bg="' . $__bg . '"' : ' data-logo-auto' ?>><?php
  if ($__d): ?><img class="site-logo lg-d" src="/brand/logo?v=<?= e($__d) ?>" alt="<?= $__alt ?>"><?php endif;
  if ($__l): ?><img class="site-logo lg-l" src="/brand/logo-light?v=<?= e($__l) ?>" alt="<?= $__d ? '' : $__alt ?>"<?= $__d ? ' aria-hidden="true"' : '' ?>><?php endif; ?></span>
<?php else: ?>
<svg viewBox="0 0 74 40" aria-hidden="true" focusable="false"><g fill="none" stroke-width="4.2"><circle cx="13" cy="11" r="7.2" stroke="#0b1530"/><circle cx="13" cy="28.5" r="9" stroke="#0b1530"/><circle cx="61" cy="11" r="7.2" stroke="#2753ff"/><circle cx="61" cy="28.5" r="9" stroke="#2753ff"/><path d="M33 9.5 L38 4 V36" stroke="#7aa2ff" stroke-linecap="round" stroke-linejoin="round"/><path d="M22.2 28.5 H51.8" stroke="#7aa2ff" stroke-linecap="round" stroke-dasharray="0 9.2"/></g></svg>
<?php endif; unset($logoBg);
