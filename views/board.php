<nav class="env-tabs" aria-label="Environments">
  <?php foreach ($zones as $tab): ?>
    <a class="<?= $zone && (int)$zone['id'] === (int)$tab['id'] ? 'active' : '' ?>" href="<?= e(url('/environments/' . (int)$tab['id'])) ?>"<?= $zone && (int)$zone['id'] === (int)$tab['id'] ? ' aria-current="page"' : '' ?>><?= e($tab['name']) ?></a>
  <?php endforeach; ?>
  <?php if (is_admin()): ?>
    <form class="env-add" method="post" action="<?= e(url('/lists')) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="kind" value="zone">
      <input type="hidden" name="return" value="board">
      <input name="name" maxlength="120" required placeholder="New environment" aria-label="New environment">
      <button class="btn small" type="submit">Add tab</button>
    </form>
  <?php endif; ?>
</nav>

<?php if (!$zone): ?>
  <section class="card empty-board">
    <h2>No environments yet</h2>
    <p class="muted">An admin can add the first environment tab from Field options.</p>
  </section>
<?php else: ?>
  <div class="page-bar">
    <p class="lede">APP and DB VMs that share a stack are shown side by side. A stack can hold many of each.</p>
    <?php if (can_write()): ?>
      <div class="page-actions">
        <a class="btn" href="<?= e(url('/app-vms/new?zone=' . (int)$zone['id'])) ?>">Add APP VM</a>
        <a class="btn" href="<?= e(url('/db-vms/new?zone=' . (int)$zone['id'])) ?>">Add DB VM</a>
      </div>
    <?php endif; ?>
  </div>
  <?php
    $groups = $groups;
    $hits = $hits;
    $showZone = $show_zone;
    require APP_ROOT . '/views/partials/pair_board.php';
  ?>
<?php endif; ?>
