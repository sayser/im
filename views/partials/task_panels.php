<?php
  $panels = $panels ?? [
      ['Assigned', $assigned, 'No open tasks.'],
      ['Completed', $completed, 'No completed tasks.'],
  ];
  $showPanelHeadings = $showPanelHeadings ?? true;
?>
<div class="task-panels">
  <?php foreach ($panels as [$heading, $rows, $empty]): ?>
    <section class="task-panel">
      <?php if ($showPanelHeadings): ?><h2><?= e($heading) ?></h2><?php endif; ?>
      <?php if (!$rows): ?>
        <section class="card empty-board">
          <p class="muted"><?= e($empty) ?></p>
        </section>
      <?php endif; ?>
      <?php foreach ($rows as $task): ?>
        <section class="card task-card">
          <div class="card-head">
            <h2><a href="<?= e(url('/tasks/' . (int)$task['id'])) ?>"><?= e($task['title']) ?></a></h2>
            <span class="meta"><?= $task['creator'] ? 'You created this' : 'Assigned to you' ?></span>
          </div>
          <?php if ($task['excerpt'] !== ''): ?>
            <p class="task-excerpt"><?= e($task['excerpt']) ?></p>
          <?php endif; ?>
          <?php if ($task['groups']): ?>
            <ul class="task-progress">
              <?php foreach ($task['groups'] as $group): ?>
                <li>
                  <span>
                    <?= e($task['creator'] ? (string)$group['name'] : 'Your VMs') ?>
                    <?php if (!empty($group['user_completed'])): ?><span class="badge yes">Completed</span><?php endif; ?>
                  </span>
                  <progress class="task-meter" max="<?= (int)$group['total'] ?>" value="<?= (int)$group['done'] ?>"><?= (int)$group['percent'] ?>%</progress>
                  <b><?= (int)$group['done'] ?> of <?= (int)$group['total'] ?> · <?= (int)$group['percent'] ?>%</b>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <p class="muted">No VMs are assigned.</p>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
