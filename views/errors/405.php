<div class="empty">
  <h2>Method not allowed</h2>
  <p>This address accepts GET and POST only.</p>
  <?php if (!empty($standalone)): ?>
    <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('/')) ?>">Back to dashboard</a>
  <?php endif; ?>
</div>
