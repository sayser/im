<?php
declare(strict_types=1);

function app_form_view(string $mode, array $input, array $errors, ?array $vm = null): void
{
    view('app_vms/form', [
        'title' => $mode === 'edit' ? 'Edit APP VM' : 'New APP VM',
        'active' => 'inventory',
        'mode' => $mode,
        'vm' => $vm,
        'input' => $input,
        'errors' => $errors,
        'clients' => choices('client', (int)$input['client_id']),
        'zones' => choices('zone', (int)$input['zone_id']),
        'stacks' => stacks_catalog(),
        'environments' => choices('environment', (int)$input['environment_id']),
        'oses' => choices('os', (int)$input['os_id']),
        'applications' => choices_multi('application', $input['application_ids']),
        'dbChoices' => vm_choices('db', $input['db_vm_ids']),
    ]);
}

function db_form_view(string $mode, array $input, array $errors, ?array $vm = null): void
{
    view('db_vms/form', [
        'title' => $mode === 'edit' ? 'Edit DB VM' : 'New DB VM',
        'active' => 'inventory',
        'mode' => $mode,
        'vm' => $vm,
        'input' => $input,
        'errors' => $errors,
        'clients' => choices('client', (int)$input['client_id']),
        'zones' => choices('zone', (int)$input['zone_id']),
        'stacks' => stacks_catalog(),
        'environments' => choices('environment', (int)$input['environment_id']),
        'oses' => choices('os', (int)$input['os_id']),
        'appChoices' => vm_choices('app', $input['app_vm_ids']),
    ]);
}

function page_app_index(): void
{
    require_login();
    $filters = read_app_filters();
    view('app_vms/index', [
        'title' => 'APP VMs',
        'active' => 'inventory',
        'filters' => $filters,
        'result' => app_vm_list($filters, 'active'),
        'clients' => choices('client', (int)$filters['client_id']),
        'environments' => choices('environment', (int)$filters['environment_id']),
        'oses' => choices('os', (int)$filters['os_id']),
        'applications' => choices('application', (int)$filters['application_id']),
        'lifecycle' => 'active',
    ]);
}

function page_app_new(): void
{
    require_writer();
    $input = app_input_blank();
    $zone = (int)($_GET['zone'] ?? 0);
    if ($zone > 0 && lookup_selectable('zone', $zone, 0)) {
        $input['zone_id'] = $zone;
    }
    app_form_view('create', $input, []);
}

function page_app_create(): void
{
    require_writer();
    csrf_check();
    $input = normalize_app_post();
    $errors = validate_app($input, null);
    if ($errors) {
        app_form_view('create', $input, $errors);
        return;
    }
    $saved = save_app_vm(null, $input);
    flash('ok', 'APP VM created.');
    redirect('/environments/' . (int)$input['zone_id']);
}

function page_app_show(array $params = []): void
{
    require_login();
    $vm = app_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    view('app_vms/show', [
        'title' => (string)$vm['name'],
        'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'inventory',
        'vm' => $vm,
        'history' => history_groups('app_vm', (int)$vm['id']),
    ]);
}

function page_app_edit(array $params = []): void
{
    require_writer();
    $vm = app_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    app_form_view('edit', app_input_from_vm($vm), [], $vm);
}

function page_app_update(array $params = []): void
{
    require_writer();
    csrf_check();
    $vm = app_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    $input = normalize_app_post();
    $errors = validate_app($input, $vm);
    if ($errors) {
        app_form_view('edit', $input, $errors, $vm);
        return;
    }
    $saved = save_app_vm((int)$vm['id'], $input);
    flash('ok', $saved['changed'] ? 'APP VM updated.' : 'No changes to save.');
    redirect('/environments/' . (int)$input['zone_id']);
}

function page_app_decommission(array $params = []): void
{
    require_writer();
    csrf_check();
    $id = (int)$params['id'];
    if (set_lifecycle('app', $id, 'decommissioned')) {
        flash('ok', 'APP VM moved to DECOM.');
    } else {
        flash('error', 'That APP VM is already decommissioned.');
    }
    redirect('/app-vms/' . $id);
}

function page_app_restore(array $params = []): void
{
    require_writer();
    csrf_check();
    $id = (int)$params['id'];
    if (set_lifecycle('app', $id, 'active')) {
        flash('ok', 'APP VM restored.');
    } else {
        flash('error', 'That APP VM is already active.');
    }
    redirect('/app-vms/' . $id);
}

function page_app_delete_form(array $params = []): void
{
    require_writer();
    $vm = app_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    view('delete_vm', [
        'title' => 'Delete APP VM',
        'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'app',
        'vm' => $vm,
        'kind' => 'app',
        'errors' => [],
    ]);
}

function page_app_delete(array $params = []): void
{
    require_writer();
    csrf_check();
    $vm = app_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    if (!hash_equals((string)$vm['name'], (string)($_POST['confirm_name'] ?? ''))) {
        view('delete_vm', [
            'title' => 'Delete APP VM',
            'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'app',
            'vm' => $vm,
            'kind' => 'app',
            'errors' => ['confirm_name' => 'Type the VM name exactly to confirm.'],
        ]);
        return;
    }
    $result = delete_vm('app', (int)$vm['id']);
    flash('ok', 'APP VM permanently deleted.');
    redirect($result['lifecycle'] === 'decommissioned' ? '/decom?type=app' : '/app-vms');
}

function page_db_index(): void
{
    require_login();
    $filters = read_db_filters();
    view('db_vms/index', [
        'title' => 'DB VMs',
        'active' => 'inventory',
        'filters' => $filters,
        'result' => db_vm_list($filters, 'active'),
        'clients' => choices('client', (int)$filters['client_id']),
        'environments' => choices('environment', (int)$filters['environment_id']),
        'oses' => choices('os', (int)$filters['os_id']),
        'lifecycle' => 'active',
    ]);
}

function page_db_new(): void
{
    require_writer();
    $input = db_input_blank();
    $zone = (int)($_GET['zone'] ?? 0);
    if ($zone > 0 && lookup_selectable('zone', $zone, 0)) {
        $input['zone_id'] = $zone;
    }
    db_form_view('create', $input, []);
}

function page_db_create(): void
{
    require_writer();
    csrf_check();
    $input = normalize_db_post();
    $errors = validate_db($input, null);
    if ($errors) {
        db_form_view('create', $input, $errors);
        return;
    }
    $saved = save_db_vm(null, $input);
    flash('ok', 'DB VM created.');
    redirect('/environments/' . (int)$input['zone_id']);
}

function page_db_show(array $params = []): void
{
    require_login();
    $vm = db_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    view('db_vms/show', [
        'title' => (string)$vm['name'],
        'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'inventory',
        'vm' => $vm,
        'history' => history_groups('db_vm', (int)$vm['id']),
    ]);
}

function page_db_edit(array $params = []): void
{
    require_writer();
    $vm = db_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    db_form_view('edit', db_input_from_vm($vm), [], $vm);
}

function page_db_update(array $params = []): void
{
    require_writer();
    csrf_check();
    $vm = db_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    $input = normalize_db_post();
    $errors = validate_db($input, $vm);
    if ($errors) {
        db_form_view('edit', $input, $errors, $vm);
        return;
    }
    $saved = save_db_vm((int)$vm['id'], $input);
    flash('ok', $saved['changed'] ? 'DB VM updated.' : 'No changes to save.');
    redirect('/environments/' . (int)$input['zone_id']);
}

function page_db_decommission(array $params = []): void
{
    require_writer();
    csrf_check();
    $id = (int)$params['id'];
    if (set_lifecycle('db', $id, 'decommissioned')) {
        flash('ok', 'DB VM moved to DECOM.');
    } else {
        flash('error', 'That DB VM is already decommissioned.');
    }
    redirect('/db-vms/' . $id);
}

function page_db_restore(array $params = []): void
{
    require_writer();
    csrf_check();
    $id = (int)$params['id'];
    if (set_lifecycle('db', $id, 'active')) {
        flash('ok', 'DB VM restored.');
    } else {
        flash('error', 'That DB VM is already active.');
    }
    redirect('/db-vms/' . $id);
}

function page_db_delete_form(array $params = []): void
{
    require_writer();
    $vm = db_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    view('delete_vm', [
        'title' => 'Delete DB VM',
        'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'db',
        'vm' => $vm,
        'kind' => 'db',
        'errors' => [],
    ]);
}

function page_db_delete(array $params = []): void
{
    require_writer();
    csrf_check();
    $vm = db_vm_find((int)$params['id']);
    if (!$vm) {
        abort(404);
    }
    if (!hash_equals((string)$vm['name'], (string)($_POST['confirm_name'] ?? ''))) {
        view('delete_vm', [
            'title' => 'Delete DB VM',
            'active' => $vm['lifecycle'] === 'decommissioned' ? 'decom' : 'db',
            'vm' => $vm,
            'kind' => 'db',
            'errors' => ['confirm_name' => 'Type the VM name exactly to confirm.'],
        ]);
        return;
    }
    $result = delete_vm('db', (int)$vm['id']);
    flash('ok', 'DB VM permanently deleted.');
    redirect($result['lifecycle'] === 'decommissioned' ? '/decom?type=db' : '/db-vms');
}

function page_decom(): void
{
    require_login();
    $type = (($_GET['type'] ?? '') === 'db') ? 'db' : 'app';
    if ($type === 'db') {
        $filters = read_db_filters();
        $filters['type'] = 'db';
        view('decom', [
            'title' => 'DECOM',
            'active' => 'decom',
            'type' => 'db',
            'filters' => $filters,
            'result' => db_vm_list($filters, 'decommissioned'),
            'clients' => choices('client', (int)$filters['client_id']),
            'environments' => choices('environment', (int)$filters['environment_id']),
            'oses' => choices('os', (int)$filters['os_id']),
        ]);
        return;
    }
    $filters = read_app_filters();
    $filters['type'] = 'app';
    view('decom', [
        'title' => 'DECOM',
        'active' => 'decom',
        'type' => 'app',
        'filters' => $filters,
        'result' => app_vm_list($filters, 'decommissioned'),
        'clients' => choices('client', (int)$filters['client_id']),
        'environments' => choices('environment', (int)$filters['environment_id']),
        'oses' => choices('os', (int)$filters['os_id']),
        'applications' => choices('application', (int)$filters['application_id']),
    ]);
}

function page_export_app(): void
{
    require_login();
    $lifecycle = (($_GET['lifecycle'] ?? '') === 'decommissioned') ? 'decommissioned' : 'active';
    $filters = read_app_filters();
    $result = app_vm_list($filters, $lifecycle, true);
    $rows = [];
    foreach ($result['rows'] as $row) {
        $rows[] = [
            $row['name'],
            $row['client_name'],
            $row['zone_name'],
            $row['environment_name'],
            (string)($row['stack_name'] ?? ''),
            $row['os_name'],
            $row['internal_ip'],
            (string)($row['external_ip'] ?? ''),
            (string)($row['url'] ?? ''),
            (string)($row['brotherhood_url'] ?? ''),
            (string)($row['status_page_url'] ?? ''),
            joined_names($row['applications']),
            joined_names($row['db_vms']),
            (string)($row['notes'] ?? ''),
            (string)($row['troubleshooting'] ?? ''),
            $row['lifecycle'] === 'decommissioned' ? 'Decommissioned' : 'Active',
            format_when($row['created_at']),
            format_when($row['updated_at']),
        ];
    }
    send_csv(
        $lifecycle === 'decommissioned' ? 'app-vms-decommissioned.csv' : 'app-vms.csv',
        ['VM Name', 'Client', 'Environment', 'Deployment', 'Stack', 'OS', 'Internal IP', 'External IP', 'URL', 'BrotherHood URL', 'Status Page', 'Applications', 'DB VMs', 'Notes', 'Troubleshooting', 'Lifecycle', 'Created', 'Updated'],
        $rows
    );
}

function page_export_db(): void
{
    require_login();
    $lifecycle = (($_GET['lifecycle'] ?? '') === 'decommissioned') ? 'decommissioned' : 'active';
    $filters = read_db_filters();
    $result = db_vm_list($filters, $lifecycle, true);
    $rows = [];
    foreach ($result['rows'] as $row) {
        $rows[] = [
            $row['name'],
            $row['client_name'],
            $row['zone_name'],
            $row['environment_name'],
            (string)($row['stack_name'] ?? ''),
            $row['os_name'],
            $row['internal_ip'],
            (int)$row['redis'] === 1 ? 'Yes' : 'No',
            joined_names($row['app_vms']),
            (string)($row['notes'] ?? ''),
            (string)($row['troubleshooting'] ?? ''),
            $row['lifecycle'] === 'decommissioned' ? 'Decommissioned' : 'Active',
            format_when($row['created_at']),
            format_when($row['updated_at']),
        ];
    }
    send_csv(
        $lifecycle === 'decommissioned' ? 'db-vms-decommissioned.csv' : 'db-vms.csv',
        ['VM Name', 'Client', 'Environment', 'Deployment', 'Stack', 'OS', 'Internal IP', 'Redis', 'APP VMs', 'Notes', 'Troubleshooting', 'Lifecycle', 'Created', 'Updated'],
        $rows
    );
}

function page_environments(): void
{
    require_login();
    $zones = zone_tabs();
    if (!$zones) {
        view('board', [
            'title' => 'Inventory',
            'active' => 'inventory',
            'zones' => [],
            'zone' => null,
            'groups' => [],
            'hits' => [],
            'show_zone' => false,
        ]);
        return;
    }
    redirect('/environments/' . (int)$zones[0]['id']);
}

function page_environment(array $params = []): void
{
    require_login();
    $zone = zone_find((int)$params['id']);
    if (!$zone || (int)$zone['active'] !== 1) {
        abort(404);
    }
    view('board', [
        'title' => (string)$zone['name'],
        'active' => 'inventory',
        'zones' => zone_tabs(),
        'zone' => $zone,
        'groups' => environment_board((int)$zone['id']),
        'hits' => [],
        'show_zone' => false,
    ]);
}

function page_search(): void
{
    require_login();
    $result = inventory_search(query_text('q', 200));
    view('search', [
        'title' => 'Search',
        'active' => 'inventory',
        'zones' => zone_tabs(),
        'result' => $result,
    ]);
}
