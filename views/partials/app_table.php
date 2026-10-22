<div class="table-wrap">
  <table class="grid">
    <thead>
      <tr>
        <th>VM Name</th>
        <th>Client</th>
        <th>Deployment</th>
        <th>OS</th>
        <th>Internal IP</th>
        <th>External IP</th>
        <th>Applications</th>
        <th>DB VMs</th>
        <th>URL</th>
        <?php if (!empty($restore)): ?><th></th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><a class="row-name" href="<?= e(url('/app-vms/' . (int)$row['id'])) ?>"><?= e($row['name']) ?></a></td>
          <td><?= e($row['client_name']) ?></td>
          <td><?= env_badge((string)$row['environment_name']) ?></td>
          <td><?= e($row['os_name']) ?></td>
          <td class="mono"><?= e($row['internal_ip']) ?></td>
          <td class="mono"><?= or_dash($row['external_ip'] ?? '') ?></td>
          <td><?= chips($row['applications']) ?></td>
          <td><?= vm_links($row['db_vms'], 'db') ?></td>
          <td><?= host_link($row['url'] ?? '') ?></td>
          <?php if (!empty($restore) && can_write()): ?>
            <td>
              <form method="post" action="<?= e(url('/app-vms/' . (int)$row['id'] . '/restore')) ?>">
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
