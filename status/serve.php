<?php
// Version: 3.2.63 — status media serve (assets+svg+mobile) (tenant + legacy, IIS-safe)
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

function status_norm_path(string $p): string
{
    $p = str_replace('\\', '/', $p);
    $p = preg_replace('#/+#', '/', $p) ?? $p;
    return rtrim($p, '/');
}

function status_is_under(string $file, string $root): bool
{
    $f = strtolower(status_norm_path($file));
    $r = strtolower(status_norm_path($root));
    if ($r === '') {
        return false;
    }
    return $f === $r || str_starts_with($f, $r . '/');
}

$rel = (string)($_GET['f'] ?? '');
$rel = str_replace(['\\', "\0"], ['/', ''], $rel);
$rel = ltrim($rel, '/');
$rel = (string)strtok($rel, '?#');

if ($rel === '' || str_contains($rel, '..')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Bad path');
}

// Allowed roots: uploads/* (tenant + legacy), assets/* (module static logos)
$kind = 'uploads';
if (str_starts_with($rel, 'assets/')) {
    $kind = 'assets';
} elseif (str_starts_with($rel, 'uploads/')) {
    $kind = 'uploads';
} elseif (!str_contains($rel, '/')) {
    $rel = 'uploads/' . $rel;
    $kind = 'uploads';
} elseif (str_starts_with($rel, 'logos/') || str_starts_with($rel, 'projects/')) {
    // Common relative logo paths saved without uploads/ prefix
    $rel = 'uploads/' . $rel;
    $kind = 'uploads';
} else {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Forbidden');
}

if ($kind === 'assets') {
    $sub = substr($rel, strlen('assets/'));
} else {
    $sub = substr($rel, strlen('uploads/'));
}
$sub = ltrim(str_replace('\\', '/', $sub), '/');
if ($sub === '' || str_contains($sub, '..')) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Bad path');
}

$base = defined('BASE_PATH') ? (string)BASE_PATH : dirname(__DIR__);
$base = status_norm_path(str_replace('\\', '/', $base));

$tenantId = defined('TENANT_ID') ? (string)TENANT_ID : '';
if ($tenantId === '') {
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '')));
    if (str_contains($host, ':')) {
        $host = explode(':', $host, 2)[0];
    }
    $mapFile = $base . '/tenants/map.json';
    if (is_readable($mapFile)) {
        $map = json_decode((string)@file_get_contents($mapFile), true);
        $exact = is_array($map) ? ($map['exact'] ?? []) : [];
        if (is_array($exact) && isset($exact[$host]) && is_string($exact[$host])) {
            $tenantId = preg_replace('/[^a-z0-9_-]/i', '', $exact[$host]) ?? '';
        }
    }
    // rc.fusionlimited.in → fusionlimited ; rc.arthsathi.com → arthsathi
    if ($tenantId === '' && preg_match('/^rc\.([a-z0-9][a-z0-9-]{0,62})\./i', $host, $m)) {
        $tenantId = strtolower($m[1]);
    }
    if ($tenantId === '' && preg_match('/^([a-z0-9][a-z0-9-]{0,62})\./i', $host, $m)) {
        $guess = strtolower($m[1]);
        if ($guess !== 'www' && $guess !== 'rc' && is_dir($base . '/tenants/' . $guess)) {
            $tenantId = $guess;
        }
    }
}

$candidates = [];
$roots = [];

$addRoot = static function (string $root) use (&$roots, &$candidates, $sub): void {
    $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
    if ($root === '') {
        return;
    }
    // Register root even if mkdir needed later — only if exists
    if (!is_dir($root)) {
        return;
    }
    $roots[] = $root;
    $candidates[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
    $bn = basename(str_replace('\\', '/', $sub));
    foreach (['logos', 'projects', 'photos', 'images', 'updates', '2026', '2025', ''] as $folder) {
        $d = $folder === '' ? $root : ($root . DIRECTORY_SEPARATOR . $folder);
        if (is_dir($d)) {
            $candidates[] = $d . DIRECTORY_SEPARATOR . $bn;
        }
    }
};

if ($kind === 'assets') {
    $addRoot(__DIR__ . DIRECTORY_SEPARATOR . 'assets');
    $addRoot($base . '/status/assets');
} elseif (defined('UPLOAD_DIR') && UPLOAD_DIR !== '') {
    $addRoot((string)UPLOAD_DIR);
}
if (defined('DATA_DIR') && DATA_DIR !== '') {
    $addRoot(rtrim((string)DATA_DIR, '/\\') . DIRECTORY_SEPARATOR . 'uploads');
}
if (defined('DATA_PATH') && DATA_PATH !== '') {
    $addRoot(rtrim((string)DATA_PATH, '/\\') . DIRECTORY_SEPARATOR . 'status' . DIRECTORY_SEPARATOR . 'uploads');
    $addRoot(rtrim((string)DATA_PATH, '/\\') . DIRECTORY_SEPARATOR . 'status');
}
if ($tenantId !== '') {
    $addRoot($base . '/tenants/' . $tenantId . '/data/status/uploads');
    $addRoot($base . '/tenants/' . $tenantId . '/data/status');
    $addRoot($base . '/tenants/' . $tenantId . '/data/uploads');
}
$addRoot(__DIR__ . '/uploads');
$addRoot($base . '/status/uploads');
$addRoot($base . '/status/data/uploads');

// If requesting status/assets/*, only search asset roots (ignore tenant uploads noise)
if ($kind === 'assets') {
    $roots = [];
    $candidates = [];
    $ar = __DIR__ . DIRECTORY_SEPARATOR . 'assets';
    if (is_dir($ar)) {
        $roots[] = $ar;
        $candidates[] = $ar . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $sub);
        $candidates[] = $ar . DIRECTORY_SEPARATOR . basename(str_replace('\\', '/', $sub));
    }
}

// Deduplicate
$seen = [];
$unique = [];
foreach ($candidates as $c) {
    $k = strtolower(str_replace('\\', '/', $c));
    if (isset($seen[$k])) {
        continue;
    }
    $seen[$k] = true;
    $unique[] = $c;
}

$real = null;
$bnWant = strtolower(basename(str_replace('\\', '/', $sub)));
$stemWant = strtolower(pathinfo($bnWant, PATHINFO_FILENAME));

foreach ($unique as $abs) {
    if (@is_file($abs) && @is_readable($abs)) {
        $resolved = @realpath($abs);
        $check = $resolved !== false ? $resolved : $abs;
        $ok = false;
        foreach ($roots as $root) {
            $rootReal = @realpath($root);
            $rootCheck = $rootReal !== false ? $rootReal : $root;
            if (status_is_under($check, $rootCheck) || status_is_under($abs, $root)) {
                $ok = true;
                break;
            }
        }
        if ($ok || $roots === []) {
            $real = $resolved !== false ? $resolved : $abs;
            break;
        }
    }
}

// Recursive basename search under roots (handles moved year/month folders)
if ($real === null && $bnWant !== '') {
    foreach ($roots as $root) {
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );
            $n = 0;
            foreach ($it as $fi) {
                if (++$n > 8000) {
                    break;
                }
                if (!$fi->isFile()) {
                    continue;
                }
                $name = $fi->getFilename();
                if (strtolower($name) === $bnWant) {
                    $real = $fi->getPathname();
                    break 2;
                }
                // Alternate extension after optimise (jpg→webp) — logos/photos only
                $stem = strtolower(pathinfo($name, PATHINFO_FILENAME));
                if ($stem === $stemWant && $stemWant !== '') {
                    $real = $fi->getPathname();
                    break 2;
                }
            }
        } catch (Throwable $e) {
        }
    }
}

if ($real === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit('Not found');
}

$ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
$mapMime = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
    'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
    'avif' => 'image/avif', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
    'mp4' => 'video/mp4', 'webm' => 'video/webm',
];
$mime = $mapMime[$ext] ?? 'application/octet-stream';
if ($mime === 'application/octet-stream' && function_exists('mime_content_type')) {
    $m = @mime_content_type($real);
    if (is_string($m) && $m !== '') {
        $mime = $m;
    }
}

$size = (int)@filesize($real);
$mtime = (int)@filemtime($real);

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
if ($size > 0) {
    header('Content-Length: ' . (string)$size);
}
header('Cache-Control: public, max-age=86400');
if ($ext === 'svg') {
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src data:; sandbox");
    header('Content-Disposition: inline; filename="' . str_replace(['"', "\r", "\n"], '', basename($real)) . '"');
}
if ($mtime > 0) {
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: "' . md5($real . '|' . $mtime . '|' . $size) . '"');
}

$fh = @fopen($real, 'rb');
if ($fh === false) {
    // last resort readfile
    if (@readfile($real) === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=UTF-8');
        exit('Read error');
    }
    exit;
}
fpassthru($fh);
fclose($fh);
exit;
