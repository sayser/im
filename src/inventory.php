<?php
declare(strict_types=1);

function existing_ids(string $table, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids || !in_array($table, ['app_vms', 'db_vms'], true)) {
        return [];
    }
    $rows = db_all('SELECT id FROM ' . $table . ' WHERE id IN (' . placeholders($ids) . ')', $ids);
    return array_map(static fn(array $row): int => (int)$row['id'], $rows);
}

function joined_names(array $rows): string
{
    $names = [];
    foreach ($rows as $row) {
        $names[] = (string)($row['name'] ?? '');
    }
    $names = array_values(array_filter($names, static fn(string $name): bool => $name !== ''));
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    return implode(', ', $names);
}

function lookup_names_sorted(array $ids): string
{
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    if (!$ids) {
        return '';
    }
    return joined_names(db_all('SELECT name FROM lookups WHERE id IN (' . placeholders($ids) . ')', $ids));
}

function vm_names_sorted(string $table, array $ids): string
{
    if (!in_array($table, ['app_vms', 'db_vms'], true)) {
        return '';
    }
    $ids = array_values(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0));
    if (!$ids) {
        return '';
    }
    return joined_names(db_all('SELECT name FROM ' . $table . ' WHERE id IN (' . placeholders($ids) . ')', $ids));
}

function lookup_display(int $id): string
{
    if ($id <= 0) {
        return '';
    }
    $name = db_val('SELECT name FROM lookups WHERE id = ?', [$id]);
    return $name === null ? '' : (string)$name;
}

function children_by_owner(array $ids, string $sql, array $params): array
{
    $grouped = [];
    foreach ($ids as $id) {
        $grouped[(int)$id] = [];
    }
    foreach (db_all($sql, $params) as $row) {
        $owner = (int)$row['owner_id'];
        unset($row['owner_id']);
        if (isset($row['id'])) {
            $row['id'] = (int)$row['id'];
        }
        $grouped[$owner][] = $row;
    }
    return $grouped;
}

function read_app_filters(): array
{
    $link = (string)($_GET['link'] ?? '');
    return [
        'q' => query_text('q'),
        'client_id' => max(0, (int)($_GET['client_id'] ?? 0)),
        'environment_id' => max(0, (int)($_GET['environment_id'] ?? 0)),
        'os_id' => max(0, (int)($_GET['os_id'] ?? 0)),
        'application_id' => max(0, (int)($_GET['application_id'] ?? 0)),
        'link' => in_array($link, ['linked', 'unlinked'], true) ? $link : '',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
}

function read_db_filters(): array
{
    $link = (string)($_GET['link'] ?? '');
    $redis = (string)($_GET['redis'] ?? '');
    return [
        'q' => query_text('q'),
        'client_id' => max(0, (int)($_GET['client_id'] ?? 0)),
        'environment_id' => max(0, (int)($_GET['environment_id'] ?? 0)),
        'os_id' => max(0, (int)($_GET['os_id'] ?? 0)),
        'redis' => in_array($redis, ['0', '1'], true) ? $redis : '',
        'link' => in_array($link, ['linked', 'unlinked'], true) ? $link : '',
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
}

function app_from_sql(): string
{
    return 'FROM app_vms v
        JOIN lookups c ON c.id = v.client_id
        JOIN lookups e ON e.id = v.environment_id
        JOIN lookups z ON z.id = v.zone_id
        JOIN lookups o ON o.id = v.os_id
        LEFT JOIN stacks s ON s.id = v.stack_id';
}

function db_from_sql(): string
{
    return 'FROM db_vms v
        JOIN lookups c ON c.id = v.client_id
        JOIN lookups e ON e.id = v.environment_id
        JOIN lookups z ON z.id = v.zone_id
        JOIN lookups o ON o.id = v.os_id
        LEFT JOIN stacks s ON s.id = v.stack_id';
}

function append_ip_match(array &$parts, array &$params, string $column, string $q): void
{
    $octets = ltrim($q, '.');
    if (preg_match('/^(?:\d{1,3}\.){1,3}\d{1,3}$/', $octets) === 1) {
        $column = sql_ident($column);
        $parts[] = '(' . $column . ' = ? OR ' . $column . ' LIKE ? OR ' . $column . ' LIKE ? OR ' . $column . ' LIKE ?)';
        array_push($params, $octets, '%.' . $octets, $octets . '.%', '%.' . $octets . '.%');
        return;
    }
    $parts[] = like_sql($column);
    $params[] = like_contains($q);
}

function append_search(array &$where, array &$params, array $columns, array $exists, string $q, array $ipColumns = []): void
{
    $like = like_contains($q);
    $parts = [];
    foreach ($columns as $column) {
        $parts[] = like_sql($column);
        $params[] = $like;
    }
    foreach ($ipColumns as $column) {
        append_ip_match($parts, $params, $column, $q);
    }
    foreach ($exists as $piece) {
        $parts[] = $piece['sql'];
        for ($i = 0; $i < $piece['binds']; $i++) {
            $params[] = $like;
        }
    }
    $where[] = '(' . implode(' OR ', $parts) . ')';
}

function app_where(array $filters, string $lifecycle): array
{
    $where = ['v.lifecycle = ?'];
    $params = [$lifecycle];
    foreach (['client_id' => 'v.client_id', 'environment_id' => 'v.environment_id', 'os_id' => 'v.os_id'] as $key => $column) {
        if ((int)$filters[$key] > 0) {
            $where[] = $column . ' = ?';
            $params[] = (int)$filters[$key];
        }
    }
    if ((int)$filters['application_id'] > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM app_vm_applications aa WHERE aa.app_vm_id = v.id AND aa.application_id = ?)';
        $params[] = (int)$filters['application_id'];
    }
    if ($filters['link'] === 'linked') {
        $where[] = 'EXISTS (SELECT 1 FROM app_db_links l WHERE l.app_vm_id = v.id)';
    } elseif ($filters['link'] === 'unlinked') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM app_db_links l WHERE l.app_vm_id = v.id)';
    }
    if ($filters['q'] !== '') {
        append_search(
            $where,
            $params,
            ['v.name', 'v.url', 'v.brotherhood_url', 'v.status_page_url', 'v.notes', 'v.troubleshooting', 'c.name', 'e.name', 'o.name', 'z.name', 's.name'],
            [
                ['sql' => 'EXISTS (SELECT 1 FROM app_vm_applications aa JOIN lookups al ON al.id = aa.application_id WHERE aa.app_vm_id = v.id AND ' . like_sql('al.name') . ')', 'binds' => 1],
                ['sql' => 'EXISTS (SELECT 1 FROM app_db_links l JOIN db_vms d ON d.id = l.db_vm_id WHERE l.app_vm_id = v.id AND (' . like_sql('d.name') . ' OR ' . like_sql('d.internal_ip') . '))', 'binds' => 2],
            ],
            $filters['q'],
            ['v.internal_ip', 'v.external_ip']
        );
    }
    return [implode(' AND ', $where), $params];
}

function db_where(array $filters, string $lifecycle): array
{
    $where = ['v.lifecycle = ?'];
    $params = [$lifecycle];
    foreach (['client_id' => 'v.client_id', 'environment_id' => 'v.environment_id', 'os_id' => 'v.os_id'] as $key => $column) {
        if ((int)$filters[$key] > 0) {
            $where[] = $column . ' = ?';
            $params[] = (int)$filters[$key];
        }
    }
    if ($filters['redis'] === '0' || $filters['redis'] === '1') {
        $where[] = 'v.redis = ?';
        $params[] = (int)$filters['redis'];
    }
    if ($filters['link'] === 'linked') {
        $where[] = 'EXISTS (SELECT 1 FROM app_db_links l WHERE l.db_vm_id = v.id)';
    } elseif ($filters['link'] === 'unlinked') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM app_db_links l WHERE l.db_vm_id = v.id)';
    }
    if ($filters['q'] !== '') {
        append_search(
            $where,
            $params,
            ['v.name', 'v.notes', 'v.troubleshooting', 'c.name', 'e.name', 'o.name', 'z.name', 's.name'],
            [
                ['sql' => 'EXISTS (SELECT 1 FROM app_db_links l JOIN app_vms a ON a.id = l.app_vm_id WHERE l.db_vm_id = v.id AND (' . like_sql('a.name') . ' OR ' . like_sql('a.internal_ip') . '))', 'binds' => 2],
            ],
            $filters['q'],
            ['v.internal_ip']
        );
    }
    return [implode(' AND ', $where), $params];
}

function hydrate_app_rows(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $marks = placeholders($ids);
    $apps = children_by_owner(
        $ids,
        'SELECT aa.app_vm_id AS owner_id, l.id, l.name
         FROM app_vm_applications aa
         JOIN lookups l ON l.id = aa.application_id
         WHERE aa.app_vm_id IN (' . $marks . ')
         ORDER BY l.sort_order, l.name',
        $ids
    );
    $dbs = children_by_owner(
        $ids,
        'SELECT l.app_vm_id AS owner_id, d.id, d.name, d.internal_ip, d.lifecycle, c.name AS client_name
         FROM app_db_links l
         JOIN db_vms d ON d.id = l.db_vm_id
         JOIN lookups c ON c.id = d.client_id
         WHERE l.app_vm_id IN (' . $marks . ')
         ORDER BY d.name, d.id',
        $ids
    );
    foreach ($rows as $i => $row) {
        $id = (int)$row['id'];
        $rows[$i]['id'] = $id;
        $rows[$i]['applications'] = $apps[$id] ?? [];
        $rows[$i]['db_vms'] = $dbs[$id] ?? [];
    }
    return attach_open_marks($rows, 'app');
}

function hydrate_db_rows(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $apps = children_by_owner(
        $ids,
        'SELECT l.db_vm_id AS owner_id, a.id, a.name, a.internal_ip, a.lifecycle, c.name AS client_name
         FROM app_db_links l
         JOIN app_vms a ON a.id = l.app_vm_id
         JOIN lookups c ON c.id = a.client_id
         WHERE l.db_vm_id IN (' . placeholders($ids) . ')
         ORDER BY a.name, a.id',
        $ids
    );
    foreach ($rows as $i => $row) {
        $id = (int)$row['id'];
        $rows[$i]['id'] = $id;
        $rows[$i]['redis'] = (int)$row['redis'];
        $rows[$i]['app_vms'] = $apps[$id] ?? [];
    }
    return attach_open_marks($rows, 'db');
}

function app_vm_list(array $filters, string $lifecycle, bool $all = false): array
{
    [$where, $params] = app_where($filters, $lifecycle);
    $from = app_from_sql() . ' WHERE ' . $where;
    $total = (int)db_val('SELECT COUNT(*) ' . $from, $params);
    [$page, $pages, $offset] = paginate($total, (int)$filters['page'], 25);
    $limit = $all ? '' : ' LIMIT 25 OFFSET ' . (int)$offset;
    $rows = db_all(
        'SELECT v.id, v.name, v.internal_ip, v.external_ip, v.url, v.brotherhood_url, v.status_page_url,
                v.notes, v.troubleshooting, v.lifecycle, v.created_at, v.updated_at, v.client_id, v.environment_id, v.zone_id, v.stack_id, v.os_id,
                c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                z.name AS zone_name, s.name AS stack_name '
        . $from . ' ORDER BY v.name ASC, v.id ASC' . $limit,
        $params
    );
    return ['rows' => hydrate_app_rows($rows), 'total' => $total, 'page' => $all ? 1 : $page, 'pages' => $all ? 1 : $pages];
}

function db_vm_list(array $filters, string $lifecycle, bool $all = false): array
{
    [$where, $params] = db_where($filters, $lifecycle);
    $from = db_from_sql() . ' WHERE ' . $where;
    $total = (int)db_val('SELECT COUNT(*) ' . $from, $params);
    [$page, $pages, $offset] = paginate($total, (int)$filters['page'], 25);
    $limit = $all ? '' : ' LIMIT 25 OFFSET ' . (int)$offset;
    $rows = db_all(
        'SELECT v.id, v.name, v.internal_ip, v.redis, v.notes, v.troubleshooting, v.lifecycle, v.created_at, v.updated_at,
                v.client_id, v.environment_id, v.zone_id, v.stack_id, v.os_id,
                c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                z.name AS zone_name, s.name AS stack_name '
        . $from . ' ORDER BY v.name ASC, v.id ASC' . $limit,
        $params
    );
    return ['rows' => hydrate_db_rows($rows), 'total' => $total, 'page' => $all ? 1 : $page, 'pages' => $all ? 1 : $pages];
}

function app_vm_find(int $id): ?array
{
    $row = db_one(
        'SELECT v.*, c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                z.name AS zone_name, s.name AS stack_name,
                cu.display_name AS created_by_name, uu.display_name AS updated_by_name, du.display_name AS decommissioned_by_name
         FROM app_vms v
         JOIN lookups c ON c.id = v.client_id
         JOIN lookups e ON e.id = v.environment_id
         JOIN lookups z ON z.id = v.zone_id
         JOIN lookups o ON o.id = v.os_id
         LEFT JOIN stacks s ON s.id = v.stack_id
         LEFT JOIN users cu ON cu.id = v.created_by
         LEFT JOIN users uu ON uu.id = v.updated_by
         LEFT JOIN users du ON du.id = v.decommissioned_by
         WHERE v.id = ?',
        [$id]
    );
    if (!$row) {
        return null;
    }
    return hydrate_app_rows([$row])[0];
}

function db_vm_find(int $id): ?array
{
    $row = db_one(
        'SELECT v.*, c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                z.name AS zone_name, s.name AS stack_name,
                cu.display_name AS created_by_name, uu.display_name AS updated_by_name, du.display_name AS decommissioned_by_name
         FROM db_vms v
         JOIN lookups c ON c.id = v.client_id
         JOIN lookups e ON e.id = v.environment_id
         JOIN lookups z ON z.id = v.zone_id
         JOIN lookups o ON o.id = v.os_id
         LEFT JOIN stacks s ON s.id = v.stack_id
         LEFT JOIN users cu ON cu.id = v.created_by
         LEFT JOIN users uu ON uu.id = v.updated_by
         LEFT JOIN users du ON du.id = v.decommissioned_by
         WHERE v.id = ?',
        [$id]
    );
    if (!$row) {
        return null;
    }
    return hydrate_db_rows([$row])[0];
}

function app_snapshot(array $vm): array
{
    return [
        'Name' => (string)$vm['name'],
        'Client' => (string)$vm['client_name'],
        'Environment' => (string)$vm['zone_name'],
        'Deployment' => (string)$vm['environment_name'],
        'Stack' => (string)($vm['stack_name'] ?? ''),
        'OS' => (string)$vm['os_name'],
        'Internal IP' => (string)$vm['internal_ip'],
        'External IP' => (string)($vm['external_ip'] ?? ''),
        'URL' => (string)($vm['url'] ?? ''),
        'BrotherHood URL' => (string)($vm['brotherhood_url'] ?? ''),
        'Status Page' => (string)($vm['status_page_url'] ?? ''),
        'Applications' => joined_names($vm['applications'] ?? []),
        'DB VMs' => joined_names($vm['db_vms'] ?? []),
        'Notes' => (string)($vm['notes'] ?? ''),
        'Troubleshooting' => (string)($vm['troubleshooting'] ?? ''),
    ];
}

function app_snapshot_input(array $in): array
{
    return [
        'Name' => (string)$in['name'],
        'Client' => lookup_display((int)$in['client_id']),
        'Environment' => lookup_display((int)$in['zone_id']),
        'Deployment' => lookup_display((int)$in['environment_id']),
        'Stack' => (string)$in['stack_name'],
        'OS' => lookup_display((int)$in['os_id']),
        'Internal IP' => (string)$in['internal_ip'],
        'External IP' => (string)$in['external_ip'],
        'URL' => (string)$in['url'],
        'BrotherHood URL' => (string)$in['brotherhood_url'],
        'Status Page' => (string)$in['status_page_url'],
        'Applications' => lookup_names_sorted($in['application_ids']),
        'DB VMs' => vm_names_sorted('db_vms', $in['db_vm_ids']),
        'Notes' => (string)$in['notes'],
        'Troubleshooting' => (string)$in['troubleshooting'],
    ];
}

function db_snapshot(array $vm): array
{
    return [
        'Name' => (string)$vm['name'],
        'Client' => (string)$vm['client_name'],
        'Environment' => (string)$vm['zone_name'],
        'Deployment' => (string)$vm['environment_name'],
        'Stack' => (string)($vm['stack_name'] ?? ''),
        'OS' => (string)$vm['os_name'],
        'Internal IP' => (string)$vm['internal_ip'],
        'Redis' => (int)$vm['redis'] === 1 ? 'Yes' : 'No',
        'APP VMs' => joined_names($vm['app_vms'] ?? []),
        'Notes' => (string)($vm['notes'] ?? ''),
        'Troubleshooting' => (string)($vm['troubleshooting'] ?? ''),
    ];
}

function db_snapshot_input(array $in): array
{
    return [
        'Name' => (string)$in['name'],
        'Client' => lookup_display((int)$in['client_id']),
        'Environment' => lookup_display((int)$in['zone_id']),
        'Deployment' => lookup_display((int)$in['environment_id']),
        'Stack' => (string)$in['stack_name'],
        'OS' => lookup_display((int)$in['os_id']),
        'Internal IP' => (string)$in['internal_ip'],
        'Redis' => ($in['redis'] ?? '') === '1' ? 'Yes' : 'No',
        'APP VMs' => vm_names_sorted('app_vms', $in['app_vm_ids']),
        'Notes' => (string)$in['notes'],
        'Troubleshooting' => (string)$in['troubleshooting'],
    ];
}

function snapshot_changes(array $before, array $after, bool $creating): array
{
    $changes = [];
    foreach ($after as $field => $value) {
        $new = (string)$value;
        $old = (string)($before[$field] ?? '');
        if ($creating && $new === '') {
            continue;
        }
        if ($old !== $new) {
            $changes[] = ['field' => (string)$field, 'old' => $creating ? '' : $old, 'new' => $new];
        }
    }
    return $changes;
}

function snapshot_deletions(array $snapshot): array
{
    $changes = [];
    foreach ($snapshot as $field => $value) {
        $old = (string)$value;
        if ($old === '') {
            continue;
        }
        $changes[] = ['field' => (string)$field, 'old' => $old, 'new' => ''];
    }
    return $changes;
}

function write_field_history(int $auditId, string $entityType, int $entityId, string $entityName, string $action, array $changes): void
{
    if (!in_array($entityType, ['app_vm', 'db_vm'], true)) {
        return;
    }
    if (!in_array($action, ['create', 'update', 'delete', 'decommission', 'restore'], true)) {
        return;
    }
    foreach ($changes as $change) {
        $old = (string)($change['old'] ?? '');
        $new = (string)($change['new'] ?? '');
        db_exec(
            'INSERT INTO field_history (audit_event_id, entity_type, entity_id, entity_name, action, field_name, old_value, new_value) VALUES (?,?,?,?,?,?,?,?)',
            [
                $auditId,
                $entityType,
                $entityId,
                mb_substr($entityName, 0, 190),
                $action,
                mb_substr((string)$change['field'], 0, 64),
                $old === '' ? null : $old,
                $new === '' ? null : $new,
            ]
        );
    }
}

function choices(string $kind, int $selected = 0): array
{
    return db_all(
        'SELECT id, name, active FROM lookups WHERE kind = ? AND (active = 1 OR id = ?) ORDER BY sort_order, name',
        [$kind, max(0, $selected)]
    );
}

function choices_multi(string $kind, array $selected): array
{
    $selected = array_values(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0));
    if (!$selected) {
        return db_all(
            'SELECT id, name, active FROM lookups WHERE kind = ? AND active = 1 ORDER BY sort_order, name',
            [$kind]
        );
    }
    return db_all(
        'SELECT id, name, active FROM lookups WHERE kind = ? AND (active = 1 OR id IN (' . placeholders($selected) . ')) ORDER BY sort_order, name',
        array_merge([$kind], $selected)
    );
}

function vm_choices(string $which, array $selected): array
{
    $table = $which === 'app' ? 'app_vms' : 'db_vms';
    $selected = array_values(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0));
    $sql = 'SELECT v.id, v.name, v.internal_ip, v.lifecycle, v.zone_id, c.name AS client_name, e.name AS environment_name
            FROM ' . $table . ' v
            JOIN lookups c ON c.id = v.client_id
            JOIN lookups e ON e.id = v.environment_id';
    $params = [];
    if ($selected) {
        $sql .= ' WHERE v.lifecycle = \'active\' OR v.id IN (' . placeholders($selected) . ')';
        $params = $selected;
    } else {
        $sql .= " WHERE v.lifecycle = 'active'";
    }
    $sql .= ' ORDER BY v.lifecycle, v.name, v.id';
    return db_all($sql, $params);
}

function lookup_selectable(string $kind, int $id, int $previousId): bool
{
    if ($id <= 0) {
        return false;
    }
    $row = db_one('SELECT kind, active FROM lookups WHERE id = ?', [$id]);
    if (!$row || $row['kind'] !== $kind) {
        return false;
    }
    return (int)$row['active'] === 1 || $id === $previousId;
}

function invalid_ip(string $ip, bool $required): ?string
{
    if ($ip === '') {
        return $required ? 'Enter an IP address.' : null;
    }
    if (strlen($ip) > 45 || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'Enter a valid IPv4 or IPv6 address.';
    }
    return null;
}

function invalid_url(string $url): ?string
{
    if ($url === '') {
        return null;
    }
    if (mb_strlen($url) > 500) {
        return 'Use 500 characters or fewer.';
    }
    if (str_contains($url, '\\') || preg_match('/[\x00-\x20]/', $url) === 1) {
        return 'Enter a valid http or https URL.';
    }
    $parts = parse_url($url);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = (string)($parts['host'] ?? '');
    $hasLogin = isset($parts['user']) || isset($parts['pass']);
    if (!is_array($parts) || $hasLogin || $host === '' || ($scheme !== 'http' && $scheme !== 'https')) {
        return 'Enter a valid http or https URL.';
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return 'Enter a valid http or https URL.';
    }
    return null;
}

function applications_problem(array $ids, array $previousIds): ?string
{
    if (!$ids) {
        return 'Select at least one application.';
    }
    if (count($ids) > 30) {
        return 'Select 30 or fewer applications.';
    }
    foreach ($ids as $id) {
        $previous = in_array((int)$id, $previousIds, true) ? (int)$id : 0;
        if (!lookup_selectable('application', (int)$id, $previous)) {
            return 'One of the selected applications is no longer available.';
        }
    }
    return null;
}

function vm_ids_problem(string $table, array $ids, int $zoneId = 0): ?string
{
    if (!$ids) {
        return null;
    }
    if (count($ids) > 100) {
        return 'Select 100 or fewer VMs.';
    }
    if (!in_array($table, ['app_vms', 'db_vms'], true)) {
        return 'One of the selected VMs is not available.';
    }
    $found = (int)db_val('SELECT COUNT(*) FROM ' . $table . ' WHERE id IN (' . placeholders($ids) . ')', $ids);
    if ($found !== count($ids)) {
        return 'One of the selected VMs is no longer available.';
    }
    if ($zoneId > 0) {
        $sameZone = (int)db_val(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE zone_id = ? AND id IN (' . placeholders($ids) . ')',
            array_merge([$zoneId], $ids)
        );
        if ($sameZone !== count($ids)) {
            return 'Link only VMs from the same environment.';
        }
    }
    return null;
}

function app_input_blank(): array
{
    $environmentId = 0;
    $production = db_one("SELECT id FROM lookups WHERE kind = 'environment' AND name = 'prod' AND active = 1");
    if ($production) {
        $environmentId = (int)$production['id'];
    }
    return [
        'name' => '',
        'client_id' => default_client_id(),
        'zone_id' => default_zone_id(),
        'stack_name' => '',
        'environment_id' => $environmentId,
        'os_id' => 0,
        'internal_ip' => '',
        'external_ip' => '',
        'url' => '',
        'brotherhood_url' => '',
        'status_page_url' => '',
        'notes' => '',
        'troubleshooting' => '',
        'application_ids' => [],
        'db_vm_ids' => [],
    ];
}

function app_input_from_vm(array $vm): array
{
    return [
        'name' => (string)$vm['name'],
        'client_id' => (int)$vm['client_id'],
        'zone_id' => (int)$vm['zone_id'],
        'stack_name' => (string)($vm['stack_name'] ?? ''),
        'environment_id' => (int)$vm['environment_id'],
        'os_id' => (int)$vm['os_id'],
        'internal_ip' => (string)$vm['internal_ip'],
        'external_ip' => (string)($vm['external_ip'] ?? ''),
        'url' => (string)($vm['url'] ?? ''),
        'brotherhood_url' => (string)($vm['brotherhood_url'] ?? ''),
        'status_page_url' => (string)($vm['status_page_url'] ?? ''),
        'notes' => (string)($vm['notes'] ?? ''),
        'troubleshooting' => (string)($vm['troubleshooting'] ?? ''),
        'application_ids' => array_map(static fn(array $row): int => (int)$row['id'], $vm['applications'] ?? []),
        'db_vm_ids' => array_map(static fn(array $row): int => (int)$row['id'], $vm['db_vms'] ?? []),
    ];
}

function normalize_app_post(): array
{
    return [
        'name' => clean_line((string)($_POST['name'] ?? '')),
        'client_id' => posted_client_id(),
        'zone_id' => (int)($_POST['zone_id'] ?? 0),
        'stack_name' => stack_name_clean((string)($_POST['stack_name'] ?? '')),
        'environment_id' => (int)($_POST['environment_id'] ?? 0),
        'os_id' => (int)($_POST['os_id'] ?? 0),
        'internal_ip' => clean_line((string)($_POST['internal_ip'] ?? '')),
        'external_ip' => clean_line((string)($_POST['external_ip'] ?? '')),
        'url' => clean_line((string)($_POST['url'] ?? '')),
        'brotherhood_url' => clean_line((string)($_POST['brotherhood_url'] ?? '')),
        'status_page_url' => clean_line((string)($_POST['status_page_url'] ?? '')),
        'notes' => clean_multiline((string)($_POST['notes'] ?? '')),
        'troubleshooting' => clean_multiline((string)($_POST['troubleshooting'] ?? '')),
        'application_ids' => posted_ids('application_ids'),
        'db_vm_ids' => posted_ids('db_vm_ids'),
    ];
}

function validate_app(array $in, ?array $existing): array
{
    $errors = [];
    $prevClient = $existing ? (int)$existing['client_id'] : 0;
    $prevZone = $existing ? (int)$existing['zone_id'] : 0;
    $prevEnv = $existing ? (int)$existing['environment_id'] : 0;
    $prevOs = $existing ? (int)$existing['os_id'] : 0;
    $prevApps = $existing ? array_map(static fn(array $row): int => (int)$row['id'], $existing['applications'] ?? []) : [];

    if ($in['name'] === '') {
        $errors['name'] = 'Enter a name.';
    } elseif (mb_strlen($in['name']) > 190) {
        $errors['name'] = 'Use 190 characters or fewer.';
    }
    if (!lookup_selectable('client', (int)$in['client_id'], $prevClient)) {
        $errors['client_id'] = 'The PT client is not available.';
    }
    if (!lookup_selectable('zone', (int)$in['zone_id'], $prevZone)) {
        $errors['zone_id'] = (int)$in['zone_id'] > 0 ? 'That environment is no longer available.' : 'Select an environment.';
    }
    if (!lookup_selectable('environment', (int)$in['environment_id'], $prevEnv)) {
        $errors['environment_id'] = (int)$in['environment_id'] > 0 ? 'That deployment is no longer available.' : 'Select a deployment.';
    }
    if (!lookup_selectable('os', (int)$in['os_id'], $prevOs)) {
        $errors['os_id'] = (int)$in['os_id'] > 0 ? 'That OS is no longer available.' : 'Select an operating system.';
    }
    if (mb_strlen((string)$in['stack_name']) > 190) {
        $errors['stack_name'] = 'Use 190 characters or fewer.';
    }
    if ($message = invalid_ip((string)$in['internal_ip'], true)) {
        $errors['internal_ip'] = $message;
    }
    if ($message = invalid_ip((string)$in['external_ip'], false)) {
        $errors['external_ip'] = $message;
    }
    if ($message = invalid_url((string)$in['url'])) {
        $errors['url'] = $message;
    }
    if ($message = invalid_url((string)$in['brotherhood_url'])) {
        $errors['brotherhood_url'] = $message;
    }
    if ($message = invalid_url((string)$in['status_page_url'])) {
        $errors['status_page_url'] = $message;
    }
    if (mb_strlen((string)$in['notes']) > 20000) {
        $errors['notes'] = 'Notes are too long.';
    }
    if (mb_strlen((string)$in['troubleshooting']) > 20000) {
        $errors['troubleshooting'] = 'Troubleshooting is too long.';
    }
    if ($message = applications_problem($in['application_ids'], $prevApps)) {
        $errors['application_ids'] = $message;
    }
    if ($message = vm_ids_problem('db_vms', $in['db_vm_ids'], (int)$in['zone_id'])) {
        $errors['db_vm_ids'] = $message;
    }
    return $errors;
}

function db_input_blank(): array
{
    $environmentId = 0;
    $production = db_one("SELECT id FROM lookups WHERE kind = 'environment' AND name = 'prod' AND active = 1");
    if ($production) {
        $environmentId = (int)$production['id'];
    }
    return [
        'name' => '',
        'client_id' => default_client_id(),
        'zone_id' => default_zone_id(),
        'stack_name' => '',
        'environment_id' => $environmentId,
        'os_id' => 0,
        'internal_ip' => '',
        'redis' => '0',
        'notes' => '',
        'troubleshooting' => '',
        'app_vm_ids' => [],
    ];
}

function db_input_from_vm(array $vm): array
{
    return [
        'name' => (string)$vm['name'],
        'client_id' => (int)$vm['client_id'],
        'zone_id' => (int)$vm['zone_id'],
        'stack_name' => (string)($vm['stack_name'] ?? ''),
        'environment_id' => (int)$vm['environment_id'],
        'os_id' => (int)$vm['os_id'],
        'internal_ip' => (string)$vm['internal_ip'],
        'redis' => (int)$vm['redis'] === 1 ? '1' : '0',
        'notes' => (string)($vm['notes'] ?? ''),
        'troubleshooting' => (string)($vm['troubleshooting'] ?? ''),
        'app_vm_ids' => array_map(static fn(array $row): int => (int)$row['id'], $vm['app_vms'] ?? []),
    ];
}

function normalize_db_post(): array
{
    $redis = (string)($_POST['redis'] ?? '');
    return [
        'name' => clean_line((string)($_POST['name'] ?? '')),
        'client_id' => posted_client_id(),
        'zone_id' => (int)($_POST['zone_id'] ?? 0),
        'stack_name' => stack_name_clean((string)($_POST['stack_name'] ?? '')),
        'environment_id' => (int)($_POST['environment_id'] ?? 0),
        'os_id' => (int)($_POST['os_id'] ?? 0),
        'internal_ip' => clean_line((string)($_POST['internal_ip'] ?? '')),
        'redis' => in_array($redis, ['0', '1'], true) ? $redis : '',
        'notes' => clean_multiline((string)($_POST['notes'] ?? '')),
        'troubleshooting' => clean_multiline((string)($_POST['troubleshooting'] ?? '')),
        'app_vm_ids' => posted_ids('app_vm_ids'),
    ];
}

function validate_db(array $in, ?array $existing): array
{
    $errors = [];
    $prevClient = $existing ? (int)$existing['client_id'] : 0;
    $prevZone = $existing ? (int)$existing['zone_id'] : 0;
    $prevEnv = $existing ? (int)$existing['environment_id'] : 0;
    $prevOs = $existing ? (int)$existing['os_id'] : 0;
    if ($in['name'] === '') {
        $errors['name'] = 'Enter a name.';
    } elseif (mb_strlen($in['name']) > 190) {
        $errors['name'] = 'Use 190 characters or fewer.';
    }
    if (!lookup_selectable('client', (int)$in['client_id'], $prevClient)) {
        $errors['client_id'] = 'The PT client is not available.';
    }
    if (!lookup_selectable('zone', (int)$in['zone_id'], $prevZone)) {
        $errors['zone_id'] = (int)$in['zone_id'] > 0 ? 'That environment is no longer available.' : 'Select an environment.';
    }
    if (!lookup_selectable('environment', (int)$in['environment_id'], $prevEnv)) {
        $errors['environment_id'] = (int)$in['environment_id'] > 0 ? 'That deployment is no longer available.' : 'Select a deployment.';
    }
    if (!lookup_selectable('os', (int)$in['os_id'], $prevOs)) {
        $errors['os_id'] = (int)$in['os_id'] > 0 ? 'That OS is no longer available.' : 'Select an operating system.';
    }
    if (mb_strlen((string)$in['stack_name']) > 190) {
        $errors['stack_name'] = 'Use 190 characters or fewer.';
    }
    if ($message = invalid_ip((string)$in['internal_ip'], true)) {
        $errors['internal_ip'] = $message;
    }
    if (!in_array($in['redis'], ['0', '1'], true)) {
        $errors['redis'] = 'Select whether Redis is installed.';
    }
    if (mb_strlen((string)$in['notes']) > 20000) {
        $errors['notes'] = 'Notes are too long.';
    }
    if (mb_strlen((string)$in['troubleshooting']) > 20000) {
        $errors['troubleshooting'] = 'Troubleshooting is too long.';
    }
    if ($message = vm_ids_problem('app_vms', $in['app_vm_ids'], (int)$in['zone_id'])) {
        $errors['app_vm_ids'] = $message;
    }
    return $errors;
}

function replace_app_applications(int $appId, array $ids): void
{
    db_exec('DELETE FROM app_vm_applications WHERE app_vm_id = ?', [$appId]);
    foreach ($ids as $applicationId) {
        db_exec('INSERT INTO app_vm_applications (app_vm_id, application_id) VALUES (?, ?)', [$appId, (int)$applicationId]);
    }
}

function replace_links_for_app(int $appId, array $dbIds): void
{
    db_exec('DELETE FROM app_db_links WHERE app_vm_id = ?', [$appId]);
    foreach ($dbIds as $dbId) {
        db_exec('INSERT INTO app_db_links (app_vm_id, db_vm_id) VALUES (?, ?)', [$appId, (int)$dbId]);
    }
}

function replace_links_for_db(int $dbId, array $appIds): void
{
    db_exec('DELETE FROM app_db_links WHERE db_vm_id = ?', [$dbId]);
    foreach ($appIds as $appId) {
        db_exec('INSERT INTO app_db_links (app_vm_id, db_vm_id) VALUES (?, ?)', [(int)$appId, $dbId]);
    }
}

function save_app_vm(?int $id, array $in): array
{
    $actor = (int)auth_user()['id'];
    $in['stack_name'] = canonical_stack_name((int)$in['zone_id'], (string)$in['stack_name']);
    $existing = null;
    if ($id !== null) {
        $existing = app_vm_find($id);
        if (!$existing) {
            abort(404);
        }
        if (app_snapshot($existing) === app_snapshot_input($in)) {
            return ['id' => $id, 'changed' => false];
        }
    }
    $after = app_snapshot_input($in);
    $savedId = db_tx(function () use ($id, $in, $actor, $existing, $after): int {
        $before = [];
        $creating = $existing === null;
        if ($creating) {
            $stackId = stack_id_for((int)$in['zone_id'], (string)$in['stack_name'], $actor);
            $id = db_insert(
                'INSERT INTO app_vms (name, client_id, environment_id, zone_id, stack_id, os_id, internal_ip, external_ip, url, brotherhood_url, status_page_url, notes, troubleshooting, created_by, updated_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $in['name'], $in['client_id'], $in['environment_id'], $in['zone_id'], $stackId, $in['os_id'], $in['internal_ip'],
                    null_if_blank($in['external_ip']), null_if_blank($in['url']), null_if_blank($in['brotherhood_url']),
                    null_if_blank($in['status_page_url']), null_if_blank($in['notes']), null_if_blank($in['troubleshooting']), $actor, $actor,
                ]
            );
            $auditAction = 'app_vm.create';
            $summary = 'Created APP VM ' . $in['name'];
            $historyAction = 'create';
        } else {
            $before = app_snapshot($existing);
            $stackId = stack_id_for((int)$in['zone_id'], (string)$in['stack_name'], $actor);
            db_exec(
                'UPDATE app_vms SET name=?, client_id=?, environment_id=?, zone_id=?, stack_id=?, os_id=?, internal_ip=?, external_ip=?, url=?, brotherhood_url=?, status_page_url=?, notes=?, troubleshooting=?, updated_by=? WHERE id=?',
                [
                    $in['name'], $in['client_id'], $in['environment_id'], $in['zone_id'], $stackId, $in['os_id'], $in['internal_ip'],
                    null_if_blank($in['external_ip']), null_if_blank($in['url']), null_if_blank($in['brotherhood_url']),
                    null_if_blank($in['status_page_url']), null_if_blank($in['notes']), null_if_blank($in['troubleshooting']), $actor, $id,
                ]
            );
            $auditAction = 'app_vm.update';
            $summary = 'Updated APP VM ' . $in['name'];
            $historyAction = 'update';
        }
        replace_app_applications((int)$id, $in['application_ids']);
        replace_links_for_app((int)$id, $in['db_vm_ids']);
        $changes = snapshot_changes($before, $after, $creating);
        if ($changes) {
            $auditId = audit_log($auditAction, $summary, 'app_vm', (int)$id);
            write_field_history($auditId, 'app_vm', (int)$id, (string)$in['name'], $historyAction, $changes);
        }
        return (int)$id;
    });
    return ['id' => $savedId, 'changed' => true];
}

function save_db_vm(?int $id, array $in): array
{
    $actor = (int)auth_user()['id'];
    $in['stack_name'] = canonical_stack_name((int)$in['zone_id'], (string)$in['stack_name']);
    $existing = null;
    if ($id !== null) {
        $existing = db_vm_find($id);
        if (!$existing) {
            abort(404);
        }
        if (db_snapshot($existing) === db_snapshot_input($in)) {
            return ['id' => $id, 'changed' => false];
        }
    }
    $after = db_snapshot_input($in);
    $savedId = db_tx(function () use ($id, $in, $actor, $existing, $after): int {
        $before = [];
        $creating = $existing === null;
        if ($creating) {
            $stackId = stack_id_for((int)$in['zone_id'], (string)$in['stack_name'], $actor);
            $id = db_insert(
                'INSERT INTO db_vms (name, client_id, environment_id, zone_id, stack_id, os_id, internal_ip, redis, notes, troubleshooting, created_by, updated_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $in['name'], $in['client_id'], $in['environment_id'], $in['zone_id'], $stackId, $in['os_id'], $in['internal_ip'],
                    $in['redis'] === '1' ? 1 : 0, null_if_blank($in['notes']), null_if_blank($in['troubleshooting']), $actor, $actor,
                ]
            );
            $auditAction = 'db_vm.create';
            $summary = 'Created DB VM ' . $in['name'];
            $historyAction = 'create';
        } else {
            $before = db_snapshot($existing);
            $stackId = stack_id_for((int)$in['zone_id'], (string)$in['stack_name'], $actor);
            db_exec(
                'UPDATE db_vms SET name=?, client_id=?, environment_id=?, zone_id=?, stack_id=?, os_id=?, internal_ip=?, redis=?, notes=?, troubleshooting=?, updated_by=? WHERE id=?',
                [
                    $in['name'], $in['client_id'], $in['environment_id'], $in['zone_id'], $stackId, $in['os_id'], $in['internal_ip'],
                    $in['redis'] === '1' ? 1 : 0, null_if_blank($in['notes']), null_if_blank($in['troubleshooting']), $actor, $id,
                ]
            );
            $auditAction = 'db_vm.update';
            $summary = 'Updated DB VM ' . $in['name'];
            $historyAction = 'update';
        }
        replace_links_for_db((int)$id, $in['app_vm_ids']);
        $changes = snapshot_changes($before, $after, $creating);
        if ($changes) {
            $auditId = audit_log($auditAction, $summary, 'db_vm', (int)$id);
            write_field_history($auditId, 'db_vm', (int)$id, (string)$in['name'], $historyAction, $changes);
        }
        return (int)$id;
    });
    return ['id' => $savedId, 'changed' => true];
}

function set_lifecycle(string $which, int $id, string $to): bool
{
    $isApp = $which === 'app';
    $vm = $isApp ? app_vm_find($id) : db_vm_find($id);
    if (!$vm) {
        abort(404);
    }
    if (!in_array($to, ['active', 'decommissioned'], true) || $vm['lifecycle'] === $to) {
        return false;
    }
    $actor = (int)auth_user()['id'];
    $table = $isApp ? 'app_vms' : 'db_vms';
    $entity = $isApp ? 'app_vm' : 'db_vm';
    $label = $isApp ? 'APP VM' : 'DB VM';
    db_tx(function () use ($table, $entity, $label, $vm, $to, $actor, $id): void {
        if ($to === 'decommissioned') {
            db_exec(
                'UPDATE ' . $table . ' SET lifecycle = \'decommissioned\', decommissioned_at = ?, decommissioned_by = ?, updated_by = ? WHERE id = ?',
                [gmdate('Y-m-d H:i:s'), $actor, $actor, $id]
            );
            $auditAction = $entity . '.decommission';
            $summary = 'Decommissioned ' . $label . ' ' . $vm['name'];
            $historyAction = 'decommission';
            $old = 'Active';
            $new = 'Decommissioned';
        } else {
            db_exec(
                'UPDATE ' . $table . " SET lifecycle = 'active', decommissioned_at = NULL, decommissioned_by = NULL, updated_by = ? WHERE id = ?",
                [$actor, $id]
            );
            $auditAction = $entity . '.restore';
            $summary = 'Restored ' . $label . ' ' . $vm['name'];
            $historyAction = 'restore';
            $old = 'Decommissioned';
            $new = 'Active';
        }
        $auditId = audit_log($auditAction, $summary, $entity, $id);
        write_field_history($auditId, $entity, $id, (string)$vm['name'], $historyAction, [
            ['field' => 'Lifecycle', 'old' => $old, 'new' => $new],
        ]);
    });
    return true;
}

function delete_vm(string $which, int $id): array
{
    $isApp = $which === 'app';
    $vm = $isApp ? app_vm_find($id) : db_vm_find($id);
    if (!$vm) {
        abort(404);
    }
    $snapshot = $isApp ? app_snapshot($vm) : db_snapshot($vm);
    $entity = $isApp ? 'app_vm' : 'db_vm';
    $label = $isApp ? 'APP VM' : 'DB VM';
    $table = $isApp ? 'app_vms' : 'db_vms';
    db_tx(function () use ($vm, $snapshot, $entity, $label, $table, $id): void {
        $auditId = audit_log($entity . '.delete', 'Permanently deleted ' . $label . ' ' . $vm['name'], $entity, $id);
        write_field_history($auditId, $entity, $id, (string)$vm['name'], 'delete', snapshot_deletions($snapshot));
        db_exec('DELETE FROM ' . $table . ' WHERE id = ?', [$id]);
    });
    return ['name' => (string)$vm['name'], 'lifecycle' => (string)$vm['lifecycle']];
}

function lookup_kind_label(string $kind): string
{
    return match ($kind) {
        'client' => 'client',
        'zone' => 'environment',
        'environment' => 'deployment',
        'application' => 'application',
        'os' => 'OS',
        default => $kind,
    };
}

function lookup_kind_heading(string $kind): string
{
    return match ($kind) {
        'client' => 'Clients',
        'zone' => 'Environments',
        'environment' => 'Deployments',
        'application' => 'Applications',
        'os' => 'Operating systems',
        default => $kind,
    };
}

function invalid_lookup_name(string $name): ?string
{
    if ($name === '') {
        return 'Enter a name.';
    }
    if (mb_strlen($name) > 120) {
        return 'Use 120 characters or fewer.';
    }
    return null;
}

function lookup_usage_map(string $kind): array
{
    $map = [];
    $add = static function (string $sql) use (&$map): void {
        foreach (db_all($sql) as $row) {
            $id = (int)$row['id'];
            $map[$id] = ($map[$id] ?? 0) + (int)$row['c'];
        }
    };
    if ($kind === 'client') {
        $add('SELECT client_id AS id, COUNT(*) AS c FROM app_vms GROUP BY client_id');
        $add('SELECT client_id AS id, COUNT(*) AS c FROM db_vms GROUP BY client_id');
    } elseif ($kind === 'environment') {
        $add('SELECT environment_id AS id, COUNT(*) AS c FROM app_vms GROUP BY environment_id');
        $add('SELECT environment_id AS id, COUNT(*) AS c FROM db_vms GROUP BY environment_id');
    } elseif ($kind === 'zone') {
        $add('SELECT zone_id AS id, COUNT(*) AS c FROM app_vms GROUP BY zone_id');
        $add('SELECT zone_id AS id, COUNT(*) AS c FROM db_vms GROUP BY zone_id');
    } elseif ($kind === 'os') {
        $add('SELECT os_id AS id, COUNT(*) AS c FROM app_vms GROUP BY os_id');
        $add('SELECT os_id AS id, COUNT(*) AS c FROM db_vms GROUP BY os_id');
    } elseif ($kind === 'application') {
        $add('SELECT application_id AS id, COUNT(*) AS c FROM app_vm_applications GROUP BY application_id');
    }
    return $map;
}

function lookups_admin(): array
{
    $out = [];
    foreach (['zone', 'client', 'environment', 'application', 'os'] as $kind) {
        $rows = db_all(
            'SELECT id, kind, name, active, sort_order FROM lookups WHERE kind = ? ORDER BY active DESC, sort_order, name',
            [$kind]
        );
        $usage = lookup_usage_map($kind);
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['active'] = (int)$row['active'];
            $row['used'] = $usage[$row['id']] ?? 0;
        }
        unset($row);
        $out[$kind] = $rows;
    }
    return $out;
}

function lookup_create(string $kind, string $name): ?string
{
    if (!in_array($kind, ['client', 'zone', 'environment', 'application', 'os'], true)) {
        return 'That list is not available.';
    }
    if ($message = invalid_lookup_name($name)) {
        return $message;
    }
    if (db_one('SELECT id FROM lookups WHERE kind = ? AND name = ?', [$kind, $name])) {
        return 'That value already exists.';
    }
    try {
        db_tx(function () use ($kind, $name): void {
            $id = db_insert(
                'INSERT INTO lookups (kind, name, active, sort_order) VALUES (?,?,1,100)',
                [$kind, $name]
            );
            audit_log('lookup.create', 'Added ' . lookup_kind_label($kind) . ' "' . $name . '"', 'lookup', $id);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That value already exists.';
        }
        throw $e;
    }
    return null;
}

function lookup_rename(int $id, string $name): ?string
{
    $row = db_one('SELECT id, kind, name FROM lookups WHERE id = ?', [$id]);
    if (!$row) {
        return 'That value was not found.';
    }
    if ($message = invalid_lookup_name($name)) {
        return $message;
    }
    if ((string)$row['name'] === $name) {
        return 'unchanged';
    }
    if (db_one('SELECT id FROM lookups WHERE kind = ? AND name = ? AND id <> ?', [$row['kind'], $name, $id])) {
        return 'That value already exists.';
    }
    try {
        db_tx(function () use ($row, $name): void {
            db_exec('UPDATE lookups SET name = ? WHERE id = ?', [$name, (int)$row['id']]);
            $auditId = audit_log(
                'lookup.rename',
                'Renamed ' . lookup_kind_label((string)$row['kind']) . ' "' . $row['name'] . '" to "' . $name . '"',
                'lookup',
                (int)$row['id']
            );
            propagate_lookup_rename($auditId, (string)$row['kind'], (int)$row['id'], (string)$row['name'], $name);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That value already exists.';
        }
        throw $e;
    }
    return null;
}

function lookup_set_active(int $id, bool $active): ?string
{
    $row = db_one('SELECT id, kind, name, active FROM lookups WHERE id = ?', [$id]);
    if (!$row) {
        return 'That value was not found.';
    }
    if ((int)$row['active'] === ($active ? 1 : 0)) {
        return 'unchanged';
    }
    $used = lookup_usage_map((string)$row['kind'])[(int)$row['id']] ?? 0;
    if (!$active && $used === 0) {
        return 'Delete unused values instead of retiring them.';
    }
    db_tx(function () use ($row, $active): void {
        db_exec('UPDATE lookups SET active = ? WHERE id = ?', [$active ? 1 : 0, (int)$row['id']]);
        audit_log(
            $active ? 'lookup.restore' : 'lookup.retire',
            ($active ? 'Restored ' : 'Retired ') . lookup_kind_label((string)$row['kind']) . ' "' . $row['name'] . '"',
            'lookup',
            (int)$row['id']
        );
    });
    return null;
}

function lookup_delete(int $id): ?string
{
    $row = db_one('SELECT id, kind, name FROM lookups WHERE id = ?', [$id]);
    if (!$row) {
        return 'That value was not found.';
    }
    if ((lookup_usage_map((string)$row['kind'])[(int)$row['id']] ?? 0) > 0) {
        return 'That value is in use. Retire it instead.';
    }
    try {
        db_tx(function () use ($row): void {
            $label = 'Deleted ' . lookup_kind_label((string)$row['kind']) . ' "' . $row['name'] . '"';
            db_exec('DELETE FROM lookups WHERE id = ?', [(int)$row['id']]);
            audit_log('lookup.delete', $label, 'lookup', (int)$row['id']);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That value is in use. Retire it instead.';
        }
        throw $e;
    }
    return null;
}

function propagate_lookup_rename(int $auditId, string $kind, int $lookupId, string $old, string $new): void
{
    $map = [
        'client' => ['client_id', 'Client'],
        'zone' => ['zone_id', 'Environment'],
        'environment' => ['environment_id', 'Deployment'],
        'os' => ['os_id', 'OS'],
    ];
    if (isset($map[$kind])) {
        [$column, $field] = $map[$kind];
        write_lookup_value_history($auditId, 'app_vms', 'app_vm', $column, $lookupId, $field, $old, $new);
        write_lookup_value_history($auditId, 'db_vms', 'db_vm', $column, $lookupId, $field, $old, $new);
        return;
    }
    if ($kind === 'application') {
        propagate_application_rename($auditId, $lookupId, $old, $new);
    }
}

function write_lookup_value_history(int $auditId, string $table, string $entityType, string $column, int $lookupId, string $field, string $old, string $new): void
{
    $allowed = [
        'app_vms' => ['app_vm', ['client_id', 'environment_id', 'zone_id', 'os_id']],
        'db_vms' => ['db_vm', ['client_id', 'environment_id', 'zone_id', 'os_id']],
    ];
    if (!isset($allowed[$table]) || $allowed[$table][0] !== $entityType || !in_array($column, $allowed[$table][1], true)) {
        return;
    }
    $rows = db_all('SELECT id, name FROM ' . $table . ' WHERE ' . $column . ' = ?', [$lookupId]);
    foreach ($rows as $row) {
        write_field_history($auditId, $entityType, (int)$row['id'], (string)$row['name'], 'update', [
            ['field' => $field, 'old' => $old, 'new' => $new],
        ]);
    }
}

function propagate_application_rename(int $auditId, int $lookupId, string $old, string $new): void
{
    $pairs = db_all(
        'SELECT v.id, v.name AS vm_name, l.name AS app_name
         FROM app_vm_applications mine
         JOIN app_vms v ON v.id = mine.app_vm_id
         JOIN app_vm_applications a ON a.app_vm_id = v.id
         JOIN lookups l ON l.id = a.application_id
         WHERE mine.application_id = ?
         ORDER BY v.id',
        [$lookupId]
    );
    $grouped = [];
    foreach ($pairs as $pair) {
        $vmId = (int)$pair['id'];
        if (!isset($grouped[$vmId])) {
            $grouped[$vmId] = ['name' => (string)$pair['vm_name'], 'apps' => []];
        }
        $grouped[$vmId]['apps'][] = (string)$pair['app_name'];
    }
    foreach ($grouped as $vmId => $info) {
        $current = $info['apps'];
        sort($current, SORT_NATURAL | SORT_FLAG_CASE);
        $previous = array_map(static fn(string $name): string => $name === $new ? $old : $name, $current);
        sort($previous, SORT_NATURAL | SORT_FLAG_CASE);
        $newValue = implode(', ', $current);
        $oldValue = implode(', ', $previous);
        if ($oldValue === $newValue) {
            continue;
        }
        write_field_history($auditId, 'app_vm', $vmId, $info['name'], 'update', [
            ['field' => 'Applications', 'old' => $oldValue, 'new' => $newValue],
        ]);
    }
}

function inventory_breakdown(string $kind): array
{
    $columns = [
        'client' => 'client_id',
        'zone' => 'zone_id',
        'environment' => 'environment_id',
        'os' => 'os_id',
    ];
    if (!isset($columns[$kind])) {
        return [];
    }
    $column = $columns[$kind];
    return db_all(
        'SELECT id, name, app_count, db_count FROM (
            SELECT l.id, l.name,
                (SELECT COUNT(*) FROM app_vms a WHERE a.lifecycle = \'active\' AND a.' . $column . ' = l.id) AS app_count,
                (SELECT COUNT(*) FROM db_vms d WHERE d.lifecycle = \'active\' AND d.' . $column . ' = l.id) AS db_count
            FROM lookups l
            WHERE l.kind = ?
         ) counts
         WHERE app_count > 0 OR db_count > 0
         ORDER BY name',
        [$kind]
    );
}

function dashboard_stats(): array
{
    return [
        'app_active' => (int)db_val("SELECT COUNT(*) FROM app_vms WHERE lifecycle = 'active'"),
        'db_active' => (int)db_val("SELECT COUNT(*) FROM db_vms WHERE lifecycle = 'active'"),
        'decommissioned' => (int)db_val("SELECT (SELECT COUNT(*) FROM app_vms WHERE lifecycle = 'decommissioned') + (SELECT COUNT(*) FROM db_vms WHERE lifecycle = 'decommissioned')"),
        'redis_yes' => (int)db_val("SELECT COUNT(*) FROM db_vms WHERE lifecycle = 'active' AND redis = 1"),
        'redis_no' => (int)db_val("SELECT COUNT(*) FROM db_vms WHERE lifecycle = 'active' AND redis = 0"),
        'client_count' => (int)db_val("SELECT COUNT(*) FROM lookups WHERE kind = 'client' AND active = 1"),
        'applications' => db_all(
            'SELECT l.id, l.name, COUNT(*) AS app_count
             FROM app_vm_applications a
             JOIN lookups l ON l.id = a.application_id
             JOIN app_vms v ON v.id = a.app_vm_id AND v.lifecycle = \'active\'
             GROUP BY l.id, l.name
             ORDER BY app_count DESC, l.name'
        ),
        'clients' => inventory_breakdown('client'),
        'zones' => inventory_breakdown('zone'),
        'environments' => inventory_breakdown('environment'),
        'oses' => inventory_breakdown('os'),
        'marks' => dashboard_marks(),
        'recent' => recent_activity(8),
    ];
}

function default_client_id(): int
{
    $client = db_one("SELECT id FROM lookups WHERE kind = 'client' AND name = 'PT' AND active = 1");
    return $client ? (int)$client['id'] : 0;
}

function posted_client_id(): int
{
    $id = (int)($_POST['client_id'] ?? 0);
    return $id > 0 ? $id : default_client_id();
}

function default_zone_id(): int
{
    $zone = db_one("SELECT id FROM lookups WHERE kind = 'zone' AND active = 1 ORDER BY sort_order, name LIMIT 1");
    return $zone ? (int)$zone['id'] : 0;
}

function stack_name_clean(string $name): string
{
    $name = clean_line($name);
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
    return trim($name);
}

function canonical_stack_name(int $zoneId, string $name): string
{
    if ($name === '' || $zoneId <= 0) {
        return '';
    }
    $existing = db_one('SELECT name FROM stacks WHERE zone_id = ? AND name = ?', [$zoneId, $name]);
    return $existing ? (string)$existing['name'] : $name;
}

function stack_id_for(int $zoneId, string $name, int $actor): ?int
{
    if ($name === '' || $zoneId <= 0) {
        return null;
    }
    $existing = db_one('SELECT id FROM stacks WHERE zone_id = ? AND name = ?', [$zoneId, $name]);
    if ($existing) {
        return (int)$existing['id'];
    }
    try {
        return db_insert(
            'INSERT INTO stacks (zone_id, name, created_by) VALUES (?,?,?)',
            [$zoneId, $name, $actor > 0 ? $actor : null]
        );
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        $existing = db_one('SELECT id FROM stacks WHERE zone_id = ? AND name = ?', [$zoneId, $name]);
        return $existing ? (int)$existing['id'] : null;
    }
}

function stacks_catalog(): array
{
    $catalog = [];
    foreach (db_all('SELECT zone_id, name FROM stacks ORDER BY name') as $row) {
        $catalog[(string)(int)$row['zone_id']][] = (string)$row['name'];
    }
    return $catalog;
}

function zone_tabs(): array
{
    return db_all(
        "SELECT id, name FROM lookups WHERE kind = 'zone' AND active = 1 ORDER BY sort_order, name"
    );
}

function zone_find(int $id): ?array
{
    $row = db_one(
        "SELECT id, name, active FROM lookups WHERE id = ? AND kind = 'zone'",
        [$id]
    );
    return $row ?: null;
}

function board_rows(string $which, string $where, array $params): array
{
    if ($which === 'app') {
        $rows = db_all(
            'SELECT v.id, v.name, v.internal_ip, v.external_ip, v.url, v.brotherhood_url, v.status_page_url,
                    v.notes, v.lifecycle, v.zone_id, v.stack_id,
                    c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                    z.name AS zone_name, z.sort_order AS zone_sort, s.name AS stack_name '
            . app_from_sql() . ' WHERE ' . $where . ' ORDER BY v.name, v.id',
            $params
        );
        return hydrate_app_rows($rows);
    }
    $rows = db_all(
        'SELECT v.id, v.name, v.internal_ip, v.redis, v.notes, v.lifecycle, v.zone_id, v.stack_id,
                c.name AS client_name, e.name AS environment_name, o.name AS os_name,
                z.name AS zone_name, z.sort_order AS zone_sort, s.name AS stack_name '
        . db_from_sql() . ' WHERE ' . $where . ' ORDER BY v.name, v.id',
        $params
    );
    return hydrate_db_rows($rows);
}

function pair_groups(array $apps, array $dbs): array
{
    $groups = [];
    $touch = static function (array &$groups, array $row, string $side): string {
        $stackId = (int)($row['stack_id'] ?? 0);
        $zoneId = (int)$row['zone_id'];
        $key = $stackId > 0 ? $zoneId . ':' . $stackId : $zoneId . ':' . $side . ':' . (int)$row['id'];
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'key' => $key,
                'stack_id' => $stackId,
                'name' => $stackId > 0 ? (string)($row['stack_name'] ?? '') : '',
                'zone_id' => $zoneId,
                'zone_name' => (string)($row['zone_name'] ?? ''),
                'zone_sort' => (int)($row['zone_sort'] ?? 0),
                'apps' => [],
                'dbs' => [],
            ];
        }
        return $key;
    };
    foreach ($apps as $row) {
        $groups[$touch($groups, $row, 'app')]['apps'][] = $row;
    }
    foreach ($dbs as $row) {
        $groups[$touch($groups, $row, 'db')]['dbs'][] = $row;
    }
    $list = array_values($groups);
    usort($list, static function (array $a, array $b): int {
        $byZone = $a['zone_sort'] <=> $b['zone_sort'];
        if ($byZone !== 0) {
            return $byZone;
        }
        $byName = strcasecmp($a['zone_name'], $b['zone_name']);
        if ($byName !== 0) {
            return $byName;
        }
        if (($a['zone_id'] <=> $b['zone_id']) !== 0) {
            return $a['zone_id'] <=> $b['zone_id'];
        }
        if ($a['stack_id'] === 0 || $b['stack_id'] === 0) {
            if ($a['stack_id'] !== $b['stack_id']) {
                return $a['stack_id'] === 0 ? 1 : -1;
            }
            $aName = (string)(($a['apps'][0]['name'] ?? $a['dbs'][0]['name'] ?? ''));
            $bName = (string)(($b['apps'][0]['name'] ?? $b['dbs'][0]['name'] ?? ''));
            return strnatcasecmp($aName, $bName);
        }
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $list;
}

function environment_board(int $zoneId): array
{
    $apps = board_rows('app', "v.zone_id = ? AND v.lifecycle = 'active'", [$zoneId]);
    $dbs = board_rows('db', "v.zone_id = ? AND v.lifecycle = 'active'", [$zoneId]);
    return pair_groups($apps, $dbs);
}

function id_clause(string $column, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return ['0 = 1', []];
    }
    return [$column . ' IN (' . placeholders($ids) . ')', $ids];
}

function inventory_search(string $q): array
{
    $q = trim($q);
    $empty = ['query' => $q, 'groups' => [], 'count' => 0, 'hits' => []];
    if ($q === '') {
        return $empty;
    }
    $appFilters = [
        'q' => $q,
        'client_id' => 0,
        'environment_id' => 0,
        'os_id' => 0,
        'application_id' => 0,
        'link' => '',
        'page' => 1,
    ];
    $dbFilters = [
        'q' => $q,
        'client_id' => 0,
        'environment_id' => 0,
        'os_id' => 0,
        'redis' => '',
        'link' => '',
        'page' => 1,
    ];
    [$appWhere, $appParams] = app_where($appFilters, 'active');
    [$dbWhere, $dbParams] = db_where($dbFilters, 'active');
    $appHits = db_all('SELECT v.id, v.stack_id ' . app_from_sql() . ' WHERE ' . $appWhere, $appParams);
    $dbHits = db_all('SELECT v.id, v.stack_id ' . db_from_sql() . ' WHERE ' . $dbWhere, $dbParams);
    $hits = [];
    $stackIds = [];
    $loneApps = [];
    $loneDbs = [];
    foreach ($appHits as $row) {
        $hits['app-' . (int)$row['id']] = true;
        if ((int)$row['stack_id'] > 0) {
            $stackIds[] = (int)$row['stack_id'];
        } else {
            $loneApps[] = (int)$row['id'];
        }
    }
    foreach ($dbHits as $row) {
        $hits['db-' . (int)$row['id']] = true;
        if ((int)$row['stack_id'] > 0) {
            $stackIds[] = (int)$row['stack_id'];
        } else {
            $loneDbs[] = (int)$row['id'];
        }
    }
    if (!$hits) {
        return $empty;
    }
    $appWhere = [];
    $appParams = [];
    $dbWhere = [];
    $dbParams = [];
    if ($stackIds) {
        [$sql, $params] = id_clause('v.stack_id', $stackIds);
        $appWhere[] = $sql;
        $dbWhere[] = $sql;
        $appParams = array_merge($appParams, $params);
        $dbParams = array_merge($dbParams, $params);
    }
    if ($loneApps) {
        [$sql, $params] = id_clause('v.id', $loneApps);
        $appWhere[] = $sql;
        $appParams = array_merge($appParams, $params);
    }
    if ($loneDbs) {
        [$sql, $params] = id_clause('v.id', $loneDbs);
        $dbWhere[] = $sql;
        $dbParams = array_merge($dbParams, $params);
    }
    $apps = $appWhere ? board_rows('app', "v.lifecycle = 'active' AND (" . implode(' OR ', $appWhere) . ')', $appParams) : [];
    $dbs = $dbWhere ? board_rows('db', "v.lifecycle = 'active' AND (" . implode(' OR ', $dbWhere) . ')', $dbParams) : [];
    return [
        'query' => $q,
        'groups' => pair_groups($apps, $dbs),
        'count' => count($hits),
        'hits' => $hits,
    ];
}
