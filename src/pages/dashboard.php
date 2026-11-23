<?php
declare(strict_types=1);

function page_dashboard(): void
{
    $user = require_login();
    $assigned = [];
    foreach (task_list_for((int)$user['id']) as $task) {
        if (($task['panel'] ?? '') !== 'completed') {
            $assigned[] = $task;
        }
    }
    view('dashboard', [
        'title' => 'Dashboard',
        'active' => 'dashboard',
        'stats' => dashboard_stats(),
        'sslSoon' => ssl_soon(28),
        'assigned' => $assigned,
    ]);
}
