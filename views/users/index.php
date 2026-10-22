<div class="page-bar">
  <form class="filters tight" method="get" action="<?= e(url('/users')) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search users" aria-label="Search users">
    <button class="btn" type="submit">Search</button>
  </form>
  <a class="btn" href="<?= e(url('/users/new')) ?>">Add user</a>
</div>
<?php if (!$users): ?>
  <div class="empty">
    <h2>No users found</h2>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="grid">
      <thead>
        <tr><th>Username</th><th>Name</th><th>Role</th><th>Status</th><th>Updated</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($users as $row): ?>
          <tr>
            <td class="mono"><?= e($row['username']) ?></td>
            <td><?= e($row['display_name']) ?></td>
            <td><?= role_badge((string)$row['role']) ?></td>
            <td><?= (int)$row['active'] === 1 ? '<span class="badge yes">Enabled</span>' : '<span class="badge no">Disabled</span>' ?></td>
            <td class="nowrap"><?= e(format_when($row['updated_at'])) ?></td>
            <td><a href="<?= e(url('/users/' . (int)$row['id'] . '/edit')) ?>">Edit</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
