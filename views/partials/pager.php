<?php if ($pages > 1): ?>
  <nav class="pager" aria-label="Pages">
    <?php if ($page > 1): ?>
      <a href="<?= e(url($path) . filter_query($filters, ['page' => $page - 1])) ?>">Previous</a>
    <?php endif; ?>
    <span>Page <?= (int)$page ?> of <?= (int)$pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="<?= e(url($path) . filter_query($filters, ['page' => $page + 1])) ?>">Next</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>
