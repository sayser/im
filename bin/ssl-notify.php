<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/http.php';
require APP_ROOT . '/src/html.php';
require APP_ROOT . '/src/mail.php';
require APP_ROOT . '/src/ssl.php';

try {
    if ((int)db_val("SELECT GET_LOCK('ptim_ssl_mail', 0)") !== 1) {
        fwrite(STDOUT, "Another reminder run is already in progress.\n");
        exit(0);
    }
    try {
        $result = ssl_send_due();
    } finally {
        db_val("SELECT RELEASE_LOCK('ptim_ssl_mail')");
    }
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, 'Sent ' . (int)$result['sent'] . " reminder(s).\n");
foreach ($result['errors'] as $error) {
    fwrite(STDERR, $error . "\n");
}
exit($result['errors'] === [] ? 0 : 1);
