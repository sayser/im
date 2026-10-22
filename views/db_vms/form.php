<?php
$missingClients = $clients === [];
$missingZones = $zones === [];
$missingEnvironments = $environments === [];
$missingOs = $oses === [];
$blocked = $missingZones || $missingEnvironments || $missingOs;
$action = $mode === 'edit' ? url('/db-vms/' . (int)$vm['id']) : url('/db-vms');
?>
<?php if ($blocked): ?>
  <div class="banner">
    <?php if (is_admin()): ?>
      This record needs an environment, deployment, and operating system.
      Add the missing values in <a href="<?= e(url('/lists')) ?>">Field options</a>.
    <?php else: ?>
      This record needs an environment, deployment, and operating system. Ask an admin to add the missing dropdown values.
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php if ($mode === 'edit' && ($vm['lifecycle'] ?? '') === 'decommissioned'): ?>
  <div class="banner">This DB VM is decommissioned. Saving keeps it on the DECOM tab until someone restores it.</div>
<?php endif; ?>

<form class="card form sheet" method="post" action="<?= e($action) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="field">
      <span>VM Name</span>
      <input name="name" required maxlength="190" value="<?= e($input['name']) ?>"<?= invalid_attr($errors, 'name') ?>>
      <?= field_error($errors, 'name') ?>
    </label>
    <label class="field">
      <span>Client</span>
      <select name="client_id"<?= invalid_attr($errors, 'client_id') ?>><?= select_options($clients, (int)$input['client_id'], '') ?></select>
      <small class="hint">Leave this as PT when the VM has no other client.</small>
      <?= field_error($errors, 'client_id') ?>
    </label>
    <label class="field">
      <span>Environment</span>
      <select name="zone_id" data-zone-select required<?= invalid_attr($errors, 'zone_id') ?>><?= select_options($zones, (int)$input['zone_id'], 'Select an environment') ?></select>
      <?= field_error($errors, 'zone_id') ?>
    </label>
    <label class="field">
      <span>Deployment</span>
      <select name="environment_id" required<?= invalid_attr($errors, 'environment_id') ?>><?= select_options($environments, (int)$input['environment_id'], 'Select a deployment') ?></select>
      <?= field_error($errors, 'environment_id') ?>
    </label>
    <label class="field">
      <span>Stack</span>
      <input name="stack_name" list="stack-names" maxlength="190" value="<?= e($input['stack_name']) ?>" placeholder="prod_test"<?= invalid_attr($errors, 'stack_name') ?>>
      <datalist id="stack-names"></datalist>
      <small class="hint">Use the same stack name on the APP and DB VMs that should appear next to each other. One stack can include many of each. Leave this blank for a VM with no partner.</small>
      <?= field_error($errors, 'stack_name') ?>
    </label>
    <label class="field">
      <span>OS</span>
      <select name="os_id" required<?= invalid_attr($errors, 'os_id') ?>><?= select_options($oses, (int)$input['os_id'], 'Select an operating system') ?></select>
      <?= field_error($errors, 'os_id') ?>
    </label>
    <label class="field">
      <span>Internal IP</span>
      <input name="internal_ip" required maxlength="45" spellcheck="false" value="<?= e($input['internal_ip']) ?>"<?= invalid_attr($errors, 'internal_ip') ?>>
      <?= field_error($errors, 'internal_ip') ?>
    </label>
    <label class="field">
      <span>Redis</span>
      <select name="redis" required<?= invalid_attr($errors, 'redis') ?>>
        <option value="0"<?= $input['redis'] === '0' ? ' selected' : '' ?>>No</option>
        <option value="1"<?= $input['redis'] === '1' ? ' selected' : '' ?>>Yes</option>
      </select>
      <?= field_error($errors, 'redis') ?>
    </label>
    <label class="field span-2">
      <span>Notes</span>
      <textarea name="notes" rows="4" maxlength="20000"<?= invalid_attr($errors, 'notes') ?>><?= e($input['notes']) ?></textarea>
      <?= field_error($errors, 'notes') ?>
    </label>
    <label class="field span-2">
      <span>Troubleshooting</span>
      <textarea name="troubleshooting" rows="8" maxlength="20000"<?= invalid_attr($errors, 'troubleshooting') ?>><?= e($input['troubleshooting']) ?></textarea>
      <?= field_error($errors, 'troubleshooting') ?>
    </label>
  </div>

  <fieldset class="picks">
    <legend>APP VMs</legend>
    <p class="hint">Optional relationship. A DB VM can serve many APP VMs, one APP VM, or none. The stack name above is what places them side by side on the environment tab.</p>
    <input type="search" data-filter-target="#app-picks" placeholder="Filter APP VMs" aria-label="Filter APP VMs">
    <div class="checks scroll" id="app-picks">
      <?php if (!$appChoices): ?>
        <p class="muted">No APP VMs to link yet.</p>
      <?php endif; ?>
      <?php foreach ($appChoices as $choice): ?>
        <?php $label = $choice['name'] . ' · ' . $choice['client_name'] . ' · ' . $choice['internal_ip']; ?>
        <label class="check" data-label="<?= e($label) ?>">
          <input type="checkbox" name="app_vm_ids[]" value="<?= (int)$choice['id'] ?>"<?= in_array((int)$choice['id'], $input['app_vm_ids'], true) ? ' checked' : '' ?>>
          <span><?= e($label) ?><?= $choice['lifecycle'] === 'decommissioned' ? ' (decommissioned)' : '' ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <?= field_error($errors, 'app_vm_ids') ?>
  </fieldset>

  <div class="form-actions">
    <button class="btn" type="submit"><?= $mode === 'edit' ? 'Save changes' : 'Create DB VM' ?></button>
    <a class="btn ghost" href="<?= e(url($mode === 'edit' ? '/environments/' . (int)$vm['zone_id'] : '/environments/' . (int)$input['zone_id'])) ?>">Cancel</a>
  </div>
</form>
<script type="application/json" id="stack-catalog"><?= json_encode($stacks, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
