<?php
/**
 * PHP 8.5+ runtime entry for Resource Centre.
 * Version gates stay enforced. Native array_first / array_last — no polyfills.
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

// PHP 8.5 provides array_first() / array_last() natively.
// Prefer them over reset()/end()/array_key_first() value lookups in app code.
