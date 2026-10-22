<?php
declare(strict_types=1);

function audit_actions(): array
{
    return [
        'auth.login' => 'Sign in',
        'auth.login_failed' => 'Failed sign-in',
        'auth.logout' => 'Sign out',
        'auth.password_change' => 'Password change',
        'auth.password_failed' => 'Rejected password change',
        'user.create' => 'User created',
        'user.update' => 'User updated',
        'user.password_reset' => 'Password reset',
        'lookup.create' => 'List value added',
        'lookup.rename' => 'List value renamed',
        'lookup.retire' => 'List value retired',
        'lookup.restore' => 'List value restored',
        'lookup.delete' => 'List value deleted',
        'app_vm.create' => 'APP VM created',
        'app_vm.update' => 'APP VM updated',
        'app_vm.delete' => 'APP VM deleted',
        'app_vm.decommission' => 'APP VM decommissioned',
        'app_vm.restore' => 'APP VM restored',
        'db_vm.create' => 'DB VM created',
        'db_vm.update' => 'DB VM updated',
        'db_vm.delete' => 'DB VM deleted',
        'db_vm.decommission' => 'DB VM decommissioned',
        'db_vm.restore' => 'DB VM restored',
    ];
}

function audit_action_label(string $action): string
{
    return audit_actions()[$action] ?? $action;
}

function audit_log(
    string $action,
    string $summary,
    ?string $entityType = null,
    ?int $entityId = null,
    ?int $userId = null,
    ?string $username = null
): int {
    $user = auth_user();
    if ($userId === null && $user) {
        $userId = (int)$user['id'];
    }
    if ($username === null && $user) {
        $username = (string)$user['username'];
    }
    return db_insert(
        'INSERT INTO audit_events (user_id, username, action, entity_type, entity_id, summary, ip) VALUES (?,?,?,?,?,?,?)',
        [$userId, $username, $action, $entityType, $entityId, mb_substr($summary, 0, 500), client_ip()]
    );
}

function audit_list(array $filters): array
{
    $where = ['1 = 1'];
    $params = [];
    if ($filters['q'] !== '') {
        $like = like_contains($filters['q']);
        $where[] = '(' . like_sql('a.summary') . ' OR ' . like_sql('a.username') . ' OR ' . like_sql('u.display_name') . ' OR ' . like_sql('u.username') . ')';
        array_push($params, $like, $like, $like, $like);
    }
    if ($filters['action'] !== '') {
        $where[] = 'a.action = ?';
        $params[] = $filters['action'];
    }
    if ($filters['from'] !== null) {
        $where[] = 'a.created_at >= ?';
        $params[] = $filters['from'];
    }
    if ($filters['to'] !== null) {
        $where[] = 'a.created_at <= ?';
        $params[] = $filters['to'];
    }
    $from = 'FROM audit_events a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where);
    $total = (int)db_val('SELECT COUNT(*) ' . $from, $params);
    [$page, $pages, $offset] = paginate($total, (int)$filters['page'], 40);
    $rows = db_all(
        'SELECT a.id, a.action, a.summary, a.created_at, a.username, a.entity_type, a.entity_id, u.display_name '
        . $from . ' ORDER BY a.id DESC LIMIT 40 OFFSET ' . (int)$offset,
        $params
    );
    return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages];
}

function audit_detail(int $id): ?array
{
    $event = db_one(
        'SELECT a.*, u.display_name FROM audit_events a LEFT JOIN users u ON u.id = a.user_id WHERE a.id = ?',
        [$id]
    );
    if (!$event) {
        return null;
    }
    $fields = db_all(
        'SELECT field_name, old_value, new_value, entity_type, entity_id, entity_name FROM field_history WHERE audit_event_id = ? ORDER BY id',
        [$id]
    );
    $groups = [];
    foreach ($fields as $field) {
        $key = $field['entity_type'] . ':' . $field['entity_id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'entity_type' => (string)$field['entity_type'],
                'entity_id' => (int)$field['entity_id'],
                'entity_name' => (string)$field['entity_name'],
                'fields' => [],
            ];
        }
        $groups[$key]['fields'][] = $field;
    }
    $appIds = [];
    $dbIds = [];
    foreach ($groups as $group) {
        if ($group['entity_type'] === 'app_vm') {
            $appIds[] = $group['entity_id'];
        } elseif ($group['entity_type'] === 'db_vm') {
            $dbIds[] = $group['entity_id'];
        }
    }
    $event['groups'] = array_values($groups);
    $event['existing_app_ids'] = existing_ids('app_vms', $appIds);
    $event['existing_db_ids'] = existing_ids('db_vms', $dbIds);
    return $event;
}

function history_groups(string $entityType, int $entityId): array
{
    if (!in_array($entityType, ['app_vm', 'db_vm'], true)) {
        return [];
    }
    $rows = db_all(
        'SELECT h.field_name, h.old_value, h.new_value, h.action, a.id AS audit_id, a.summary, a.created_at, u.display_name
         FROM field_history h
         JOIN audit_events a ON a.id = h.audit_event_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE h.entity_type = ? AND h.entity_id = ?
         ORDER BY a.id DESC, h.id ASC
         LIMIT 500',
        [$entityType, $entityId]
    );
    $groups = [];
    foreach ($rows as $row) {
        $auditId = (int)$row['audit_id'];
        if (!isset($groups[$auditId])) {
            $groups[$auditId] = [
                'id' => $auditId,
                'summary' => (string)$row['summary'],
                'action' => (string)$row['action'],
                'created_at' => $row['created_at'],
                'display_name' => $row['display_name'] ?: 'Former user',
                'fields' => [],
            ];
        }
        $groups[$auditId]['fields'][] = [
            'field' => (string)$row['field_name'],
            'old' => $row['old_value'],
            'new' => $row['new_value'],
        ];
    }
    return array_values($groups);
}

function recent_activity(int $limit = 8): array
{
    $limit = max(1, min(20, $limit));
    return db_all(
        'SELECT a.id, a.summary, a.created_at, u.display_name
         FROM audit_events a
         LEFT JOIN users u ON u.id = a.user_id
         WHERE a.action NOT LIKE \'auth.%\'
         ORDER BY a.id DESC
         LIMIT ' . $limit
    );
}
