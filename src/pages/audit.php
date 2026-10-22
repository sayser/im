<?php
declare(strict_types=1);

function read_audit_filters(): array
{
    $action = (string)($_GET['action'] ?? '');
    if (!array_key_exists($action, audit_actions())) {
        $action = '';
    }
    $fromDay = query_text('from', 10);
    $toDay = query_text('to', 10);
    return [
        'q' => query_text('q'),
        'action' => $action,
        'from_day' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDay) === 1 ? $fromDay : '',
        'to_day' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDay) === 1 ? $toDay : '',
        'from' => utc_day_start($fromDay),
        'to' => utc_day_end($toDay),
        'page' => max(1, (int)($_GET['page'] ?? 1)),
    ];
}

function page_audit(): void
{
    require_login();
    $filters = read_audit_filters();
    view('audit/index', [
        'title' => 'Audit',
        'active' => 'audit',
        'filters' => $filters,
        'result' => audit_list($filters),
        'actions' => audit_actions(),
    ]);
}

function page_audit_show(array $params = []): void
{
    require_login();
    $event = audit_detail((int)$params['id']);
    if (!$event) {
        abort(404);
    }
    view('audit/show', [
        'title' => 'Audit event',
        'active' => 'audit',
        'event' => $event,
    ]);
}
