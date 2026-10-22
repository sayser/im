<?php
$filtering = filters_on($filters, ['q', 'client_id', 'environment_id', 'os_id', 'redis', 'link']);
$export = url('/export/db-vms.csv') . filter_query($filters, ['lifecycle' => $lifecycle, 'page' => null]);
?>
<div class="page-bar">
  <p class="lede"><?= (int)$result['total'] ?> DB VM<?= (int)$result['total'] === 1 ? '' : 's' ?></p>
  <div class="page-actions">
    <a class="btn ghost" href="<?= e($export) ?>">Export CSV</a>
    <?php if (can_write()): ?>
      <a class="btn" href="<?= e(url('/db-vms/new')) ?>">Add DB VM</a>
    <?php endif; ?>
  </div>
</div>

<form class="filters" method="get" action="<?= e(url('/db-vms')) ?>">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, IP, notes" aria-label="Search DB VMs">
  <select name="client_id" aria-label="Client"><?= select_options($clients, (int)$filters['client_id'], 'All clients') ?></select>
  <select name="environment_id" aria-label="Deployment"><?= select_options($environments, (int)$filters['environment_id'], 'All deployments') ?></select>
  <select name="os_id" aria-label="OS"><?= select_options($oses, (int)$filters['os_id'], 'All operating systems') ?></select>
  <select name="redis" aria-label="Redis">
    <option value="">Redis: any</option>
    <option value="1"<?= $filters['redis'] === '1' ? ' selected' : '' ?>>Redis: yes</option>
    <option value="0"<?= $filters['redis'] === '0' ? ' selected' : '' ?>>Redis: no</option>
  </select>
  <select name="link" aria-label="APP link">
    <option value="">Any APP link</option>
    <option value="linked"<?= $filters['link'] === 'linked' ? ' selected' : '' ?>>Has an APP VM</option>
    <option value="unlinked"<?= $filters['link'] === 'unlinked' ? ' selected' : '' ?>>No APP VM</option>
  </select>
  <button class="btn" type="submit">Apply</button>
  <?php if ($filtering): ?><a class="btn ghost" href="<?= e(url('/db-vms')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
  <div class="empty">
    <h2><?= $filtering ? 'No DB VMs match these filters' : 'No DB VMs yet' ?></h2>
    <p><?= $filtering ? 'Clear the filters or search for something else.' : 'A DB VM is the database server. Redis is recorded here. It can serve many APP VMs, one, or none.' ?></p>
  </div>
<?php else: ?>
  <?php
    $rows = $result['rows'];
    $restore = false;
    require APP_ROOT . '/views/partials/db_table.php';
    $page = $result['page'];
    $pages = $result['pages'];
    $path = '/db-vms';
    require APP_ROOT . '/views/partials/pager.php';
  ?>
<?php endif; ?>
