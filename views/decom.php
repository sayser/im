<?php
$isApp = $type === 'app';
$filtering = $isApp
    ? filters_on($filters, ['q', 'client_id', 'environment_id', 'os_id', 'application_id', 'link'])
    : filters_on($filters, ['q', 'client_id', 'environment_id', 'os_id', 'redis', 'link']);
$exportPath = $isApp ? '/export/app-vms.csv' : '/export/db-vms.csv';
$export = url($exportPath) . filter_query($filters, ['lifecycle' => 'decommissioned', 'page' => null, 'type' => null]);
$clear = url('/decom?type=' . $type);
?>
<div class="segmented" role="tablist" aria-label="Decommissioned inventory">
  <a class="<?= $isApp ? 'on' : '' ?>" href="<?= e(url('/decom?type=app')) ?>" <?= $isApp ? 'aria-current="page"' : '' ?>>APP VMs</a>
  <a class="<?= !$isApp ? 'on' : '' ?>" href="<?= e(url('/decom?type=db')) ?>" <?= !$isApp ? 'aria-current="page"' : '' ?>>DB VMs</a>
</div>

<div class="page-bar">
  <p class="lede"><?= (int)$result['total'] ?> decommissioned <?= $isApp ? 'APP' : 'DB' ?> VM<?= (int)$result['total'] === 1 ? '' : 's' ?></p>
  <a class="btn ghost" href="<?= e($export) ?>">Export CSV</a>
</div>

<form class="filters" method="get" action="<?= e(url('/decom')) ?>">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="<?= $isApp ? 'Search name, IP, URL, notes' : 'Search name, IP, notes' ?>" aria-label="Search">
  <select name="client_id" aria-label="Client"><?= select_options($clients, (int)$filters['client_id'], 'All clients') ?></select>
  <select name="environment_id" aria-label="Deployment"><?= select_options($environments, (int)$filters['environment_id'], 'All deployments') ?></select>
  <select name="os_id" aria-label="OS"><?= select_options($oses, (int)$filters['os_id'], 'All operating systems') ?></select>
  <?php if ($isApp): ?>
    <select name="application_id" aria-label="Application"><?= select_options($applications, (int)$filters['application_id'], 'All applications') ?></select>
    <select name="link" aria-label="DB link">
      <option value="">Any DB link</option>
      <option value="linked"<?= $filters['link'] === 'linked' ? ' selected' : '' ?>>Has a DB VM</option>
      <option value="unlinked"<?= $filters['link'] === 'unlinked' ? ' selected' : '' ?>>No DB VM</option>
    </select>
  <?php else: ?>
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
  <?php endif; ?>
  <button class="btn" type="submit">Apply</button>
  <?php if ($filtering): ?><a class="btn ghost" href="<?= e($clear) ?>">Clear</a><?php endif; ?>
</form>

<?php if (!$result['rows']): ?>
  <div class="empty">
    <h2><?= $filtering ? 'Nothing matches these filters' : 'The DECOM tab is empty' ?></h2>
    <p>Decommissioned VMs leave the main lists and stay here until they are restored or permanently deleted.</p>
  </div>
<?php else: ?>
  <?php
    $rows = $result['rows'];
    $restore = true;
    require APP_ROOT . '/views/partials/' . ($isApp ? 'app_table.php' : 'db_table.php');
    $page = $result['page'];
    $pages = $result['pages'];
    $path = '/decom';
    require APP_ROOT . '/views/partials/pager.php';
  ?>
<?php endif; ?>
