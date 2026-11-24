<?php
use App\{Pricing, Csrf};
$cad2 = fn(?int $c) => $c === null ? '—' : '$' . number_format($c / 100, ($c % 100) ? 2 : 0);
$dollars = fn(?int $c) => $c === null ? '' : number_format($c / 100, 2, '.', '');
$priced = array_values(array_filter($rows, fn($r) => $r['price'] !== null));
$slug = $svc['slug'];
?>
<div class="db pz">
<div class="db-head">
  <div><h1><?= e($svc['name']) ?></h1><p class="db-bc"><a href="/admin/pricing">Pricing strategy</a> · <?= e(App\Catalog::CATEGORIES[$svc['category']] ?? $svc['category']) ?></p></div>
  <div class="db-acts">
    <a class="btn btn-ghost btn-sm" href="/services/<?= e($slug) ?>" target="_blank" rel="noopener">View on website</a>
    <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="research"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="btn btn-primary btn-sm"<?= $aiOn ? '' : ' title="Needs the Anthropic connection"' ?>>Research this service</button></form>
  </div>
</div>

<?php if (!$priced): ?><div class="ct-warn"><p><b>This service is quoted individually,</b> so there is no list price to compare. You can still record what competitors charge below.</p></div><?php endif; ?>

<?php foreach ($rows as $r): $c = $r['cost']; $v = $r['variant']; ?>
<section class="db-card pz-pt" aria-label="<?= e($r['vlabel'] ?: $svc['name']) ?>">
  <div class="pz-pth">
    <h2><?= e($r['vlabel'] ?: 'Price') ?></h2>
    <div class="pz-break">
      <span><small>Our price</small><b><?= $r['price'] === null ? 'Quoted' : e($cad2($r['price'])) ?><?= $r['price'] !== null && $r['billing'] !== 'one-time' ? '<em>/' . e($r['billing']) . '</em>' : '' ?></b></span>
      <span><small>Government fee</small><b><?= e($cad2($r['gov'])) ?></b></span>
      <span><small>Other costs</small><b><?= e($cad2($r['third'])) ?></b></span>
      <span><small>818 markup</small><b class="<?= $r['markup'] !== null && $r['markup'] < 0 ? 'bad' : 'ok' ?>"><?= $r['markup'] === null ? '—' : e($cad2($r['markup'])) . ' <em>' . (int)$r['markup_pct'] . '%</em>' ?></b></span>
      <span><small>Competitors</small><b><?= $r['comp'] ? e($cad2($r['low'])) . ($r['low'] !== $r['high'] ? '–' . e($cad2($r['high'])) : '') : '—' ?></b></span>
    </div>
  </div>

  <div class="pz-cols">
    <form method="post" action="/admin/pricing/action" class="pz-cost">
      <h3>Our costs</h3>
      <?php if ($c && $c['status'] !== 'confirmed' && $c['updated_by'] === 'pricing'): ?><p class="pz-note">Found by the Pricing Agent. Check the source page, then confirm.</p><?php endif; ?>
      <?= Csrf::field() ?><input type="hidden" name="action" value="cost"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="variant" value="<?= e($v) ?>">
      <div class="pz-2"><label>Government fee (CAD)<input type="text" name="gov_fee" inputmode="decimal" value="<?= e($dollars($r['gov'])) ?>" placeholder="0.00"></label>
        <label>Other costs (CAD)<input type="text" name="third_party" inputmode="decimal" value="<?= e($dollars($r['third'])) ?>" placeholder="e.g. wholesale cost"></label></div>
      <label>Official source<input name="source_url" type="url" value="<?= e((string)($c['source_url'] ?? '')) ?>" placeholder="https://"></label>
      <?php if (!empty($c['source_url'])): ?><a class="pz-src" href="<?= e($c['source_url']) ?>" target="_blank" rel="noopener noreferrer">Open source page</a><?php endif; ?>
      <label>Notes<input type="text" name="notes" value="<?= e((string)($c['notes'] ?? '')) ?>" placeholder="What the fee covers"></label>
      <label class="se-check"><input type="checkbox" name="confirmed" value="1"<?= ($c['status'] ?? '') === 'confirmed' ? ' checked' : '' ?>> I checked this against the official source</label>
      <button class="btn btn-sm">Save costs</button>
    </form>

    <div class="pz-comp">
      <h3>Competitors <?= $r['comp'] ? '<span>' . count($r['comp']) . '</span>' : '' ?></h3>
      <?php if (!$r['comp']): ?><p class="pz-note">None recorded yet.</p><?php endif; ?>
      <ul><?php foreach ($r['comp'] as $cp): ?>
        <li><div><b><?= e($cp['competitor']) ?></b> <span class="pz-cp"><?= e($cad2((int)$cp['price_cents'])) ?><?= $cp['billing'] ? '/' . e($cp['billing']) : '' ?></span>
          <small><?= e(implode(' · ', array_filter([$cp['region'], $cp['includes_gov_fee'] === null ? null : ((int)$cp['includes_gov_fee'] ? 'government fee included' : 'plus government fee'), $cp['found_by'] === 'pricing' ? 'found by the Pricing Agent' : 'added by you', substr((string)$cp['observed_at'], 0, 10)]))) ?></small>
          <?php if ($cp['notes']): ?><small><?= e($cp['notes']) ?></small><?php endif; ?></div>
          <div class="pz-ca"><?php if ($cp['url']): ?><a href="<?= e($cp['url']) ?>" target="_blank" rel="noopener noreferrer">Source</a><?php endif; ?>
          <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="dismiss"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="id" value="<?= (int)$cp['id'] ?>"><button class="sf-reset">Remove</button></form></div></li>
      <?php endforeach; ?></ul>
      <details class="pz-add"><summary>Add a competitor price</summary>
        <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="competitor"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="variant" value="<?= e($v) ?>">
          <div class="pz-2"><label>Business<input type="text" name="competitor" required maxlength="160"></label><label>Price (CAD)<input type="text" name="price" required inputmode="decimal" placeholder="0.00"></label></div>
          <div class="pz-2"><label>Where<input type="text" name="region" maxlength="60" placeholder="e.g. Halifax, NS"></label>
            <label>Government fee<select name="includes_gov_fee"><option value="">Not sure</option><option value="1">Included in their price</option><option value="0">Charged on top</option></select></label></div>
          <label>Page address<input name="url" type="url" placeholder="https://"></label>
          <label>Notes<input type="text" name="notes" maxlength="500" placeholder="What their price includes"></label>
          <button class="btn btn-sm">Add</button></form>
      </details>
    </div>

    <div class="pz-propose">
      <h3>Price proposal</h3>
      <?php foreach ($r['proposals'] as $p): ?>
        <div class="pz-open"><b><?= e($cad2($p['current_cents'] === null ? null : (int)$p['current_cents'])) ?> → <?= e($cad2((int)$p['proposed_cents'])) ?></b> <small><?= e(Pricing::STRATEGIES[$p['strategy']] ?? '') ?> · <?= e($p['created_by'] === 'pricing' ? 'Pricing Agent' : $p['created_by']) ?></small>
          <p><?= nl2br(e((string)$p['rationale'])) ?></p>
          <div class="pz-pa"><form method="post" action="/admin/pricing/action" data-confirm="Change the live price to <?= e($cad2((int)$p['proposed_cents'])) ?>?"><?= Csrf::field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-primary btn-sm">Approve</button></form>
          <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="btn btn-sm">Reject</button></form></div></div>
      <?php endforeach; ?>
      <?php if ($r['price'] !== null): ?>
      <details class="pz-add"<?= $r['proposals'] ? '' : ' open' ?>><summary><?= $r['proposals'] ? 'Make a different proposal' : 'Propose a new price' ?></summary>
        <form method="post" action="/admin/pricing/action"><?= Csrf::field() ?><input type="hidden" name="action" value="propose"><input type="hidden" name="slug" value="<?= e($slug) ?>"><input type="hidden" name="variant" value="<?= e($v) ?>">
          <div class="pz-2"><label>New price (CAD)<input type="text" name="price" required inputmode="decimal" placeholder="<?= e($dollars($r['price'])) ?>"></label>
            <label>Strategy<select name="strategy"><?php foreach (Pricing::STRATEGIES as $k => $n): ?><option value="<?= $k ?>"><?= e($n) ?></option><?php endforeach; ?></select></label></div>
          <label>Reason<input type="text" name="rationale" maxlength="2000" placeholder="Why this price"></label>
          <button class="btn btn-sm">Add proposal</button></form>
      </details>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php if ($history): ?>
<section class="db-card pz-hist"><div class="db-ch"><div><h2>Past decisions</h2></div></div>
  <table><thead><tr><th>Option</th><th class="num">From</th><th class="num">To</th><th>Decision</th><th>By</th><th>When</th></tr></thead><tbody>
  <?php foreach ($history as $h): ?><tr><td><?= e($h['variant'] !== '' ? (Pricing::REGIONS[$h['variant']] ?? '.' . $h['variant']) : '—') ?></td><td class="num"><?= e($cad2($h['current_cents'] === null ? null : (int)$h['current_cents'])) ?></td><td class="num"><?= e($cad2((int)$h['proposed_cents'])) ?></td><td><?= e(label($h['status'])) ?></td><td><?= e((string)$h['decided_by']) ?></td><td><?= e(substr((string)$h['decided_at'], 0, 10)) ?></td></tr><?php endforeach; ?>
  </tbody></table></section>
<?php endif; ?>
</div>
