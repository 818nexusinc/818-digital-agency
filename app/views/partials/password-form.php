<form method="post" action="/account/password" class="pw-form"><?= App\Csrf::field() ?>
  <?php if (!$forced): ?><label>Current password<input type="password" name="current" required autocomplete="current-password"></label><?php endif; ?>
  <label>New password<input type="password" name="new" required minlength="10" autocomplete="new-password"></label>
  <label>New password again<input type="password" name="confirm" required minlength="10" autocomplete="new-password"></label>
  <p class="small muted">At least 10 characters. A short sentence is easy to remember and hard to guess.</p>
  <button class="btn btn-primary">Change password</button>
</form>
