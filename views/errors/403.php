<div class="empty">
  <h2>Not allowed</h2>
  <p>Your role cannot open this page.</p>
  <?php if (!empty($standalone)): ?>
    <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('/')) ?>">Back to dashboard</a>
  <?php endif; ?>
</div>
