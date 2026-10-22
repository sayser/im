<main class="auth-card">
  <span class="mark">PT</span>
  <h1>Create the admin account</h1>
  <p class="muted">This account is the first admin for PT Inventory Manager. It can add users and the dropdown values used on inventory records.</p>
  <?php if (!empty($errors['form'])): ?>
    <div class="flash error"><?= e($errors['form']) ?></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/setup')) ?>" class="stack-form">
    <?= csrf_field() ?>
    <label class="field">
      <span>Username</span>
      <input name="username" autocomplete="username" required maxlength="64" value="<?= e($input['username']) ?>"<?= invalid_attr($errors, 'username') ?>>
      <?= field_error($errors, 'username') ?>
    </label>
    <label class="field">
      <span>Display name</span>
      <input name="display_name" autocomplete="name" required maxlength="120" value="<?= e($input['display_name']) ?>"<?= invalid_attr($errors, 'display_name') ?>>
      <?= field_error($errors, 'display_name') ?>
    </label>
    <label class="field">
      <span>Password</span>
      <input name="password" type="password" autocomplete="new-password" required>
      <small class="hint">At least 12 characters, with a letter and a number.</small>
      <?= field_error($errors, 'password') ?>
    </label>
    <label class="field">
      <span>Confirm password</span>
      <input name="confirm_password" type="password" autocomplete="new-password" required>
      <?= field_error($errors, 'confirm_password') ?>
    </label>
    <button class="btn" type="submit">Create admin and continue</button>
  </form>
</main>
