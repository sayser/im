<?php
declare(strict_types=1);

function task_input(string $title = '', string $description = '', array $items = []): array
{
    return [
        'title' => $title,
        'description' => $description,
        'items' => $items,
    ];
}

function task_input_from_post(): array
{
    $parsed = task_parse_items($_POST['items'] ?? []);
    return [
        'title' => clean_line((string)($_POST['title'] ?? '')),
        'description' => clean_multiline((string)($_POST['description'] ?? '')),
        'items' => $parsed['items'],
        'parse_error' => $parsed['error'],
    ];
}

function task_form_data(array $input, string $formError, ?array $task = null): array
{
    $selected = [];
    foreach ($input['items'] as $item) {
        $selected[] = [
            'key' => $item['side'] . ':' . (int)$item['vm_id'],
            'user' => (string)(int)$item['user_id'],
        ];
    }
    $include = array_map(static fn(array $item): int => (int)$item['user_id'], $input['items']);
    return [
        'title' => $task ? 'Edit task' : 'New task',
        'active' => 'tasks',
        'task' => $task,
        'input' => $input,
        'formError' => $formError,
        'users' => task_user_options($include),
        'catalog' => task_catalog($task ? (int)$task['id'] : 0),
        'tags' => task_tag_names(),
        'zones' => choices('zone'),
        'oses' => choices('os'),
        'environments' => choices('environment'),
        'clients' => choices('client'),
        'selected' => $selected,
    ];
}

function task_flash_mail(string $lead, array $notes): void
{
    flash('ok', $lead . ($notes ? ' ' . implode(' ', $notes) : ''));
}

function page_tasks(): void
{
    $user = require_login();
    $assigned = [];
    $completed = [];
    foreach (task_list_for((int)$user['id']) as $task) {
        if (($task['panel'] ?? '') === 'completed') {
            $completed[] = $task;
        } else {
            $assigned[] = $task;
        }
    }
    view('tasks/index', [
        'title' => 'Tasks',
        'active' => 'tasks',
        'assigned' => $assigned,
        'completed' => $completed,
        'taskTab' => 'open',
    ]);
}

function page_task_archived(): void
{
    $user = require_login();
    view('tasks/archived', [
        'title' => 'Archived tasks',
        'active' => 'tasks',
        'taskTab' => 'archived',
        'tasks' => task_archived_for((int)$user['id']),
    ]);
}

function page_task_new(): void
{
    require_login();
    view('tasks/form', task_form_data(task_input(), ''));
}

function page_task_create(): void
{
    $user = require_login();
    csrf_check();
    $input = task_input_from_post();
    if ($input['parse_error']) {
        view('tasks/form', task_form_data($input, (string)$input['parse_error']));
        return;
    }
    $saved = task_save(null, (int)$user['id'], $input['title'], $input['description'], $input['items']);
    if ($saved['error']) {
        view('tasks/form', task_form_data($input, (string)$saved['error']));
        return;
    }
    task_flash_mail('Task created.', $saved['mail']);
    redirect('/tasks/' . (int)$saved['id']);
}

function page_task_show(array $params = []): void
{
    $user = require_login();
    $task = task_find((int)$params['id']);
    if (!$task || !task_can_see($task, (int)$user['id'])) {
        abort(404);
    }
    $creator = (int)$task['created_by'] === (int)$user['id'];
    $groups = task_groups((int)$task['id']);
    if (!$creator) {
        $groups = array_values(array_filter(
            $groups,
            static fn(array $group): bool => $group['user_id'] === (int)$user['id']
        ));
    } else {
        usort($groups, static function (array $a, array $b) use ($user): int {
            $mineA = $a['user_id'] === (int)$user['id'] ? 0 : 1;
            $mineB = $b['user_id'] === (int)$user['id'] ? 0 : 1;
            return $mineA <=> $mineB ?: strcasecmp($a['name'], $b['name']);
        });
    }
    view('tasks/show', [
        'title' => (string)$task['title'],
        'active' => 'tasks',
        'task' => $task,
        'creator' => $creator,
        'groups' => $groups,
        'userId' => (int)$user['id'],
    ]);
}

function page_task_edit(array $params = []): void
{
    $user = require_login();
    $task = task_find((int)$params['id']);
    if (!$task || (int)$task['created_by'] !== (int)$user['id'] || ($task['archived_at'] !== null && $task['archived_at'] !== '')) {
        abort(404);
    }
    view('tasks/form', task_form_data(
        task_input((string)$task['title'], (string)$task['description'], task_items_input((int)$task['id'])),
        '',
        $task
    ));
}

function page_task_update(array $params = []): void
{
    $user = require_login();
    csrf_check();
    $task = task_find((int)$params['id']);
    if (!$task || !task_can_see($task, (int)$user['id'])) {
        abort(404);
    }
    $action = (string)($_POST['action'] ?? '');
    $archived = $task['archived_at'] !== null && $task['archived_at'] !== '';
    if ($archived && in_array($action, ['toggle', 'finish', 'save'], true)) {
        flash('error', 'That task is archived.');
        redirect('/tasks/' . (int)$task['id']);
    }
    if ($action === 'toggle') {
        $done = (string)($_POST['done'] ?? '') === '1';
        if (!task_toggle((int)$task['id'], (int)($_POST['item'] ?? 0), (int)$user['id'], $done)) {
            flash('error', 'That VM is not assigned to you.');
        }
        redirect('/tasks/' . (int)$task['id']);
    }
    if ($action === 'finish') {
        $finished = task_finish((int)$task['id'], (int)$user['id']);
        if (!$finished['ok']) {
            flash('error', 'That task is not assigned to you.');
        } else {
            task_flash_mail('Task completed.', $finished['mail']);
        }
        redirect('/tasks/' . (int)$task['id']);
    }
    if ((int)$task['created_by'] !== (int)$user['id']) {
        abort(404);
    }
    if ($action === 'archive') {
        task_set_archived((int)$task['id'], (int)$user['id'], true);
        flash('ok', 'Task archived.');
        redirect('/tasks/archived');
    }
    if ($action === 'restore') {
        task_set_archived((int)$task['id'], (int)$user['id'], false);
        flash('ok', 'Task restored.');
        redirect('/tasks/' . (int)$task['id']);
    }
    if ($action === 'delete') {
        task_delete((int)$task['id'], (int)$user['id']);
        flash('ok', 'Task deleted.');
        redirect('/tasks');
    }
    if ($action !== 'save') {
        flash('error', 'That action is not available.');
        redirect('/tasks/' . (int)$task['id']);
    }
    $input = task_input_from_post();
    if ($input['parse_error']) {
        view('tasks/form', task_form_data($input, (string)$input['parse_error'], $task));
        return;
    }
    $saved = task_save((int)$task['id'], (int)$user['id'], $input['title'], $input['description'], $input['items']);
    if ($saved['error']) {
        view('tasks/form', task_form_data($input, (string)$saved['error'], $task));
        return;
    }
    task_flash_mail('Task updated.', $saved['mail']);
    redirect('/tasks/' . (int)$task['id']);
}
