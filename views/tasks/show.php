<div class="page-bar">
  <div class="task-description"><?= nl2e($task['description']) ?></div>
  <div class="page-actions">
    <?php if ($creator && ($task['archived_at'] === null || $task['archived_at'] === '')): ?>
      <a class="btn ghost" href="<?= e(url('/tasks/' . (int)$task['id'] . '/edit')) ?>">Edit</a>
    <?php endif; ?>
    <a class="btn ghost" href="<?= e(url('/tasks')) ?>">All tasks</a>
  </div>
</div>
<p class="meta task-by">Created by <?= e($task['creator_name']) ?> · <?= e(format_when((string)$task['created_at'])) ?></p>

<?php if (!$groups): ?>
  <section class="card empty-board">
    <h2>No VMs on this task</h2>
    <p class="muted">The VMs that were assigned here have been removed from the inventory.</p>
  </section>
<?php endif; ?>

<?php foreach ($groups as $group): ?>
  <section class="card task-group">
    <div class="card-head">
      <h2><?= e(!$creator && (int)$group['user_id'] === $userId ? 'Your VMs' : (string)$group['name']) ?></h2>
      <b class="task-score"><?= (int)$group['done'] ?> of <?= (int)$group['total'] ?> · <?= (int)$group['percent'] ?>%</b>
    </div>
    <progress class="task-meter task-meter-wide" max="<?= (int)$group['total'] ?>" value="<?= (int)$group['done'] ?>"><?= (int)$group['percent'] ?>%</progress>
    <ul class="task-vms">
      <?php $mine = (int)$group['user_id'] === $userId; ?>
      <?php $archived = $task['archived_at'] !== null && $task['archived_at'] !== ''; ?>
      <?php $locked = $archived || !empty($group['user_completed']); ?>
      <?php foreach ($group['items'] as $item): ?>
        <li>
          <?php if ($mine && !$locked): ?>
            <form method="post" action="<?= e(url('/tasks/' . (int)$task['id'])) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="item" value="<?= (int)$item['id'] ?>">
              <input type="hidden" name="done" value="0">
              <label class="task-check">
                <input type="checkbox" name="done" value="1" data-task-check <?= $item['done'] ? 'checked' : '' ?>>
                <span>Completed</span>
              </label>
            </form>
          <?php else: ?>
            <label class="task-check">
              <input type="checkbox" disabled <?= $item['done'] ? 'checked' : '' ?>>
              <span>Completed</span>
            </label>
          <?php endif; ?>
          <span class="pill-kind <?= $item['side'] === 'db' ? 'db' : 'app' ?>"><?= $item['side'] === 'db' ? 'DB' : 'APP' ?></span>
          <?php if ($item['removed']): ?>
            <span><?= e($item['name']) ?></span>
          <?php else: ?>
            <a class="row-name" href="<?= e(url(($item['side'] === 'db' ? '/db-vms/' : '/app-vms/') . (int)$item['vm_id'])) ?>"><?= e($item['name']) ?></a>
            <span class="muted"><?= e($item['zone']) ?></span>
            <span class="mono"><?= e($item['ip']) ?></span>
            <?php if (!empty($item['urls'])): ?><span class="task-urls"><?= url_lines($item['urls'], true) ?></span><?php endif; ?>
            <?php if ($item['lifecycle'] === 'decommissioned'): ?><span class="badge decom">DECOM</span><?php endif; ?>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php if ($mine && !$locked): ?>
      <form class="task-finish" method="post" action="<?= e(url('/tasks/' . (int)$task['id'])) ?>">
        <?= csrf_field() ?>
        <button class="btn" type="submit" name="action" value="finish">Completed</button>
      </form>
    <?php elseif ($locked): ?>
      <p class="task-finished">Completed</p>
    <?php else: ?>
      <p class="muted task-finish">Not completed</p>
    <?php endif; ?>
  </section>
<?php endforeach; ?>

<?php if ($creator): ?>
  <?php $archived = $task['archived_at'] !== null && $task['archived_at'] !== ''; ?>
  <div class="detail-actions">
    <form method="post" action="<?= e(url('/tasks/' . (int)$task['id'])) ?>">
      <?= csrf_field() ?>
      <?php if ($archived): ?>
        <button class="btn ghost" type="submit" name="action" value="restore">Restore</button>
      <?php else: ?>
        <button class="btn ghost" type="submit" name="action" value="archive">Archive</button>
      <?php endif; ?>
    </form>
    <form method="post" action="<?= e(url('/tasks/' . (int)$task['id'])) ?>" data-confirm="Delete this task?">
      <?= csrf_field() ?>
      <button class="btn danger" type="submit" name="action" value="delete">Delete task</button>
    </form>
  </div>
<?php endif; ?>
