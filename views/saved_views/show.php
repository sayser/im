<p class="crumb"><a href="<?= e(url('/views')) ?>">Views</a> <span>/</span> <?= e($saved['name']) ?></p>

<section class="card">
  <div class="card-head">
    <div>
      <h2><?= e($saved['name']) ?></h2>
      <p class="muted"><?= e($summary) ?></p>
    </div>
  </div>
  <?php
    $membersApps = $apps;
    $membersDbs = $dbs;
    $canRemove = false;
    $emptyMessage = 'No active VMs match this view.';
    require APP_ROOT . '/views/partials/mark_members.php';
  ?>
</section>

<?php $view = $saved; require APP_ROOT . '/views/saved_views/form.php'; ?>
<div class="form-actions">
  <form method="post" action="<?= e(url('/views/' . (int)$saved['id'])) ?>" data-confirm-delete="Delete this view? The servers stay in the inventory.">
    <?= csrf_field() ?>
    <button class="btn danger ghost" name="action" value="delete" type="submit">Delete view</button>
  </form>
</div>
