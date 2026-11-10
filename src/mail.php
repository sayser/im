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
        'public_base_url' => rtrim(trim((string)($mail['public_base_url'] ?? '')), '/'),
    ];
}

function send_mail(string $to, string $subject, string $body): ?string
{
    $settings = mail_settings();
    if ($settings['from_address'] === '' || filter_var($settings['from_address'], FILTER_VALIDATE_EMAIL) === false) {
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
        $mail->Timeout = 8;
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
