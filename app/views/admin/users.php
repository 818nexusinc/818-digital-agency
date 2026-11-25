<?php
use App\{Csrf, Users};
$ago = fn(?string $d) => $d ? substr($d, 0, 10) : 'Never';
?>
<div class="db um">
<div class="db-head"><div><h1>Users</h1><p class="db-bc">Who can sign in, what they can do, and a way back in when someone is stuck</p></div>
  <a class="btn btn-primary" href="#invite">Invite someone</a></div>

<nav class="int-tabs" aria-label="Users">
  <a href="/admin/users?group=team"<?= $group === 'team' ? ' aria-current="page"' : '' ?>>Team <span><?= $counts['team'] ?></span></a>
  <a href="/admin/users?group=customers"<?= $group === 'customers' ? ' aria-current="page"' : '' ?>>Customers <span><?= $counts['customers'] ?></span></a>
  <a href="/admin/users?group=all"<?= $group === 'all' ? ' aria-current="page"' : '' ?>>Everyone <span><?= $counts['team'] + $counts['customers'] ?></span></a>
</nav>

<form class="um-filter" method="get" action="/admin/users">
  <input type="hidden" name="group" value="<?= e($group) ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search by name or email" aria-label="Search by name or email">
  <select name="status" aria-label="Status"><option value="">Any status</option><option value="active"<?= $status === 'active' ? ' selected' : '' ?>>Active</option><option value="suspended"<?= $status === 'suspended' ? ' selected' : '' ?>>Suspended (<?= $counts['suspended'] ?>)</option><option value="locked"<?= $status === 'locked' ? ' selected' : '' ?>>Locked out (<?= $counts['locked'] ?>)</option></select>
  <button class="btn">Filter</button><?php if ($q !== '' || $status !== ''): ?><a class="db-more" href="/admin/users?group=<?= e($group) ?>">Clear</a><?php endif; ?>
</form>

<section class="db-card um-list">
<?php if (!$rows): ?><p class="muted" style="padding:20px 24px">Nobody matches. <?= $q !== '' ? 'Try another search, or invite them below.' : '' ?></p>
<?php else: ?>
<table class="tbl"><thead><tr><th>Name</th><th>Role</th><th>Access</th><th>Sign-in</th><th>Last sign-in</th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): $mine = (int)$u['id'] === (int)$me['id']; ?>
<tr>
  <td><a href="/admin/users/<?= (int)$u['id'] ?>"><b><?= e($u['name']) ?></b></a><?= $mine ? ' <span class="small muted">(you)</span>' : '' ?><div class="small muted"><?= e($u['email']) ?></div></td>
  <td><?= e(Users::ROLE_NAMES[$u['role']] ?? $u['role']) ?></td>
  <td><?php if ($u['status'] !== 'active'): ?><span class="badge b-red">Suspended</span><?php elseif ($u['locked']): ?><span class="badge b-amber">Locked out</span><?php elseif ($u['invited']): ?><span class="badge">Invited</span><?php else: ?><span class="badge b-green">Active</span><?php endif; ?></td>
  <td class="small"><?= $u['passkeys'] ? (int)$u['passkeys'] . ' passkey' . ($u['passkeys'] > 1 ? 's' : '') : 'Password' ?><?= $u['mfa_enabled'] ? ' · two-step' : '' ?></td>
  <td class="small"><?= e($ago($u['last_login_at'])) ?></td>
  <td><a class="btn btn-sm btn-ghost" href="/admin/users/<?= (int)$u['id'] ?>">Manage</a></td>
</tr>
<?php endforeach; ?></tbody></table>
<?php endif; ?>
</section>

<section class="db-card um-invite" id="invite">
  <h2>Invite someone</h2>
  <p class="small muted">We create the account and email them a link to choose their own password (it works for 72 hours). You never see or send a password.</p>
  <form method="post" action="/admin/users" class="um-inv-form"><?= Csrf::field() ?>
    <label>Name<input type="text" name="name" maxlength="190" required autocomplete="off"></label>
    <label>Email<input type="email" name="email" maxlength="190" required autocomplete="off"></label>
    <label>Role<select name="role"><?php foreach ($roles as $r): ?><option value="<?= e($r) ?>"<?= $r === 'staff' ? ' selected' : '' ?>><?= e(Users::ROLE_NAMES[$r]) ?></option><?php endforeach; ?></select></label>
    <button class="btn btn-primary">Send invite</button>
  </form>
  <p class="small muted">Admins can manage settings, integrations and staff. Staff work on customers, orders and projects. What each role can do is set under Settings &amp; team → Role permissions.</p>
</section>
</div>
