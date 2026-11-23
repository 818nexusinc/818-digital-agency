<?php
/* Building blocks for the step-by-step service forms. Each helper returns HTML; the form engine in app.js
   adds step navigation, validation, live pricing and the review step. Prices shown come from the catalog. */
use App\Catalog;

if (!function_exists('wz_attrs')) {
function wz_attrs(array $a): string { $o = ''; foreach ($a as $k => $v) { if ($v === false || $v === null) continue; $o .= ' ' . $k . ($v === true ? '' : '="' . e((string)$v) . '"'); } return $o; }

/** Text-like input with label, hint and an error slot. $label ending in * marks it required. */
function wz_field(string $name, string $label, string $type = 'text', array $a = [], string $hint = ''): string {
    $req = str_ends_with($label, '*'); $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name) . '_' . substr(md5($name . $label), 0, 4);
    $clean = rtrim($label, ' *');
    $attrs = ['id' => $id, 'name' => $name, 'type' => $type, 'required' => $req, 'data-label' => $a['data-label'] ?? $clean] + $a;
    $tag = $type === 'textarea' ? '<textarea' . wz_attrs(array_diff_key($attrs, ['type' => 1])) . '></textarea>' : '<input' . wz_attrs($attrs) . '>';
    return '<div class="fld' . (!empty($a['data-wide']) ? ' wide' : '') . '"><label for="' . $id . '">' . e($clean) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>' . $tag
        . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '') . '<p class="fe" aria-live="polite"></p></div>';
}
function wz_select(string $name, string $label, array $options, array $a = []): string {
    $req = str_ends_with($label, '*'); $id = 'f_' . preg_replace('/[^a-z0-9]+/i', '_', $name) . '_' . substr(md5($name . $label), 0, 4);
    $o = '<option value="">Select…</option>'; foreach ($options as $v => $t) $o .= '<option value="' . e((string)$v) . '">' . e($t) . '</option>';
    return '<div class="fld' . (!empty($a['data-wide']) ? ' wide' : '') . '"><label for="' . $id . '">' . e(rtrim($label, ' *')) . ($req ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label><select' . wz_attrs(['id' => $id, 'name' => $name, 'required' => $req, 'data-label' => rtrim($label, ' *')] + $a) . '>' . $o . '</select><p class="fe" aria-live="polite"></p></div>';
}
/** Province tiles. $prices: code → cents (null = not offered → "Coming soon"). */
function wz_provinces(string $name, array $prices, string $label = 'Province or territory', bool $fed = false, ?string $default = null, array $by = []): string {
    // $prices: code → cents. Empty array = every province available, no price on the tile (quote-priced services).
    // $by: ['field' => 'slug', 'maps' => [slugValue => [code → cents]]] prices the tile from another field's value.
    $all = ($fed ? ['FED' => 'Federal'] : []) + App\Catalog::PROVINCES; unset($all['FED']); if ($fed) $all = ['FED' => 'Federal'] + $all;
    $h = '<fieldset class="tiles prov" data-label="' . e($label) . '"><legend>' . e($label) . ' <span class="req" aria-hidden="true">*</span></legend><div class="tile-grid">';
    foreach ($all as $c => $n) {
        if ($by) { $vals = array_map(fn($m) => $m[$c] ?? null, $by['maps']); $off = !array_filter($vals, fn($v) => $v !== null); $p = null; }
        else { $off = $prices !== [] && ($prices[$c] ?? null) === null; $p = $prices[$c] ?? null; }
        $attr = '';
        if ($by) { $attr = ' data-by="' . e($by['field']) . '" data-line="Government & service fee"'; foreach ($vals as $sv => $v) if ($v !== null) $attr .= ' data-c-' . e($sv) . '="' . (int)$v . '"'; }
        elseif ($p !== null) $attr = ' data-cents="' . (int)$p . '" data-line="Government & service fee"';
        $h .= '<label class="tile' . ($off ? ' off' : '') . '"><input type="radio" name="' . e($name) . '" value="' . $c . '"' . ($off ? ' disabled' : '') . ($c === $default && !$off ? ' checked' : '') . ' required data-label="' . e($label) . '" data-text="' . e($c === 'FED' ? 'Federal (CBCA)' : $n) . '"' . $attr . '>'
            . '<b>' . $c . '</b><span>' . e($off ? 'Coming soon' : ($c === 'FED' ? 'Canada-wide' : $n)) . '</span></label>';
    }
    return $h . '</div><p class="fe" aria-live="polite"></p></fieldset>';
}
/** Option cards (radio). Each option: [value, title, description, badge, extra attrs]. */
function wz_cards(string $name, string $label, array $opts, string $cls = '', bool $required = true): string {
    $h = '<fieldset class="cards ' . $cls . '" data-label="' . e($label) . '"><legend>' . e($label) . ($required ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</legend><div class="card-grid">';
    foreach ($opts as $o) {
        [$v, $t, $d] = $o; $badge = $o[3] ?? ''; $ex = $o[4] ?? [];
        $h .= '<label class="ocard"><input type="radio" name="' . e($name) . '" value="' . e($v) . '"' . ($required ? ' required' : '') . ' data-label="' . e($label) . '" data-text="' . e($t) . '"' . wz_attrs($ex) . '>'
            . '<span class="oc-top"><b>' . e($t) . '</b>' . ($badge !== '' ? '<em>' . e($badge) . '</em>' : '') . '</span>' . ($d !== '' ? '<span class="oc-d">' . e($d) . '</span>' : '') . '</label>';
    }
    return $h . '</div><p class="fe" aria-live="polite"></p></fieldset>';
}
/** Add-on checkboxes priced from the catalog. */
function wz_addons(array $slugs, string $label = 'Optional add-ons', string $name = 'addons[]'): string {
    $h = '<fieldset class="checks"><legend>' . e($label) . '</legend>';
    foreach ($slugs as $s) {
        $svc = Catalog::find($s); if (!$svc) continue; $p = Catalog::price($svc, []);
        $h .= '<label class="ck"><input type="checkbox" name="' . e($name) . '" value="' . e($s) . '" data-label="Add-on" data-text="' . e($svc['name']) . '"' . ($p ? ' data-cents="' . (int)$p['unit_cents'] . '" data-line="' . e($svc['name']) . '"' : '') . '>'
            . '<span><b>' . e($svc['name']) . '</b><small>' . e($svc['summary']) . '</small></span><i>' . ($p ? '+' . e(money($p['unit_cents'])) : '') . '</i></label>';
    }
    return $h . '</fieldset>';
}
/** Repeatable group (partners, directors, mailboxes). $inner uses {i} for the index and {n} for the number. */
function wz_repeat(string $key, string $itemLabel, string $inner, int $min = 1, int $max = 10, string $addLabel = 'Add another'): string {
    return '<div class="rep" data-repeat="' . e($key) . '" data-min="' . $min . '" data-max="' . $max . '" data-item="' . e($itemLabel) . '">'
        . '<div class="rep-list"></div><template>' . '<div class="rep-item"><div class="rep-head"><b>' . e($itemLabel) . ' {n}</b><button type="button" class="link-btn" data-rep-remove>Remove</button></div><div class="grid2">' . $inner . '</div></div></template>'
        . '<button type="button" class="btn btn-ghost btn-sm" data-rep-add>+ ' . e($addLabel) . '</button><p class="fe" aria-live="polite"></p></div>';
}
/** Wizard shell. $steps: titles. Endpoint: /order/add or /order/quote. */
function wz_open(array $steps, array $a = []): string {
    $h = '<form class="wz" data-wz novalidate' . wz_attrs($a) . '><ol class="wz-tabs">';
    foreach ($steps as $i => $s) $h .= '<li' . ($i === 0 ? ' class="on"' : '') . '><span>' . ($i + 1) . '</span><em>' . e($s) . '</em></li>';
    return $h . '</ol><div class="wz-bar"><i></i></div>';
}
function wz_step(int $i, string $title, string $body, bool $last = false, string $next = 'Continue'): string {
    return '<section class="wz-step" data-step="' . $i . '"' . ($i ? ' hidden' : '') . '><h3>' . e($title) . '</h3>' . $body
        . '<div class="wz-nav">' . ($i ? '<button type="button" class="btn btn-ghost" data-wz-back>Back</button>' : '<span></span>')
        . ($last ? '' : '<button type="button" class="btn btn-primary" data-wz-next>' . e($next) . '</button>') . '</div></section>';
}
/** Final review step: summary of everything entered + agreement + submit. */
function wz_review(int $i, string $submit, bool $quote = false): string {
    return '<section class="wz-step" data-step="' . $i . '" hidden><h3>Review &amp; submit</h3><p class="muted">Check everything below. You can go back to change any step.</p><dl class="wz-review" data-wz-review></dl>'
        . '<label class="ck agree"><input type="checkbox" name="agree" value="1" required data-label="Agreement"><span>I have read and agree to the <a href="/legal/terms" target="_blank" rel="noopener">Service Agreement</a> and <a href="/legal/refunds" target="_blank" rel="noopener">Refund Policy</a>.' . ($quote ? '' : ' Nothing is filed until I review and sign the final filing in my portal.') . '</span></label><p class="fe" aria-live="polite"></p>'
        . '<div class="wz-nav"><button type="button" class="btn btn-ghost" data-wz-back>Back</button><button type="submit" class="btn btn-primary" data-wz-submit>' . e($submit) . '</button></div>'
        . '<div class="wz-done" hidden></div></section>';
}
function wz_close(): string { return '<input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true"></form>'; }

/** Sticky summary card. $rows: [label, value]; $incl: included items. */
function wz_summary(string $title, ?int $baseCents, string $note, array $rows, array $incl, string $tone = 'blue'): string {
    $h = '<aside class="wz-sum tone-b-' . $tone . '"><p class="ws-k">' . e($title) . '</p><p class="ws-total"><b data-sum-total data-base="' . (int)($baseCents ?? 0) . '">' . ($baseCents !== null ? e(money($baseCents)) : '—') . '</b> <span>CAD</span></p><p class="ws-note">' . e($note) . '</p>'
        . '<ul class="ws-lines" data-sum-lines></ul><dl class="ws-rows">';
    foreach ($rows as [$k, $v]) $h .= '<dt>' . e($k) . '</dt>' . (str_starts_with($v, '@') ? '<dd data-sum-field="' . e(substr($v, 1)) . '">—</dd>' : '<dd>' . e($v) . '</dd>');
    $h .= '</dl>';
    if ($incl) { $h .= '<p class="ws-k" style="margin-top:16px">Included</p><ul class="ws-incl">'; foreach ($incl as $x) $h .= '<li>' . e($x) . '</li>'; $h .= '</ul>'; }
    return $h . '</aside>';
}
function wz_mbar(): string { return '<div class="wz-mbar" data-wz-mbar><span><small>Total</small><b data-sum-total-m>—</b></span><button type="button" class="btn btn-primary" data-wz-mnext>Continue</button></div>'; }
}
