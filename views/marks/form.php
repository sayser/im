<form class="card form narrow" method="post" action="<?= e(url('/marks')) ?>">
  <?= csrf_field() ?>
  <?php if ($formError !== ''): ?>
    <div class="flash error"><?= e($formError) ?></div>
  <?php endif; ?>
  <div class="form-grid">
    <label class="field span-2">
      <span>Name</span>
      <input name="name" required maxlength="120" value="<?= e($input['name']) ?>">
    </label>
    <div class="field span-2">
      <span>Color</span>
      <?php if (!$colors): ?>
        <p class="muted">An admin needs to restore a mark color in Field options.</p>
      <?php else: ?>
        <div class="swatch-picker">
          <?php foreach ($colors as $color): ?>
            <?php $class = mark_swatch_class((string)$color['swatch']); ?>
            <label class="swatch-choice <?= e($class) ?>">
              <input type="radio" name="swatch" value="<?= e($color['swatch']) ?>"<?= $input['swatch'] === $color['swatch'] ? ' checked' : '' ?> required>
              <span class="swatch-dot <?= e($class) ?>"></span>
              <?= e($color['name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <label class="field span-2">
      <span>Note</span>
      <textarea name="note" rows="3" maxlength="500" placeholder="What this group of servers needs"><?= e($input['note']) ?></textarea>
    </label>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">Create mark</button>
    <a class="btn ghost" href="<?= e(url('/marks')) ?>">Cancel</a>
  </div>
</form>
