<?php
$appsOnly = !empty($appsOnly);
$zoneLinks = str_starts_with($href, '/environments/');
?>
<article class="card">
  <div class="card-head">
    <h2><?= e($heading) ?></h2>
  </div>
  <?php if (!$rows): ?>
    <p class="muted">Nothing to count yet.</p>
  <?php else: ?>
    <ul class="meter">
      <?php foreach ($rows as $row): ?>
        <?php
          $appCount = (int)$row['app_count'];
          $dbCount = (int)($row['db_count'] ?? 0);
          $appHref = url($href . (int)$row['id']);
          $dbHref = url(str_replace('/app-vms?', '/db-vms?', $href) . (int)$row['id']);
        ?>
        <li>
          <a href="<?= e($appHref) ?>"><?= e($row['name']) ?></a>
          <?php if ($appsOnly): ?>
            <b><?= $appCount ?></b>
          <?php elseif ($zoneLinks): ?>
            <b><?= $appCount ?> APP · <?= $dbCount ?> DB</b>
          <?php else: ?>
            <b>
              <a href="<?= e($appHref) ?>"><?= $appCount ?> APP</a>
              <a href="<?= e($dbHref) ?>"><?= $dbCount ?> DB</a>
            </b>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</article>
