<?php $editing = $mode === 'edit'; ?>
<form class="card form narrow" method="post" action="<?= e(url($editing ? '/users/' . (int)$userRow['id'] : '/users')) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <?php if ($formError !== ''): ?>
    <div class="flash error"><?= e($formError) ?></div>
  <?php endif; ?>
  <div class="form-grid">
    <label class="field">
      <span>Username</span>
      <?php if ($editing): ?>
        <input value="<?= e($input['username']) ?>" disabled>
      <?php else: ?>
        <input name="username" required maxlength="64" autocomplete="off" value="<?= e($input['username']) ?>">
      <?php endif; ?>
    </label>
    <label class="field">
      <span>Display name</span>
      <input name="display_name" required maxlength="120" value="<?= e($input['display_name']) ?>">
    </label>
    <label class="field">
      <span>Role</span>
      <select name="role" required>
        <?php foreach (['admin' => 'Admin', 'ops' => 'OPs', 'view' => 'View-only'] as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $input['role'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span><?= $editing ? 'New password' : 'Temporary password' ?></span>
      <input name="password" type="password" autocomplete="new-password" <?= $editing ? '' : 'required' ?>>
      <small class="hint"><?= $editing ? 'Leave blank to keep the current password. At least 12 characters, with a letter and a number.' : 'The user will be asked to change this at first sign-in.' ?></small>
    </label>
    <?php if ($editing): ?>
      <label class="check span-2">
        <input type="checkbox" name="active" value="1"<?= (int)$input['active'] === 1 ? ' checked' : '' ?>>
        <span>Account enabled</span>
      </label>
    <?php endif; ?>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= $editing ? 'Save user' : 'Create user' ?></button>
    <a class="btn ghost" href="<?= e(url('/users')) ?>">Cancel</a>
  </div>
</form>
