<?php
declare(strict_types=1);

function saved_view_sides(): array
{
    return [
        'app' => 'APP VMs',
        'db' => 'DB VMs',
        'both' => 'APP and DB',
    ];
}

function saved_view_rows(int $userId): array
{
    return db_all(
        'SELECT v.id, v.name, v.vm_side, v.zone_id, v.os_id, v.environment_id,
                z.name AS zone_name, o.name AS os_name, e.name AS deployment_name
         FROM saved_views v
         LEFT JOIN lookups z ON z.id = v.zone_id
         LEFT JOIN lookups o ON o.id = v.os_id
         LEFT JOIN lookups e ON e.id = v.environment_id
         WHERE v.user_id = ?
         ORDER BY v.name, v.id',
        [$userId]
    );
}

function saved_view_find(int $id, int $userId): ?array
{
    $row = db_one(
        'SELECT v.id, v.user_id, v.name, v.vm_side, v.zone_id, v.os_id, v.environment_id,
                z.name AS zone_name, o.name AS os_name, e.name AS deployment_name
         FROM saved_views v
         LEFT JOIN lookups z ON z.id = v.zone_id
         LEFT JOIN lookups o ON o.id = v.os_id
         LEFT JOIN lookups e ON e.id = v.environment_id
         WHERE v.id = ? AND v.user_id = ?',
        [$id, $userId]
    );
    return $row ?: null;
}

function saved_view_summary(array $view): string
{
    $sides = saved_view_sides();
    $side = $sides[(string)($view['vm_side'] ?? '')] ?? 'APP VMs';
    return implode(' · ', [
        $side,
        (string)($view['zone_name'] ?? '') !== '' ? (string)$view['zone_name'] : 'Any environment',
        (string)($view['os_name'] ?? '') !== '' ? (string)$view['os_name'] : 'Any OS',
        (string)($view['deployment_name'] ?? '') !== '' ? (string)$view['deployment_name'] : 'Any deployment',
    ]);
}

function saved_view_where(array $view): array
{
    $where = ["v.lifecycle = 'active'"];
    $params = [];
    foreach (['zone_id', 'os_id', 'environment_id'] as $column) {
        $id = (int)($view[$column] ?? 0);
        if ($id > 0) {
            $where[] = 'v.' . $column . ' = ?';
            $params[] = $id;
        }
    }
    return [implode(' AND ', $where), $params];
}

function saved_view_count(array $view): int
{
    [$where, $params] = saved_view_where($view);
    $count = 0;
    $side = (string)($view['vm_side'] ?? 'app');
    if ($side !== 'db') {
        $count += (int)db_val('SELECT COUNT(*) FROM app_vms v WHERE ' . $where, $params);
    }
    if ($side !== 'app') {
        $count += (int)db_val('SELECT COUNT(*) FROM db_vms v WHERE ' . $where, $params);
    }
    return $count;
}

function saved_view_matches(array $view): array
{
    [$where, $params] = saved_view_where($view);
    $side = (string)($view['vm_side'] ?? 'app');
    return [
        'apps' => $side === 'db' ? [] : board_rows('app', $where, $params),
        'dbs' => $side === 'app' ? [] : board_rows('db', $where, $params),
    ];
}

function saved_view_lookup(int $id, string $kind, int $previous): ?string
{
    if ($id <= 0) {
        return null;
    }
    if (!lookup_selectable($kind, $id, $previous)) {
        return match ($kind) {
            'zone' => 'That environment is no longer available.',
            'os' => 'That operating system is no longer available.',
            default => 'That deployment is no longer available.',
        };
    }
    return null;
}

function saved_view_save(?int $id, int $userId, string $name, string $side, int $zoneId, int $osId, int $environmentId): array
{
    $name = clean_line($name);
    if ($name === '' || mb_strlen($name) > 120) {
        return ['error' => 'Enter a name up to 120 characters.', 'id' => 0];
    }
    if (!isset(saved_view_sides()[$side])) {
        return ['error' => 'Choose APP VMs, DB VMs, or both.', 'id' => 0];
    }
    $existing = null;
    if ($id !== null) {
        $existing = saved_view_find($id, $userId);
        if (!$existing) {
            return ['error' => 'That view is no longer available.', 'id' => 0];
        }
    } else {
        $owned = (int)db_val('SELECT COUNT(*) FROM saved_views WHERE user_id = ?', [$userId]);
        if ($owned >= 50) {
            return ['error' => 'You can save up to 50 views.', 'id' => 0];
        }
    }
    $previousZone = $existing ? (int)$existing['zone_id'] : 0;
    $previousOs = $existing ? (int)$existing['os_id'] : 0;
    $previousDeployment = $existing ? (int)$existing['environment_id'] : 0;
    if ($message = saved_view_lookup($zoneId, 'zone', $previousZone)) {
        return ['error' => $message, 'id' => 0];
    }
    if ($message = saved_view_lookup($osId, 'os', $previousOs)) {
        return ['error' => $message, 'id' => 0];
    }
    if ($message = saved_view_lookup($environmentId, 'environment', $previousDeployment)) {
        return ['error' => $message, 'id' => 0];
    }
    $taken = db_one(
        'SELECT id FROM saved_views WHERE user_id = ? AND name = ? AND id <> ?',
        [$userId, $name, $existing ? (int)$existing['id'] : 0]
    );
    if ($taken) {
        return ['error' => 'You already have a view with that name.', 'id' => 0];
    }
    $zoneValue = $zoneId > 0 ? $zoneId : null;
    $osValue = $osId > 0 ? $osId : null;
    $deploymentValue = $environmentId > 0 ? $environmentId : null;
    if (!$existing) {
        $newId = db_insert(
            'INSERT INTO saved_views (user_id, name, vm_side, zone_id, os_id, environment_id) VALUES (?,?,?,?,?,?)',
            [$userId, $name, $side, $zoneValue, $osValue, $deploymentValue]
        );
        return ['error' => null, 'id' => $newId];
    }
    db_exec(
        'UPDATE saved_views SET name = ?, vm_side = ?, zone_id = ?, os_id = ?, environment_id = ? WHERE id = ? AND user_id = ?',
        [$name, $side, $zoneValue, $osValue, $deploymentValue, (int)$existing['id'], $userId]
    );
    return ['error' => null, 'id' => (int)$existing['id']];
}

function saved_view_delete(int $id, int $userId): bool
{
    $existing = saved_view_find($id, $userId);
    if (!$existing) {
        return false;
    }
    db_exec('DELETE FROM saved_views WHERE id = ? AND user_id = ?', [$id, $userId]);
    return true;
}
