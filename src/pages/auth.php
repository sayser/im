<?php
declare(strict_types=1);

function page_setup(): void
{
    view('setup', [
        'title' => 'Create admin',
        'layout' => 'bare',
        'errors' => [],
        'input' => ['username' => '', 'display_name' => ''],
    ]);
}

function page_setup_submit(): void
{
    if (user_count() > 0) {
        abort(404);
    }
    csrf_check();
    $username = clean_line((string)($_POST['username'] ?? ''));
    $display = clean_line((string)($_POST['display_name'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $errors = [];
    if ($message = invalid_username($username)) {
        $errors['username'] = $message;
    }
    if ($message = invalid_display_name($display)) {
        $errors['display_name'] = $message;
    }
    if ($message = invalid_password($password)) {
        $errors['password'] = $message;
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'The passwords do not match.';
    }
    if ($errors) {
        view('setup', [
            'title' => 'Create admin',
            'layout' => 'bare',
            'errors' => $errors,
            'input' => ['username' => $username, 'display_name' => $display],
        ]);
        return;
    }
    if ((int)db_val('SELECT GET_LOCK(?, 10)', ['ptim_setup']) !== 1) {
        view('setup', [
            'title' => 'Create admin',
            'layout' => 'bare',
            'errors' => ['form' => 'Setup is busy. Wait a moment and try again.'],
            'input' => ['username' => $username, 'display_name' => $display],
        ]);
        return;
    }
    try {
        $id = db_tx(function () use ($username, $display, $password): int {
            if (user_count() > 0) {
                throw new RuntimeException('setup-closed');
            }
            return db_insert(
                'INSERT INTO users (username, display_name, password_hash, role, active, must_change_password) VALUES (?,?,?,?,1,0)',
                [$username, $display, password_make($password), 'admin']
            );
        });
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'setup-closed') {
            redirect('/login');
        }
        throw $e;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            view('setup', [
                'title' => 'Create admin',
                'layout' => 'bare',
                'errors' => ['username' => 'That username is already in use.'],
                'input' => ['username' => $username, 'display_name' => $display],
            ]);
            return;
        }
        throw $e;
    } finally {
        db_val('SELECT RELEASE_LOCK(?)', ['ptim_setup']);
    }
    begin_session($id);
    audit_log('user.create', 'Created the first admin account ' . $username, 'user', $id);
    flash('ok', 'Admin account created.');
    redirect('/');
}

function page_login(): void
{
    if (auth_user()) {
        redirect('/');
    }
    view('login', [
        'title' => 'Sign in',
        'layout' => 'bare',
        'errors' => [],
        'username' => '',
    ]);
}

function page_login_submit(): void
{
    if (auth_user()) {
        redirect('/');
    }
    csrf_check();
    $username = mb_substr(clean_line((string)($_POST['username'] ?? '')), 0, 64);
    $password = (string)($_POST['password'] ?? '');
    $fail = static function (string $message) use ($username): void {
        view('login', [
            'title' => 'Sign in',
            'layout' => 'bare',
            'errors' => ['form' => $message],
            'username' => $username,
        ]);
    };
    if (invalid_username($username) !== null || $password === '' || strlen($password) > 72) {
        password_verify('wrong-password', dummy_password_hash());
        $fail('Those credentials are not valid.');
        return;
    }
    $user = db_one(
        'SELECT id, password_hash, active, must_change_password FROM users WHERE username = ?',
        [$username]
    );
    $hash = is_array($user) ? (string)$user['password_hash'] : dummy_password_hash();
    $valid = password_verify($password, $hash);
    if (login_locked($username)) {
        $fail('Too many sign-in attempts. Try again in a few minutes.');
        return;
    }
    if (!is_array($user) || !$valid || (int)$user['active'] !== 1) {
        audit_log('auth.login_failed', 'Failed sign-in for ' . $username, null, null, null, $username);
        $fail('Those credentials are not valid.');
        return;
    }
    if (password_is_stale($hash)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_make($password), (int)$user['id']]);
    }
    begin_session((int)$user['id']);
    audit_log('auth.login', 'Signed in');
    redirect((int)$user['must_change_password'] === 1 ? '/account/password' : '/');
}

function page_logout(): void
{
    csrf_check();
    $user = auth_user();
    if ($user) {
        audit_log('auth.logout', 'Signed out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => (string)$params['path'],
            'domain' => (string)$params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => (bool)$params['httponly'],
            'samesite' => (string)($params['samesite'] ?? 'Strict'),
        ]);
    }
    session_destroy();
    session_start();
    flash('ok', 'Signed out.');
    redirect('/login');
}

function page_password(): void
{
    $user = require_login();
    view('account/password', [
        'title' => 'Password',
        'active' => 'password',
        'errors' => [],
        'mustChange' => (int)$user['must_change_password'] === 1,
    ]);
}

function page_password_submit(): void
{
    $user = require_login();
    csrf_check();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $errors = [];
    $hash = user_password_hash((int)$user['id']) ?? dummy_password_hash();
    if (strlen($current) > 72) {
        password_verify('wrong-password', $hash);
        $currentOk = false;
    } else {
        $currentOk = password_verify($current, $hash);
    }
    $locked = recent_failures('auth.password_failed', 'username', (string)$user['username']) >= 8;
    if ($locked) {
        $errors['current_password'] = 'Too many attempts. Try again in a few minutes.';
    } elseif (!$currentOk) {
        audit_log('auth.password_failed', 'Rejected a password change');
        $errors['current_password'] = 'The current password is not correct.';
    }
    if ($message = invalid_password($new)) {
        $errors['new_password'] = $message;
    }
    if ($new !== $confirm) {
        $errors['confirm_password'] = 'The passwords do not match.';
    }
    if (!$errors && $hash !== null && password_verify($new, $hash)) {
        $errors['new_password'] = 'Choose a different password than the current one.';
    }
    if ($errors) {
        view('account/password', [
            'title' => 'Password',
            'active' => 'password',
            'errors' => $errors,
            'mustChange' => (int)$user['must_change_password'] === 1,
        ]);
        return;
    }
    db_exec(
        'UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?',
        [password_make($new), (int)$user['id']]
    );
    audit_log('auth.password_change', 'Changed password');
    begin_session((int)$user['id'], false);
    flash('ok', 'Password updated.');
    redirect('/');
}
