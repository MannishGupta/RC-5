<?php
/**
 * PHP 8.5.12+ runtime entry for Resource Centre 5.2.
 * Hard gate: refuses to boot below 8.5.12.
 * Native array_first() / array_last() — no polyfills.
 *
 * @package ResourceCentre
 * @version 5.2.0
 */
declare(strict_types=1);

// PHP 8.5.12 → PHP_VERSION_ID = 80512
if (\PHP_VERSION_ID < 80512) {
    $msg = 'Resource Centre 5.2 requires PHP 8.5.12 or newer (security + language baseline). '
         . 'Current: ' . \PHP_VERSION;
    if (\PHP_SAPI === 'cli') {
        fwrite(\STDERR, $msg . \PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Retry-After: 3600');
    }
    echo $msg;
    exit;
}

// Prefer native array_first() / array_last() over reset()/end() value lookups.

if (\PHP_SAPI !== 'cli') {
    if (function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
    }
    $tz = date_default_timezone_get();
    if ($tz === 'UTC' || $tz === '') {
        date_default_timezone_set('Asia/Kolkata');
    }
}
