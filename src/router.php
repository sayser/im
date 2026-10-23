<?php
declare(strict_types=1);

require APP_ROOT . '/src/pages/auth.php';
require APP_ROOT . '/src/pages/dashboard.php';
require APP_ROOT . '/src/pages/vms.php';
require APP_ROOT . '/src/pages/admin.php';
require APP_ROOT . '/src/pages/audit.php';
require APP_ROOT . '/src/pages/marks.php';
require APP_ROOT . '/src/pages/saved_views.php';

function match_route(string $pattern, string $path): ?array
{
    if (!str_contains($pattern, '{')) {
        return $pattern === $path ? [] : null;
    }
    $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[1-9][0-9]*)', $pattern) . '$#';
    if (preg_match($regex, $path, $matches) !== 1) {
        return null;
    }
    $params = [];
    foreach ($matches as $key => $value) {
        if (is_string($key)) {
            $params[$key] = (int)$value;
        }
    }
    return $params;
}

function dispatch(): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        abort(405);
    }
    if ($method === 'POST') {
        csrf_check();
    }
    $path = request_path();

    if (user_count() === 0) {
        if ($method === 'GET' && $path === '/setup') {
            page_setup();
            return;
        }
        if ($method === 'POST' && $path === '/setup') {
            page_setup_submit();
            return;
        }
        redirect('/setup');
    }
    if ($path === '/setup') {
        abort(404);
    }

    if ($method === 'POST' && $path === '/login') {
        page_login_submit();
        return;
    }
    if ($method === 'GET' && $path === '/login') {
        page_login();
        return;
    }

    require_login();

    if ($method === 'POST' && $path === '/logout') {
        page_logout();
        return;
    }

    $user = auth_user();
    if ($user && (int)$user['must_change_password'] === 1 && $path !== '/account/password') {
        redirect('/account/password');
    }

    $routes = [
        ['GET', '/', 'page_dashboard'],
        ['GET', '/environments', 'page_environments'],
        ['GET', '/environments/{id}', 'page_environment'],
        ['GET', '/search', 'page_search'],
        ['GET', '/account/password', 'page_password'],
        ['POST', '/account/password', 'page_password_submit'],
        ['GET', '/app-vms', 'page_app_index'],
        ['GET', '/app-vms/new', 'page_app_new'],
        ['POST', '/app-vms', 'page_app_create'],
        ['GET', '/app-vms/{id}', 'page_app_show'],
        ['GET', '/app-vms/{id}/edit', 'page_app_edit'],
        ['POST', '/app-vms/{id}', 'page_app_update'],
        ['POST', '/app-vms/{id}/decommission', 'page_app_decommission'],
        ['POST', '/app-vms/{id}/restore', 'page_app_restore'],
        ['GET', '/app-vms/{id}/delete', 'page_app_delete_form'],
        ['POST', '/app-vms/{id}/delete', 'page_app_delete'],
        ['GET', '/db-vms', 'page_db_index'],
        ['GET', '/db-vms/new', 'page_db_new'],
        ['POST', '/db-vms', 'page_db_create'],
        ['GET', '/db-vms/{id}', 'page_db_show'],
        ['GET', '/db-vms/{id}/edit', 'page_db_edit'],
        ['POST', '/db-vms/{id}', 'page_db_update'],
        ['POST', '/db-vms/{id}/decommission', 'page_db_decommission'],
        ['POST', '/db-vms/{id}/restore', 'page_db_restore'],
        ['GET', '/db-vms/{id}/delete', 'page_db_delete_form'],
        ['POST', '/db-vms/{id}/delete', 'page_db_delete'],
        ['GET', '/decom', 'page_decom'],
        ['GET', '/views', 'page_saved_views'],
        ['GET', '/views/new', 'page_saved_view_new'],
        ['POST', '/views', 'page_saved_view_create'],
        ['GET', '/views/{id}', 'page_saved_view_show'],
        ['POST', '/views/{id}', 'page_saved_view_update'],
        ['GET', '/marks', 'page_marks'],
        ['GET', '/marks/new', 'page_mark_new'],
        ['POST', '/marks', 'page_mark_create'],
        ['GET', '/marks/{id}', 'page_mark_show'],
        ['POST', '/marks/{id}', 'page_mark_update'],
        ['POST', '/app-vms/{id}/marks', 'page_app_mark'],
        ['POST', '/db-vms/{id}/marks', 'page_db_mark'],
        ['GET', '/export/app-vms.csv', 'page_export_app'],
        ['GET', '/export/db-vms.csv', 'page_export_db'],
        ['GET', '/audit', 'page_audit'],
        ['GET', '/audit/{id}', 'page_audit_show'],
        ['GET', '/users', 'page_users'],
        ['GET', '/users/new', 'page_user_new'],
        ['POST', '/users', 'page_user_create'],
        ['GET', '/users/{id}/edit', 'page_user_edit'],
        ['POST', '/users/{id}', 'page_user_update'],
        ['GET', '/lists', 'page_lists'],
        ['POST', '/lists', 'page_lists_create'],
        ['POST', '/lists/{id}', 'page_lists_update'],
        ['POST', '/mark-colors', 'page_mark_color_update'],
    ];

    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($routeMethod !== $method) {
            continue;
        }
        $params = match_route($pattern, $path);
        if ($params === null) {
            continue;
        }
        $reflection = new ReflectionFunction($handler);
        if ($reflection->getNumberOfParameters() === 0) {
            $handler();
        } else {
            $handler($params);
        }
        return;
    }

    abort(404);
}
