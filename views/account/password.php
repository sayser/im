<?php if ($mustChange): ?>
  <div class="banner">Choose a new password before using the inventory.</div>
<?php else: ?>
  <p class="lede">Change your password and the email address used for SSL reminders.</p>
<?php endif; ?>
<form class="card form narrow" method="post" action="<?= e(url('/account/password')) ?>">
  <h2>Password</h2>
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="field span-2">
      <span>Current password</span>
      <input name="current_password" type="password" autocomplete="current-password" required<?= invalid_attr($errors, 'current_password') ?>>
      <?= field_error($errors, 'current_password') ?>
    </label>
    <label class="field span-2">
      <span>New password</span>
      <input name="new_password" type="password" autocomplete="new-password" required<?= invalid_attr($errors, 'new_password') ?>>
      <small class="hint">At least 12 characters, with a letter and a number.</small>
      <?= field_error($errors, 'new_password') ?>
    </label>
    <label class="field span-2">
      <span>Confirm new password</span>
      <input name="confirm_password" type="password" autocomplete="new-password" required<?= invalid_attr($errors, 'confirm_password') ?>>
      <?= field_error($errors, 'confirm_password') ?>
    </label>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">Update password</button>
  </div>
</form>
<?php if (!$mustChange): ?>
  <form class="card form narrow" method="post" action="<?= e(url('/account/email')) ?>">
    <h2>Email</h2>
    <?= csrf_field() ?>
    <div class="form-grid">
      <label class="field span-2">
        <span>Email</span>
        <input name="email" type="email" maxlength="254" value="<?= e($email ?? '') ?>"<?= invalid_attr(['email' => $emailError ?? ''], 'email') ?>>
        <small class="hint">SSL certificate reminders are sent to this address.</small>
        <?= field_error(['email' => $emailError ?? ''], 'email') ?>
      </label>
    </div>
    <div class="form-actions">
      <button class="btn" type="submit">Save email</button>
    </div>
  </form>
<?php endif; ?>
