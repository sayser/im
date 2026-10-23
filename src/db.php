<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = app_config()['db'] ?? null;
    if (!is_array($c) || !isset($c['name'], $c['user']) || $c['name'] === '' || $c['user'] === '') {
        render_setup_needed('bad-config');
    }

    $host = (string)($c['host'] ?? '127.0.0.1');
    $name = (string)$c['name'];
    if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $host) || !preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        render_setup_needed('bad-config');
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        (int)($c['port'] ?? 3306),
        $name,
        'utf8mb4'
    );

    $pdo = new PDO($dsn, (string)$c['user'], (string)($c['pass'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_LOCAL_INFILE => false,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+00:00'");
    $pdo->exec("SET SESSION sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION,ERROR_FOR_DIVISION_BY_ZERO,NO_ZERO_DATE,NO_ZERO_IN_DATE'");

    return $pdo;
}

function schema_ready(): bool
{
    $have = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (!is_array($have)) {
        return false;
    }
    $have = array_map(static fn($table): string => strtolower((string)$table), $have);
    foreach (['users', 'lookups', 'stacks', 'app_vms', 'db_vms', 'app_vm_urls', 'app_vm_applications', 'app_db_links', 'audit_events', 'field_history', 'mark_colors', 'marks', 'mark_app_vms', 'mark_db_vms', 'saved_views'] as $table) {
        if (!in_array($table, $have, true)) {
            return false;
        }
    }
    return true;
}

function db_all(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    return $stmt->fetchAll();
}

function db_one(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

function db_val(string $sql, array $params = []): mixed
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
    $value = $stmt->fetchColumn();
    return $value === false ? null : $value;
}

function db_exec(string $sql, array $params = []): void
{
    $stmt = db()->prepare($sql);
    $stmt->execute(array_values($params));
}

function db_insert(string $sql, array $params = []): int
{
    db_exec($sql, $params);
    return (int)db()->lastInsertId();
}

function db_tx(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function like_contains(string $value): string
{
    return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value) . '%';
}

function sql_ident(string $name): string
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name) !== 1) {
        throw new InvalidArgumentException('Invalid SQL identifier.');
    }
    return $name;
}

function like_sql(string $column): string
{
    return sql_ident($column) . " LIKE ? ESCAPE '\\\\'";
}

function placeholders(array $values): string
{
    return implode(',', array_fill(0, count($values), '?'));
}
