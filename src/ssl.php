<?php
declare(strict_types=1);

function ssl_rows(): array
{
    $userId = (int)(auth_user()['id'] ?? 0);
    $rows = db_all(
        'SELECT c.id, c.url, c.expires_on, c.updated_at,
                s.lead_amount, s.lead_unit
         FROM ssl_certs c
         LEFT JOIN ssl_subscriptions s ON s.ssl_cert_id = c.id AND s.user_id = ?
         ORDER BY c.expires_on, c.url, c.id',
        [$userId]
    );
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = (int)$row['id'];
        $rows[$i]['days_left'] = days_until((string)$row['expires_on']);
        $rows[$i]['lead_amount'] = $row['lead_amount'] === null ? null : (int)$row['lead_amount'];
    }
    return $rows;
}

function ssl_find(int $id): ?array
{
    $row = db_one(
        'SELECT c.id, c.url, c.expires_on, c.created_at, c.updated_at,
                cu.display_name AS created_by_name, uu.display_name AS updated_by_name
         FROM ssl_certs c
         LEFT JOIN users cu ON cu.id = c.created_by
         LEFT JOIN users uu ON uu.id = c.updated_by
         WHERE c.id = ?',
        [$id]
    );
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['days_left'] = days_until((string)$row['expires_on']);
    $row['subscription'] = ssl_subscription($row['id'], (int)auth_user()['id']);
    return $row;
}

function ssl_soon(int $withinDays = 28): array
{
    $zone = new DateTimeZone(display_timezone_name());
    $cutoff = (new DateTimeImmutable('today', $zone))->modify('+' . $withinDays . ' days')->format('Y-m-d');
    $rows = db_all(
        'SELECT id, url, expires_on FROM ssl_certs WHERE expires_on <= ? ORDER BY expires_on, url, id',
        [$cutoff]
    );
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = (int)$row['id'];
        $rows[$i]['days_left'] = days_until((string)$row['expires_on']);
    }
    return $rows;
}

function ssl_subscription(int $certId, int $userId): ?array
{
    $row = db_one(
        'SELECT id, lead_amount, lead_unit FROM ssl_subscriptions WHERE ssl_cert_id = ? AND user_id = ?',
        [$certId, $userId]
    );
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['lead_amount'] = (int)$row['lead_amount'];
    return $row;
}

function days_until(string $day): int
{
    $zone = new DateTimeZone(display_timezone_name());
    $today = new DateTimeImmutable('today', $zone);
    $expires = DateTimeImmutable::createFromFormat('!Y-m-d', substr($day, 0, 10), $zone);
    if (!$expires) {
        return 0;
    }
    return (int)$today->diff($expires)->format('%r%a');
}

function ssl_input_from_post(): array
{
    return [
        'url' => clean_line((string)($_POST['url'] ?? '')),
        'expires_on' => clean_line((string)($_POST['expires_on'] ?? '')),
    ];
}

function validate_ssl(array $in): array
{
    $errors = [];
    if ($message = invalid_url((string)$in['url'])) {
        $errors['url'] = $message ?? 'Enter a URL.';
    } elseif ($in['url'] === '') {
        $errors['url'] = 'Enter a URL.';
    }
    $day = (string)$in['expires_on'];
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day);
    if (!$parsed || $parsed->format('Y-m-d') !== $day) {
        $errors['expires_on'] = 'Enter the expiration date.';
    }
    return $errors;
}

function ssl_save(?int $id, array $in): int
{
    $actor = (int)auth_user()['id'];
    if ($id === null) {
        $newId = db_insert(
            'INSERT INTO ssl_certs (url, expires_on, created_by, updated_by) VALUES (?,?,?,?)',
            [$in['url'], $in['expires_on'], $actor, $actor]
        );
        audit_log('ssl.create', 'Added SSL certificate ' . $in['url'], 'ssl_cert', $newId);
        return $newId;
    }
    $existing = ssl_find($id);
    if (!$existing) {
        abort(404);
    }
    if ($existing['url'] === $in['url'] && substr((string)$existing['expires_on'], 0, 10) === $in['expires_on']) {
        return $id;
    }
    db_exec(
        'UPDATE ssl_certs SET url = ?, expires_on = ?, updated_by = ? WHERE id = ?',
        [$in['url'], $in['expires_on'], $actor, $id]
    );
    audit_log('ssl.update', 'Updated SSL certificate ' . $in['url'], 'ssl_cert', $id);
    return $id;
}

function ssl_delete(int $id): void
{
    $existing = ssl_find($id);
    if (!$existing) {
        abort(404);
    }
    db_exec('DELETE FROM ssl_certs WHERE id = ?', [$id]);
    audit_log('ssl.delete', 'Deleted SSL certificate ' . $existing['url'], 'ssl_cert', $id);
}

function ssl_lead_problem(int $amount, string $unit): ?string
{
    if (!in_array($unit, ['day', 'week'], true)) {
        return 'Choose days or weeks.';
    }
    $max = $unit === 'week' ? 52 : 365;
    if ($amount < 1 || $amount > $max) {
        return $unit === 'week' ? 'Use 1 to 52 weeks.' : 'Use 1 to 365 days.';
    }
    return null;
}

function ssl_subscribe(int $certId, int $userId, int $amount, string $unit): ?string
{
    if (!ssl_find($certId)) {
        abort(404);
    }
    if ($message = ssl_lead_problem($amount, $unit)) {
        return $message;
    }
    $email = user_email($userId);
    if ($email === '') {
        return 'Add your email address on the Account page before you can get reminders.';
    }
    db_exec(
        'INSERT INTO ssl_subscriptions (ssl_cert_id, user_id, lead_amount, lead_unit, last_notified_for)
         VALUES (?,?,?,?,NULL)
         ON DUPLICATE KEY UPDATE lead_amount = VALUES(lead_amount), lead_unit = VALUES(lead_unit), last_notified_for = NULL',
        [$certId, $userId, $amount, $unit]
    );
    audit_log('ssl.subscribe', 'Saved an SSL reminder', 'ssl_cert', $certId);
    return null;
}

function ssl_due_notices(): array
{
    return db_all(
        'SELECT s.id, s.lead_amount, s.lead_unit, s.last_notified_for,
                c.id AS cert_id, c.url, c.expires_on, u.email, u.display_name
         FROM ssl_subscriptions s
         JOIN ssl_certs c ON c.id = s.ssl_cert_id
         JOIN users u ON u.id = s.user_id AND u.active = 1
         WHERE u.email IS NOT NULL AND u.email <> \'\''
    );
}

function ssl_send_due(): array
{
    $sent = 0;
    $errors = [];
    foreach (ssl_due_notices() as $row) {
        $expires = substr((string)$row['expires_on'], 0, 10);
        if ((string)$row['last_notified_for'] === $expires) {
            continue;
        }
        $leadDays = (string)$row['lead_unit'] === 'week' ? (int)$row['lead_amount'] * 7 : (int)$row['lead_amount'];
        if (days_until($expires) > $leadDays) {
            continue;
        }
        $error = send_mail((string)$row['email'], ssl_subject((string)$row['url'], $expires), ssl_body($row, $expires, $leadDays));
        if ($error !== null) {
            $errors[] = (string)$row['email'] . ': ' . $error;
            continue;
        }
        db_exec('UPDATE ssl_subscriptions SET last_notified_for = ? WHERE id = ?', [$expires, (int)$row['id']]);
        $sent++;
    }
    return ['sent' => $sent, 'errors' => $errors];
}

function ssl_unsubscribe(int $certId, int $userId): void
{
    db_exec('DELETE FROM ssl_subscriptions WHERE ssl_cert_id = ? AND user_id = ?', [$certId, $userId]);
    audit_log('ssl.unsubscribe', 'Removed an SSL reminder', 'ssl_cert', $certId);
}

function ssl_subject(string $url, string $expires): string
{
    return 'SSL certificate expires ' . format_day($expires) . ' for ' . $url;
}

function ssl_body(array $row, string $expires, int $leadDays): string
{
    $left = days_until($expires);
    $when = $left < 0 ? 'expired ' . abs($left) . ' days ago' : ($left === 0 ? 'expires today' : 'expires in ' . $left . ' days');
    $lead = (int)$row['lead_amount'] . ' ' . ((string)$row['lead_unit'] === 'week' ? 'week' : 'day') . ((int)$row['lead_amount'] === 1 ? '' : 's');
    $lines = [
        'Hello ' . (string)$row['display_name'] . ',',
        '',
        'The certificate for ' . (string)$row['url'] . ' ' . $when . ' (' . format_day($expires) . ').',
        'You asked for a reminder ' . $lead . ' before it expires.',
    ];
    $base = mail_settings()['public_base_url'];
    if ($base !== '') {
        $lines[] = '';
        $lines[] = $base . '/ssl/' . (int)$row['cert_id'];
    }
    return implode("\n", $lines);
}
