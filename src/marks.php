<?php
declare(strict_types=1);

function mark_swatches(): array
{
    return ['amber', 'red', 'orange', 'teal', 'blue', 'violet', 'pink', 'slate'];
}

function mark_swatch_class(string $swatch): string
{
    return in_array($swatch, mark_swatches(), true) ? 'swatch-' . $swatch : '';
}

function mark_card_class(array $marks): string
{
    foreach ($marks as $mark) {
        $class = mark_swatch_class((string)($mark['swatch'] ?? ''));
        if ($class !== '' && ($mark['status'] ?? 'open') === 'open') {
            return ' has-mark ' . $class;
        }
    }
    return '';
}

function mark_chips(array $marks): string
{
    $html = '';
    foreach ($marks as $mark) {
        if (($mark['status'] ?? 'open') !== 'open') {
            continue;
        }
        $class = mark_swatch_class((string)($mark['swatch'] ?? ''));
        if ($class === '') {
            continue;
        }
        $html .= '<span class="mark-chip ' . $class . '">' . e((string)$mark['name']) . '</span>';
    }
    return $html === '' ? '' : '<span class="mark-chips">' . $html . '</span>';
}

function mark_side(string $side): ?string
{
    return in_array($side, ['app', 'db'], true) ? $side : null;
}

function mark_member_table(string $side): ?array
{
    return match ($side) {
        'app' => ['mark_app_vms', 'app_vm_id', 'app_vms', 'app_vm', 'APP VM'],
        'db' => ['mark_db_vms', 'db_vm_id', 'db_vms', 'db_vm', 'DB VM'],
        default => null,
    };
}

function invalid_mark_note(string $note): ?string
{
    if (mb_strlen($note) > 500) {
        return 'Use 500 characters or fewer.';
    }
    return null;
}

function mark_color_usable(string $swatch, string $current = ''): bool
{
    if (!in_array($swatch, mark_swatches(), true)) {
        return false;
    }
    $row = db_one('SELECT active FROM mark_colors WHERE swatch = ?', [$swatch]);
    if (!$row) {
        return false;
    }
    return (int)$row['active'] === 1 || $swatch === $current;
}

function mark_color_choices(string $current = ''): array
{
    return db_all(
        'SELECT swatch, name, active FROM mark_colors WHERE active = 1 OR swatch = ? ORDER BY sort_order, name',
        [$current]
    );
}

function mark_color_admin(): array
{
    return db_all(
        'SELECT c.swatch, c.name, c.active, c.sort_order,
                (SELECT COUNT(*) FROM marks m WHERE m.swatch = c.swatch) AS used
         FROM mark_colors c
         ORDER BY c.sort_order, c.name'
    );
}

function mark_color_update(string $swatch, string $name, string $action): ?string
{
    if (!in_array($swatch, mark_swatches(), true)) {
        return 'That color is not available.';
    }
    $row = db_one('SELECT swatch, name, active FROM mark_colors WHERE swatch = ?', [$swatch]);
    if (!$row) {
        return 'That color is not available.';
    }
    if ($action === 'rename') {
        if ($message = invalid_lookup_name($name)) {
            return $message;
        }
        if ($name === (string)$row['name']) {
            return 'unchanged';
        }
        $taken = db_one('SELECT swatch FROM mark_colors WHERE name = ? AND swatch <> ?', [$name, $swatch]);
        if ($taken) {
            return 'That color name is already in use.';
        }
        db_exec('UPDATE mark_colors SET name = ? WHERE swatch = ?', [$name, $swatch]);
        audit_log('mark_color.update', 'Renamed mark color "' . $row['name'] . '" to "' . $name . '"', 'mark_color', null);
        return null;
    }
    if ($action === 'retire' || $action === 'restore') {
        $active = $action === 'restore' ? 1 : 0;
        if ((int)$row['active'] === $active) {
            return 'unchanged';
        }
        db_exec('UPDATE mark_colors SET active = ? WHERE swatch = ?', [$active, $swatch]);
        audit_log(
            'mark_color.update',
            ($active === 1 ? 'Restored' : 'Retired') . ' mark color "' . $row['name'] . '"',
            'mark_color',
            null
        );
        return null;
    }
    return 'That action is not available.';
}

function mark_rows(string $status): array
{
    if (!in_array($status, ['open', 'closed'], true)) {
        return [];
    }
    $rows = db_all(
        'SELECT mk.id, mk.name, mk.swatch, mk.note, mk.status, c.name AS color_name,
                (SELECT COUNT(*) FROM mark_app_vms a WHERE a.mark_id = mk.id) AS app_count,
                (SELECT COUNT(*) FROM mark_db_vms d WHERE d.mark_id = mk.id) AS db_count
         FROM marks mk
         JOIN mark_colors c ON c.swatch = mk.swatch
         WHERE mk.status = ?
         ORDER BY mk.name, mk.id',
        [$status]
    );
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = (int)$row['id'];
        $rows[$i]['app_count'] = (int)$row['app_count'];
        $rows[$i]['db_count'] = (int)$row['db_count'];
    }
    return $rows;
}

function mark_find(int $id): ?array
{
    $row = db_one(
        'SELECT mk.id, mk.name, mk.swatch, mk.note, mk.status, mk.closed_at, c.name AS color_name,
                u.display_name AS closed_by_name
         FROM marks mk
         JOIN mark_colors c ON c.swatch = mk.swatch
         LEFT JOIN users u ON u.id = mk.closed_by
         WHERE mk.id = ?',
        [$id]
    );
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['note'] = (string)($row['note'] ?? '');
    return $row;
}

function open_mark_options(): array
{
    return db_all(
        "SELECT id, name, swatch FROM marks WHERE status = 'open' ORDER BY name, id"
    );
}

function attach_open_marks(array $rows, string $side): array
{
    $spec = mark_member_table($side);
    if (!$rows || $spec === null) {
        return $rows;
    }
    [$table, $column] = $spec;
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $found = db_all(
        'SELECT mvm.' . $column . ' AS owner_id, mk.id, mk.name, mk.swatch, mk.status, c.name AS color_name
         FROM ' . $table . ' mvm
         JOIN marks mk ON mk.id = mvm.mark_id AND mk.status = \'open\'
         JOIN mark_colors c ON c.swatch = mk.swatch
         WHERE mvm.' . $column . ' IN (' . placeholders($ids) . ')
         ORDER BY mk.name, mk.id',
        $ids
    );
    $grouped = [];
    foreach ($ids as $id) {
        $grouped[$id] = [];
    }
    foreach ($found as $mark) {
        $grouped[(int)$mark['owner_id']][] = [
            'id' => (int)$mark['id'],
            'name' => (string)$mark['name'],
            'swatch' => (string)$mark['swatch'],
            'status' => (string)$mark['status'],
            'color_name' => (string)$mark['color_name'],
        ];
    }
    foreach ($rows as $i => $row) {
        $rows[$i]['marks'] = $grouped[(int)$row['id']] ?? [];
    }
    return $rows;
}

function vm_marks(string $side, int $vmId): array
{
    $spec = mark_member_table($side);
    if ($spec === null || $vmId <= 0) {
        return [];
    }
    [$table, $column] = $spec;
    $rows = db_all(
        'SELECT mk.id, mk.name, mk.swatch, mk.status, c.name AS color_name
         FROM ' . $table . ' mvm
         JOIN marks mk ON mk.id = mvm.mark_id
         JOIN mark_colors c ON c.swatch = mk.swatch
         WHERE mvm.' . $column . ' = ?
         ORDER BY mk.status, mk.name, mk.id',
        [$vmId]
    );
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = (int)$row['id'];
    }
    return $rows;
}

function mark_save(?int $id, string $name, string $swatch, string $note): array
{
    $current = '';
    $existing = null;
    if ($id !== null) {
        $existing = mark_find($id);
        if (!$existing) {
            return ['error' => 'That mark was not found.', 'id' => 0];
        }
        $current = (string)$existing['swatch'];
    }
    if ($message = invalid_lookup_name($name)) {
        return ['error' => $message, 'id' => (int)$id];
    }
    if ($message = invalid_mark_note($note)) {
        return ['error' => $message, 'id' => (int)$id];
    }
    if (!mark_color_usable($swatch, $current)) {
        return ['error' => 'Select a color.', 'id' => (int)$id];
    }
    $taken = db_one('SELECT id FROM marks WHERE name = ? AND id <> ?', [$name, (int)$id]);
    if ($taken) {
        return ['error' => 'That mark name is already in use.', 'id' => (int)$id];
    }
    $actor = auth_user();
    $actorId = $actor ? (int)$actor['id'] : null;
    $noteValue = $note === '' ? null : $note;
    if ($existing === null) {
        $newId = db_insert(
            'INSERT INTO marks (name, swatch, note, created_by) VALUES (?,?,?,?)',
            [$name, $swatch, $noteValue, $actorId]
        );
        audit_log('mark.create', 'Created mark ' . $name, 'mark', $newId);
        return ['error' => null, 'id' => $newId];
    }
    $same = $name === $existing['name'] && $swatch === $existing['swatch'] && $note === $existing['note'];
    if ($same) {
        return ['error' => 'unchanged', 'id' => (int)$existing['id']];
    }
    db_exec('UPDATE marks SET name = ?, swatch = ?, note = ? WHERE id = ?', [$name, $swatch, $noteValue, (int)$existing['id']]);
    audit_log('mark.update', 'Updated mark ' . $name, 'mark', (int)$existing['id']);
    return ['error' => null, 'id' => (int)$existing['id']];
}

function mark_set_status(int $id, string $status): ?string
{
    $mark = mark_find($id);
    if (!$mark) {
        return 'That mark was not found.';
    }
    if (!in_array($status, ['open', 'closed'], true) || $mark['status'] === $status) {
        return 'unchanged';
    }
    $actor = auth_user();
    $actorId = $actor ? (int)$actor['id'] : null;
    if ($status === 'closed') {
        db_exec(
            'UPDATE marks SET status = \'closed\', closed_at = UTC_TIMESTAMP(), closed_by = ? WHERE id = ?',
            [$actorId, $id]
        );
        audit_log('mark.close', 'Closed mark ' . $mark['name'], 'mark', $id);
        return null;
    }
    db_exec(
        'UPDATE marks SET status = \'open\', closed_at = NULL, closed_by = NULL WHERE id = ?',
        [$id]
    );
    audit_log('mark.reopen', 'Reopened mark ' . $mark['name'], 'mark', $id);
    return null;
}

function mark_picker_catalog(int $markId): array
{
    $appIds = [];
    $dbIds = [];
    foreach (db_all('SELECT app_vm_id FROM mark_app_vms WHERE mark_id = ?', [$markId]) as $row) {
        $appIds[(int)$row['app_vm_id']] = true;
    }
    foreach (db_all('SELECT db_vm_id FROM mark_db_vms WHERE mark_id = ?', [$markId]) as $row) {
        $dbIds[(int)$row['db_vm_id']] = true;
    }
    $sql = 'SELECT v.id, v.name, v.internal_ip, v.os_id, v.zone_id, v.environment_id,
                   o.name AS os_name, z.name AS zone_name, e.name AS environment_name
            FROM %s v
            JOIN lookups o ON o.id = v.os_id
            JOIN lookups z ON z.id = v.zone_id
            JOIN lookups e ON e.id = v.environment_id
            WHERE v.lifecycle = \'active\'
            ORDER BY v.name, v.id';
    $out = [];
    foreach ([['app', 'app_vms', $appIds], ['db', 'db_vms', $dbIds]] as [$side, $table, $have]) {
        foreach (db_all(sprintf($sql, $table)) as $vm) {
            $id = (int)$vm['id'];
            $out[] = [
                'side' => $side,
                'id' => $id,
                'name' => (string)$vm['name'],
                'os_id' => (int)$vm['os_id'],
                'zone_id' => (int)$vm['zone_id'],
                'environment_id' => (int)$vm['environment_id'],
                'os' => (string)$vm['os_name'],
                'zone' => (string)$vm['zone_name'],
                'deployment' => (string)$vm['environment_name'],
                'ip' => (string)$vm['internal_ip'],
                'added' => isset($have[$id]),
            ];
        }
    }
    return $out;
}

function mark_add_selected(int $markId, array $tokens): array
{
    $apps = [];
    $dbs = [];
    foreach ($tokens as $token) {
        if (preg_match('/^(app|db):([1-9][0-9]*)$/', (string)$token, $match) !== 1) {
            continue;
        }
        if ($match[1] === 'app') {
            $apps[] = (int)$match[2];
        } else {
            $dbs[] = (int)$match[2];
        }
    }
    $apps = array_values(array_unique($apps));
    $dbs = array_values(array_unique($dbs));
    if (!$apps && !$dbs) {
        return ['error' => 'Select at least one VM.', 'count' => 0];
    }
    if (count($apps) + count($dbs) > 500) {
        return ['error' => 'Select 500 or fewer VMs at a time.', 'count' => 0];
    }
    $count = 0;
    foreach (['app' => $apps, 'db' => $dbs] as $side => $ids) {
        if (!$ids) {
            continue;
        }
        $result = mark_change_members($markId, $side, $ids, true);
        if ($result['error']) {
            return $result;
        }
        $count += $result['count'];
    }
    return ['error' => null, 'count' => $count];
}

function mark_delete(int $id): ?string
{
    $mark = mark_find($id);
    if (!$mark) {
        return 'That mark was not found.';
    }
    db_tx(function () use ($mark, $id): void {
        audit_log('mark.delete', 'Deleted mark ' . $mark['name'], 'mark', $id);
        db_exec('DELETE FROM marks WHERE id = ?', [$id]);
    });
    return null;
}

function mark_change_members(int $markId, string $side, array $ids, bool $add): array
{
    $mark = mark_find($markId);
    if (!$mark) {
        return ['error' => 'That mark was not found.', 'count' => 0];
    }
    $spec = mark_member_table($side);
    if ($spec === null) {
        return ['error' => 'Select APP or DB servers.', 'count' => 0];
    }
    [$linkTable, $column, $vmTable, $entity, $label] = $spec;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return ['error' => null, 'count' => 0];
    }
    if ($add && $mark['status'] !== 'open') {
        return ['error' => 'Open this mark before adding servers.', 'count' => 0];
    }
    if (count($ids) > 500) {
        return ['error' => 'Narrow the filter. 500 is the most that can be added at once.', 'count' => 0];
    }
    $vms = db_all(
        'SELECT id, name FROM ' . $vmTable . ' WHERE id IN (' . placeholders($ids) . ')',
        $ids
    );
    if (count($vms) !== count($ids)) {
        return ['error' => 'One of the selected servers is no longer available.', 'count' => 0];
    }
    $actor = auth_user();
    $actorId = $actor ? (int)$actor['id'] : null;
    $changed = [];
    db_tx(function () use ($add, $linkTable, $column, $markId, $vms, $actorId, $entity, $label, $mark, &$changed): void {
        foreach ($vms as $vm) {
            $vmId = (int)$vm['id'];
            $present = db_one(
                'SELECT 1 AS ok FROM ' . $linkTable . ' WHERE mark_id = ? AND ' . $column . ' = ?',
                [$markId, $vmId]
            );
            if ($add && $present) {
                continue;
            }
            if (!$add && !$present) {
                continue;
            }
            if ($add) {
                db_exec(
                    'INSERT INTO ' . $linkTable . ' (mark_id, ' . $column . ', created_by) VALUES (?,?,?)',
                    [$markId, $vmId, $actorId]
                );
            } else {
                db_exec(
                    'DELETE FROM ' . $linkTable . ' WHERE mark_id = ? AND ' . $column . ' = ?',
                    [$markId, $vmId]
                );
            }
            $changed[] = $vm;
        }
        if (!$changed) {
            return;
        }
        $verb = $add ? 'Added' : 'Removed';
        $summary = count($changed) === 1
            ? $verb . ' ' . $label . ' ' . $changed[0]['name'] . ($add ? ' to ' : ' from ') . $mark['name']
            : $verb . ' ' . count($changed) . ' ' . $label . 's ' . ($add ? 'to ' : 'from ') . $mark['name'];
        $auditId = audit_log('mark.members', $summary, 'mark', $markId);
        foreach ($changed as $vm) {
            write_field_history($auditId, $entity, (int)$vm['id'], (string)$vm['name'], 'update', [[
                'field' => 'Mark',
                'old' => $add ? '' : $mark['name'],
                'new' => $add ? $mark['name'] : '',
            ]]);
        }
    });
    return ['error' => null, 'count' => count($changed)];
}

function mark_frames(string $status, ?int $onlyId = null): array
{
    $marks = mark_rows($status);
    if ($onlyId !== null) {
        $marks = array_values(array_filter(
            $marks,
            static fn(array $mark): bool => (int)$mark['id'] === $onlyId
        ));
    }
    if (!$marks) {
        return [];
    }
    $markIds = array_map(static fn(array $mark): int => (int)$mark['id'], $marks);
    $appLinks = db_all(
        'SELECT mark_id, app_vm_id FROM mark_app_vms WHERE mark_id IN (' . placeholders($markIds) . ')',
        $markIds
    );
    $dbLinks = db_all(
        'SELECT mark_id, db_vm_id FROM mark_db_vms WHERE mark_id IN (' . placeholders($markIds) . ')',
        $markIds
    );
    $appIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['app_vm_id'], $appLinks)));
    $dbIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['db_vm_id'], $dbLinks)));
    $apps = [];
    $dbs = [];
    if ($appIds) {
        foreach (board_rows('app', 'v.id IN (' . placeholders($appIds) . ')', $appIds) as $row) {
            $apps[(int)$row['id']] = $row;
        }
    }
    if ($dbIds) {
        foreach (board_rows('db', 'v.id IN (' . placeholders($dbIds) . ')', $dbIds) as $row) {
            $dbs[(int)$row['id']] = $row;
        }
    }
    foreach ($marks as $i => $mark) {
        $marks[$i]['apps'] = [];
        $marks[$i]['dbs'] = [];
    }
    $index = [];
    foreach ($marks as $i => $mark) {
        $index[(int)$mark['id']] = $i;
    }
    foreach ($appLinks as $link) {
        $vm = $apps[(int)$link['app_vm_id']] ?? null;
        if ($vm) {
            $marks[$index[(int)$link['mark_id']]]['apps'][] = $vm;
        }
    }
    foreach ($dbLinks as $link) {
        $vm = $dbs[(int)$link['db_vm_id']] ?? null;
        if ($vm) {
            $marks[$index[(int)$link['mark_id']]]['dbs'][] = $vm;
        }
    }
    return $marks;
}

function dashboard_marks(): array
{
    $rows = mark_rows('open');
    foreach ($rows as $i => $row) {
        $rows[$i]['name'] = (string)$row['name'];
    }
    return $rows;
}
