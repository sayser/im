<?php
declare(strict_types=1);

function saved_view_input(?array $view = null): array
{
    return [
        'name' => (string)($view['name'] ?? ''),
        'vm_side' => (string)($view['vm_side'] ?? 'app'),
        'zone_id' => (int)($view['zone_id'] ?? 0),
        'os_id' => (int)($view['os_id'] ?? 0),
        'environment_id' => (int)($view['environment_id'] ?? 0),
    ];
}

function saved_view_input_from_post(): array
{
    $side = (string)($_POST['vm_side'] ?? 'app');
    return [
        'name' => clean_line((string)($_POST['name'] ?? '')),
        'vm_side' => isset(saved_view_sides()[$side]) ? $side : 'app',
        'zone_id' => (int)($_POST['zone_id'] ?? 0),
        'os_id' => (int)($_POST['os_id'] ?? 0),
        'environment_id' => (int)($_POST['environment_id'] ?? 0),
    ];
}

function saved_view_form_data(array $input, string $formError, ?array $view = null): array
{
    return [
        'title' => $view ? 'Edit view' : 'New view',
        'active' => 'views',
        'view' => $view,
        'input' => $input,
        'formError' => $formError,
        'zones' => choices('zone', (int)$input['zone_id']),
        'oses' => choices('os', (int)$input['os_id']),
        'environments' => choices('environment', (int)$input['environment_id']),
    ];
}

function page_saved_views(): void
{
    $user = require_login();
    $rows = saved_view_rows((int)$user['id']);
    foreach ($rows as $i => $row) {
        $rows[$i]['summary'] = saved_view_summary($row);
        $rows[$i]['vm_count'] = saved_view_count($row);
    }
    view('saved_views/index', [
        'title' => 'Views',
        'active' => 'views',
        'views' => $rows,
    ]);
}

function page_saved_view_new(): void
{
    require_login();
    view('saved_views/form', saved_view_form_data(saved_view_input(), ''));
}

function page_saved_view_create(): void
{
    $user = require_login();
    csrf_check();
    $input = saved_view_input_from_post();
    $saved = saved_view_save(
        null,
        (int)$user['id'],
        $input['name'],
        $input['vm_side'],
        $input['zone_id'],
        $input['os_id'],
        $input['environment_id']
    );
    if ($saved['error']) {
        view('saved_views/form', saved_view_form_data($input, $saved['error']));
        return;
    }
    flash('ok', 'View created.');
    redirect('/views/' . (int)$saved['id']);
}

function page_saved_view_show(array $params = []): void
{
    $user = require_login();
    $saved = saved_view_find((int)$params['id'], (int)$user['id']);
    if (!$saved) {
        abort(404);
    }
    $matches = saved_view_matches($saved);
    view('saved_views/show', [
        'title' => (string)$saved['name'],
        'active' => 'views',
        'saved' => $saved,
        'summary' => saved_view_summary($saved),
        'apps' => $matches['apps'],
        'dbs' => $matches['dbs'],
        'input' => saved_view_input($saved),
        'formError' => '',
        'zones' => choices('zone', (int)$saved['zone_id']),
        'oses' => choices('os', (int)$saved['os_id']),
        'environments' => choices('environment', (int)$saved['environment_id']),
    ]);
}

function page_saved_view_update(array $params = []): void
{
    $user = require_login();
    csrf_check();
    $saved = saved_view_find((int)$params['id'], (int)$user['id']);
    if (!$saved) {
        abort(404);
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete') {
        saved_view_delete((int)$saved['id'], (int)$user['id']);
        flash('ok', 'View deleted.');
        redirect('/views');
    }
    if ($action !== 'save') {
        flash('error', 'That action is not available.');
        redirect('/views/' . (int)$saved['id']);
    }
    $input = saved_view_input_from_post();
    $result = saved_view_save(
        (int)$saved['id'],
        (int)$user['id'],
        $input['name'],
        $input['vm_side'],
        $input['zone_id'],
        $input['os_id'],
        $input['environment_id']
    );
    if ($result['error']) {
        flash('error', $result['error']);
    } else {
        flash('ok', 'View updated.');
    }
    redirect('/views/' . (int)$saved['id']);
}
