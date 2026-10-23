<?php
$side = ($side ?? '') === 'db' ? 'db' : 'app';
$hit = !empty($hit);
$marks = $vm['marks'] ?? [];
$cardClass = 'vm-card ' . $side . ($hit ? ' is-hit' : '') . mark_card_class($marks);
$href = $side === 'app' ? '/app-vms/' . (int)$vm['id'] : '/db-vms/' . (int)$vm['id'];
?>
<a class="<?= e($cardClass) ?>" href="<?= e(url($href)) ?>">
  <div class="vm-rows">
    <div class="vm-row">
      <div class="vm-id">
        <h3><?= e($vm['name']) ?></h3>
        <span class="pill-kind"><?= $side === 'app' ? 'APP' : 'DB' ?></span>
        <?= env_badge((string)$vm['environment_name']) ?>
        <span class="os-pill"><?= e($vm['os_name']) ?></span>
        <?= mark_chips($marks) ?>
      </div>
      <?= fact_line('Client', (string)$vm['client_name']) ?>
      <?= fact_line('Environment', (string)$vm['zone_name']) ?>
      <?= fact_line('Stack', (string)($vm['stack_name'] ?? '')) ?>
    </div>
    <?php if ($side === 'app'): ?>
      <div class="vm-row">
        <?= fact_line('Internal IP', (string)$vm['internal_ip'], true) ?>
        <?= fact_line('External IP', (string)($vm['external_ip'] ?? ''), true) ?>
        <?= fact_row('Applications', chips($vm['applications'] ?? [])) ?>
        <?= fact_row('DB VMs', linked_names($vm['db_vms'] ?? [])) ?>
      </div>
      <div class="vm-row">
        <?= fact_row('URL', soft_url($vm['url'] ?? '')) ?>
        <?= fact_row('BrotherHood URL', soft_url($vm['brotherhood_url'] ?? '')) ?>
        <?= fact_row('Status Page', soft_url($vm['status_page_url'] ?? '')) ?>
        <?= fact_row('Notes', nl2e($vm['notes'] ?? '')) ?>
      </div>
    <?php else: ?>
      <?php $redis = (int)$vm['redis'] === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>'; ?>
      <div class="vm-row">
        <?= fact_line('Internal IP', (string)$vm['internal_ip'], true) ?>
        <?= fact_row('Redis', $redis) ?>
        <?= fact_row('APP VMs', linked_names($vm['app_vms'] ?? [])) ?>
      </div>
      <div class="vm-row">
        <?= fact_row('Notes', nl2e($vm['notes'] ?? '')) ?>
      </div>
    <?php endif; ?>
  </div>
</a>
