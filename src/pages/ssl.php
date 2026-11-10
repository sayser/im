<?php
declare(strict_types=1);

function page_ssl(): void
{
    require_login();
    view('ssl/index', [
        'title' => 'SSL',
        'active' => 'ssl',
        'rows' => ssl_rows(),
    ]);
}

function page_ssl_new(): void
{
    require_writer();
    view('ssl/form', [
        'title' => 'New SSL certificate',
        'active' => 'ssl',
        'mode' => 'create',
        'cert' => null,
        'input' => ['url' => '', 'expires_on' => ''],
        'errors' => [],
    ]);
}

function page_ssl_create(): void
{
    require_writer();
    csrf_check();
    $input = ssl_input_from_post();
    $errors = validate_ssl($input);
    if ($errors) {
        view('ssl/form', [
            'title' => 'New SSL certificate',
            'active' => 'ssl',
            'mode' => 'create',
            'cert' => null,
            'input' => $input,
            'errors' => $errors,
        ]);
        return;
    }
    $id = ssl_save(null, $input);
    flash('ok', 'SSL certificate added.');
    redirect('/ssl/' . $id);
}

function page_ssl_show(array $params = []): void
{
    require_login();
    $cert = ssl_find((int)$params['id']);
    if (!$cert) {
        abort(404);
    }
    view('ssl/show', [
        'title' => 'SSL certificate',
        'active' => 'ssl',
        'cert' => $cert,
        'leadError' => '',
    ]);
}

function page_ssl_edit(array $params = []): void
{
    require_writer();
    $cert = ssl_find((int)$params['id']);
    if (!$cert) {
        abort(404);
    }
    view('ssl/form', [
        'title' => 'Edit SSL certificate',
        'active' => 'ssl',
        'mode' => 'edit',
        'cert' => $cert,
        'input' => ['url' => $cert['url'], 'expires_on' => substr((string)$cert['expires_on'], 0, 10)],
        'errors' => [],
    ]);
}

function page_ssl_update(array $params = []): void
{
    require_login();
    csrf_check();
    $id = (int)$params['id'];
    $action = (string)($_POST['action'] ?? 'save');
    if ($action === 'delete') {
        require_writer();
        ssl_delete($id);
        flash('ok', 'SSL certificate deleted.');
        redirect('/ssl');
    }
    if ($action === 'unsubscribe') {
        ssl_unsubscribe($id, (int)auth_user()['id']);
        flash('ok', 'You will no longer get a reminder for this certificate.');
        redirect('/ssl/' . $id);
    }
    if ($action === 'subscribe') {
        $amount = (int)($_POST['lead_amount'] ?? 0);
        $unit = (string)($_POST['lead_unit'] ?? 'day');
        $error = ssl_subscribe($id, (int)auth_user()['id'], $amount, $unit);
        if ($error !== null) {
            $cert = ssl_find($id);
            if (!$cert) {
                abort(404);
            }
            view('ssl/show', [
                'title' => 'SSL certificate',
                'active' => 'ssl',
                'cert' => $cert,
                'leadError' => $error,
            ]);
            return;
        }
        flash('ok', 'Reminder saved.');
        redirect('/ssl/' . $id);
    }
    require_writer();
    $input = ssl_input_from_post();
    $errors = validate_ssl($input);
    $cert = ssl_find($id);
    if (!$cert) {
        abort(404);
    }
    if ($errors) {
        $cert = ssl_find($id);
        view('ssl/form', [
            'title' => 'Edit SSL certificate',
            'active' => 'ssl',
            'mode' => 'edit',
            'cert' => $cert,
            'input' => $input,
            'errors' => $errors,
        ]);
        return;
    }
    ssl_save($id, $input);
    flash('ok', 'SSL certificate updated.');
    redirect('/ssl/' . $id);
}
