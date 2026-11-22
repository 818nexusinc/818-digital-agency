<div class="topbar"><h1>Account &amp; privacy</h1></div>
<div class="grid g2" style="align-items:start">
<form class="panel" method="post" action="/portal/account"><?= App\Csrf::field() ?><input type="hidden" name="action" value="profile"><h2>Profile</h2>
  <div class="field"><label for="n">Name</label><input id="n" name="name" type="text" value="<?= e($c['name']) ?>"></div><div class="field"><label>Email</label><input type="email" value="<?= e($u['email']) ?>" disabled></div>
  <div class="grid g2"><div class="field"><label for="ph">Phone</label><input id="ph" name="phone" type="tel" value="<?= e($c['phone']) ?>"></div><div class="field"><label for="bn">Business</label><input id="bn" name="business_name" type="text" value="<?= e($c['business_name']) ?>"></div></div>
  <div class="field"><label for="loc">Location</label><input id="loc" name="location" type="text" value="<?= e($c['location']) ?>"></div>
  <label class="check"><input type="checkbox" name="consent_marketing" value="1"<?= $c['consent_marketing'] ? ' checked' : '' ?>> Marketing emails (tips, offers)</label>
  <label class="check"><input type="checkbox" name="memory_opt_in" value="1"<?= $c['memory_opt_in'] ? ' checked' : '' ?>> Let the assistant remember helpful preferences</label>
  <button class="btn btn-primary" style="margin-top:12px">Save</button></form>
<div><div class="panel"><h2>What the assistant remembers</h2><?php if (!$memories): ?><div class="empty">Nothing saved.</div><?php endif; ?>
  <?php foreach ($memories as $m): ?><form class="row spread" method="post" action="/portal/account" style="padding:6px 0;border-bottom:1px solid var(--line-2)"><?= App\Csrf::field() ?><input type="hidden" name="action" value="forget"><input type="hidden" name="memory_id" value="<?= (int)$m['id'] ?>"><span class="small"><b><?= e($m['mkey']) ?>:</b> <?= e($m['mvalue']) ?></span><button class="btn btn-ghost btn-sm">Delete</button></form><?php endforeach; ?>
  <?php if ($memories): ?><form method="post" action="/portal/account" style="margin-top:10px" data-confirm="Delete everything the assistant remembers about you?"><?= App\Csrf::field() ?><input type="hidden" name="action" value="forget"><button class="btn btn-ghost btn-sm">Delete all</button></form><?php endif; ?>
  <p style="margin-top:14px"><a class="btn btn-ghost btn-sm" href="/portal/account/export">Download my data (JSON)</a></p></div>
<form class="panel" method="post" action="/portal/account"><?= App\Csrf::field() ?><input type="hidden" name="action" value="password"><h2>Change password</h2><div class="field"><label for="cur">Current password</label><input id="cur" name="current" type="password" required autocomplete="current-password"></div><div class="field"><label for="new">New password</label><input id="new" name="new" type="password" required minlength="10" autocomplete="new-password"></div><button class="btn btn-ghost">Update password</button></form></div>
</div>
