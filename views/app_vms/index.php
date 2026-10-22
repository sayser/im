<?php
$filtering = filters_on($filters, ['q', 'client_id', 'environment_id', 'os_id', 'application_id', 'link']);
$export = url('/export/app-vms.csv') . filter_query($filters, ['lifecycle' => $lifecycle, 'page' => null]);
?>
<div class="page-bar">
  <p class="lede"><?= (int)$result['total'] ?> APP VM<?= (int)$result['total'] === 1 ? '' : 's' ?></p>
  <div class="page-actions">
    <a class="btn ghost" href="<?= e($export) ?>">Export CSV</a>
    <?php if (can_write()): ?>
      <a class="btn" href="<?= e(url('/app-vms/new')) ?>">Add APP VM</a>
    <?php endif; ?>
  </div>
</div>

<form class="filters" method="get" action="<?= e(url('/app-vms')) ?>">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search name, IP, URL, notes" aria-label="Search APP VMs">
  <select name="client_id" aria-label="Client"><?= select_options($clients, (int)$filters['client_id'], 'All clients') ?></select>
  <select name="environment_id" aria-label="Deployment"><?= select_options($environments, (int)$filters['environment_id'], 'All deployments') ?></select>
  <select name="os_id" aria-label="OS"><?= select_options($oses, (int)$filters['os_id'], 'All operating systems') ?></select>
  <select name="application_id" aria-label="Application"><?= select_options($applications, (int)$filters['application_id'], 'All applications') ?></select>
  <select name="link" aria-label="DB link">
    <option value="">Any DB link</option>
    <option value="linked"<?= $filters['link'] === 'linked' ? ' selected' : '' ?>>Has a DB VM</option>
    <option value="unlinked"<?= $filters['link'] === 'unlinked' ? ' selected' : '' ?>>No DB VM</option>
  </select>
  <button class="btn" type="submit">Apply</button>
  <?php if ($filtering): ?><a class="btn ghost" href="<?= e(url('/app-vms')) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
  <div class="empty">
    <h2><?= $filtering ? 'No APP VMs match these filters' : 'No APP VMs yet' ?></h2>
    <p><?= $filtering ? 'Clear the filters or search for something else.' : 'An APP VM is the application server. It can link to many DB VMs, one DB VM, or none.' ?></p>
  </div>
<?php else: ?>
  <?php
    $rows = $result['rows'];
    $restore = false;
    require APP_ROOT . '/views/partials/app_table.php';
    $page = $result['page'];
    $pages = $result['pages'];
    $path = '/app-vms';
    require APP_ROOT . '/views/partials/pager.php';
  ?>
<?php endif; ?>
