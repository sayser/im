<?php $filtering = filters_on($filters, ['q', 'action', 'from_day', 'to_day']); ?>
<form class="filters" method="get" action="<?= e(url('/audit')) ?>">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search summary or user" aria-label="Search audit">
  <select name="action" aria-label="Action">
    <option value="">All actions</option>
    <?php foreach ($actions as $value => $label): ?>
      <option value="<?= e($value) ?>"<?= $filters['action'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($filters['from_day']) ?>" aria-label="From date">
  <input type="date" name="to" value="<?= e($filters['to_day']) ?>" aria-label="To date">
  <button class="btn" type="submit">Apply</button>
  <?php if ($filtering): ?><a class="btn ghost" href="<?= e(url('/audit')) ?>">Clear</a><?php endif; ?>
</form>
<p class="hint">Times shown in <?= e(display_timezone_name()) ?>.</p>

<?php if (!$result['rows']): ?>
  <div class="empty">
    <h2>No audit events</h2>
    <p>Sign-ins, inventory edits, and field-option changes will show up here.</p>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="grid">
      <thead>
        <tr><th>When</th><th>Who</th><th>Action</th><th>Summary</th></tr>
      </thead>
      <tbody>
        <?php foreach ($result['rows'] as $row): ?>
          <tr>
            <td class="nowrap"><?= e(format_when($row['created_at'])) ?></td>
            <td><?= e($row['display_name'] ?: ($row['username'] ?: 'Unknown')) ?></td>
            <td><?= e(audit_action_label((string)$row['action'])) ?></td>
            <td><a href="<?= e(url('/audit/' . (int)$row['id'])) ?>"><?= e($row['summary']) ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php
    $page = $result['page'];
    $pages = $result['pages'];
    $path = '/audit';
    $filters = [
        'q' => $filters['q'],
        'action' => $filters['action'],
        'from' => $filters['from_day'],
        'to' => $filters['to_day'],
        'page' => $page,
    ];
    require APP_ROOT . '/views/partials/pager.php';
  ?>
<?php endif; ?>
