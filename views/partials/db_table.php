<div class="table-wrap">
  <table class="grid">
    <thead>
      <tr>
        <th>VM Name</th>
        <th>Client</th>
        <th>Deployment</th>
        <th>OS</th>
        <th>Internal IP</th>
        <th>Redis</th>
        <th>APP VMs</th>
        <?php if (!empty($restore)): ?><th></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><a class="row-name" href="<?= e(url('/db-vms/' . (int)$row['id'])) ?>"><?= e($row['name']) ?></a></td>
          <td><?= e($row['client_name']) ?></td>
          <td><?= env_badge((string)$row['environment_name']) ?></td>
          <td><?= e($row['os_name']) ?></td>
          <td class="mono"><?= e($row['internal_ip']) ?></td>
          <td><?= (int)$row['redis'] === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>' ?></td>
          <td><?= vm_links($row['app_vms'], 'app') ?></td>
          <?php if (!empty($restore) && can_write()): ?>
            <td>
              <form method="post" action="<?= e(url('/db-vms/' . (int)$row['id'] . '/restore')) ?>">
                <?= csrf_field() ?>
                <button class="btn small" type="submit">Restore</button>
              </form>
            </td>
          <?php elseif (!empty($restore)): ?>
            <td></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
