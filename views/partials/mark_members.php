<?php
$canRemove = !empty($canRemove);
$markId = (int)($markId ?? 0);
$members = [
    'app' => $membersApps ?? [],
    'db' => $membersDbs ?? [],
];
?>
<?php if (!$members['app'] && !$members['db']): ?>
  <p class="muted"><?= e($emptyMessage ?? 'No servers in this mark yet.') ?></p>
<?php else: ?>
  <div class="mark-lines">
    <?php foreach ($members as $side => $rows): ?>
      <?php foreach ($rows as $vm): ?>
        <div class="mark-line">
          <span class="pill-kind <?= $side === 'app' ? 'app' : 'db' ?>"><?= $side === 'app' ? 'APP' : 'DB' ?></span>
          <a href="<?= e(url(($side === 'app' ? '/app-vms/' : '/db-vms/') . (int)$vm['id'])) ?>"><?= e($vm['name']) ?></a>
          <span><?= e($vm['os_name']) ?></span>
          <span><?= e($vm['zone_name']) ?></span>
          <?= env_badge((string)$vm['environment_name']) ?>
          <span class="mono"><?= e($vm['internal_ip']) ?></span>
          <?php if (($vm['lifecycle'] ?? '') === 'decommissioned'): ?><span class="badge decom">DECOM</span><?php endif; ?>
          <?php if ($canRemove): ?>
            <form method="post" action="<?= e(url('/marks/' . $markId)) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="side" value="<?= e($side) ?>">
              <input type="hidden" name="vm_id" value="<?= (int)$vm['id'] ?>">
              <button class="btn small ghost" type="submit">Remove</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
