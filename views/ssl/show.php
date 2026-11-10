<?php
$sub = $cert['subscription'];
$left = (int)$cert['days_left'];
$amount = $sub['lead_amount'] ?? 30;
$unit = $sub['lead_unit'] ?? 'day';
?>
<p class="crumb"><a href="<?= e(url('/ssl')) ?>">SSL</a> <span>/</span> Certificate</p>
<?php if (can_write()): ?>
  <div class="page-actions detail-actions">
    <a class="btn" href="<?= e(url('/ssl/' . (int)$cert['id'] . '/edit')) ?>">Edit</a>
    <form method="post" action="<?= e(url('/ssl/' . (int)$cert['id'])) ?>" data-confirm-delete="Delete this certificate? Reminders for it are removed too.">
      <?= csrf_field() ?>
      <button class="btn danger ghost" name="action" value="delete" type="submit">Delete</button>
    </form>
  </div>
<?php endif; ?>
<section class="card">
  <h2>Certificate</h2>
  <dl class="facts">
    <div><dt>URL</dt><dd><?= full_link($cert['url']) ?></dd></div>
    <div><dt>Expires</dt><dd><?= e(format_day($cert['expires_on'])) ?></dd></div>
    <div><dt>Days left</dt><dd><?= $left < 0 ? 'Expired' : (string)$left ?></dd></div>
  </dl>
</section>
<section class="card">
  <h2>Your reminder</h2>
  <?php if (user_email((int)$currentUser['id']) === ''): ?>
    <p class="muted">Add your email on the <a href="<?= e(url('/account/password')) ?>">Account</a> page before a reminder can be sent.</p>
  <?php endif; ?>
  <form class="form" method="post" action="<?= e(url('/ssl/' . (int)$cert['id'])) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="subscribe">
    <div class="form-grid">
      <label class="field">
        <span>Notify me</span>
        <input name="lead_amount" type="number" min="1" max="365" required value="<?= (int)$amount ?>"<?= invalid_attr(['lead' => $leadError], 'lead') ?>>
      </label>
      <label class="field">
        <span>Before expiration</span>
        <select name="lead_unit">
          <option value="day"<?= $unit === 'day' ? ' selected' : '' ?>>Days</option>
          <option value="week"<?= $unit === 'week' ? ' selected' : '' ?>>Weeks</option>
        </select>
      </label>
    </div>
    <p class="hint">A daily job emails you when this certificate is inside that window.</p>
    <?= field_error(['lead' => $leadError], 'lead') ?>
    <div class="form-actions">
      <button class="btn" type="submit"><?= $sub ? 'Update reminder' : 'Watch this certificate' ?></button>
    </div>
  </form>
  <?php if ($sub): ?>
    <form method="post" action="<?= e(url('/ssl/' . (int)$cert['id'])) ?>">
      <?= csrf_field() ?>
      <button class="btn ghost" name="action" value="unsubscribe" type="submit">Stop reminding me</button>
    </form>
  <?php endif; ?>
</section>
