<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = is_string($uri) ? rawurldecode($uri) : '/';
    $root = realpath(__DIR__);
    $file = realpath(__DIR__ . $path);
    $inside = $root !== false
        && $file !== false
        && is_file($file)
        && str_starts_with($file, $root . DIRECTORY_SEPARATOR);
    if ($path !== '/' && !str_contains($path, '..') && !str_contains($path, "\0") && $inside) {
        return false;
    }
}

require __DIR__ . '/index.php';
