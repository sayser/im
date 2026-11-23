<?php $editing = !empty($task); ?>
<form class="card form" method="post" action="<?= e(url($editing ? '/tasks/' . (int)$task['id'] : '/tasks')) ?>" data-task-form>
  <?= csrf_field() ?>
  <?php if ($editing): ?><input type="hidden" name="action" value="save"><?php endif; ?>
  <?php if ($formError !== ''): ?>
    <div class="flash error"><?= e($formError) ?></div>
  <?php endif; ?>
  <div class="form-grid">
    <label class="field span-2">
      <span>Name</span>
      <input name="title" required maxlength="160" value="<?= e($input['title']) ?>">
    </label>
    <label class="field span-2">
      <span>Description</span>
      <textarea name="description" required maxlength="4000" rows="4"><?= e($input['description']) ?></textarea>
    </label>
  </div>
  <div class="task-picker" data-task-picker>
    <p class="hint">Filter the inventory the same way you would for a view, check the VMs that belong on this task, then assign those VMs to people. Each person only sees the VMs you assign to them.</p>
    <div class="task-tools">
      <select data-task-filter="side" aria-label="VMs">
        <option value="">APP and DB</option>
        <option value="app">APP VMs</option>
        <option value="db">DB VMs</option>
      </select>
      <select data-task-filter="zone_id" aria-label="Environment">
        <?= select_options($zones, 0, 'Any environment') ?>
      </select>
      <select data-task-filter="environment_id" aria-label="Deployment">
        <?= select_options($environments, 0, 'Any deployment') ?>
      </select>
      <select data-task-filter="os_id" aria-label="OS">
        <?= select_options($oses, 0, 'Any OS') ?>
      </select>
      <select data-task-filter="client_id" aria-label="Client">
        <?= select_options($clients, 0, 'Any client') ?>
      </select>
      <select data-task-filter="tag" aria-label="Tag">
        <option value="">Any tag</option>
        <option value="__none__">No tag</option>
        <?php foreach ($tags as $tag): ?>
          <option value="<?= e($tag) ?>"><?= e($tag) ?></option>
        <?php endforeach; ?>
      </select>
      <input data-task-filter="q" type="search" placeholder="Name or IP" aria-label="Search VMs" autocomplete="off">
    </div>
    <div class="task-tools">
      <label class="task-all"><input type="checkbox" data-task-all> Select shown</label>
      <select data-task-assign aria-label="Assign checked VMs to">
        <option value="">Assign checked to</option>
        <?php foreach ($users as $person): ?>
          <option value="<?= (int)$person['id'] ?>"><?= e($person['display_name']) ?><?= trim((string)$person['email']) === '' ? ' · no email' : '' ?><?= (int)$person['active'] !== 1 ? ' · inactive' : '' ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn ghost" type="button" data-task-apply>Assign</button>
      <span class="task-count" data-task-count></span>
    </div>
    <p class="flash error" data-task-client-error hidden></p>
    <div class="task-pick-scroll">
      <div class="task-pick-head">
        <span></span><span>Type</span><span>VM</span><span>Environment</span><span>Deployment</span><span>OS</span><span>Tag</span><span>IP</span><span>Person</span>
      </div>
      <div data-task-list></div>
      <p class="muted task-empty" data-task-empty hidden>No VMs match these filters.</p>
    </div>
    <select id="task-user-template" hidden>
      <option value="">Person</option>
      <?php foreach ($users as $person): ?>
        <option value="<?= (int)$person['id'] ?>"><?= e($person['display_name']) ?><?= trim((string)$person['email']) === '' ? ' · no email' : '' ?><?= (int)$person['active'] !== 1 ? ' · inactive' : '' ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit"><?= $editing ? 'Save task' : 'Create task' ?></button>
    <a class="btn ghost" href="<?= e(url($editing ? '/tasks/' . (int)$task['id'] : '/tasks')) ?>">Cancel</a>
  </div>
</form>
<script type="application/json" id="task-catalog"><?= json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script type="application/json" id="task-selected"><?= json_encode($selected, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
