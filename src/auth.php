<?php
declare(strict_types=1);

function auth_user(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;
    if ($refresh) {
        $loaded = false;
        $user = null;
    }
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $id = (int)($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return $user = null;
    }
    $row = db_one(
        'SELECT id, username, display_name, role, active, must_change_password FROM users WHERE id = ?',
        [$id]
    );
    if (!$row || (int)$row['active'] !== 1 || !session_client_matches()) {
        $_SESSION = [];
        session_regenerate_id(true);
        return $user = null;
    }
    $row['id'] = (int)$row['id'];
    $row['active'] = (int)$row['active'];
    $row['must_change_password'] = (int)$row['must_change_password'];
    return $user = $row;
}

function auth_refresh(): void
{
    auth_user(true);
}

function require_login(): array
{
    $user = auth_user();
    if (!$user) {
        if (empty($_SESSION['_flash'])) {
            flash('error', 'Sign in to continue.');
        }
        redirect('/login');
    }
    return $user;
}

function require_writer(): array
{
    $user = require_login();
    if (!in_array($user['role'], ['admin', 'ops'], true)) {
        abort(403);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        abort(403);
    }
    return $user;
}

function is_admin(): bool
{
    return (auth_user()['role'] ?? '') === 'admin';
}

function can_write(): bool
{
    return in_array(auth_user()['role'] ?? '', ['admin', 'ops'], true);
}

function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Admin',
        'ops' => 'OPs',
        'view' => 'View-only',
        default => $role,
    };
}

function user_count(): int
{
    return (int)db_val('SELECT COUNT(*) FROM users');
}

function active_admin_count(): int
{
    return (int)db_val("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1");
}

function find_user(int $id): ?array
{
    $row = db_one(
        'SELECT id, username, display_name, role, active, must_change_password, created_at, updated_at FROM users WHERE id = ?',
        [$id]
    );
    if (!$row) {
        return null;
    }
    $row['id'] = (int)$row['id'];
    $row['active'] = (int)$row['active'];
    $row['must_change_password'] = (int)$row['must_change_password'];
    return $row;
}

function users_list(string $q): array
{
    if ($q === '') {
        $rows = db_all('SELECT id, username, display_name, role, active, updated_at FROM users ORDER BY username');
    } else {
        $like = like_contains($q);
        $rows = db_all(
            'SELECT id, username, display_name, role, active, updated_at FROM users WHERE '
            . like_sql('username') . ' OR ' . like_sql('display_name') . ' ORDER BY username',
            [$like, $like]
        );
    }
    foreach ($rows as $i => $row) {
        $rows[$i]['id'] = (int)$row['id'];
        $rows[$i]['active'] = (int)$row['active'];
    }
    return $rows;
}

function user_password_hash(int $id): ?string
{
    $hash = db_val('SELECT password_hash FROM users WHERE id = ?', [$id]);
    return $hash === null ? null : (string)$hash;
}

function invalid_username(string $username): ?string
{
    if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username) !== 1) {
        return 'Use 3–64 characters: letters, numbers, dot, underscore, or hyphen.';
    }
    return null;
}

function invalid_display_name(string $name): ?string
{
    if ($name === '') {
        return 'Enter a display name.';
    }
    if (mb_strlen($name) > 120) {
        return 'Use 120 characters or fewer.';
    }
    return null;
}

function invalid_password(string $password): ?string
{
    if (strlen($password) < 12) {
        return 'Use at least 12 characters.';
    }
    if (strlen($password) > 72) {
        return 'Use 72 characters or fewer.';
    }
    if (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
        return 'Use at least one letter and one number.';
    }
    return null;
}

function password_options(): array
{
    if (defined('PASSWORD_ARGON2ID')) {
        return [PASSWORD_ARGON2ID, ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1]];
    }
    return [PASSWORD_BCRYPT, ['cost' => 12]];
}

function password_make(string $password): string
{
    [$algo, $options] = password_options();
    $hash = password_hash($password, $algo, $options);
    if (!is_string($hash) || $hash === '') {
        throw new RuntimeException('Could not store the password.');
    }
    return $hash;
}

function password_is_stale(string $hash): bool
{
    [$algo, $options] = password_options();
    return password_needs_rehash($hash, $algo, $options);
}

function dummy_password_hash(): string
{
    static $hash = null;
    if ($hash === null) {
        $hash = password_make('not-a-real-password');
    }
    return $hash;
}

function session_client_mark(): string
{
    return hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function session_client_matches(): bool
{
    $known = $_SESSION['client'] ?? '';
    return is_string($known) && $known !== '' && hash_equals($known, session_client_mark());
}

function begin_session(int $userId, bool $fresh = true): void
{
    $started = $fresh ? time() : (int)($_SESSION['started'] ?? time());
    session_regenerate_id(true);
    $_SESSION = [
        'uid' => $userId,
        'last_seen' => time(),
        'started' => $started,
        'client' => session_client_mark(),
        '_csrf' => bin2hex(random_bytes(32)),
    ];
    auth_refresh();
}

function recent_failures(string $action, string $column, string $value): int
{
    if ($value === '' || !in_array($action, ['auth.login_failed', 'auth.password_failed'], true)) {
        return 0;
    }
    if (!in_array($column, ['username', 'ip'], true)) {
        return 0;
    }
    $since = gmdate('Y-m-d H:i:s', time() - 900);
    return (int)db_val(
        'SELECT COUNT(*) FROM audit_events WHERE action = ? AND ' . $column . ' = ? AND created_at >= ?',
        [$action, $value, $since]
    );
}

function login_locked(string $username): bool
{
    return recent_failures('auth.login_failed', 'username', $username) >= 8
        || recent_failures('auth.login_failed', 'ip', client_ip()) >= 30;
}

function user_create(string $username, string $displayName, string $role, string $password): ?string
{
    if ($message = invalid_username($username)) {
        return $message;
    }
    if ($message = invalid_display_name($displayName)) {
        return $message;
    }
    if (!in_array($role, ['admin', 'ops', 'view'], true)) {
        return 'Select a role.';
    }
    if ($message = invalid_password($password)) {
        return $message;
    }
    if (db_one('SELECT id FROM users WHERE username = ?', [$username])) {
        return 'That username is already in use.';
    }
    try {
        db_tx(function () use ($username, $displayName, $role, $password): void {
            $id = db_insert(
                'INSERT INTO users (username, display_name, password_hash, role, active, must_change_password) VALUES (?,?,?,?,1,1)',
                [$username, $displayName, password_make($password), $role]
            );
            audit_log('user.create', 'Created user ' . $username . ' (' . role_label($role) . ')', 'user', $id);
        });
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return 'That username is already in use.';
        }
        throw $e;
    }
    return null;
}

function user_update(int $id, string $displayName, string $role, bool $active, string $newPassword): ?string
{
    $user = find_user($id);
    if (!$user) {
        return 'That user was not found.';
    }
    if ($message = invalid_display_name($displayName)) {
        return $message;
    }
    if (!in_array($role, ['admin', 'ops', 'view'], true)) {
        return 'Select a role.';
    }
    $actor = auth_user();
    if ($actor && (int)$actor['id'] === $id && !$active) {
        return 'You cannot disable your own account.';
    }
    if ($user['role'] === 'admin' && $user['active'] === 1 && active_admin_count() <= 1) {
        if ($role !== 'admin' || !$active) {
            return 'The last active admin cannot be changed or disabled.';
        }
    }
    $reset = $newPassword !== '';
    if ($reset && ($message = invalid_password($newPassword))) {
        return $message;
    }
    $same = $displayName === $user['display_name']
        && $role === $user['role']
        && ($active ? 1 : 0) === $user['active']
        && !$reset;
    if ($same) {
        return 'unchanged';
    }
    $mustChange = $reset && $actor && (int)$actor['id'] !== $id ? 1 : 0;
    $hash = $reset ? password_make($newPassword) : null;
    db_tx(function () use ($id, $displayName, $role, $active, $hash, $mustChange, $reset, $user): void {
        if ($hash !== null) {
            db_exec(
                'UPDATE users SET display_name = ?, role = ?, active = ?, password_hash = ?, must_change_password = ? WHERE id = ?',
                [$displayName, $role, $active ? 1 : 0, $hash, $mustChange, $id]
            );
        } else {
            db_exec(
                'UPDATE users SET display_name = ?, role = ?, active = ? WHERE id = ?',
                [$displayName, $role, $active ? 1 : 0, $id]
            );
        }
        $summary = 'Updated user ' . $user['username'] . ': ' . role_label($role) . ', ' . ($active ? 'enabled' : 'disabled');
        if ($reset) {
            $summary .= ', password reset';
        }
        audit_log($reset ? 'user.password_reset' : 'user.update', $summary, 'user', $id);
    });
    if ($actor && (int)$actor['id'] === $id) {
        auth_refresh();
    }
    return null;
}
