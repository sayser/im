<?php
$hits = $hits ?? [];
$showZone = !empty($showZone);
?>
<?php if (!$groups): ?>
  <section class="card empty-board">
    <h2>No VMs on this tab yet</h2>
    <p class="muted">Add an APP VM and a DB VM with the same stack name and they will sit next to each other.</p>
  </section>
<?php endif; ?>
<?php foreach ($groups as $group): ?>
  <?php
    $rows = max(count($group['apps']), count($group['dbs']), 1);
  ?>
  <section class="project-block<?= $group['stack_id'] > 0 ? '' : ' project-solo' ?>">
    <?php if ($group['stack_id'] > 0): ?>
      <header class="project-head">
        <div>
          <?php if ($showZone): ?><p class="kicker"><?= e($group['zone_name']) ?></p><?php endif; ?>
          <h2><?= e($group['name']) ?></h2>
        </div>
      </header>
      <div class="pair-labels" aria-hidden="true"><span>APP</span><span>DB</span></div>
    <?php elseif ($showZone): ?>
      <p class="kicker"><?= e($group['zone_name']) ?></p>
    <?php endif; ?>
    <div class="pair-grid">
      <?php for ($i = 0; $i < $rows; $i++): ?>
        <?php $app = $group['apps'][$i] ?? null; ?>
        <?php $db = $group['dbs'][$i] ?? null; ?>
        <?php if ($app): ?>
          <?php $vm = $app; $side = 'app'; $hit = isset($hits['app-' . (int)$app['id']]); require APP_ROOT . '/views/partials/vm_card.php'; ?>
        <?php else: ?>
          <div class="vm-card placeholder">No APP VM in this row</div>
        <?php endif; ?>
        <?php if ($db): ?>
          <?php $vm = $db; $side = 'db'; $hit = isset($hits['db-' . (int)$db['id']]); require APP_ROOT . '/views/partials/vm_card.php'; ?>
        <?php else: ?>
          <div class="vm-card placeholder">No DB VM in this row</div>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  </section>
<?php endforeach; ?>
