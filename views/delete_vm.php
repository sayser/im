<?php $noun = $kind === 'app' ? 'APP VM' : 'DB VM'; ?>
<section class="card danger-card">
  <h2>Permanently delete this <?= e($noun) ?></h2>
  <p>This removes <strong><?= e($vm['name']) ?></strong> from the inventory. The deletion stays in the audit log with the field values it had. This cannot be undone. Decommission is the reversible action.</p>
  <form method="post" action="<?= e(url(($kind === 'app' ? '/app-vms/' : '/db-vms/') . (int)$vm['id'] . '/delete')) ?>" class="stack-form">
    <?= csrf_field() ?>
    <label class="field">
      <span>Type <code><?= e($vm['name']) ?></code> to confirm</span>
      <input name="confirm_name" autocomplete="off" spellcheck="false" required<?= invalid_attr($errors, 'confirm_name') ?>>
      <?= field_error($errors, 'confirm_name') ?>
    </label>
    <div class="form-actions">
      <button class="btn danger" type="submit">Delete permanently</button>
      <a class="btn ghost" href="<?= e(url(($kind === 'app' ? '/app-vms/' : '/db-vms/') . (int)$vm['id'])) ?>">Cancel</a>
    </div>
  </form>
</section>
