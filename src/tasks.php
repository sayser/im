<?php
declare(strict_types=1);

function task_percent(int $done, int $total): int
{
    if ($total <= 0) {
        return 0;
    }
    return (int)round($done * 100 / $total);
}

function task_excerpt(string $text): string
{
    $flat = preg_replace('/\s+/u', ' ', trim($text));
    $flat = $flat ?? '';
    if (mb_strlen($flat) <= 160) {
        return $flat;
    }
    return rtrim(mb_substr($flat, 0, 157)) . '...';
}

function task_find(int $id): ?array
{
    $row = db_one(
        'SELECT t.id, t.title, t.description, t.created_by, t.created_at, t.updated_at, t.archived_at, u.display_name AS creator_name
         FROM tasks t
         JOIN users u ON u.id = t.created_by
         WHERE t.id = ?',
        [$id]
    );
    return $row ?: null;
}

function task_can_see(array $task, int $userId): bool
{
    if ((int)$task['created_by'] === $userId) {
        return true;
    }
    return (int)db_val(
        'SELECT COUNT(*) FROM task_items WHERE task_id = ? AND assigned_user_id = ?',
        [(int)$task['id'], $userId]
    ) > 0;
}

function task_user_options(array $includeIds = []): array
{
    $includeIds = array_values(array_unique(array_filter(array_map('intval', $includeIds), static fn(int $id): bool => $id > 0)));
    if (!$includeIds) {
        return db_all(
            'SELECT id, display_name, username, email, active FROM users WHERE active = 1 ORDER BY display_name, username'
        );
    }
    return db_all(
        'SELECT id, display_name, username, email, active FROM users WHERE active = 1 OR id IN (' . placeholders($includeIds) . ') ORDER BY display_name, username',
        $includeIds
    );
}

function task_tag_names(): array
{
    $names = [];
    foreach (db_all('SELECT DISTINCT name FROM stacks ORDER BY name') as $row) {
        $name = (string)$row['name'];
        if ($name !== '') {
            $names[] = $name;
        }
    }
    return $names;
}

function task_catalog(int $taskId): array
{
    $keep = ['app' => [], 'db' => []];
    if ($taskId > 0) {
        foreach (db_all('SELECT vm_side, vm_id FROM task_items WHERE task_id = ?', [$taskId]) as $row) {
            $side = (string)$row['vm_side'];
            if (isset($keep[$side])) {
                $keep[$side][] = (int)$row['vm_id'];
            }
        }
    }
    $out = [];
    foreach (['app' => 'app_vms', 'db' => 'db_vms'] as $side => $table) {
        $where = "v.lifecycle = 'active'";
        $params = [];
        if ($keep[$side]) {
            $where = '(' . $where . ' OR v.id IN (' . placeholders($keep[$side]) . '))';
            $params = $keep[$side];
        }
        $rows = db_all(
            'SELECT v.id, v.name, v.internal_ip, v.lifecycle, v.os_id, v.zone_id, v.environment_id, v.client_id,
                    o.name AS os_name, z.name AS zone_name, e.name AS environment_name, c.name AS client_name,
                    COALESCE(s.name, \'\') AS stack_name
             FROM ' . $table . ' v
             JOIN lookups o ON o.id = v.os_id
             JOIN lookups z ON z.id = v.zone_id
             JOIN lookups e ON e.id = v.environment_id
             JOIN lookups c ON c.id = v.client_id
             LEFT JOIN stacks s ON s.id = v.stack_id
             WHERE ' . $where . '
             ORDER BY v.name, v.id',
            $params
        );
        foreach ($rows as $vm) {
            $out[] = [
                'side' => $side,
                'id' => (int)$vm['id'],
                'name' => (string)$vm['name'],
                'os_id' => (int)$vm['os_id'],
                'zone_id' => (int)$vm['zone_id'],
                'environment_id' => (int)$vm['environment_id'],
                'client_id' => (int)$vm['client_id'],
                'tag' => (string)$vm['stack_name'],
                'os' => (string)$vm['os_name'],
                'zone' => (string)$vm['zone_name'],
                'deployment' => (string)$vm['environment_name'],
                'client' => (string)$vm['client_name'],
                'ip' => (string)$vm['internal_ip'],
                'lifecycle' => (string)$vm['lifecycle'],
            ];
        }
    }
    usort($out, static function (array $a, array $b): int {
        return strcasecmp($a['name'], $b['name']) ?: ($a['id'] <=> $b['id']);
    });
    return $out;
}

function task_parse_items(mixed $raw): array
{
    if (!is_array($raw)) {
        return ['error' => 'Select at least one VM and assign each one to a person.', 'items' => []];
    }
    $items = [];
    $seen = [];
    foreach ($raw as $token) {
        if (preg_match('/^(app|db):([1-9][0-9]*):([1-9][0-9]*)$/', (string)$token, $match) !== 1) {
            return ['error' => 'One of the selected VMs is not valid.', 'items' => $items];
        }
        $key = $match[1] . ':' . $match[2];
        if (isset($seen[$key])) {
            return ['error' => 'Each VM can only be assigned to one person on a task.', 'items' => $items];
        }
        $seen[$key] = true;
        $items[] = [
            'side' => $match[1],
            'vm_id' => (int)$match[2],
            'user_id' => (int)$match[3],
        ];
    }
    if (!$items) {
        return ['error' => 'Select at least one VM and assign each one to a person.', 'items' => []];
    }
    if (count($items) > 500) {
        return ['error' => 'A task can include up to 500 VMs.', 'items' => []];
    }
    return ['error' => null, 'items' => $items];
}

function task_items_input(int $taskId): array
{
    $items = [];
    foreach (db_all('SELECT vm_side, vm_id, assigned_user_id FROM task_items WHERE task_id = ? ORDER BY id', [$taskId]) as $row) {
        $items[] = [
            'side' => (string)$row['vm_side'],
            'vm_id' => (int)$row['vm_id'],
            'user_id' => (int)$row['assigned_user_id'],
        ];
    }
    return $items;
}

function task_validate(string $title, string $description, array $items, array $existingUserIds, array $existingVmKeys): ?string
{
    if ($title === '' || mb_strlen($title) > 160) {
        return 'Enter a name up to 160 characters.';
    }
    if ($description === '' || mb_strlen($description) > 4000) {
        return 'Enter a description up to 4000 characters.';
    }
    $appIds = [];
    $dbIds = [];
    $userIds = [];
    foreach ($items as $item) {
        if ($item['side'] === 'app') {
            $appIds[] = $item['vm_id'];
        } else {
            $dbIds[] = $item['vm_id'];
        }
        $userIds[] = $item['user_id'];
    }
    $allowedUsers = [];
    foreach (task_user_options($existingUserIds) as $user) {
        $allowedUsers[(int)$user['id']] = (int)$user['active'] === 1;
    }
    foreach ($userIds as $userId) {
        if (!array_key_exists($userId, $allowedUsers)) {
            return 'Choose a person who can sign in.';
        }
        if (!$allowedUsers[$userId] && !in_array($userId, $existingUserIds, true)) {
            return 'Choose a person who can sign in.';
        }
    }
    $found = [];
    foreach (['app' => $appIds, 'db' => $dbIds] as $side => $ids) {
        $ids = array_values(array_unique($ids));
        if (!$ids) {
            continue;
        }
        $table = $side === 'app' ? 'app_vms' : 'db_vms';
        foreach (db_all('SELECT id, lifecycle FROM ' . $table . ' WHERE id IN (' . placeholders($ids) . ')', $ids) as $row) {
            $key = $side . ':' . (int)$row['id'];
            if ((string)$row['lifecycle'] !== 'active' && !isset($existingVmKeys[$key])) {
                return 'Only active VMs can be added to a task.';
            }
            $found[$key] = true;
        }
    }
    foreach ($items as $item) {
        $key = $item['side'] . ':' . $item['vm_id'];
        if (!isset($found[$key])) {
            return 'One of the selected VMs is no longer available.';
        }
    }
    return null;
}

function task_assignment_state(int $taskId): array
{
    $done = [];
    $byUser = [];
    foreach (db_all('SELECT vm_side, vm_id, assigned_user_id, completed_at FROM task_items WHERE task_id = ?', [$taskId]) as $row) {
        $vm = $row['vm_side'] . ':' . (int)$row['vm_id'];
        $userId = (int)$row['assigned_user_id'];
        $done[$vm . ':' . $userId] = $row['completed_at'];
        $byUser[$userId][] = $vm;
    }
    foreach ($byUser as &$list) {
        sort($list);
    }
    unset($list);
    return ['done' => $done, 'by_user' => $byUser];
}

function task_item_rows(int $taskId): array
{
    return task_with_urls(db_all(
        'SELECT i.id, i.vm_side, i.vm_id, i.assigned_user_id, i.completed_at, u.display_name, u.email,
                s.completed_at AS user_completed_at,
                a.name AS app_name, a.internal_ip AS app_ip, a.url AS app_url, a.lifecycle AS app_lifecycle, az.name AS app_zone,
                d.name AS db_name, d.internal_ip AS db_ip, d.lifecycle AS db_lifecycle, dz.name AS db_zone
         FROM task_items i
         JOIN users u ON u.id = i.assigned_user_id
         LEFT JOIN task_user_status s ON s.task_id = i.task_id AND s.user_id = i.assigned_user_id
         LEFT JOIN app_vms a ON i.vm_side = \'app\' AND a.id = i.vm_id
         LEFT JOIN lookups az ON az.id = a.zone_id
         LEFT JOIN db_vms d ON i.vm_side = \'db\' AND d.id = i.vm_id
         LEFT JOIN lookups dz ON dz.id = d.zone_id
         WHERE i.task_id = ?
         ORDER BY u.display_name, u.id, COALESCE(a.name, d.name), i.id',
        [$taskId]
    ));
}

function task_with_urls(array $rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        if ((string)$row['vm_side'] === 'app') {
            $ids[] = (int)$row['vm_id'];
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    $byVm = [];
    if ($ids) {
        foreach (db_all(
            'SELECT app_vm_id, url FROM app_vm_urls WHERE app_vm_id IN (' . placeholders($ids) . ') ORDER BY sort_order, id',
            $ids
        ) as $urlRow) {
            $url = trim((string)$urlRow['url']);
            if ($url !== '') {
                $byVm[(int)$urlRow['app_vm_id']][] = $url;
            }
        }
    }
    foreach ($rows as $i => $row) {
        $urls = [];
        if ((string)$row['vm_side'] === 'app') {
            $urls = $byVm[(int)$row['vm_id']] ?? [];
            $fallback = trim((string)($row['app_url'] ?? ''));
            if (!$urls && $fallback !== '') {
                $urls = [$fallback];
            }
        }
        $rows[$i]['urls'] = $urls;
    }
    return $rows;
}

function task_assignment_message(string $name, string $title, string $description, int $count): string
{
    return "Hello " . $name . ",\n\n"
        . "You were assigned a task.\n\n"
        . $title . "\n\n"
        . $description . "\n\n"
        . "VMs assigned: " . $count . "\n";
}

function task_completed_message(string $name, string $title, array $assigneeNames): string
{
    $lines = [
        'Hello ' . $name . ',',
        '',
        'This task is completed.',
        '',
        $title,
        '',
        count($assigneeNames) === 1 ? 'Assigned user:' : 'Assigned users:',
    ];
    foreach ($assigneeNames as $assigneeName) {
        $lines[] = '- ' . $assigneeName;
    }
    return implode("\n", $lines) . "\n";
}

function task_notify(int $taskId, array $beforeByUser, array $afterByUser): array
{
    $task = task_find($taskId);
    if (!$task) {
        return [];
    }
    $fresh = [];
    foreach (array_unique(array_merge(array_keys($beforeByUser), array_keys($afterByUser))) as $userId) {
        $userId = (int)$userId;
        $old = $beforeByUser[$userId] ?? [];
        $new = $afterByUser[$userId] ?? [];
        if ($new && !$old) {
            $fresh[$userId] = true;
        }
    }
    $userIds = array_keys($fresh);
    if (!$userIds) {
        return [];
    }
    if (!mail_ready()) {
        return ['Email could not be sent because the mail settings are incomplete.'];
    }
    $people = [];
    foreach (db_all(
        'SELECT id, display_name, email FROM users WHERE id IN (' . placeholders($userIds) . ')',
        $userIds
    ) as $user) {
        $people[(int)$user['id']] = $user;
    }
    $notes = [];
    $queued = false;
    foreach ($userIds as $userId) {
        $person = $people[$userId] ?? null;
        $name = $person ? (string)$person['display_name'] : 'that person';
        $email = $person ? trim((string)$person['email']) : '';
        if ($email === '') {
            $notes[] = 'No email was sent to ' . $name . ' because that account has no email address.';
            continue;
        }
        $count = count($afterByUser[$userId] ?? []);
        if ($count < 1) {
            continue;
        }
        queue_mail($email, 'Task: ' . (string)$task['title'], task_assignment_message($name, (string)$task['title'], (string)$task['description'], $count));
        $queued = true;
    }
    if ($queued) {
        $notes = array_merge($notes, mail_delivery_notes());
    }
    return $notes;
}

function task_save(?int $id, int $actorId, string $title, string $description, array $items): array
{
    $title = clean_line($title);
    $description = clean_multiline($description);
    $existing = null;
    $existingUserIds = [];
    $existingVmKeys = [];
    $before = ['done' => [], 'by_user' => []];
    if ($id !== null) {
        $existing = task_find($id);
        if (!$existing || (int)$existing['created_by'] !== $actorId) {
            return ['error' => 'That task is no longer available.', 'id' => 0, 'mail' => []];
        }
        $before = task_assignment_state($id);
        $existingUserIds = array_map('intval', array_keys($before['by_user']));
        foreach ($before['by_user'] as $vms) {
            foreach ($vms as $key) {
                $existingVmKeys[$key] = true;
            }
        }
    }
    if ($message = task_validate($title, $description, $items, $existingUserIds, $existingVmKeys)) {
        return ['error' => $message, 'id' => 0, 'mail' => []];
    }
    $taskId = db_tx(function () use ($existing, $actorId, $title, $description, $items, $before): int {
        if (!$existing) {
            $taskId = db_insert(
                'INSERT INTO tasks (title, description, created_by) VALUES (?,?,?)',
                [$title, $description, $actorId]
            );
            audit_log('task.create', 'Created task ' . $title, 'task', $taskId);
        } else {
            $taskId = (int)$existing['id'];
            db_exec(
                'UPDATE tasks SET title = ?, description = ? WHERE id = ? AND created_by = ?',
                [$title, $description, $taskId, $actorId]
            );
            audit_log('task.update', 'Updated task ' . $title, 'task', $taskId);
            db_exec('DELETE FROM task_items WHERE task_id = ?', [$taskId]);
        }
        $keptStatus = [];
        if ($existing) {
            foreach (db_all('SELECT user_id, completed_at FROM task_user_status WHERE task_id = ?', [$taskId]) as $row) {
                $keptStatus[(int)$row['user_id']] = $row['completed_at'];
            }
        }
        $userIds = [];
        foreach ($items as $item) {
            $keep = $before['done'][$item['side'] . ':' . $item['vm_id'] . ':' . $item['user_id']] ?? null;
            db_exec(
                'INSERT INTO task_items (task_id, vm_side, vm_id, assigned_user_id, completed_at) VALUES (?,?,?,?,?)',
                [$taskId, $item['side'], $item['vm_id'], $item['user_id'], $keep]
            );
            $userIds[(int)$item['user_id']] = true;
        }
        db_exec('DELETE FROM task_user_status WHERE task_id = ?', [$taskId]);
        foreach (array_keys($userIds) as $userId) {
            db_exec(
                'INSERT INTO task_user_status (task_id, user_id, completed_at) VALUES (?,?,?)',
                [$taskId, $userId, $keptStatus[$userId] ?? null]
            );
        }
        task_clear_completion_notice($taskId);
        return $taskId;
    });
    $after = task_assignment_state($taskId);
    return [
        'error' => null,
        'id' => $taskId,
        'mail' => task_notify($taskId, $before['by_user'], $after['by_user']),
    ];
}

function task_set_archived(int $id, int $actorId, bool $archived): bool
{
    $task = task_find($id);
    if (!$task || (int)$task['created_by'] !== $actorId) {
        return false;
    }
    $already = $task['archived_at'] !== null && $task['archived_at'] !== '';
    if ($archived === $already) {
        return true;
    }
    if ($archived) {
        db_exec('UPDATE tasks SET archived_at = UTC_TIMESTAMP() WHERE id = ? AND created_by = ?', [$id, $actorId]);
        audit_log('task.archive', 'Archived task ' . $task['title'], 'task', $id);
    } else {
        db_exec('UPDATE tasks SET archived_at = NULL WHERE id = ? AND created_by = ?', [$id, $actorId]);
        audit_log('task.restore', 'Restored task ' . $task['title'], 'task', $id);
    }
    return true;
}

function task_archived_for(int $userId): array
{
    return db_all(
        'SELECT t.id, t.title, t.created_at, t.archived_at, t.created_by, u.display_name AS creator_name,
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM task_items i
                        WHERE i.task_id = t.id
                          AND NOT EXISTS (
                            SELECT 1 FROM task_user_status s
                            WHERE s.task_id = i.task_id
                              AND s.user_id = i.assigned_user_id
                              AND s.completed_at IS NOT NULL
                          )
                    ) THEN NULL
                    ELSE (SELECT MAX(s2.completed_at) FROM task_user_status s2 WHERE s2.task_id = t.id)
                END AS completed_at
         FROM tasks t
         JOIN users u ON u.id = t.created_by
         WHERE t.archived_at IS NOT NULL
           AND (t.created_by = ? OR EXISTS (
                SELECT 1 FROM task_items i WHERE i.task_id = t.id AND i.assigned_user_id = ?
           ))
         ORDER BY t.archived_at DESC, t.id DESC',
        [$userId, $userId]
    );
}

function task_delete(int $id, int $actorId): bool
{
    $task = task_find($id);
    if (!$task || (int)$task['created_by'] !== $actorId) {
        return false;
    }
    db_tx(function () use ($task, $actorId): void {
        audit_log('task.delete', 'Deleted task ' . $task['title'], 'task', (int)$task['id']);
        db_exec('DELETE FROM tasks WHERE id = ? AND created_by = ?', [(int)$task['id'], $actorId]);
    });
    return true;
}

function task_toggle(int $taskId, int $itemId, int $userId, bool $done): bool
{
    $item = db_one(
        'SELECT i.id, i.completed_at, t.title, a.name AS app_name, d.name AS db_name, i.vm_side
         FROM task_items i
         JOIN tasks t ON t.id = i.task_id
         LEFT JOIN app_vms a ON i.vm_side = \'app\' AND a.id = i.vm_id
         LEFT JOIN db_vms d ON i.vm_side = \'db\' AND d.id = i.vm_id
         WHERE i.id = ? AND i.task_id = ? AND i.assigned_user_id = ?',
        [$itemId, $taskId, $userId]
    );
    if (!$item) {
        return false;
    }
    $complete = $item['completed_at'] !== null && $item['completed_at'] !== '';
    if ($done === $complete) {
        return true;
    }
    $name = (string)$item['vm_side'] === 'db' ? (string)($item['db_name'] ?? '') : (string)($item['app_name'] ?? '');
    if ($name === '') {
        $name = 'a removed VM';
    }
    if ($done) {
        db_exec(
            'UPDATE task_items SET completed_at = UTC_TIMESTAMP() WHERE id = ? AND task_id = ? AND assigned_user_id = ?',
            [$itemId, $taskId, $userId]
        );
        audit_log('task.complete', 'Checked off ' . $name . ' on task ' . $item['title'], 'task', $taskId);
    } else {
        db_exec(
            'UPDATE task_items SET completed_at = NULL WHERE id = ? AND task_id = ? AND assigned_user_id = ?',
            [$itemId, $taskId, $userId]
        );
        audit_log('task.reopen', 'Unchecked ' . $name . ' on task ' . $item['title'], 'task', $taskId);
    }
    return true;
}

function task_claim_completion(int $taskId): bool
{
    return db_affect(
        'UPDATE tasks
         SET completion_notified_at = UTC_TIMESTAMP()
         WHERE id = ?
           AND completion_notified_at IS NULL
           AND EXISTS (SELECT 1 FROM task_items WHERE task_id = ?)
           AND NOT EXISTS (
             SELECT 1 FROM task_items i
             LEFT JOIN task_user_status s
               ON s.task_id = i.task_id AND s.user_id = i.assigned_user_id AND s.completed_at IS NOT NULL
             WHERE i.task_id = ? AND s.user_id IS NULL
           )',
        [$taskId, $taskId, $taskId]
    ) === 1;
}

function task_clear_completion_notice(int $taskId): void
{
    db_exec(
        'UPDATE tasks
         SET completion_notified_at = NULL
         WHERE id = ?
           AND (
             NOT EXISTS (SELECT 1 FROM task_items WHERE task_id = ?)
             OR EXISTS (
               SELECT 1 FROM task_items i
               LEFT JOIN task_user_status s
                 ON s.task_id = i.task_id AND s.user_id = i.assigned_user_id AND s.completed_at IS NOT NULL
               WHERE i.task_id = ? AND s.user_id IS NULL
             )
           )',
        [$taskId, $taskId, $taskId]
    );
}

function task_prune_status(int $taskId): void
{
    db_exec(
        'DELETE FROM task_user_status
         WHERE task_id = ?
           AND user_id NOT IN (
             SELECT assigned_user_id FROM (
               SELECT DISTINCT assigned_user_id FROM task_items WHERE task_id = ?
             ) remaining
           )',
        [$taskId, $taskId]
    );
}

function task_after_items_removed(array $taskIds): array
{
    $notes = [];
    foreach ($taskIds as $taskId) {
        $taskId = (int)$taskId;
        if ($taskId < 1) {
            continue;
        }
        task_prune_status($taskId);
        $notes = array_merge($notes, task_notify_finished($taskId));
    }
    return $notes;
}

function task_notify_finished(int $taskId): array
{
    $notes = db_tx(function () use ($taskId): array {
        if (!task_claim_completion($taskId)) {
            return [];
        }
        $task = task_find($taskId);
        $creator = $task ? db_one('SELECT display_name, email FROM users WHERE id = ?', [(int)$task['created_by']]) : null;
        if (!$task || !$creator) {
            return [];
        }
        $name = (string)$creator['display_name'];
        $email = trim((string)$creator['email']);
        if ($email === '') {
            return ['No email was sent to ' . $name . ' because that account has no email address.'];
        }
        if (!mail_ready()) {
            return ['Email could not be sent because the mail settings are incomplete.'];
        }
        $assignees = db_all(
            'SELECT u.id, u.display_name
             FROM task_items i
             JOIN users u ON u.id = i.assigned_user_id
             WHERE i.task_id = ?
             GROUP BY u.id, u.display_name
             ORDER BY u.display_name, u.id',
            [$taskId]
        );
        $names = array_map(static fn(array $assignee): string => (string)$assignee['display_name'], $assignees);
        queue_mail($email, 'Task completed: ' . (string)$task['title'], task_completed_message($name, (string)$task['title'], $names));
        return ['queued'];
    });
    if ($notes === ['queued']) {
        return mail_delivery_notes();
    }
    return $notes;
}

function task_finish(int $taskId, int $userId): array
{
    $has = (int)db_val(
        'SELECT COUNT(*) FROM task_items WHERE task_id = ? AND assigned_user_id = ?',
        [$taskId, $userId]
    );
    if ($has < 1) {
        return ['ok' => false, 'mail' => []];
    }
    $existing = db_val(
        'SELECT completed_at FROM task_user_status WHERE task_id = ? AND user_id = ?',
        [$taskId, $userId]
    );
    if ($existing !== null && $existing !== '') {
        return ['ok' => true, 'mail' => []];
    }
    $task = task_find($taskId);
    db_exec(
        'INSERT INTO task_user_status (task_id, user_id, completed_at) VALUES (?,?,UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE completed_at = COALESCE(completed_at, UTC_TIMESTAMP())',
        [$taskId, $userId]
    );
    audit_log('task.finish', 'Marked task ' . (string)($task['title'] ?? '') . ' completed', 'task', $taskId);
    return ['ok' => true, 'mail' => task_notify_finished($taskId)];
}

function task_groups(int $taskId): array
{
    $groups = [];
    foreach (task_item_rows($taskId) as $row) {
        $userId = (int)$row['assigned_user_id'];
        if (!isset($groups[$userId])) {
            $finishedByUser = $row['user_completed_at'] !== null && $row['user_completed_at'] !== '';
            $groups[$userId] = [
                'user_id' => $userId,
                'name' => (string)$row['display_name'],
                'done' => 0,
                'total' => 0,
                'percent' => 0,
                'user_completed' => $finishedByUser,
                'items' => [],
            ];
        }
        $side = (string)$row['vm_side'];
        $isDb = $side === 'db';
        $name = $isDb ? (string)($row['db_name'] ?? '') : (string)($row['app_name'] ?? '');
        $finished = $row['completed_at'] !== null && $row['completed_at'] !== '';
        $groups[$userId]['total']++;
        if ($finished) {
            $groups[$userId]['done']++;
        }
        $groups[$userId]['items'][] = [
            'id' => (int)$row['id'],
            'side' => $side,
            'vm_id' => (int)$row['vm_id'],
            'name' => $name !== '' ? $name : 'Removed VM',
            'ip' => $isDb ? (string)($row['db_ip'] ?? '') : (string)($row['app_ip'] ?? ''),
            'zone' => $isDb ? (string)($row['db_zone'] ?? '') : (string)($row['app_zone'] ?? ''),
            'lifecycle' => $isDb ? (string)($row['db_lifecycle'] ?? '') : (string)($row['app_lifecycle'] ?? ''),
            'done' => $finished,
            'removed' => $name === '',
            'urls' => $row['urls'] ?? [],
        ];
    }
    foreach ($groups as &$group) {
        $group['percent'] = task_percent($group['done'], $group['total']);
    }
    unset($group);
    return array_values($groups);
}

function task_list_for(int $userId): array
{
    $tasks = db_all(
        'SELECT t.id, t.title, t.description, t.created_by, t.updated_at, u.display_name AS creator_name
         FROM tasks t
         JOIN users u ON u.id = t.created_by
         WHERE t.archived_at IS NULL
           AND (t.created_by = ? OR EXISTS (
            SELECT 1 FROM task_items i WHERE i.task_id = t.id AND i.assigned_user_id = ?
         ))
         ORDER BY t.updated_at DESC, t.id DESC',
        [$userId, $userId]
    );
    if (!$tasks) {
        return [];
    }
    $ids = array_map(static fn(array $task): int => (int)$task['id'], $tasks);
    $stats = db_all(
        'SELECT i.task_id, i.assigned_user_id, u.display_name,
                COUNT(*) AS total, SUM(i.completed_at IS NOT NULL) AS done,
                MAX(s.completed_at) AS user_completed_at
         FROM task_items i
         JOIN users u ON u.id = i.assigned_user_id
         LEFT JOIN task_user_status s ON s.task_id = i.task_id AND s.user_id = i.assigned_user_id
         WHERE i.task_id IN (' . placeholders($ids) . ')
         GROUP BY i.task_id, i.assigned_user_id, u.display_name
         ORDER BY u.display_name, i.assigned_user_id',
        $ids
    );
    $byTask = [];
    foreach ($stats as $stat) {
        $byTask[(int)$stat['task_id']][] = [
            'user_id' => (int)$stat['assigned_user_id'],
            'name' => (string)$stat['display_name'],
            'done' => (int)$stat['done'],
            'total' => (int)$stat['total'],
            'percent' => task_percent((int)$stat['done'], (int)$stat['total']),
            'user_completed' => $stat['user_completed_at'] !== null && $stat['user_completed_at'] !== '',
        ];
    }
    $out = [];
    foreach ($tasks as $task) {
        $creator = (int)$task['created_by'] === $userId;
        $allGroups = $byTask[(int)$task['id']] ?? [];
        $allFinished = $allGroups !== [];
        $mineFinished = false;
        foreach ($allGroups as $group) {
            if (empty($group['user_completed'])) {
                $allFinished = false;
            }
            if ($group['user_id'] === $userId && !empty($group['user_completed'])) {
                $mineFinished = true;
            }
        }
        $groups = $creator ? $allGroups : array_values(array_filter(
            $allGroups,
            static fn(array $group): bool => $group['user_id'] === $userId
        ));
        $out[] = [
            'id' => (int)$task['id'],
            'title' => (string)$task['title'],
            'excerpt' => task_excerpt((string)$task['description']),
            'creator' => $creator,
            'creator_name' => (string)$task['creator_name'],
            'panel' => ($creator ? $allFinished : $mineFinished) ? 'completed' : 'assigned',
            'groups' => $groups,
        ];
    }
    return $out;
}
