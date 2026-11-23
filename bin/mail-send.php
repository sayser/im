<?php
declare(strict_types=1);

// Retries task mail that could not be sent during the request.
// */5 * * * * php /var/www/pt-inventory/bin/mail-send.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/http.php';
require APP_ROOT . '/src/html.php';
require APP_ROOT . '/src/mail.php';

try {
    $result = deliver_outbox(50, 20);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, 'Sent ' . (int)$result['sent'] . " queued message(s).\n");
if ($result['deferred']) {
    fwrite(STDOUT, "Another mail run is already in progress.\n");
}
exit($result['failed'] > 0 ? 1 : 0);
