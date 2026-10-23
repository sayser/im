<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/http.php';
require APP_ROOT . '/src/html.php';
require APP_ROOT . '/src/auth.php';
require APP_ROOT . '/src/audit.php';
require APP_ROOT . '/src/inventory.php';
require APP_ROOT . '/src/marks.php';
require APP_ROOT . '/src/saved_views.php';
require APP_ROOT . '/src/router.php';

function boot(): void
{
    if (!extension_loaded('pdo_mysql') || !extension_loaded('mbstring')) {
        render_setup_needed('extensions');
    }
    $config = app_config();
    date_default_timezone_set('UTC');
    mb_internal_encoding('UTF-8');
    ini_set('display_errors', empty($config['debug']) ? '0' : '1');
    ini_set('log_errors', '1');
    ini_set('zend.exception_ignore_args', '1');
    send_security_headers();
    reject_oversized_body();
    if (empty($config['debug']) && empty($config['cookie_secure'])) {
        render_setup_needed('https');
    }

    $idle = max(300, (int)($config['session_idle_seconds'] ?? 900));
    $absolute = max(900, (int)($config['session_absolute_seconds'] ?? 43200));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', empty($config['cookie_secure']) ? '0' : '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    ini_set('session.gc_maxlifetime', (string)max($idle, $absolute));

    $name = (string)($config['session_name'] ?? 'ptim');
    session_name(preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name) === 1 ? $name : 'ptim');
    $base = app_base_path();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $base === '' ? '/' : $base,
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => !empty($config['cookie_secure']),
    ]);
    session_start();
    enforce_idle_timeout();
    set_exception_handler('handle_exception');

    try {
        db();
        if (!schema_ready()) {
            render_setup_needed('schema');
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        render_setup_needed('db');
    }
}
