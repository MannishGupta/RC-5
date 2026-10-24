<?php
// Version: 5.0.0 (PHP 8.5.12+ runtime helpers)
/**
 * PHP 8.5.12+ runtime helpers for Resource Centre.
 * Loaded early from bootstrap / tenant entry points.
 * Native array_first / array_last exist on 8.5; polyfills only when absent.
 */
declare(strict_types=1);

if (\PHP_VERSION_ID < 80500) {
    $msg = 'Resource Centre 5.x requires PHP 8.5.0 or newer (recommended 8.5.12+ for security fixes). '
        . 'Current: ' . \PHP_VERSION;
    if (\PHP_SAPI === 'cli') {
        fwrite(\STDERR, $msg . \PHP_EOL);
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $msg;
    exit;
}

if (!\function_exists('array_first')) {
    /**
     * @param array<mixed> $array
     */
    function array_first(array $array): mixed
    {
        if ($array === []) {
            return null;
        }
        return $array[\array_key_first($array)];
    }
}

if (!\function_exists('array_last')) {
    /**
     * @param array<mixed> $array
     */
    function array_last(array $array): mixed
    {
        if ($array === []) {
            return null;
        }
        return $array[\array_key_last($array)];
    }
}
