<div class="page-bar">
  <p class="lede">A view is a saved filter. Servers that match are listed here, including ones added later.</p>
  <div class="page-actions">
    <a class="btn" href="<?= e(url('/views/new')) ?>">New view</a>
  </div>
</div>

<?php if (!$views): ?>
  <section class="card empty-board">
    <h2>No views yet</h2>
    <p class="muted">Create a view for a set you check often, such as every APP VM in one environment and operating system.</p>
  </section>
<?php else: ?>
  <section class="card">
    <ul class="option-list view-list">
      <?php foreach ($views as $view): ?>
        <li>
          <a href="<?= e(url('/views/' . (int)$view['id'])) ?>"><?= e($view['name']) ?></a>
          <span class="muted"><?= e($view['summary']) ?></span>
          <span class="meta"><?= (int)$view['vm_count'] ?> VM<?= (int)$view['vm_count'] === 1 ? '' : 's' ?></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
