<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#0e120f">
  <title><?= e($title) ?> · PT Inventory Manager</title>
  <link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
</head>
<body>
  <a class="skip" href="#content">Skip to content</a>
  <div class="scrim" data-nav-scrim></div>
  <div class="shell">
    <aside class="side">
      <a class="brand" href="<?= e(url('/')) ?>">
        <span class="mark">PT</span>
        <span>
          <strong>PT Inventory Manager</strong>
          <small>Client VM inventory</small>
        </span>
      </a>
      <?php
        $locked = !empty($currentUser['must_change_password']);
        $nav = [
            ['dashboard', 'Dashboard', '/'],
            ['inventory', 'Inventory', '/environments'],
            ['decom', 'DECOM', '/decom'],
            ['audit', 'Audit', '/audit'],
        ];
        if (is_admin()) {
            $nav[] = ['users', 'Users', '/users'];
            $nav[] = ['lists', 'Field options', '/lists'];
        }
      ?>
      <?php if (!$locked): ?>
        <nav class="nav" aria-label="Primary">
          <?php foreach ($nav as [$key, $label, $href]): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e(url($href)) ?>" <?= $active === $key ? 'aria-current="page"' : '' ?>>
              <?= icon($key) ?><span><?= e($label) ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      <?php else: ?>
        <p class="side-note">Set a new password to continue.</p>
      <?php endif; ?>
      <div class="side-foot">
        <?php if (!$locked): ?>
          <a class="<?= $active === 'password' ? 'active' : '' ?>" href="<?= e(url('/account/password')) ?>"><?= icon('key') ?><span>Password</span></a>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/logout')) ?>">
          <?= csrf_field() ?>
          <button type="submit"><?= icon('logout') ?><span>Sign out</span></button>
        </form>
      </div>
    </aside>
    <div class="main">
      <header class="top">
        <button class="icon-btn nav-toggle" type="button" data-nav-toggle aria-label="Open menu"><?= icon('menu') ?></button>
        <h1><?= e($title) ?></h1>
        <?php if (!$locked): ?>
          <form class="find" method="get" action="<?= e(url('/search')) ?>" role="search">
            <label class="sr" for="site-search">Search inventory</label>
            <input id="site-search" name="q" type="search" value="<?= e(query_text('q')) ?>" placeholder="Name, URL, or IP" autocomplete="off">
          </form>
        <?php endif; ?>
        <div class="who">
          <span class="who-name"><?= e($currentUser['display_name'] ?? '') ?></span>
          <?= role_badge((string)($currentUser['role'] ?? 'view')) ?>
        </div>
      </header>
      <main id="content" class="content">
        <?php if ($flashMessage): ?>
          <div class="flash <?= e($flashMessage['type']) ?>" role="status"><?= e($flashMessage['message']) ?></div>
        <?php endif; ?>
        <?= $content ?>
      </main>
    </div>
  </div>
  <script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
