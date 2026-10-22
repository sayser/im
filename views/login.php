<main class="auth-card">
  <span class="mark">PT</span>
  <h1>PT Inventory Manager</h1>
  <p class="muted">Sign in to view the client VM inventory.</p>
  <?php if (!empty($errors['form'])): ?>
    <div class="flash error"><?= e($errors['form']) ?></div>
  <?php endif; ?>
  <form method="post" action="<?= e(url('/login')) ?>" class="stack-form">
    <?= csrf_field() ?>
    <label class="field">
      <span>Username</span>
      <input name="username" autocomplete="username" required maxlength="64" value="<?= e($username) ?>">
    </label>
    <label class="field">
      <span>Password</span>
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button class="btn" type="submit">Sign in</button>
  </form>
</main>
