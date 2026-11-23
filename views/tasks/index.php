<?php require __DIR__ . '/tabs.php'; ?>
<div class="page-bar">
  <p class="lede">Open work stays under Assigned. Marking your part completed moves it to Completed.</p>
  <div class="page-actions">
    <a class="btn" href="<?= e(url('/tasks/new')) ?>">New task</a>
  </div>
</div>

<?php require APP_ROOT . '/views/partials/task_panels.php'; ?>
