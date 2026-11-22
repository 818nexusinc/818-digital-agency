<?php
use App\{Filings, Catalog};
$badge = function ($s) { include APP_ROOT . '/views/partials/status.php'; };
$t = $f['filing_type']; $j = $f['jurisdiction']; $ent = $d['entity'] ?? [];
$editable = in_array($f['status'], ['awaiting_details', 'needs_changes', 'awaiting_authorization', 'rejected'], true);
$corp = in_array($t, ['incorporation', 'coop_incorporation'], true); $unincorp = in_array($t, ['sole_prop_registration', 'partnership_registration', 'business_name_registration'], true);
$maint = in_array($t, ['annual_return', 'corporate_change', 'document_order'], true);
$provSel = function (string $name, ?string $val) { $o = '<select name="' . e($name) . '"><option value="">Province</option>'; foreach (['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'] as $c) $o .= '<option' . ($val === $c ? ' selected' : '') . '>' . $c . '</option>'; return $o . '</select>'; };
$addrFields = function (string $prefix, array $a) use ($provSel) { return '<div class="grid g4" style="grid-template-columns:2fr 1fr .8fr .8fr"><input type="text" name="' . e($prefix) . '[street]" placeholder="Street address" value="' . e($a['street'] ?? '') . '"><input type="text" name="' . e($prefix) . '[city]" placeholder="City" value="' . e($a['city'] ?? '') . '">' . $provSel($prefix . '[province]', $a['province'] ?? null) . '<input type="text" name="' . e($prefix) . '[postal]" placeholder="Postal code" value="' . e($a['postal'] ?? '') . '"></div>'; };
$person = function (string $group, int $i, array $p, bool $dir) use ($addrFields) {
    $n = "{$group}[{$i}]";
    return '<div class="person"><div class="grid g3"><input type="text" name="' . $n . '[first]" placeholder="First name" value="' . e($p['first'] ?? '') . '"><input type="text" name="' . $n . '[last]" placeholder="Last name" value="' . e($p['last'] ?? '') . '"><input type="email" name="' . $n . '[email]" placeholder="Email (optional)" value="' . e($p['email'] ?? '') . '"></div>'
        . $addrFields($n . '[address]', $p['address'] ?? []) . ($dir ? '<div class="row" style="margin-top:6px"><label class="check small"><input type="checkbox" name="' . $n . '[resident_canadian]" value="1"' . (!empty($p['resident_canadian']) ? ' checked' : '') . '> Resident Canadian</label><label class="check small"><input type="checkbox" name="' . $n . '[incorporator]" value="1"' . (!empty($p['incorporator']) || ($i === 0 && !$p) ? ' checked' : '') . '> Incorporator (signs the articles)</label></div>' : '') . '</div>';
};
?>
<div class="topbar"><div><a class="small" href="/portal/projects/<?= e($p['number']) ?>">← <?= e($p['title']) ?></a><h1><?= e(Filings::TYPE_LABELS[$t]) ?></h1><span class="muted small"><?= e(Filings::JURISDICTIONS[$j][0]) ?> · <?= e($f['number']) ?></span></div><?php $badge($f['status']); ?></div>
<ol class="steps-bar"><?php foreach (['Your details', 'Validation', 'Sign & authorize', 'Submitted', 'Registered'] as $i => $s): $doneIdx = match ($f['status']) { 'awaiting_details', 'needs_changes' => 0, 'awaiting_authorization' => 2, 'authorized', 'in_review', 'awaiting_approval', 'ready_to_file' => 3, 'submitted' => 3, 'accepted', 'completed' => 5, default => 0 }; ?><li class="<?= $i < $doneIdx ? 'done' : ($i === $doneIdx ? 'now' : '') ?>"><?= e($s) ?></li><?php endforeach; ?></ol>
<?php if (!empty($v['errors'])): ?><div class="alert alert-error" id="issues"><b>Please fix the following before signing:</b><ul style="margin:8px 0 0"><?php foreach ($v['errors'] as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!empty($v['warnings'])): ?><div class="alert alert-warn"><?php foreach ($v['warnings'] as $wr): ?><div><?= e($wr) ?></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($f['status'] === 'rejected' && $f['rejected_reason']): ?><div class="alert alert-error">The registry returned the filing: <?= e($f['rejected_reason']) ?></div><?php endif; ?>
<?php if (in_array($f['status'], ['authorized', 'in_review', 'awaiting_approval', 'ready_to_file', 'submitted'], true)): ?><div class="alert alert-info">Authorized by <?= e($f['auth_signer']) ?> on <?= e($f['authorized_at']) ?> UTC. Our Filing Agent is handling the submission — you'll get an email when the registry responds.</div><?php endif; ?>
<?php if (in_array($f['status'], ['accepted', 'completed'], true)): ?><div class="alert alert-success"><b>Registered.</b> <?= $f['registry_number'] ? 'Registry number: <b>' . e($f['registry_number']) . '</b>. ' : '' ?>Your documents are in <a href="/portal/documents">Documents</a>.</div><?php endif; ?>

<?php if ($editable): ?>
<form class="panel" method="post" action="/portal/projects/<?= e($p['number']) ?>/filing"><?= App\Csrf::field() ?>
  <p class="muted small">These details go to the government registry exactly as entered. Use full legal names and street addresses (no PO boxes for registered offices). Saving re-validates the filing; any change after signing requires a new signature.</p>
  <?php if ($corp): ?>
    <h2>Corporate name</h2>
    <div class="grid g3"><div class="field"><label>Name type</label><select name="entity[name_type]"><option value="named"<?= ($ent['name_type'] ?? '') === 'named' ? ' selected' : '' ?>>Named corporation</option><option value="numbered"<?= ($ent['name_type'] ?? '') === 'numbered' ? ' selected' : '' ?>>Numbered corporation</option></select></div>
      <div class="field"><label>Proposed name</label><input type="text" name="entity[proposed_name]" value="<?= e($ent['proposed_name'] ?? '') ?>" placeholder="e.g. Maple Clean"></div>
      <div class="field"><label>Legal ending</label><select name="entity[legal_ending]"><?php foreach (['', 'Inc.', 'Ltd.', 'Corp.', 'Limited', 'Incorporated', 'Corporation'] as $le): ?><option value="<?= e($le) ?>"<?= ($ent['legal_ending'] ?? '') === $le ? ' selected' : '' ?>><?= $le ?: '—' ?></option><?php endforeach; ?></select></div></div>
    <div class="grid g2"><div class="field"><label><?= $j === 'BC' ? 'BC Name Request number (NR)' : 'NUANS / name reservation number' ?></label><input type="text" name="entity[name_reservation]" value="<?= e($ent['name_reservation'] ?? '') ?>"><div class="hint">Not needed for numbered corporations.</div></div>
      <div class="field"><label>Business activities</label><input type="text" name="entity[activity]" value="<?= e($ent['activity'] ?? '') ?>" placeholder="e.g. Residential and commercial cleaning"></div></div>
    <h2>Registered office<?= $j !== 'FED' ? ' (must be in ' . e(Catalog::PROVINCES[$j] ?? $j) . ')' : '' ?></h2><?= $addrFields('registered_office', $d['registered_office'] ?? []) ?>
    <?php if ($j === 'BC'): ?><h2 style="margin-top:16px">Records office</h2><label class="check small"><input type="checkbox" name="records_same" value="1" checked> Same as registered office</label><?= $addrFields('records_office', $d['records_office'] ?? []) ?><?php endif; ?>
    <h2 style="margin-top:18px">Directors</h2><?php for ($i = 0; $i < 4; $i++) echo $person('directors', $i, $d['directors'][$i] ?? [], true); ?>
    <h2 style="margin-top:18px">Share structure</h2><div class="field"><input type="text" name="shares[classes]" value="<?= e($d['shares']['classes'] ?? 'Common shares, unlimited number, no par value') ?>"><div class="hint">Most small businesses start with one class of common shares. Ask the assistant if you need something else.</div></div>
  <?php elseif ($unincorp): ?>
    <h2>Business</h2><div class="grid g3"><div class="field"><label>Business name</label><input type="text" name="entity[proposed_name]" value="<?= e($ent['proposed_name'] ?? '') ?>"></div><div class="field"><label>Activity</label><input type="text" name="entity[activity]" value="<?= e($ent['activity'] ?? '') ?>"></div><div class="field"><label>Start date</label><input type="date" name="entity[start_date]" value="<?= e($ent['start_date'] ?? '') ?>"></div></div>
    <h2>Business address</h2><?= $addrFields('registered_office', $d['registered_office'] ?? []) ?>
    <h2 style="margin-top:18px"><?= $t === 'partnership_registration' ? 'Partners' : 'Owner(s)' ?></h2><?php for ($i = 0; $i < 3; $i++) echo $person('owners', $i, $d['owners'][$i] ?? [], false); ?>
  <?php elseif ($maint): ?>
    <h2>Existing corporation</h2><div class="grid g2"><div class="field"><label>Corporation / registry number</label><input type="text" name="entity[corporation_number]" value="<?= e($ent['corporation_number'] ?? '') ?>"></div>
      <div class="field"><label>Company key<?= $j === 'ON' ? ' (required in Ontario)' : ' (if your registry uses one)' ?></label><input type="password" name="entity[company_key]" value="<?= e($ent['company_key'] ?? '') ?>" autocomplete="off"></div></div>
    <?php if ($t === 'corporate_change'): ?><div class="field"><label>What should be filed?</label><textarea name="entity[changes]"><?= e($ent['changes'] ?? '') ?></textarea></div><div class="field"><label>Effective date</label><input type="date" name="entity[effective_date]" value="<?= e($ent['effective_date'] ?? '') ?>"></div><?php endif; ?>
    <?php if ($t === 'document_order'): ?><div class="field"><label>Documents needed</label><textarea name="entity[changes]"><?= e($ent['changes'] ?? '') ?></textarea></div><?php endif; ?>
    <h2>Registered office (current)</h2><?= $addrFields('registered_office', $d['registered_office'] ?? []) ?>
    <h2 style="margin-top:18px">Signing director</h2><?= $person('directors', 0, $d['directors'][0] ?? [], true) ?>
  <?php else: ?>
    <h2>Name(s) to search</h2><div class="field"><input type="text" name="entity[proposed_name]" value="<?= e($ent['proposed_name'] ?? '') ?>" placeholder="Up to three names, separated by ;"></div><div class="field"><label>Business activity</label><input type="text" name="entity[activity]" value="<?= e($ent['activity'] ?? '') ?>"></div>
    <h2>Contact</h2><?= $person('directors', 0, $d['directors'][0] ?? [], false) ?>
  <?php endif; ?>
  <div class="field" style="margin-top:16px"><label>Official contact email (registry notices)</label><input type="email" name="contact_email" value="<?= e($d['contact_email'] ?? '') ?>" required></div>
  <button class="btn btn-primary">Save &amp; validate</button>
</form>
<?php endif; ?>

<?php if ($f['status'] === 'awaiting_authorization'): $people = Filings::people($d); ?>
<form class="panel sign" id="sign" method="post" action="/portal/projects/<?= e($p['number']) ?>/filing/authorize"><?= App\Csrf::field() ?>
  <h2>Review &amp; sign</h2><pre class="sheet"><?= e(Filings::sheet($f)) ?></pre>
  <div class="grid g2"><div class="field"><label>Your full legal name (must match a person listed above)</label><input type="text" name="signer" required autocomplete="name"></div>
    <div class="field"><label>Capacity</label><select name="capacity"><option>Director and incorporator</option><option>Director</option><option>Owner</option><option>Partner</option><option>Authorized officer</option></select></div></div>
  <label class="check"><input type="checkbox" name="attest" value="1" required> I confirm the information above is true and complete, that I am authorized to make this filing, and I authorize <?= e(App\Settings::get('company.legal')) ?> to submit it to the <?= e(Filings::JURISDICTIONS[$j][0]) ?> on my behalf. I understand this is a legal filing with a government registry.</label>
  <button class="btn btn-primary" style="margin-top:14px">Sign &amp; authorize filing</button>
  <p class="hint">We record your name, the time, your IP address and a fingerprint of these exact details. If anything changes, you'll be asked to sign again.</p></form>
<?php endif; ?>
<?php if ($events): ?><div class="panel"><h2>Filing history</h2><ul class="timeline"><?php foreach ($events as $ev): if ($ev['kind'] === 'agent_review') continue; ?><li><time><?= e(substr($ev['created_at'], 0, 16)) ?> UTC</time><?= e(label($ev['kind'])) ?>: <?= e(str_limit((string)$ev['detail'], 200)) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
