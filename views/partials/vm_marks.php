<?php
$attached = [];
foreach ($vmMarks as $mark) {
    $attached[(int)$mark['id']] = true;
}
$available = [];
foreach ($openMarks as $mark) {
    if (!isset($attached[(int)$mark['id']])) {
        $available[] = $mark;
    }
}
$postPath = ($markSide === 'db' ? '/db-vms/' : '/app-vms/') . (int)$vm['id'] . '/marks';
?>
<section class="card">
  <h2>Marks</h2>
  <?php if (!$vmMarks): ?>
    <p class="muted">This server is not in a mark.</p>
  <?php else: ?>
    <ul class="option-list">
      <?php foreach ($vmMarks as $mark): ?>
        <li>
          <a class="mark-chip <?= e(mark_swatch_class((string)$mark['swatch'])) ?>" href="<?= e(url('/marks/' . (int)$mark['id'])) ?>"><?= e($mark['name']) ?></a>
          <?php if ($mark['status'] !== 'open'): ?><span class="badge decom">Closed</span><?php endif; ?>
          <?php if (can_write() && $mark['status'] === 'open'): ?>
            <form method="post" action="<?= e(url($postPath)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="mark_id" value="<?= (int)$mark['id'] ?>">
              <button class="btn small ghost" type="submit">Remove</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if (can_write()): ?>
    <?php if ($available): ?>
      <form class="inline-add" method="post" action="<?= e(url($postPath)) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label class="sr" for="vm-mark">Add to a mark</label>
        <select id="vm-mark" name="mark_id" required>
          <option value="">Add to a mark</option>
          <?php foreach ($available as $mark): ?>
            <option value="<?= (int)$mark['id'] ?>"><?= e($mark['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn small" type="submit">Add</button>
      </form>
    <?php else: ?>
      <p class="muted">Create a mark before adding this server to one.</p>
    <?php endif; ?>
  <?php endif; ?>
</section>
