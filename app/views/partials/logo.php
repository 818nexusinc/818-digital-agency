<?php /* The uploaded logo when there is one (Site editor → Logo), otherwise "The Link" mark: 8 · 1 · 8 built from rings, the 1 as connector. */
$__lv = App\SiteText::logoVersion();
if ($__lv): ?><img class="site-logo" src="/brand/logo?v=<?= e($__lv) ?>" alt="<?= e(App\Settings::get('company.name')) ?>"><?php else: ?>
<svg viewBox="0 0 74 40" aria-hidden="true" focusable="false"><g fill="none" stroke-width="4.2"><circle cx="13" cy="11" r="7.2" stroke="#0b1530"/><circle cx="13" cy="28.5" r="9" stroke="#0b1530"/><circle cx="61" cy="11" r="7.2" stroke="#2753ff"/><circle cx="61" cy="28.5" r="9" stroke="#2753ff"/><path d="M33 9.5 L38 4 V36" stroke="#7aa2ff" stroke-linecap="round" stroke-linejoin="round"/><path d="M22.2 28.5 H51.8" stroke="#7aa2ff" stroke-linecap="round" stroke-dasharray="0 9.2"/></g></svg>
<?php endif; ?>
