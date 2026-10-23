<?php
declare(strict_types=1);

function mark_form_input(?array $mark = null): array
{
    return [
        'name' => (string)($mark['name'] ?? ''),
        'swatch' => (string)($mark['swatch'] ?? ''),
        'note' => (string)($mark['note'] ?? ''),
    ];
}

function page_marks(): void
{
    require_login();
    view('marks/index', [
        'title' => 'Marks',
        'active' => 'marks',
        'openMarks' => mark_frames('open'),
        'closedMarks' => mark_rows('closed'),
    ]);
}

function page_mark_new(): void
{
    require_writer();
    view('marks/form', [
        'title' => 'New mark',
        'active' => 'marks',
        'input' => mark_form_input(),
        'colors' => mark_color_choices(),
        'formError' => '',
    ]);
}

function page_mark_create(): void
{
    require_writer();
    csrf_check();
    $input = [
        'name' => clean_line((string)($_POST['name'] ?? '')),
        'swatch' => (string)($_POST['swatch'] ?? ''),
        'note' => clean_multiline((string)($_POST['note'] ?? '')),
    ];
    $saved = mark_save(null, $input['name'], $input['swatch'], $input['note']);
    if ($saved['error']) {
        view('marks/form', [
            'title' => 'New mark',
            'active' => 'marks',
            'input' => $input,
            'colors' => mark_color_choices(),
            'formError' => $saved['error'],
        ]);
        return;
    }
    flash('ok', 'Mark created. Add the servers that need this work.');
    redirect('/marks/' . (int)$saved['id']);
}

function page_mark_show(array $params = []): void
{
    require_login();
    $mark = mark_find((int)$params['id']);
    if (!$mark) {
        abort(404);
    }
    $frames = mark_frames((string)$mark['status'], (int)$mark['id']);
    $frame = null;
    foreach ($frames as $candidate) {
        if ((int)$candidate['id'] === (int)$mark['id']) {
            $frame = $candidate;
            break;
        }
    }
    if ($frame === null) {
        $frame = $mark + ['apps' => [], 'dbs' => []];
    }
    view('marks/show', [
        'title' => (string)$mark['name'],
        'active' => 'marks',
        'mark' => $mark,
        'frame' => $frame,
        'colors' => mark_color_choices((string)$mark['swatch']),
        'oses' => choices('os'),
        'zones' => choices('zone'),
        'environments' => choices('environment'),
        'picker' => mark_picker_catalog((int)$mark['id']),
        'formError' => '',
    ]);
}

function page_mark_update(array $params = []): void
{
    require_writer();
    csrf_check();
    $mark = mark_find((int)$params['id']);
    if (!$mark) {
        abort(404);
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'save') {
        $saved = mark_save(
            (int)$mark['id'],
            clean_line((string)($_POST['name'] ?? '')),
            (string)($_POST['swatch'] ?? ''),
            clean_multiline((string)($_POST['note'] ?? ''))
        );
        if ($saved['error'] === null) {
            flash('ok', 'Mark updated.');
        } elseif ($saved['error'] === 'unchanged') {
            flash('ok', 'No changes to save.');
        } else {
            flash('error', $saved['error']);
        }
        redirect('/marks/' . (int)$mark['id']);
    }
    if ($action === 'close' || $action === 'reopen') {
        $result = mark_set_status((int)$mark['id'], $action === 'close' ? 'closed' : 'open');
        flash($result && $result !== 'unchanged' ? 'error' : 'ok', $result && $result !== 'unchanged' ? $result : ($action === 'close' ? 'Mark closed.' : 'Mark reopened.'));
        redirect('/marks/' . (int)$mark['id']);
    }
    if ($action === 'add') {
        $tokens = $_POST['vm_ids'] ?? [];
        $result = mark_add_selected((int)$mark['id'], is_array($tokens) ? $tokens : []);
        if ($result['error']) {
            flash('error', $result['error']);
        } elseif ($result['count'] === 0) {
            flash('ok', 'Those VMs are already in this mark.');
        } else {
            flash('ok', 'Added ' . (int)$result['count'] . ' VM' . ($result['count'] === 1 ? '' : 's') . '.');
        }
        redirect('/marks/' . (int)$mark['id']);
    }
    if ($action === 'delete') {
        $result = mark_delete((int)$mark['id']);
        flash($result ? 'error' : 'ok', $result ?: 'Mark deleted.');
        redirect($result ? '/marks/' . (int)$mark['id'] : '/marks');
    }
    if ($action === 'remove') {
        $result = mark_change_members((int)$mark['id'], (string)($_POST['side'] ?? ''), [(int)($_POST['vm_id'] ?? 0)], false);
        flash($result['error'] ? 'error' : 'ok', $result['error'] ?: 'Server removed from this mark.');
        redirect('/marks/' . (int)$mark['id']);
    }
    flash('error', 'That action is not available.');
    redirect('/marks/' . (int)$mark['id']);
}

function page_vm_mark(string $side, int $vmId): void
{
    require_writer();
    csrf_check();
    $vm = $side === 'app' ? app_vm_find($vmId) : db_vm_find($vmId);
    if (!$vm) {
        abort(404);
    }
    $add = (string)($_POST['action'] ?? '') === 'add';
    $result = mark_change_members((int)($_POST['mark_id'] ?? 0), $side, [$vmId], $add);
    if ($result['error']) {
        flash('error', $result['error']);
    } elseif ($result['count'] === 0) {
        flash('ok', $add ? 'That server is already in this mark.' : 'That server is not in this mark.');
    } else {
        flash('ok', $add ? 'Added to the mark.' : 'Removed from the mark.');
    }
    redirect(($side === 'app' ? '/app-vms/' : '/db-vms/') . $vmId);
}

function page_app_mark(array $params = []): void
{
    page_vm_mark('app', (int)$params['id']);
}

function page_db_mark(array $params = []): void
{
    page_vm_mark('db', (int)$params['id']);
}

function page_mark_color_update(): void
{
    require_admin();
    csrf_check();
    $result = mark_color_update(
        (string)($_POST['swatch'] ?? ''),
        clean_line((string)($_POST['name'] ?? '')),
        (string)($_POST['action'] ?? '')
    );
    if ($result === null) {
        flash('ok', 'Mark colors updated.');
    } elseif ($result === 'unchanged') {
        flash('ok', 'No changes to save.');
    } else {
        flash('error', $result);
    }
    redirect('/lists');
}
