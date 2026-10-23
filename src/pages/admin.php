<?php
declare(strict_types=1);

function page_users(): void
{
    require_admin();
    $q = query_text('q');
    view('users/index', [
        'title' => 'Users',
        'active' => 'users',
        'q' => $q,
        'users' => users_list($q),
    ]);
}

function page_user_new(): void
{
    require_admin();
    view('users/form', [
        'title' => 'New user',
        'active' => 'users',
        'mode' => 'create',
        'userRow' => null,
        'input' => ['username' => '', 'display_name' => '', 'role' => 'view', 'active' => 1],
        'formError' => '',
    ]);
}

function page_user_create(): void
{
    require_admin();
    csrf_check();
    $input = [
        'username' => clean_line((string)($_POST['username'] ?? '')),
        'display_name' => clean_line((string)($_POST['display_name'] ?? '')),
        'role' => (string)($_POST['role'] ?? ''),
        'active' => 1,
    ];
    $error = user_create($input['username'], $input['display_name'], $input['role'], (string)($_POST['password'] ?? ''));
    if ($error) {
        view('users/form', [
            'title' => 'New user',
            'active' => 'users',
            'mode' => 'create',
            'userRow' => null,
            'input' => $input,
            'formError' => $error,
        ]);
        return;
    }
    flash('ok', 'User created. They will be asked to change the temporary password.');
    redirect('/users');
}

function page_user_edit(array $params = []): void
{
    require_admin();
    $user = find_user((int)$params['id']);
    if (!$user) {
        abort(404);
    }
    view('users/form', [
        'title' => 'Edit user',
        'active' => 'users',
        'mode' => 'edit',
        'userRow' => $user,
        'input' => [
            'username' => $user['username'],
            'display_name' => $user['display_name'],
            'role' => $user['role'],
            'active' => $user['active'],
        ],
        'formError' => '',
    ]);
}

function page_user_update(array $params = []): void
{
    require_admin();
    csrf_check();
    $user = find_user((int)$params['id']);
    if (!$user) {
        abort(404);
    }
    $input = [
        'username' => $user['username'],
        'display_name' => clean_line((string)($_POST['display_name'] ?? '')),
        'role' => (string)($_POST['role'] ?? ''),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
    $result = user_update((int)$user['id'], $input['display_name'], $input['role'], $input['active'] === 1, (string)($_POST['password'] ?? ''));
    if ($result === 'unchanged') {
        flash('ok', 'No changes to save.');
        redirect('/users/' . $user['id'] . '/edit');
    }
    if ($result !== null) {
        view('users/form', [
            'title' => 'Edit user',
            'active' => 'users',
            'mode' => 'edit',
            'userRow' => $user,
            'input' => $input,
            'formError' => $result,
        ]);
        return;
    }
    $actor = auth_user();
    flash('ok', 'User updated.');
    if ($actor && (int)$actor['id'] === (int)$user['id'] && $actor['role'] !== 'admin') {
        redirect('/');
    }
    redirect('/users/' . $user['id'] . '/edit');
}

function page_lists(): void
{
    require_admin();
    view('lists/index', [
        'title' => 'Field options',
        'active' => 'lists',
        'groups' => lookups_admin(),
        'markColors' => mark_color_admin(),
    ]);
}

function page_lists_create(): void
{
    require_admin();
    csrf_check();
    $kind = (string)($_POST['kind'] ?? '');
    $name = clean_line((string)($_POST['name'] ?? ''));
    $error = lookup_create($kind, $name);
    flash($error ? 'error' : 'ok', $error ?: 'Added ' . $name . '.');
    if (!$error && $kind === 'zone' && (string)($_POST['return'] ?? '') === 'board') {
        $created = db_one('SELECT id FROM lookups WHERE kind = ? AND name = ?', ['zone', $name]);
        if ($created) {
            redirect('/environments/' . (int)$created['id']);
        }
    }
    redirect('/lists');
}

function page_lists_update(array $params = []): void
{
    require_admin();
    csrf_check();
    $id = (int)$params['id'];
    $action = (string)($_POST['action'] ?? '');
    $result = match ($action) {
        'rename' => lookup_rename($id, clean_line((string)($_POST['name'] ?? ''))),
        'retire' => lookup_set_active($id, false),
        'restore' => lookup_set_active($id, true),
        'delete' => lookup_delete($id),
        default => 'That action is not available.',
    };
    if ($result === null) {
        flash('ok', 'Field options updated.');
    } elseif ($result === 'unchanged') {
        flash('ok', 'No changes to save.');
    } else {
        flash('error', $result);
    }
    redirect('/lists');
}
