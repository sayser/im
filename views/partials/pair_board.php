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
          <?php $hit = isset($hits['app-' . (int)$app['id']]); ?>
          <a class="vm-card app<?= $hit ? ' is-hit' : '' ?>" href="<?= e(url('/app-vms/' . (int)$app['id'])) ?>">
            <div class="vm-rows">
              <div class="vm-row">
                <div class="vm-id">
                  <h3><?= e($app['name']) ?></h3>
                  <span class="pill-kind">APP</span>
                  <?= env_badge((string)$app['environment_name']) ?>
                  <span class="os-pill"><?= e($app['os_name']) ?></span>
                </div>
                <?= fact_line('Client', (string)$app['client_name']) ?>
                <?= fact_line('Environment', (string)$app['zone_name']) ?>
                <?= fact_line('Stack', (string)($app['stack_name'] ?? '')) ?>
              </div>
              <div class="vm-row">
                <?= fact_line('Internal IP', (string)$app['internal_ip'], true) ?>
                <?= fact_line('External IP', (string)($app['external_ip'] ?? ''), true) ?>
                <?= fact_row('Applications', chips($app['applications'] ?? [])) ?>
                <?= fact_row('DB VMs', linked_names($app['db_vms'] ?? [])) ?>
              </div>
              <div class="vm-row">
                <?= fact_row('URL', soft_url($app['url'] ?? '')) ?>
                <?= fact_row('BrotherHood URL', soft_url($app['brotherhood_url'] ?? '')) ?>
                <?= fact_row('Status Page', soft_url($app['status_page_url'] ?? '')) ?>
                <?= fact_row('Notes', nl2e($app['notes'] ?? '')) ?>
              </div>
            </div>
          </a>
        <?php else: ?>
          <div class="vm-card placeholder">No APP VM in this row</div>
        <?php endif; ?>
        <?php if ($db): ?>
          <?php $hit = isset($hits['db-' . (int)$db['id']]); ?>
          <?php $redis = (int)$db['redis'] === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>'; ?>
          <a class="vm-card db<?= $hit ? ' is-hit' : '' ?>" href="<?= e(url('/db-vms/' . (int)$db['id'])) ?>">
            <div class="vm-rows">
              <div class="vm-row">
                <div class="vm-id">
                  <h3><?= e($db['name']) ?></h3>
                  <span class="pill-kind">DB</span>
                  <?= env_badge((string)$db['environment_name']) ?>
                  <span class="os-pill"><?= e($db['os_name']) ?></span>
                </div>
                <?= fact_line('Client', (string)$db['client_name']) ?>
                <?= fact_line('Environment', (string)$db['zone_name']) ?>
                <?= fact_line('Stack', (string)($db['stack_name'] ?? '')) ?>
              </div>
              <div class="vm-row">
                <?= fact_line('Internal IP', (string)$db['internal_ip'], true) ?>
                <?= fact_row('Redis', $redis) ?>
                <?= fact_row('APP VMs', linked_names($db['app_vms'] ?? [])) ?>
              </div>
              <div class="vm-row">
                <?= fact_row('Notes', nl2e($db['notes'] ?? '')) ?>
              </div>
            </div>
          </a>
        <?php else: ?>
          <div class="vm-card placeholder">No DB VM in this row</div>
        <?php endif; ?>
      <?php endfor; ?>
    </div>
  </section>
<?php endforeach; ?>
