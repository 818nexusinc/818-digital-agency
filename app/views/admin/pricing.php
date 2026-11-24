<?php
use App\{Catalog, Pricing, Csrf};
$cad = fn(?int $c, int $d = 0) => $c === null ? '—' : '$' . number_format($c / 100, $d);
$cad2 = fn(?int $c) => $c === null ? '—' : '$' . number_format($c / 100, ($c % 100) ? 2 : 0);
$cats = ['all' => 'All'] + array_diff_key(Catalog::CATEGORIES, ['addon' => 1]) + ['addon' => 'Add-ons'];
$chartRows = array_values(array_filter($rows, fn($r) => $r['price'] !== null));
$bill = fn($r) => $r['billing'] === 'one-time' ? '' : '/' . $r['billing'];

/*
 * Horizontal stacked bars: government fee + third-party cost + 818 markup = our price.
 * Competitor prices sit on the same row as dots joined by their low–high range. One axis (CAD).
 */
$chart = function (array $rows) use ($cad2, $bill): string {
    if (!$rows) return '';
    // Layout: a heading line per service that has several options, then one bar per option.
    $lay = []; $seen = [];
    foreach ($rows as $r) { if ($r['vlabel'] !== '' && !isset($seen[$r['slug']])) { $lay[] = ['h', $r['name']]; $seen[$r['slug']] = 1; } $lay[] = ['b', $r]; }
    $W = 980; $L = 250; $R = 90; $rowH = 32; $headH = 26; $T = 20; $B = 30;
    $H = $T + $B; foreach ($lay as $l) $H += $l[0] === 'h' ? $headH : $rowH;
    $max = 0; foreach ($rows as $r) $max = max($max, (int)$r['price'], (int)($r['high'] ?? 0));
    $raw = max(1, $max) / 5; $p = 10 ** floor(log10($raw)); $step = $p; foreach ([1, 2, 2.5, 5, 10] as $f) if ($f * $p >= $raw) { $step = $f * $p; break; }
    $top = $step * ceil($max / $step ?: 1); $pw = $W - $L - $R;
    $x = fn($v) => $L + $v / $top * $pw;
    $o = '<svg class="pz-svg" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Government fee, other costs and 818 markup that make up each price, with competitor prices">';
    for ($v = 0; $v <= $top + 0.1; $v += $step) { $xx = round($x($v), 1);
        $o .= '<line class="' . ($v ? 'grid' : 'base') . '" x1="' . $xx . '" x2="' . $xx . '" y1="' . ($T - 6) . '" y2="' . ($H - $B) . '"/><text class="ax" x="' . $xx . '" y="' . ($H - 10) . '" text-anchor="middle">' . e($cad2((int)$v)) . '</text>'; }
    $y = $T;
    foreach ($lay as [$kind, $r]) {
        if ($kind === 'h') { $o .= '<text class="lbl" x="0" y="' . ($y + 18) . '">' . e($r) . '</text>'; $y += $headH; continue; }
        $cy = $y + $rowH / 2; $bh = 16; $by = $cy - $bh / 2; $y += $rowH;
        if ($r['vlabel'] !== '') $o .= '<text class="var" x="16" y="' . ($cy + 4) . '">' . e($r['vlabel']) . '</text>';
        else $o .= '<text class="lbl" x="0" y="' . ($cy + 4) . '">' . e(str_limit($r['name'], 32)) . '</text>';
        $segs = []; $cursor = 0;
        if ($r['markup'] !== null) {
            foreach ([['gov', (int)$r['gov'], 'Government fee'], ['third', (int)$r['third'], 'Other costs'], ['mk', max(0, (int)$r['markup']), '818 markup']] as [$k, $v, $lbl]) if ($v > 0) { $segs[] = [$k, $cursor, $v, $lbl]; $cursor += $v; }
        } else $segs[] = ['unk', 0, (int)$r['price'], 'Costs not entered yet'];
        $n = count($segs);
        foreach ($segs as $j => [$k, $from, $v, $lbl]) {
            $x0 = $x($from) + ($j ? 1 : 0); $x1 = $x($from + $v) - ($j < $n - 1 ? 1 : 0); $w = max(0, $x1 - $x0); if ($w <= 0) continue;
            $rad = $j === $n - 1 ? min(4, $w / 2) : 0;
            $d = $rad ? 'M' . round($x0, 1) . ',' . $by . ' H' . round($x1 - $rad, 1) . ' Q' . round($x1, 1) . ',' . $by . ' ' . round($x1, 1) . ',' . ($by + $rad) . ' V' . ($by + $bh - $rad) . ' Q' . round($x1, 1) . ',' . ($by + $bh) . ' ' . round($x1 - $rad, 1) . ',' . ($by + $bh) . ' H' . round($x0, 1) . ' Z'
                : 'M' . round($x0, 1) . ',' . $by . ' H' . round($x1, 1) . ' V' . ($by + $bh) . ' H' . round($x0, 1) . ' Z';
            $o .= '<path class="sg sg-' . $k . '" d="' . $d . '"><title>' . e($r['name'] . ($r['vlabel'] ? ' · ' . $r['vlabel'] : '') . ' — ' . $lbl . ': ' . $cad2($v)) . '</title></path>';
        }
        if ($r['markup'] !== null && $r['markup'] < 0) $o .= '<text class="loss" x="' . round($x((int)$r['price']) + 6, 1) . '" y="' . ($cy - 10) . '">below cost</text>';
        if ($r['comp']) {
            if ($r['low'] !== $r['high']) $o .= '<line class="rng" x1="' . round($x($r['low']), 1) . '" x2="' . round($x($r['high']), 1) . '" y1="' . $cy . '" y2="' . $cy . '"/>';
            foreach ($r['comp'] as $c) $o .= '<circle class="cp" cx="' . round($x((int)$c['price_cents']), 1) . '" cy="' . $cy . '" r="4.5"><title>' . e($c['competitor'] . ': ' . $cad2((int)$c['price_cents']) . ($c['includes_gov_fee'] === null ? '' : ($c['includes_gov_fee'] ? ' (fee included)' : ' (plus government fee)'))) . '</title></circle>';
        }
        $o .= '<text class="pr" x="' . ($W - $R + 10) . '" y="' . ($cy + 4) . '">' . e($cad2((int)$r['price']) . $bill($r)) . '</text>';
    }
    return $o . '</svg>';
};
$s = $sum;
?>
<div class="db pz">
<div class="db-head">
  <div><h1>Pricing strategy</h1><p class="db-bc">What each price is made of, what competitors in Atlantic Canada charge, and the Pricing Agent's proposals</p></div>
  <div class="db-acts"><a class="btn btn-ghost btn-sm" href="/admin/catalog">Services &amp; pricing</a><a class="btn btn-ghost btn-sm" href="/admin/agents/pricing">Pricing Agent settings</a></div>
</div>

<?php if (!$aiOn): ?><div class="ct-warn"><p><b>Web research needs the Anthropic connection.</b> Until then, add fees and competitor prices yourself on each service's page and the chart still works. <a href="/admin/integrations">Connect Anthropic</a></p></div><?php endif; ?>

<div class="pz-top">
  <section class="db-card pz-ask">
    <h2>Ask the Pricing Agent</h2>
    <p class="db-sub">It searches government sites for the official filing fees and checks what other providers in New Brunswick, Nova Scotia, PEI and Newfoundland and Labrador charge. Every number it saves links to where it found it. Prices only change when you approve.</p>
    <form method="post" action="/admin/pricing/action" class="pz-form"><?= Csrf::field() ?><input type="hidden" name="action" value="research"><input type="hidden" name="cat" value="<?= e($cat) ?>">
      <label class="sr" for="pz-focus">Anything to focus on</label><input id="pz-focus" name="focus" type="text" maxlength="600" placeholder="Optional: e.g. focus on Nova Scotia incorporations">
      <button class="btn btn-primary">Research all services</button></form>
    <?php if ($tasks): ?><ul class="pz-tasks"><?php foreach ($tasks as $t): ?><li><a href="/admin/tasks/<?= (int)$t['id'] ?>"><?= e($t['title']) ?></a> <span class="pill-s s-<?= e($t['status']) ?>"><?= e(label($t['status'])) ?></span> <small><?= e(substr((string)($t['finished_at'] ?: $t['created_at']), 0, 16)) ?> UTC</small></li><?php endforeach; ?></ul><?php endif; ?>
  </section>
  <dl class="db-card pz-k">
    <div><dt>Price points</dt><dd><?= $s['points'] ?></dd></div>
    <div><dt>Costs on file</dt><dd><?= $s['costed'] ?><small> · <?= $s['confirmed'] ?> confirmed</small></dd></div>
    <div><dt>Average 818 markup</dt><dd><?= $s['avg_markup'] === null ? '—' : $s['avg_markup'] . '%' ?></dd></div>
    <div><dt>Competitor prices</dt><dd><?= $s['competitors'] ?></dd></div>
    <div><dt>Proposals waiting</dt><dd class="<?= $s['proposals'] ? 'warn' : '' ?>"><?= $s['proposals'] ?></dd></div>
    <div><dt>Below cost</dt><dd class="<?= $s['losing'] ? 'bad' : '' ?>"><?= $s['losing'] ?></dd></div>
  </dl>
</div>

<?php if ($proposals): ?>
<section class="db-card pz-props" aria-labelledby="pp-h">
  <div class="db-ch"><div><h2 id="pp-h">Proposals waiting for you</h2><p class="db-sub">Approving changes the price on the website straight away.</p></div></div>
  <?php foreach ($proposals as $p): $up = $p['current_cents'] !== null && $p['proposed_cents'] > $p['current_cents']; ?>
  <div class="pz-prop">
    <div class="pz-pm"><b><?= e($p['name']) ?><?= $p['variant'] !== '' ? ' · ' . e(Pricing::REGIONS[$p['variant']] ?? '.' . $p['variant']) : '' ?></b>
      <span class="pz-chg"><?= $cad2($p['current_cents'] === null ? null : (int)$p['current_cents']) ?> → <strong class="<?= $up ? 'up' : 'down' ?>"><?= $cad2((int)$p['proposed_cents']) ?></strong></span>
      <small><?= e(Pricing::STRATEGIES[$p['strategy']] ?? $p['strategy']) ?> · by <?= e($p['created_by'] === 'pricing' ? 'Pricing Agent' : $p['created_by']) ?></small>
      <p><?= nl2br(e(str_limit((string)$p['rationale'], 600))) ?></p></div>
    <div class="pz-pa">
      <form method="post" action="/admin/pricing/action" data-confirm="Change the live price to <?= e($cad2((int)$p['proposed_cents'])) ?>?"><?= Csrf::field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="cat" value="<?= e($cat) ?>"><button class="btn btn-primary btn-sm">Approve</button></form>
      <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="cat" value="<?= e($cat) ?>"><button class="btn btn-sm">Reject</button></form>
      <a class="db-more" href="/admin/pricing/<?= e($p['service_slug']) ?>">Details</a>
    </div>
  </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<section class="db-card pz-chart" aria-labelledby="ch-h">
  <div class="db-ch">
    <div><h2 id="ch-h">What each price is made of</h2><p class="db-sub">Government fee, other costs and our markup add up to the price customers pay. Dots are competitor prices; hover for details.</p></div>
  </div>
  <nav class="pz-cats" aria-label="Service group"><?php foreach ($cats as $k => $n): ?><a href="/admin/pricing?cat=<?= e($k) ?>"<?= $cat === $k ? ' aria-current="page"' : '' ?>><?= e($n) ?></a><?php endforeach; ?></nav>
  <div class="pz-legend"><span><i class="lg-sw sg-gov"></i>Government fee</span><span><i class="lg-sw sg-third"></i>Other costs</span><span><i class="lg-sw sg-mk"></i>818 markup</span><span><i class="lg-sw sg-unk"></i>Costs not entered yet</span><span><i class="lg-dot"></i>Competitor price</span></div>
  <?php if ($chartRows): ?><div class="pz-plot"><?= $chart($chartRows) ?></div>
  <?php else: ?><div class="ct-empty"><b>No listed prices in this group.</b><p>These services are quoted individually.</p></div><?php endif; ?>
</section>

<section class="db-card pz-tbl" aria-labelledby="tb-h">
  <div class="db-ch"><div><h2 id="tb-h">Price points</h2><p class="db-sub">Open a service to enter official fees, add competitor prices or propose a change.</p></div></div>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Service</th><th>Option</th><th class="num">Our price</th><th class="num">Gov. fee</th><th class="num">Other costs</th><th class="num">818 markup</th><th>Competitors</th><th>Position</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/admin/pricing/<?= e($r['slug']) ?>"><?= e($r['name']) ?></a></td>
        <td><?= e($r['vlabel'] ?: '—') ?></td>
        <td class="num"><?= $r['price'] === null ? '<span class="mut">Quoted</span>' : e($cad2($r['price']) . $bill($r)) ?></td>
        <td class="num"><?= $r['gov'] === null ? '<span class="mut">—</span>' : e($cad2($r['gov'])) . ($r['confirmed'] ? '' : ' <span class="unc" title="Found by the Pricing Agent; not confirmed yet">unconfirmed</span>') ?></td>
        <td class="num"><?= $r['third'] === null ? '<span class="mut">—</span>' : e($cad2($r['third'])) ?></td>
        <td class="num"><?= $r['markup'] === null ? '<span class="mut">—</span>' : '<b class="' . ($r['markup'] < 0 ? 'bad' : '') . '">' . e($cad2($r['markup'])) . '</b> <small>' . (int)$r['markup_pct'] . '%</small>' ?></td>
        <td><?= $r['comp'] ? e($cad2($r['low'])) . ($r['low'] !== $r['high'] ? '–' . e($cad2($r['high'])) : '') . ' <small>(' . count($r['comp']) . ')</small>' : '<span class="mut">None yet</span>' ?></td>
        <td><?= $r['position'] ? '<span class="pos pos-' . $r['position'] . '">' . ['below' => 'Below market', 'at' => 'At market', 'above' => 'Above market'][$r['position']] . '</span>' : '<span class="mut">—</span>' ?><?= $r['proposals'] ? ' <span class="pos pos-prop">Proposal</span>' : '' ?></td>
        <td><a class="db-more" href="/admin/pricing/<?= e($r['slug']) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
</div>
