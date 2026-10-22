<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Setup · PT Inventory Manager</title>
  <link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="auth">
  <main class="auth-card">
    <span class="mark">PT</span>
    <h1>PT Inventory Manager needs setup</h1>
    <?php if ($reason === 'missing-config'): ?>
      <p>Copy <code>config/config.example.php</code> to <code>config/config.php</code> and set the MySQL host, database, user, and password.</p>
    <?php elseif ($reason === 'bad-config'): ?>
      <p><code>config/config.php</code> must return an array with <code>db.name</code> and <code>db.user</code>. Hostnames and database names can use letters, numbers, underscore, dot, colon, and hyphen.</p>
    <?php elseif ($reason === 'extensions'): ?>
      <p>Enable the PHP 8.3 extensions <code>pdo_mysql</code> and <code>mbstring</code>, then reload php-fpm.</p>
    <?php elseif ($reason === 'schema'): ?>
      <p>The database is reachable, but the tables are missing. Import <code>sql/schema.sql</code> into the database named in the config.</p>
    <?php elseif ($reason === 'https'): ?>
      <p>Set <code>cookie_secure</code> to <code>true</code> and serve the site over HTTPS. With <code>debug</code> off, the app will not start a session on plain HTTP.</p>
    <?php else: ?>
      <p>Cannot connect to MySQL. Check the host, port, database name, user, and password in <code>config/config.php</code>. The PHP error log has the driver message.</p>
    <?php endif; ?>
  </main>
</body>
</html>
