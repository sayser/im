<?php require __DIR__ . '/tabs.php'; ?>
<div class="page-bar">
  <p class="lede">Archived tasks stay here with the dates they were created, completed, and archived.</p>
</div>

<?php if (!$tasks): ?>
  <section class="card empty-board">
    <h2>No archived tasks</h2>
    <p class="muted">The person who created a task can archive it from the task page.</p>
  </section>
<?php else: ?>
  <div class="table-wrap">
    <table class="grid">
      <thead>
        <tr>
          <th>Task</th>
          <th>Created</th>
          <th>Completed</th>
          <th>Archived</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tasks as $task): ?>
          <?php $completed = format_when($task['completed_at']); ?>
          <tr>
            <td><a class="row-name" href="<?= e(url('/tasks/' . (int)$task['id'])) ?>"><?= e($task['title']) ?></a></td>
            <td class="nowrap"><?= e(format_when($task['created_at'])) ?></td>
            <td class="nowrap"><?= $completed === '' ? '—' : e($completed) ?></td>
            <td class="nowrap"><?= e(format_when($task['archived_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
