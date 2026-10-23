<?php if ((int)$stats['client_count'] === 0): ?>
  <div class="banner">
    <?php if (is_admin()): ?>
      Add a client in <a href="<?= e(url('/lists')) ?>">Field options</a> before inventory records can be created.
    <?php else: ?>
      An admin needs to add at least one client before inventory records can be created.
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="dash">
  <section class="dash-grid">
    <?php
      $heading = 'Environments';
      $rows = $stats['zones'];
      $href = '/environments/';
      $appsOnly = false;
      require APP_ROOT . '/views/partials/dash_panel.php';
    ?>
    <article class="card">
      <div class="card-head">
        <h2>Decommissioned</h2>
      </div>
      <a class="decom-count" href="<?= e(url('/decom')) ?>"><?= (int)$stats['decommissioned'] ?></a>
    </article>
  </section>

  <section class="dash-grid">
    <?php
      $blocks = [
          ['Deployments', $stats['environments'], '/app-vms?environment_id=', false],
          ['Applications', $stats['applications'], '/app-vms?application_id=', true],
      ];
    ?>
    <?php foreach ($blocks as [$heading, $rows, $href, $appsOnly]): ?>
      <?php require APP_ROOT . '/views/partials/dash_panel.php'; ?>
    <?php endforeach; ?>
  </section>

  <?php
    $heading = 'Open marks';
    $rows = $stats['marks'];
    $href = '/marks/';
    $appsOnly = false;
    require APP_ROOT . '/views/partials/dash_panel.php';
  ?>

  <section class="card">
    <div class="card-head">
      <h2>Recent changes</h2>
      <a href="<?= e(url('/audit')) ?>">Full audit</a>
    </div>
    <?php if (!$stats['recent']): ?>
      <p class="muted">No changes yet.</p>
    <?php else: ?>
      <ul class="activity">
        <?php foreach ($stats['recent'] as $event): ?>
          <li>
            <a href="<?= e(url('/audit/' . (int)$event['id'])) ?>"><?= e($event['summary']) ?></a>
            <span><?= e($event['display_name'] ?: 'Former user') ?> · <?= e(format_when($event['created_at'])) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
