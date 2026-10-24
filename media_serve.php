<?php
// Version: 5.0.1 (PHP 8.5.12+ critical path)
declare(strict_types=1);

$base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}

require_once BASE_PATH . '/app/tenant_bootstrap.php';

if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
if (!defined('IMG_PATH')) {
    define('IMG_PATH', DATA_PATH . '/media/images');
}

/**
 * MIME map for allowed extensions (match expression style).
 */
function rc_media_mime(string $ext): ?string
{
    return match (strtolower($ext)) {
        'webp' => 'image/webp',
        'jpg', 'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'ico'  => 'image/x-icon',
        'bmp'  => 'image/bmp',
        'avif' => 'image/avif',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        default => null,
    };
}

function rc_media_deny(int $code, string $message): never
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

// ── Master logos: ?m=banks|oems/{slug}.{ext} ──────────────────────────
$m = (string) ($_GET['m'] ?? '');
if ($m !== '') {
    $m = str_replace(['\\', "\0"], ['/', ''], $m);
    $m = ltrim($m, '/');
    if (!preg_match('#^(banks|oems)/([a-z0-9][a-z0-9\-]{0,80})\.(svg|png|webp)$#i', $m, $mm)) {
        rc_media_deny(400, 'Bad master logo path');
    }
    $kind = strtolower($mm[1]);
    $slug = strtolower($mm[2]);
    $ext  = strtolower($mm[3]);
    $root = rtrim((string) DATA_PATH, '/\\') . '/masters/logos/' . $kind;
    $path = $root . '/' . $slug . '.' . $ext;

    if (is_file(BASE_PATH . '/app/PathJail.php') && !class_exists('PathJail', false)) {
        require_once BASE_PATH . '/app/PathJail.php';
    }
    if (class_exists('PathJail', false)) {
        try {
            PathJail::assertWritable($path, $root);
        } catch (Throwable) {
            rc_media_deny(403, 'Forbidden');
        }
    }

    $real = realpath($path);
    $rootReal = realpath($root);
    if ($real === false || $rootReal === false || !str_starts_with($real, $rootReal) || !is_file($real)) {
        rc_media_deny(404, 'Not found');
    }

    $mime = rc_media_mime($ext);
    if ($mime === null) {
        rc_media_deny(415, 'Unsupported type');
    }

    $mtime = (int) @filemtime($real);
    $size  = (int) @filesize($real);
    $etag  = '"' . md5($real . '|' . $mtime . '|' . $size) . '"';

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    if ($ext === 'svg') {
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
    }
    header('Cache-Control: public, max-age=86400');
    header('ETag: ' . $etag);
    header('Content-Length: ' . (string) $size);
    header('Access-Control-Allow-Origin: *');
    header('Cross-Origin-Resource-Policy: cross-origin');
    readfile($real);
    exit;
}

// ── Tenant / general media: ?f=filename ────────────────────────────────
$f = (string) ($_GET['f'] ?? $_GET['file'] ?? '');
$f = str_replace(['\\', "\0"], ['/', ''], $f);
$f = ltrim($f, '/');
if ($f === '' || str_contains($f, '..')) {
    rc_media_deny(400, 'Bad path');
}

$candidates = [];
if (defined('IMG_PATH')) {
    $candidates[] = rtrim((string) IMG_PATH, '/\\') . '/' . $f;
}
$candidates[] = rtrim((string) DATA_PATH, '/\\') . '/media/images/' . $f;
$candidates[] = rtrim((string) DATA_PATH, '/\\') . '/images/' . $f;
$candidates[] = rtrim((string) DATA_PATH, '/\\') . '/media/' . $f;
$candidates[] = rtrim((string) DATA_PATH, '/\\') . '/' . $f;

$resolved = null;
foreach ($candidates as $cand) {
    $real = realpath($cand);
    $dataReal = realpath((string) DATA_PATH);
    if ($real !== false && $dataReal !== false && str_starts_with($real, $dataReal) && is_file($real)) {
        $resolved = $real;
        break;
    }
}

// Recursive basename search under DATA_PATH/media (bounded)
if ($resolved === null && is_dir((string) DATA_PATH . '/media')) {
    $baseName = basename($f);
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                rtrim((string) DATA_PATH, '/\\') . '/media',
                FilesystemIterator::SKIP_DOTS
            )
        );
        $n = 0;
        foreach ($it as $fi) {
            if (++$n > 5000) {
                break;
            }
            if ($fi->isFile() && strcasecmp($fi->getFilename(), $baseName) === 0) {
                $resolved = $fi->getPathname();
                break;
            }
        }
    } catch (Throwable) {
        // ignore scan errors
    }
}

if ($resolved === null) {
    rc_media_deny(404, 'Not found');
}

$ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
$mime = rc_media_mime($ext);
if ($mime === null) {
    rc_media_deny(415, 'Unsupported type');
}

$mtime = (int) @filemtime($resolved);
$size  = (int) @filesize($resolved);
$etag  = '"' . md5($resolved . '|' . $mtime . '|' . $size) . '"';

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim((string) $_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');

if ($ext === 'pdf') {
    header('Content-Disposition: inline; filename="' . str_replace('"', '', basename($f)) . '"');
}
if ($ext === 'svg') {
    header("Content-Security-Policy: default-src 'none'; img-src * data: https: http:; style-src 'unsafe-inline' 'self'; font-src 'self' data:; sandbox");
    header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename($f)) . '"');
}

header('Content-Length: ' . (string) $size);
if (preg_match('/^company-(logo|favicon|cover)\./i', basename($resolved))) {
    header('Cache-Control: no-cache, must-revalidate');
} else {
    header('Cache-Control: public, max-age=2592000, immutable');
}
header('Access-Control-Allow-Origin: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

$fp = fopen($resolved, 'rb');
if ($fp !== false) {
    fpassthru($fp);
    fclose($fp);
} else {
    readfile($resolved);
}
exit;
