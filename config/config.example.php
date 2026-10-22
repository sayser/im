<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'pt_inventory',
        'user' => 'pt_inventory',
        'pass' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    // Leave empty when the site is served from the host root.
    'base_path' => '',
    'session_name' => 'ptim',
    // Idle timeout is 15 minutes. The session also ends 12 hours after sign-in.
    'session_idle_seconds' => 900,
    'session_absolute_seconds' => 43200,
    'display_timezone' => 'America/New_York',
    // Required in production. The app refuses to start when this is false and debug is false.
    'cookie_secure' => true,
    'debug' => false,
];
