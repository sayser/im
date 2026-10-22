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
<body class="auth">
  <?php if (!empty($flashMessage)): ?>
    <div class="flash <?= e($flashMessage['type']) ?> auth-flash" role="status"><?= e($flashMessage['message']) ?></div>
  <?php endif; ?>
  <?= $content ?>
</body>
</html>
