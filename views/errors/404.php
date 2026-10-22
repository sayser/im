<div class="empty">
  <h2>Page not found</h2>
  <p>That address is not part of PT Inventory Manager.</p>
  <?php if (!empty($standalone)): ?>
    <a class="btn" href="<?= e(url('/login')) ?>">Sign in</a>
  <?php else: ?>
    <a class="btn" href="<?= e(url('/')) ?>">Back to dashboard</a>
  <?php endif; ?>
</div>
