<?php
declare(strict_types=1);

function page_dashboard(): void
{
    require_login();
    view('dashboard', [
        'title' => 'Dashboard',
        'active' => 'dashboard',
        'stats' => dashboard_stats(),
    ]);
}
