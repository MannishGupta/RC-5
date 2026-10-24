<?php
// Version: 5.0.1 (PHP 8.5.12+ critical path)
declare(strict_types=1);

if (is_file(__DIR__ . '/app/php85_runtime.php')) {
    require_once __DIR__ . '/app/php85_runtime.php';
}

/**
 * Lightweight uptime probe — no auth, no tenant, no secrets.
 * GET /health.php → application/json
 */
try {
    if (is_file(__DIR__ . '/version.php')) {
        require_once __DIR__ . '/version.php';
    }

    $payload = [
        'status'  => 'ok',
        'app'     => 'Resource Centre',
        'version' => defined('APP_VERSION') ? (string) APP_VERSION : 'unknown',
        'php'     => PHP_VERSION,
        'time'    => gmdate('c'),
    ];

    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
} catch (Throwable) {
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Robots-Tag: noindex');
        header('Cache-Control: no-store');
    }
    echo '{"status":"ok","app":"Resource Centre","version":"unknown","time":"' . gmdate('c') . '"}';
}
