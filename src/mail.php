<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once APP_ROOT . '/lib/PHPMailer/Exception.php';
require_once APP_ROOT . '/lib/PHPMailer/PHPMailer.php';
require_once APP_ROOT . '/lib/PHPMailer/SMTP.php';

function mail_settings(): array
{
    $mail = app_config()['mail'] ?? [];
    if (!is_array($mail)) {
        $mail = [];
    }
    return [
        'host' => (string)($mail['host'] ?? '127.0.0.1'),
        'port' => (int)($mail['port'] ?? 25),
        'from_address' => trim((string)($mail['from_address'] ?? '')),
        'from_name' => trim((string)($mail['from_name'] ?? 'PT Inventory Manager')),
    ];
}

function mail_ready(): bool
{
    $settings = mail_settings();
    return $settings['from_address'] !== '' && filter_var($settings['from_address'], FILTER_VALIDATE_EMAIL) !== false;
}

function send_mail(string $to, string $subject, string $body, int $timeout = 8): ?string
{
    $settings = mail_settings();
    if (!mail_ready()) {
        return 'Set mail.from_address in config.php before email can be sent.';
    }
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return 'That email address is not valid.';
    }
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $settings['host'];
        $mail->Port = $settings['port'] > 0 ? $settings['port'] : 25;
        $mail->SMTPAuth = false;
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = max(1, $timeout);
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($settings['from_address'], $settings['from_name']);
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
    } catch (Exception) {
        return 'The mail server did not accept the message.';
    }
    return null;
}

function queue_mail(string $to, string $subject, string $body): int
{
    return db_insert(
        'INSERT INTO mail_outbox (recipient, subject, body) VALUES (?,?,?)',
        [mb_substr($to, 0, 254), mb_substr($subject, 0, 255), $body]
    );
}

function deliver_outbox(int $limit = 20, float $budgetSeconds = 3.0): array
{
    $limit = max(1, min(100, $limit));
    if ((int)db_val("SELECT GET_LOCK('ptim_mail_outbox', 0)") !== 1) {
        return ['sent' => 0, 'failed' => 0, 'deferred' => true];
    }
    try {
        $rows = db_all(
            'SELECT id, recipient, subject, body FROM mail_outbox
             WHERE sent_at IS NULL AND attempts < 12
             ORDER BY id
             LIMIT ' . $limit
        );
        $started = microtime(true);
        $sent = 0;
        $failed = 0;
        foreach ($rows as $row) {
            if ((microtime(true) - $started) >= $budgetSeconds) {
                break;
            }
            $error = send_mail((string)$row['recipient'], (string)$row['subject'], (string)$row['body'], 2);
            if ($error === null) {
                db_exec(
                    'UPDATE mail_outbox SET sent_at = UTC_TIMESTAMP(), last_error = NULL WHERE id = ?',
                    [(int)$row['id']]
                );
                $sent++;
                continue;
            }
            $failed++;
            if ($error === 'Set mail.from_address in config.php before email can be sent.') {
                break;
            }
            db_exec(
                'UPDATE mail_outbox SET attempts = attempts + 1, last_error = ? WHERE id = ?',
                [mb_substr($error, 0, 255), (int)$row['id']]
            );
            break;
        }
        return ['sent' => $sent, 'failed' => $failed, 'deferred' => false];
    } finally {
        db_val("SELECT RELEASE_LOCK('ptim_mail_outbox')");
    }
}

function mail_delivery_notes(): array
{
    $result = deliver_outbox(20, 2.5);
    if ($result['deferred']) {
        return ['Email will be sent shortly.'];
    }
    if ($result['failed'] > 0) {
        return ['Email could not be sent right away. It will be retried.'];
    }
    return [];
}
