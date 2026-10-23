<?php
$editing = !empty($view);
$action = $editing ? url('/views/' . (int)$view['id']) : url('/views');
?>
<form class="card form narrow" method="post" action="<?= e($action) ?>">
  <?= csrf_field() ?>
  <?php if ($editing): ?><input type="hidden" name="action" value="save"><?php endif; ?>
  <?php if ($editing): ?><h2>View criteria</h2><?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="flash error"><?= e($formError) ?></div>
  <?php endif; ?>
  <div class="form-grid">
    <label class="field span-2">
      <span>Name</span>
      <input name="name" required maxlength="120" value="<?= e($input['name']) ?>">
    </label>
    <label class="field">
      <span>VMs</span>
      <select name="vm_side" required>
        <?php foreach (saved_view_sides() as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $input['vm_side'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="field">
      <span>Environment</span>
      <select name="zone_id">
        <?= select_options($zones, (int)$input['zone_id'], 'Any environment') ?>
      </select>
    </label>
    <label class="field">
      <span>OS</span>
      <select name="os_id">
        <?= select_options($oses, (int)$input['os_id'], 'Any OS') ?>
      </select>
    </label>
    <label class="field">
      <span>Deployment</span>
      <select name="environment_id">
        <?= select_options($environments, (int)$input['environment_id'], 'Any deployment') ?>
      </select>
    </label>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= $editing ? 'Save view' : 'Create view' ?></button>
    <a class="btn ghost" href="<?= e(url($editing ? '/views/' . (int)$view['id'] : '/views')) ?>">Cancel</a>
  </div>
</form>
