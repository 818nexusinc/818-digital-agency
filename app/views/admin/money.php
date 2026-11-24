<?php
$d = $dash;
$ago = function (?string $at): string {
    if (!$at) return '';
    $s = max(0, time() - strtotime($at . ' UTC'));
    return $s < 3600 ? max(1, (int)round($s / 60)) . 'm' : ($s < 86400 ? (int)round($s / 3600) . 'h' : ($s < 86400 * 30 ? (int)round($s / 86400) . 'd' : substr($at, 0, 10)));
};
$cronOk = $cron && strtotime($cron . ' UTC') >= time() - 900;
$aiOn = $providers['anthropic'] || $providers['openai'];
$pct = fn(int $a, int $b) => $b > 0 ? (int)round($a / $b * 100) : 0;
$cad = fn(int $c) => '$' . number_format($c / 100, 0);

/* Grouped bar chart as SVG: one axis, rounded data ends, 2px gap inside each pair, native tooltip per bar. */
$chart = function (array $rows, string $id) use ($cad): string {
    $W = 920; $H = 300; $L = 64; $R = 12; $T = 14; $B = 34;
    $max = 0; foreach ($rows as $r) $max = max($max, $r['inv'], $r['paid']);
    $step = $max <= 0 ? 1000 : (function ($m) { $raw = $m / 4; $p = 10 ** floor(log10($raw)); foreach ([1, 2, 2.5, 5, 10] as $f) if ($f * $p >= $raw) return $f * $p; return 10 * $p; })($max);
    $top = $step * 4; $pw = $W - $L - $R; $ph = $H - $T - $B; $n = count($rows); $slot = $pw / max(1, $n);
    $bw = min(22, ($slot - 14) / 2);
    $y = fn($v) => $T + $ph - ($v / $top) * $ph;
    $o = '<svg class="ybars" id="' . $id . '" viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="Invoiced and paid revenue by period">';
    for ($i = 0; $i <= 4; $i++) { $v = $step * $i; $yy = round($y($v), 1);
        $o .= '<line x1="' . $L . '" x2="' . ($W - $R) . '" y1="' . $yy . '" y2="' . $yy . '" class="' . ($i ? 'grid' : 'base') . '"/><text x="' . ($L - 8) . '" y="' . ($yy + 4) . '" class="ax" text-anchor="end">' . e($cad((int)$v)) . '</text>'; }
    foreach ($rows as $i => $r) {
        $cx = $L + $slot * $i + $slot / 2;
        foreach ([['inv', $cx - $bw - 1, 'b-inv', 'Invoiced'], ['paid', $cx + 1, 'b-paid', 'Paid']] as [$k, $x, $cls, $lbl]) {
            $v = $r[$k]; $h = max(0, $T + $ph - $y($v)); $yy = $T + $ph - $h; $rad = min(4, $h / 2, $bw / 2);
            $path = $h <= 0 ? '' : 'M' . round($x, 1) . ',' . round($T + $ph, 1) . ' V' . round($yy + $rad, 1) . ' Q' . round($x, 1) . ',' . round($yy, 1) . ' ' . round($x + $rad, 1) . ',' . round($yy, 1)
                . ' H' . round($x + $bw - $rad, 1) . ' Q' . round($x + $bw, 1) . ',' . round($yy, 1) . ' ' . round($x + $bw, 1) . ',' . round($yy + $rad, 1) . ' V' . round($T + $ph, 1) . ' Z';
            $o .= '<g class="bar ' . $cls . '"><rect x="' . round($x - 3, 1) . '" y="' . $T . '" width="' . round($bw + 6, 1) . '" height="' . $ph . '" class="hit"/>' . ($path ? '<path d="' . $path . '"/>' : '') . '<title>' . e($r['label'] . ' · ' . $lbl . ': $' . number_format($v / 100, 2)) . '</title></g>';
        }
        $o .= '<text x="' . round($cx, 1) . '" y="' . ($H - 10) . '" class="ax" text-anchor="middle">' . e($r['label']) . '</text>';
    }
    return $o . '</svg>';
};
$quarters = []; foreach (array_chunk($d['months'], 3) as $q) $quarters[] = ['label' => $q[0]['label'] . '–' . end($q)['label'], 'inv' => array_sum(array_column($q, 'inv')), 'paid' => array_sum(array_column($q, 'paid'))];
$yInv = array_sum(array_column($d['months'], 'inv')); $yPaid = array_sum(array_column($d['months'], 'paid'));
$today = count($d['week']) - 1;
?>
<div class="db">
<div class="db-head">
  <div><h1>Financial dashboard</h1><p class="db-bc">Orders, revenue and pipeline · <?= e(gmdate('l, j F')) ?></p></div>
  <div class="db-acts"><a class="btn btn-ghost btn-sm" href="/admin/orders">Orders</a><a class="btn btn-ghost btn-sm" href="/admin/customers">Customers</a><a class="btn btn-primary btn-sm" href="/admin/pricing">Pricing strategy</a></div>
</div>



<div class="db-row">
  <h2 class="db-sec">Order progress</h2>
  <div class="seg" role="group" aria-label="Period"><?php foreach ([7, 30, 90] as $rg): ?><a href="?range=<?= $rg ?>"<?= $d['range'] === $rg ? ' aria-current="true"' : '' ?>><?= $rg ?>d</a><?php endforeach; ?></div>
</div>
<section class="db-prog">
  <?php foreach ([['Paid orders', $d['paid'], $d['orders'], 'paid', 'pg-1', '/admin/orders'], ['Awaiting payment', $d['await'], $d['orders'], 'of orders', 'pg-2', '/admin/orders'], ['Leads converted', $d['conv'], $d['leads'], 'converted', 'pg-3', '/admin/leads']] as [$lbl, $a, $b, $word, $cls, $href]): $p = $pct($a, $b); ?>
  <a class="db-card pg <?= $cls ?>" href="<?= $href ?>"><span class="db-k"><?= e($lbl) ?></span><span class="db-v"><b><?= $a ?></b> / <?= $b ?></span>
    <span class="pbar" role="progressbar" aria-valuenow="<?= $p ?>" aria-valuemin="0" aria-valuemax="100" aria-label="<?= e($lbl) ?>"><i style="width:<?= $p ?>%"></i></span><span class="db-s"><?= $p ?>% <?= e($word) ?> · last <?= $d['range'] ?> days</span></a>
  <?php endforeach; ?>
</section>

<div class="db-grid">
  <section class="db-card db-year">
    <div class="db-ch"><div><h2>Yearly summary</h2><p class="db-tot"><span>Invoiced <b><?= money($yInv) ?></b></span><span>Paid <b><?= money($yPaid) ?></b></span><span>AI costs <b>US$<?= number_format($d['expenses'] / 100, 2) ?></b></span></p></div>
      <div class="seg" role="tablist" aria-label="Chart period"><button type="button" role="tab" aria-selected="true" data-show="yb-m">Monthly</button><button type="button" role="tab" aria-selected="false" data-show="yb-q">Quarterly</button></div></div>
    <div class="db-legend"><span><i class="lg-sw sw-inv"></i>Invoiced</span><span><i class="lg-sw sw-paid"></i>Paid</span></div>
    <div class="db-chart"><div data-chart="yb-m"><?= $chart($d['months'], 'yb-m') ?></div><div data-chart="yb-q" hidden><?= $chart($quarters, 'yb-q') ?></div></div>
    <details class="db-table"><summary>Show as table</summary><table><thead><tr><th>Month</th><th>Invoiced</th><th>Paid</th></tr></thead><tbody><?php foreach ($d['months'] as $r): ?><tr><td><?= e($r['label']) ?></td><td><?= money($r['inv']) ?></td><td><?= money($r['paid']) ?></td></tr><?php endforeach; ?></tbody></table></details>
  </section>

  <section class="db-earn" aria-labelledby="earn-h">
    <h2 id="earn-h">Earnings</h2>
    <div class="days" role="tablist" aria-label="Day"><?php foreach ($d['week'] as $i => $w): ?><button type="button" role="tab" aria-selected="<?= $i === $today ? 'true' : 'false' ?>" data-day="<?= $i ?>"><?= e($w['label']) ?></button><?php endforeach; ?></div>
    <?php foreach ($d['week'] as $i => $w): ?><div class="earn-v" data-day-panel="<?= $i ?>"<?= $i === $today ? '' : ' hidden' ?>><b><?= money($w['cents']) ?></b><span><?= e($w['label']) ?> earnings · <?= (int)$w['orders'] ?> paid order<?= $w['orders'] === 1 ? '' : 's' ?></span></div><?php endforeach; ?>
    <div class="earn-foot"><span>Last 7 days</span><b><?= money(array_sum(array_column($d['week'], 'cents'))) ?></b></div>
    <a class="earn-link" href="/admin/finance">Open finance</a>
  </section>
</div>

<div class="db-grid2">
  <section class="db-pipe">
    <h2>Sales pipeline</h2>
    <b class="pv"><?= e($m['pipeline_value']) ?></b><span>open opportunity value</span>
    <dl><div><dt>New leads, 7 days</dt><dd><?= (int)$newLeads ?></dd></div><div><dt>Lead conversion, 30 days</dt><dd><?= e($m['conversion_rate']) ?></dd></div><div><dt>Follow-ups overdue</dt><dd><?= (int)$m['leads_overdue'] ?></dd></div><div><dt>Unfinished forms</dt><dd><?= (int)$draftsOpen ?></dd></div></dl>
    <a href="/admin/leads">Open leads</a>
  </section>
  <section class="db-card db-orders">
    <div class="db-ch"><h2>Recent orders</h2><a href="/admin/orders">All orders</a></div>
    <?php if (!$d['recent']): ?><div class="empty">No orders yet.</div><?php else: ?>
    <table><thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Status</th><th style="text-align:right">Total</th></tr></thead><tbody>
    <?php foreach ($d['recent'] as $o): ?>
      <tr><td><a href="/admin/orders/<?= e($o['number']) ?>"><?= e($o['number']) ?></a></td><td><?= e($o['name'] ?: $o['email']) ?><small><?= e((string)$o['business_name']) ?></small></td><td class="small"><?= e(substr($o['created_at'], 0, 10)) ?></td>
        <td><?php $s = $o['status']; include APP_ROOT . '/views/partials/status.php'; ?></td><td style="text-align:right"><?= money((int)$o['total_cents']) ?></td></tr>
    <?php endforeach; ?></tbody></table><?php endif; ?>
  </section>
</div>

</div>
