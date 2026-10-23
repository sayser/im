<?php $class = mark_swatch_class((string)$mark['swatch']); ?>
<p class="crumb">
  <a href="<?= e(url('/marks')) ?>">Marks</a>
  <span>/</span> <?= e($mark['name']) ?>
</p>

<?php if ($mark['status'] !== 'open'): ?>
  <div class="banner">This mark is closed<?= $mark['closed_at'] ? ' as of ' . e(format_when($mark['closed_at'])) : '' ?><?= !empty($mark['closed_by_name']) ? ' by ' . e($mark['closed_by_name']) : '' ?>. Its servers are no longer colored.</div>
<?php endif; ?>

<section class="mark-frame <?= e($class) ?>">
  <header class="mark-head">
    <div>
      <h2><?= e($mark['name']) ?></h2>
      <?php if ($mark['note'] !== ''): ?><p class="muted"><?= e($mark['note']) ?></p><?php endif; ?>
    </div>
    <span class="mark-chip <?= e($class) ?>"><?= count($frame['apps']) ?> APP · <?= count($frame['dbs']) ?> DB</span>
  </header>
  <?php
    $membersApps = $frame['apps'];
    $membersDbs = $frame['dbs'];
    $markId = (int)$mark['id'];
    $canRemove = can_write();
    require APP_ROOT . '/views/partials/mark_members.php';
  ?>
</section>

<?php if (can_write()): ?>
  <section class="card form">
    <h2>Add servers</h2>
    <?php if ($mark['status'] !== 'open'): ?>
      <p class="muted">Reopen the mark before adding servers.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('/marks/' . (int)$mark['id'])) ?>" data-mark-picker>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <script type="application/json" id="mark-catalog"><?= json_encode($picker, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
        <div class="mark-filters">
          <label class="field">
            <span>VMs</span>
            <select data-mark-kind>
              <option value="both">APP and DB</option>
              <option value="app">APP VMs</option>
              <option value="db">DB VMs</option>
            </select>
          </label>
          <label class="field">
            <span>OS</span>
            <select data-mark-os>
              <?= select_options($oses, 0, 'Any OS') ?>
            </select>
          </label>
          <label class="field">
            <span>Environment</span>
            <select data-mark-zone>
              <?= select_options($zones, 0, 'Any environment') ?>
            </select>
          </label>
          <label class="field">
            <span>Deployment</span>
            <select data-mark-deployment>
              <?= select_options($environments, 0, 'Any deployment') ?>
            </select>
          </label>
          <label class="field">
            <span>Search</span>
            <input data-mark-q maxlength="200" placeholder="Name or IP" autocomplete="off">
          </label>
        </div>
        <div class="field">
          <span>Select VMs</span>
          <div class="mark-picker">
            <button class="mark-picker-btn" type="button" data-mark-toggle>Select VMs</button>
            <div class="mark-picker-menu" data-mark-menu hidden>
              <label class="mark-pick-all">
                <input type="checkbox" data-mark-all>
                <span>Select shown</span>
              </label>
              <div class="mark-pick-scroll">
                <div class="mark-pick mark-pick-head">
                  <span></span>
                  <span>Kind</span>
                  <span>Name</span>
                  <span>OS</span>
                  <span>Env</span>
                  <span>Deploy</span>
                  <span>IP</span>
                </div>
                <div data-mark-list></div>
                <p class="muted mark-pick-empty" data-mark-empty hidden>No VMs match these filters.</p>
              </div>
            </div>
          </div>
        </div>
        <div class="form-actions">
          <button class="btn" type="submit">Add selected VMs</button>
        </div>
      </form>
    <?php endif; ?>
  </section>

  <section class="card form">
    <h2>Mark details</h2>
    <form method="post" action="<?= e(url('/marks/' . (int)$mark['id'])) ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <div class="form-grid">
        <label class="field span-2">
          <span>Name</span>
          <input name="name" required maxlength="120" value="<?= e($mark['name']) ?>">
        </label>
        <div class="field span-2">
          <span>Color</span>
          <div class="swatch-picker">
            <?php foreach ($colors as $color): ?>
              <?php $choice = mark_swatch_class((string)$color['swatch']); ?>
              <label class="swatch-choice <?= e($choice) ?>">
                <input type="radio" name="swatch" value="<?= e($color['swatch']) ?>"<?= $mark['swatch'] === $color['swatch'] ? ' checked' : '' ?> required>
                <span class="swatch-dot <?= e($choice) ?>"></span>
                <?= e($color['name']) ?><?php if ((int)$color['active'] !== 1): ?> (retired)<?php endif; ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="field span-2">
          <span>Note</span>
          <textarea name="note" rows="2" maxlength="500"><?= e($mark['note']) ?></textarea>
        </label>
      </div>
      <div class="form-actions">
        <button class="btn" type="submit">Save mark</button>
      </div>
    </form>
    <div class="form-actions">
      <form method="post" action="<?= e(url('/marks/' . (int)$mark['id'])) ?>">
        <?= csrf_field() ?>
        <?php if ($mark['status'] === 'open'): ?>
          <button class="btn ghost" name="action" value="close" type="submit">Close mark</button>
        <?php else: ?>
          <button class="btn" name="action" value="reopen" type="submit">Reopen mark</button>
        <?php endif; ?>
      </form>
      <form method="post" action="<?= e(url('/marks/' . (int)$mark['id'])) ?>" data-confirm-delete="Delete this mark? The servers stay in the inventory.">
        <?= csrf_field() ?>
        <button class="btn danger ghost" name="action" value="delete" type="submit">Delete mark</button>
      </form>
    </div>
  </section>
<?php endif; ?>
