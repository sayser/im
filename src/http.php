<?php
declare(strict_types=1);

function app_config(): array
{
    static $config = null;
    static $loading = false;
    if (is_array($config)) {
        return $config;
    }
    if ($loading) {
        throw new RuntimeException('Configuration is not available.');
    }
    $loading = true;
    $path = APP_ROOT . '/config/config.php';
    if (!is_file($path)) {
        render_setup_needed('missing-config');
    }
    $loaded = require $path;
    if (!is_array($loaded)) {
        render_setup_needed('bad-config');
    }
    $config = $loaded;
    $loading = false;
    return $config;
}

function render_setup_needed(string $reason): never
{
    http_response_code(503);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $css = '/assets/css/app.css';
    if (!in_array($reason, ['missing-config', 'bad-config', 'extensions'], true)) {
        $base = rtrim((string)(app_config()['base_path'] ?? ''), '/');
        $css = $base . '/assets/css/app.css';
    }
    require APP_ROOT . '/views/setup_needed.php';
    exit;
}

function send_security_headers(): void
{
    $policy = "default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; "
        . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; frame-src 'none'";
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-XSS-Protection: 0');
    header('Referrer-Policy: same-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Permissions-Policy: accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    header('X-Robots-Tag: noindex, nofollow');
    if (!empty(app_config()['cookie_secure'])) {
        $policy .= '; upgrade-insecure-requests';
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Content-Security-Policy: ' . $policy);
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function app_base_path(): string
{
    $base = rtrim((string)(app_config()['base_path'] ?? ''), '/');
    if ($base !== '' && preg_match('#\A/[A-Za-z0-9._~/-]*\z#', $base) !== 1) {
        return '';
    }
    return $base;
}

function safe_path(string $path): string
{
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
    }
    $query = '';
    $split = strpos($path, '?');
    if ($split !== false) {
        $query = substr($path, $split + 1);
        $path = substr($path, 0, $split);
        if (substr_count($query, '?') > 0) {
            return '/';
        }
    }
    if (!safe_path_piece($path, true) || ($split !== false && !safe_path_piece($query, false))) {
        return '/';
    }
    return $split === false ? $path : $path . '?' . $query;
}

function safe_path_piece(string $piece, bool $isPath): bool
{
    if ($piece === '' || str_contains($piece, '//') || str_contains($piece, '\\') || str_contains($piece, '..')) {
        return false;
    }
    $pattern = $isPath
        ? '#\A/[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*\z#'
        : '#\A[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*\z#';
    return preg_match($pattern, $piece) === 1;
}

function url(string $path): string
{
    return app_base_path() . safe_path($path);
}

function asset(string $path): string
{
    $relative = '/' . ltrim($path, '/');
    $full = APP_ROOT . '/public' . $relative;
    $version = is_file($full) ? (string)filemtime($full) : '1';
    return url($relative) . '?v=' . $version;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($uri) ? rawurldecode($uri) : '/';
    $path = str_replace('\\', '/', $path);
    if (str_contains($path, "\0") || str_contains($path, '..')) {
        return '/';
    }
    $base = app_base_path();
    if ($base !== '' && ($path === $base || str_starts_with($path, $base . '/'))) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    $path = '/' . ltrim($path, '/');
    if ($path !== '/') {
        $path = rtrim($path, '/');
    }
    return $path === '' ? '/' : $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'] = [
        'type' => $type === 'error' ? 'error' : 'ok',
        'message' => $message,
    ];
}

function take_flash(): ?array
{
    if (empty($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
        return null;
    }
    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string)($_POST['_csrf'] ?? '');
    $known = (string)($_SESSION['_csrf'] ?? '');
    if ($known === '' || !hash_equals($known, $sent)) {
        http_response_code(419);
        header('Content-Type: text/html; charset=utf-8');
        $css = e(asset('assets/css/app.css'));
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Form expired</title>';
        echo '<link rel="stylesheet" href="' . $css . '"></head>';
        echo '<body class="auth"><main class="auth-card"><h1>Form expired</h1><p>Go back, refresh the page, and submit it again.</p></main></body></html>';
        exit;
    }
}

function client_ip(): string
{
    return mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function reject_oversized_body(): void
{
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length <= 2000000) {
        return;
    }
    http_response_code(413);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Request is too large.';
    exit;
}

function view(string $template, array $data = []): void
{
    if (str_contains($template, '..')) {
        throw new RuntimeException('Invalid view.');
    }
    $user = auth_user();
    $layout = (string)($data['layout'] ?? ($user ? 'layout' : 'bare'));
    if (!in_array($layout, ['layout', 'bare'], true)) {
        $layout = 'layout';
    }
    unset($data['layout']);
    $data['currentUser'] = $user;
    $data['standalone'] = $user === null;
    $data['title'] = (string)($data['title'] ?? 'PT Inventory Manager');
    $data['active'] = (string)($data['active'] ?? '');
    $data['flashMessage'] = take_flash();
    $content = render_template($template, $data);
    header('Content-Type: text/html; charset=utf-8');
    $title = $data['title'];
    $active = $data['active'];
    $currentUser = $user;
    $flashMessage = $data['flashMessage'];
    require APP_ROOT . '/views/' . $layout . '.php';
}

function render_template(string $template, array $data): string
{
    $file = APP_ROOT . '/views/' . $template . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Missing view ' . $template);
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string)ob_get_clean();
}

function abort(int $code): never
{
    http_response_code($code);
    $titles = [403 => 'Not allowed', 404 => 'Not found', 405 => 'Method not allowed'];
    $template = is_file(APP_ROOT . '/views/errors/' . $code . '.php') ? 'errors/' . $code : 'errors/404';
    view($template, [
        'title' => $titles[$code] ?? 'Error',
        'active' => '',
    ]);
    exit;
}

function clean_line(string $value): string
{
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '';
    }
    $value = str_replace(["\0", "\r", "\n"], '', $value);
    $value = trim($value);
    $collapsed = preg_replace('/\s+/u', ' ', $value);
    return $collapsed ?? '';
}

function clean_multiline(string $value): string
{
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '';
    }
    $value = str_replace("\0", '', $value);
    return trim(str_replace(["\r\n", "\r"], "\n", $value));
}

function posted_ids(string $key): array
{
    $raw = $_POST[$key] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $ids = [];
    foreach ($raw as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function null_if_blank(string $value): ?string
{
    return $value === '' ? null : $value;
}

function query_text(string $key, int $max = 200): string
{
    $value = trim((string)($_GET[$key] ?? ''));
    if (!mb_check_encoding($value, 'UTF-8')) {
        return '';
    }
    return mb_substr($value, 0, $max);
}

function paginate(int $total, int $requested, int $per): array
{
    $pages = max(1, (int)ceil($total / max(1, $per)));
    $page = min(max(1, $requested), $pages);
    return [$page, $pages, ($page - 1) * $per];
}

function display_timezone_name(): string
{
    $name = (string)(app_config()['display_timezone'] ?? 'America/New_York');
    try {
        new DateTimeZone($name);
        return $name;
    } catch (Exception) {
        return 'UTC';
    }
}

function format_when(mixed $utc): string
{
    if ($utc === null || $utc === '') {
        return '';
    }
    try {
        $dt = new DateTimeImmutable((string)$utc, new DateTimeZone('UTC'));
        return $dt->setTimezone(new DateTimeZone(display_timezone_name()))->format('M j, Y g:i A');
    } catch (Exception) {
        return (string)$utc;
    }
}

function utc_day_start(string $day): ?string
{
    return utc_day_bound($day, '00:00:00');
}

function utc_day_end(string $day): ?string
{
    return utc_day_bound($day, '23:59:59');
}

function utc_day_bound(string $day, string $clock): ?string
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) !== 1) {
        return null;
    }
    try {
        $local = new DateTimeImmutable($day . ' ' . $clock, new DateTimeZone(display_timezone_name()));
        return $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Exception) {
        return null;
    }
}

function handle_exception(Throwable $e): void
{
    error_log($e->__toString());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $debug = false;
    try {
        $debug = !empty(app_config()['debug']);
    } catch (Throwable) {
        $debug = false;
    }
    $css = '/assets/css/app.css';
    try {
        $css = e(asset('assets/css/app.css'));
    } catch (Throwable) {
        $css = '/assets/css/app.css';
    }
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Something went wrong</title>';
    echo '<link rel="stylesheet" href="' . $css . '"></head><body class="auth"><main class="auth-card">';
    echo '<h1>Something went wrong</h1>';
    if ($debug) {
        echo '<pre class="notes">' . e($e->getMessage()) . '</pre>';
    } else {
        echo '<p>The error was logged. Try again, or ask an admin to check the PHP error log.</p>';
    }
    echo '</main></body></html>';
}

function end_session(string $message): void
{
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['_flash'] = ['type' => 'error', 'message' => $message];
}

function enforce_idle_timeout(): void
{
    if (empty($_SESSION['uid'])) {
        return;
    }
    $now = time();
    $idle = max(300, (int)(app_config()['session_idle_seconds'] ?? 900));
    $absolute = max(900, (int)(app_config()['session_absolute_seconds'] ?? 43200));
    $idleFor = $now - (int)($_SESSION['last_seen'] ?? 0);
    $age = $now - (int)($_SESSION['started'] ?? 0);
    if (!isset($_SESSION['last_seen'], $_SESSION['started']) || $idleFor > $idle || $age > $absolute) {
        end_session('Your session expired. Sign in again.');
        return;
    }
    $_SESSION['last_seen'] = $now;
}

function send_csv(string $filename, array $header, array $rows): never
{
    if (preg_match('/^[A-Za-z0-9._-]+$/', $filename) !== 1) {
        $filename = 'export.csv';
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    if ($out === false) {
        throw new RuntimeException('Could not write the export.');
    }
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header);
    foreach ($rows as $row) {
        $line = [];
        foreach ($row as $value) {
            $text = (string)$value;
            if ($text !== '' && preg_match('/^[\t\r\n ]*[=+\-@|]/', $text) === 1) {
                $text = "'" . $text;
            }
            $line[] = $text;
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}
