<?php $editing = $mode === 'edit'; ?>
<p class="crumb"><a href="<?= e(url('/ssl')) ?>">SSL</a> <span>/</span> <?= $editing ? 'Edit' : 'New' ?></p>
<form class="card form narrow" method="post" action="<?= e(url($editing ? '/ssl/' . (int)$cert['id'] : '/ssl')) ?>">
  <?= csrf_field() ?>
  <div class="form-grid">
    <label class="field span-2">
      <span>URL</span>
      <input name="url" type="text" maxlength="500" spellcheck="false" required value="<?= e($input['url']) ?>"<?= invalid_attr($errors, 'url') ?>>
      <?= field_error($errors, 'url') ?>
    </label>
    <label class="field">
      <span>Expiration date</span>
      <input name="expires_on" type="date" required value="<?= e($input['expires_on']) ?>"<?= invalid_attr($errors, 'expires_on') ?>>
      <?= field_error($errors, 'expires_on') ?>
    </label>
  </div>
  <div class="form-actions">
    <button class="btn" type="submit">Save certificate</button>
    <a class="btn ghost" href="<?= e(url($editing ? '/ssl/' . (int)$cert['id'] : '/ssl')) ?>">Cancel</a>
  </div>
</form>
