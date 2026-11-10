<div class="page-bar">
  <p class="lede">Track certificate expiration dates. Add your email on the Account page, then choose how far ahead you want a reminder.</p>
  <?php if (can_write()): ?>
    <a class="btn" href="<?= e(url('/ssl/new')) ?>">Add certificate</a>
  <?php endif; ?>
</div>
<?php if (!$rows): ?>
  <div class="empty">
    <h2>No certificates yet</h2>
  </div>
<?php else: ?>
  <div class="table-wrap">
    <table class="grid">
      <thead>
        <tr><th>URL</th><th>Expires</th><th>Days left</th><th>Your reminder</th></tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <?php $left = (int)$row['days_left']; ?>
          <tr>
            <td><a href="<?= e(url('/ssl/' . (int)$row['id'])) ?>"><?= e($row['url']) ?></a></td>
            <td class="nowrap"><?= e(format_day($row['expires_on'])) ?></td>
            <td><?= $left < 0 ? 'Expired' : (string)$left ?></td>
            <td>
              <?php if ($row['lead_amount'] === null): ?>
                <span class="muted">Not watching</span>
              <?php else: ?>
                <?= (int)$row['lead_amount'] ?> <?= e((string)$row['lead_unit']) ?><?= (int)$row['lead_amount'] === 1 ? '' : 's' ?> before
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
