<?php
declare(strict_types=1);

// SSL reminders, plus any task mail that could not be sent during the request.
// Safe to run often; a reminder is sent once per certificate expiration.
// */15 * * * * php /var/www/pt-inventory/bin/ssl-notify.php
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
$queued = deliver_outbox(50, 20);
fwrite(STDOUT, 'Sent ' . (int)$queued['sent'] . " queued message(s).\n");
if ($queued['failed'] > 0) {
    fwrite(STDERR, "Queued mail is still waiting.\n");
}
exit($result['errors'] === [] && $queued['failed'] === 0 ? 0 : 1);
