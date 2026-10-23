<p class="crumb">
  <a href="<?= e(url($vm['lifecycle'] === 'decommissioned' ? '/decom?type=db' : '/environments/' . (int)$vm['zone_id'])) ?>"><?= $vm['lifecycle'] === 'decommissioned' ? 'DECOM' : e($vm['zone_name']) ?></a>
  <span>/</span> <?= e($vm['name']) ?>
</p>

<?php if ($vm['lifecycle'] === 'decommissioned'): ?>
  <div class="banner">
    Decommissioned <?= e(format_when($vm['decommissioned_at'])) ?>
    <?php if (!empty($vm['decommissioned_by_name'])): ?> by <?= e($vm['decommissioned_by_name']) ?><?php endif; ?>.
    Restore puts it back on the main DB VM list.
  </div>
<?php endif; ?>

<?php if (can_write()): ?>
  <div class="page-actions detail-actions">
    <a class="btn" href="<?= e(url('/db-vms/' . (int)$vm['id'] . '/edit')) ?>">Edit</a>
    <?php if ($vm['lifecycle'] === 'active'): ?>
      <form method="post" action="<?= e(url('/db-vms/' . (int)$vm['id'] . '/decommission')) ?>" data-confirm="Move this DB VM to the DECOM tab? You can restore it later.">
        <?= csrf_field() ?>
        <button class="btn ghost" type="submit">Decommission</button>
      </form>
    <?php else: ?>
      <form method="post" action="<?= e(url('/db-vms/' . (int)$vm['id'] . '/restore')) ?>">
        <?= csrf_field() ?>
        <button class="btn" type="submit">Restore</button>
      </form>
    <?php endif; ?>
    <a class="btn danger ghost" href="<?= e(url('/db-vms/' . (int)$vm['id'] . '/delete')) ?>">Delete</a>
  </div>
<?php endif; ?>

<div class="split">
  <section class="card">
    <h2>DB VM</h2>
    <dl class="facts">
      <div><dt>Client</dt><dd><?= e($vm['client_name']) ?></dd></div>
      <div><dt>Environment</dt><dd><?= e($vm['zone_name']) ?></dd></div>
      <div><dt>Deployment</dt><dd><?= env_badge((string)$vm['environment_name']) ?></dd></div>
      <div><dt>Stack</dt><dd><?= or_dash($vm['stack_name'] ?? '') ?></dd></div>
      <div><dt>OS</dt><dd><?= e($vm['os_name']) ?></dd></div>
      <div><dt>Internal IP</dt><dd class="mono"><?= e($vm['internal_ip']) ?></dd></div>
      <div><dt>Redis</dt><dd><?= (int)$vm['redis'] === 1 ? '<span class="badge yes">Yes</span>' : '<span class="badge no">No</span>' ?></dd></div>
    </dl>
    <p class="meta">
      Updated <?= e(format_when($vm['updated_at'])) ?><?= !empty($vm['updated_by_name']) ? ' by ' . e($vm['updated_by_name']) : '' ?>
      · Created <?= e(format_when($vm['created_at'])) ?><?= !empty($vm['created_by_name']) ? ' by ' . e($vm['created_by_name']) : '' ?>
    </p>
  </section>
  <section class="card">
    <h2>APP VMs</h2>
    <?php if (!$vm['app_vms']): ?>
      <p class="muted">No APP VMs linked.</p>
    <?php else: ?>
      <ul class="link-list">
        <?php foreach ($vm['app_vms'] as $linked): ?>
          <li>
            <a href="<?= e(url('/app-vms/' . (int)$linked['id'])) ?>"><?= e($linked['name']) ?></a>
            <span class="mono"><?= e($linked['internal_ip']) ?></span>
            <span class="muted"><?= e($linked['client_name']) ?></span>
            <?php if ($linked['lifecycle'] === 'decommissioned'): ?><span class="badge decom">DECOM</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <h2>Notes</h2>
    <div class="notes"><?= nl2e($vm['notes'] ?? '') ?></div>
    <h2>Troubleshooting</h2>
    <div class="notes"><?= nl2e($vm['troubleshooting'] ?? '') ?></div>
  </section>
</div>

<?php require APP_ROOT . '/views/partials/vm_marks.php'; ?>

<section class="card">
  <h2>History</h2>
  <?php require APP_ROOT . '/views/partials/history.php'; ?>
</section>
