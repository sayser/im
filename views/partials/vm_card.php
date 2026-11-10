<?php
$side = ($side ?? '') === 'db' ? 'db' : 'app';
$hit = !empty($hit);
$marks = $vm['marks'] ?? [];
$cardClass = 'vm-card ' . $side . ($hit ? ' is-hit' : '') . mark_card_class($marks);
$href = $side === 'app' ? '/app-vms/' . (int)$vm['id'] : '/db-vms/' . (int)$vm['id'];
$notes = trim((string)($vm['notes'] ?? ''));
$cv = (int)($vm['cv_backup'] ?? 0) === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>';
?>
<a class="<?= e($cardClass) ?>" href="<?= e(url($href)) ?>">
  <div class="vm-head">
    <h3><?= e($vm['name']) ?></h3>
    <div class="vm-pills">
      <span class="pill-kind"><?= $side === 'app' ? 'APP' : 'DB' ?></span>
      <?= env_badge((string)$vm['environment_name']) ?>
      <span class="os-pill"><?= e($vm['os_name']) ?></span>
      <?= mark_chips($marks) ?>
    </div>
  </div>
  <div class="vm-facts">
    <?= fact_line('Client', (string)$vm['client_name'], false, false, 'c1') ?>
    <?= fact_line('Environment', (string)$vm['zone_name'], false, false, 'c2') ?>
    <?= fact_line('Tag', (string)($vm['stack_name'] ?? ''), false, false, 'c3') ?>
    <?= fact_line('Internal IP', (string)$vm['internal_ip'], true, false, 'c1') ?>
    <?php if ($side === 'app'): ?>
      <?= fact_line('Host', (string)($vm['host'] ?? ''), false, false, 'c2') ?>
      <?= fact_row('CV backup', $cv, false, 'c3') ?>
      <?= fact_line('External IP', (string)($vm['external_ip'] ?? ''), true, false, 'c1') ?>
      <?= fact_row('Applications', chips($vm['applications'] ?? []), false, 'c2') ?>
      <?= fact_row('DB VMs', linked_names($vm['db_vms'] ?? []), false, 'c3') ?>
    <?php else: ?>
      <?php $redis = (int)$vm['redis'] === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>'; ?>
      <?= fact_line('Host', (string)($vm['host'] ?? ''), false, false, 'c2') ?>
      <?= fact_row('CV backup', $cv, false, 'c3') ?>
      <?= fact_row('Redis', $redis, false, 'c1') ?>
      <?= fact_row('APP VMs', linked_names($vm['app_vms'] ?? []), false, 'rest') ?>
    <?php endif; ?>
    <?php if ($notes !== ''): ?>
      <?= fact_row('Notes', nl2e($notes), false, 'wide') ?>
    <?php endif; ?>
  </div>
  <?php if ($side === 'app'): ?>
    <div class="vm-links">
      <div class="vm-link">
        <span>URL</span>
        <?= url_lines($vm['urls'] ?? [], false) ?>
      </div>
      <?php $brother = trim((string)($vm['brotherhood_url'] ?? '')); ?>
      <?php if ($brother !== ''): ?>
        <div class="vm-link">
          <span>BrotherHood</span>
          <span class="url-line" title="<?= e($brother) ?>"><?= e($brother) ?></span>
        </div>
      <?php endif; ?>
      <?php $status = trim((string)($vm['status_page_url'] ?? '')); ?>
      <?php if ($status !== ''): ?>
        <div class="vm-link">
          <span>Status</span>
          <span class="url-line" title="<?= e($status) ?>"><?= e($status) ?></span>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</a>
