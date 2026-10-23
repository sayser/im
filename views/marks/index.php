<div class="page-bar">
  <p class="lede">A mark groups servers that share a job, such as a PHP upgrade. The server stays on its environment tab and in its stack.</p>
  <?php if (can_write()): ?>
    <div class="page-actions">
      <a class="btn" href="<?= e(url('/marks/new')) ?>">New mark</a>
    </div>
  <?php endif; ?>
</div>

<?php if (!$openMarks): ?>
  <section class="card empty-board">
    <h2>No open marks</h2>
    <p class="muted">Create a mark, then add the servers that need the same work.</p>
  </section>
<?php endif; ?>

<?php foreach ($openMarks as $mark): ?>
  <?php $class = mark_swatch_class((string)$mark['swatch']); ?>
  <section class="mark-frame <?= e($class) ?>">
    <header class="mark-head">
      <div>
        <h2><a href="<?= e(url('/marks/' . (int)$mark['id'])) ?>"><?= e($mark['name']) ?></a></h2>
        <?php if ((string)$mark['note'] !== ''): ?><p class="muted"><?= e($mark['note']) ?></p><?php endif; ?>
      </div>
      <div class="mark-head-side">
        <span class="mark-chip <?= e($class) ?>"><?= (int)$mark['app_count'] ?> APP · <?= (int)$mark['db_count'] ?> DB</span>
        <a class="btn small ghost" href="<?= e(url('/marks/' . (int)$mark['id'])) ?>"><?= can_write() ? 'Add or remove' : 'Open' ?></a>
      </div>
    </header>
    <?php
      $membersApps = $mark['apps'];
      $membersDbs = $mark['dbs'];
      $markId = (int)$mark['id'];
      $canRemove = false;
      require APP_ROOT . '/views/partials/mark_members.php';
    ?>
  </section>
<?php endforeach; ?>

<?php if ($closedMarks): ?>
  <section class="card">
    <h2>Closed</h2>
    <ul class="option-list">
      <?php foreach ($closedMarks as $mark): ?>
        <li>
          <a href="<?= e(url('/marks/' . (int)$mark['id'])) ?>"><?= e($mark['name']) ?></a>
          <span class="mark-chip <?= e(mark_swatch_class((string)$mark['swatch'])) ?>"><?= (int)$mark['app_count'] ?> APP · <?= (int)$mark['db_count'] ?> DB</span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
<?php endif; ?>
