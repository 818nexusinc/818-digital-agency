<?php
/* Work for one service group (or one service): every order, project, filing, lead and unfinished form, newest open work first. */
$rows = array_values(array_filter($all, fn($r) => (!$open || $r[9]) && ($type === '' || $r[0] === $type)));
$counts = []; foreach ($all as $r) if (!$open || $r[9]) $counts[$r[0]] = ($counts[$r[0]] ?? 0) + 1;
$base = '/admin/work/' . $cat . ($slug ? '/' . $slug : '');
$q = fn(array $o) => $base . (($s = http_build_query(array_filter($o + ['type' => $type, 'show' => $open ? '' : 'all']))) ? '?' . $s : '');
$svcName = $slug ? (array_column($svcs, 'name', 'slug')[$slug] ?? $slug) : null;
$cad = fn(?int $c) => $c === null ? '' : '$' . number_format($c / 100, ($c % 100) ? 2 : 0);
$ago = function (string $at): string { $s = max(0, time() - strtotime($at . ' UTC')); return $s < 3600 ? max(1, (int)round($s / 60)) . ' min' : ($s < 86400 ? (int)round($s / 3600) . ' h' : ($s < 86400 * 60 ? (int)round($s / 86400) . ' d' : substr($at, 0, 10))); };
?>
<div class="db wk">
<div class="db-head">
  <div><h1><?= e($svcName ?? $title) ?></h1><p class="db-bc"><?= $slug ? '<a href="/admin/work/' . e($cat) . '">' . e($title) . '</a> · ' : '' ?>Every request for <?= $slug ? 'this service' : 'these services' ?>: orders, projects, filings, leads and unfinished forms</p></div>
  <div class="db-acts"><?php if ($slug): ?><a class="btn btn-ghost btn-sm" href="/admin/pricing/<?= e($slug) ?>">Pricing</a><a class="btn btn-ghost btn-sm" href="/services/<?= e($slug) ?>" target="_blank" rel="noopener">View on website</a><?php endif; ?></div>
</div>

<nav class="wk-svcs" aria-label="Services in <?= e($title) ?>">
  <a href="/admin/work/<?= e($cat) ?>"<?= $slug ? '' : ' aria-current="page"' ?>>All <?= e(mb_strtolower($title)) ?></a>
  <?php foreach ($svcs as $s): ?><a href="/admin/work/<?= e($cat) ?>/<?= e($s['slug']) ?>"<?= $slug === $s['slug'] ? ' aria-current="page"' : '' ?>><?= e($s['name']) ?><?= $s['open'] ? ' <i>' . $s['open'] . '</i>' : '' ?></a><?php endforeach; ?>
</nav>

<section class="db-card wk-list">
  <div class="wk-bar">
    <nav class="wk-types" aria-label="Kind of request">
      <a href="<?= e($q(['type' => ''])) ?>"<?= $type === '' ? ' aria-current="true"' : '' ?>>All <span><?= array_sum($counts) ?></span></a>
      <?php foreach (App\WorkBoard::TYPES as $k => $n): ?><a href="<?= e($q(['type' => $k])) ?>"<?= $type === $k ? ' aria-current="true"' : '' ?>><?= e($n) ?> <span><?= (int)($counts[$k] ?? 0) ?></span></a><?php endforeach; ?>
    </nav>
    <nav class="seg" aria-label="Which requests"><a href="<?= e($q(['show' => ''])) ?>"<?= $open ? ' aria-current="true"' : '' ?>>Open</a><a href="<?= e($q(['show' => 'all'])) ?>"<?= !$open ? ' aria-current="true"' : '' ?>>All, including finished</a></nav>
  </div>
  <?php if (!$rows): ?><div class="ct-empty"><b><?= $open ? 'No open requests.' : 'No requests yet.' ?></b><p>Orders, projects, filings, leads and unfinished forms for <?= $slug ? 'this service' : 'these services' ?> appear here as they come in.</p></div>
  <?php else: ?>
  <div class="tbl-wrap"><table>
    <thead><tr><th>Kind</th><th>Reference</th><th>Request</th><th>Customer</th><?= $slug ? '' : '<th>Service</th>' ?><th>Status</th><th class="num">Value</th><th>When</th></tr></thead>
    <tbody>
    <?php foreach ($rows as [$k, $ref, $what, $who, $svc, $status, $cents, $at, $href, $isOpen]): ?>
      <tr class="<?= $isOpen ? '' : 'done' ?>">
        <td><span class="wk-k k-<?= e($k) ?>"><?= e(rtrim(App\WorkBoard::TYPES[$k], 's') === 'Unfinished form' ? 'Form' : rtrim(App\WorkBoard::TYPES[$k], 's')) ?></span></td>
        <td><a href="<?= e($href) ?>"><?= e($ref) ?></a></td>
        <td class="wk-what"><?= e(str_limit((string)preg_replace('/\s+/u', ' ', $what), 90)) ?></td>
        <td><?= e($who) ?></td>
        <?= $slug ? '' : '<td>' . e($svc) . '</td>' ?>
        <td><span class="wk-st"><?= e(label($status)) ?></span></td>
        <td class="num"><?= e($cad($cents)) ?></td>
        <td><time datetime="<?= e(str_replace(' ', 'T', $at)) ?>Z" title="<?= e($at) ?> UTC"><?= e($ago($at)) ?></time></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
</div>
