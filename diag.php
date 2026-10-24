<?php
// Version: 5.1.3 (shared-hosting probe + extended host tests)
/**
 * Resource Centre — environment diagnostic (shared hosting safe)
 *
 * Usage:
 *   https://your-host/diag.php
 *   https://your-host/diag.php?key=YOUR_SECRET   (if DIAG_KEY is set below)
 *
 * DELETE this file after go-live. It is noindex and should not stay public.
 */
declare(strict_types=1);

// Optional: set a secret so random visitors cannot open this page.
// Example: const DIAG_KEY = 'change-me-long-random';
const DIAG_KEY = '';

if (DIAG_KEY !== '') {
    $k = (string) ($_GET['key'] ?? '');
    if (!hash_equals(DIAG_KEY, $k)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow');
        echo "Forbidden. Append ?key=… matching DIAG_KEY in diag.php\n";
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
}

$appVersion = 'unknown';
if (is_file(__DIR__ . '/version.php')) {
    require_once __DIR__ . '/version.php';
    if (defined('APP_VERSION')) {
        $appVersion = (string) APP_VERSION;
    }
}


// ── Header stamp (time, IPs, host identity) ─────────────────
$stampNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
$stampUtc = $stampNow->setTimezone(new DateTimeZone('UTC'));

$serverAddr = (string) ($_SERVER['SERVER_ADDR'] ?? '');
if ($serverAddr === '' && function_exists('gethostbyname')) {
    $hn = (string) ($_SERVER['SERVER_NAME'] ?? gethostname() ?: '');
    if ($hn !== '' && $hn !== 'localhost') {
        $resolved = @gethostbyname($hn);
        if (is_string($resolved) && $resolved !== '' && $resolved !== $hn) {
            $serverAddr = $resolved;
        }
    }
}
$remoteAddr = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$httpsOn = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
);
$hostHeader = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
$reqUri = (string) ($_SERVER['REQUEST_URI'] ?? '/diag.php');
$scheme = $httpsOn ? 'https' : 'http';
$publicUrl = $hostHeader !== '' ? ($scheme . '://' . $hostHeader . $reqUri) : $reqUri;

$stamp = [
    'Generated (IST)'   => $stampNow->format('Y-m-d H:i:s T'),
    'Generated (UTC)'   => $stampUtc->format('Y-m-d H:i:s T'),
    'Unix time'         => (string) $stampNow->getTimestamp(),
    'RC version'        => $appVersion,
    'PHP version'       => PHP_VERSION . ' / ' . PHP_SAPI,
    'Server software'   => (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'),
    'Hostname'          => (string) (function_exists('gethostname') ? (gethostname() ?: '') : ''),
    'HTTP_HOST'         => $hostHeader,
    'Public URL'        => $publicUrl,
    'Document root'     => (string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__),
    'Script path'       => (string) (__FILE__),
    'Server IP'         => $serverAddr !== '' ? $serverAddr : '(not exposed by host)',
    'Your IP (client)'  => $remoteAddr !== '' ? $remoteAddr : '(unknown)',
    'HTTPS'             => $httpsOn ? 'yes' : 'no',
    'Server port'       => (string) ($_SERVER['SERVER_PORT'] ?? ''),
    'X-Forwarded-For'   => (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
    'X-Forwarded-Proto' => (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''),
    'User agent'        => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
    'Loaded php.ini'    => (string) (php_ini_loaded_file() ?: '(none)'),
    'Additional ini'    => (string) (php_ini_scanned_files() ?: '(none)'),
];


/**
 * @return array{ok:bool,label:string,detail:string,level:string}
 */
function row(bool $ok, string $label, string $detail = '', string $level = ''): array
{
    if ($level === '') {
        $level = $ok ? 'ok' : 'fail';
    }
    return ['ok' => $ok, 'label' => $label, 'detail' => $detail, 'level' => $level];
}

$required = [];
$optional = [];
$info = [];

// ── PHP version ──────────────────────────────────────────────
$phpId = PHP_VERSION_ID;
$minId = 80500; // RC 5.x
$recId = 80512; // 8.5.12 security floor
$phpOk = $phpId >= $minId;
$phpDetail = 'Running ' . PHP_VERSION . ' (ID ' . $phpId . '). ';
if ($phpId < $minId) {
    $phpDetail .= 'Required: PHP 8.5.0+. Ask host to switch this domain to PHP 8.5.';
} elseif ($phpId < $recId) {
    $phpDetail .= 'Works, but upgrade to 8.5.12+ for security fixes when Plesk offers it.';
    $phpOk = true;
} else {
    $phpDetail .= 'Meets recommended 8.5.12+ security build.';
}
$required[] = row($phpId >= $minId, 'PHP version ≥ 8.5.0', $phpDetail, $phpId >= $recId ? 'ok' : ($phpId >= $minId ? 'warn' : 'fail'));

// ── Required extensions ──────────────────────────────────────
$reqExt = [
    'json'     => 'JSON encode/decode (API, AppDB)',
    'mbstring' => 'Multibyte strings (Hindi/en-IN, sanitiser)',
    'fileinfo' => 'MIME detection on uploads (finfo)',
    'openssl'  => 'HTTPS clients, signatures, secure tokens',
    'curl'     => 'Outbound HTTP (metals, AI, Google Sheets, oEmbed)',
    'gd'       => 'Image resize/optimise, PWA icons, OG cards',
    'dom'      => 'DOMDocument — SVG sanitise & XML tools',
    'libxml'   => 'XML/HTML parsing used with DOM',
    'zlib'     => 'Compression / some archive flows',
];
foreach ($reqExt as $ext => $why) {
    $on = extension_loaded($ext);
    $extra = '';
    if ($ext === 'dom') {
        $extra = class_exists('DOMDocument', false) ? ' DOMDocument class OK.' : ' extension flag on but class missing.';
        $on = $on && class_exists('DOMDocument', false);
    }
    if ($ext === 'gd' && $on) {
        $g = gd_info();
        $bits = [];
        foreach (['JPEG Support' => 'JPEG', 'PNG Support' => 'PNG', 'WebP Support' => 'WebP', 'FreeType Support' => 'FreeType'] as $k => $lab) {
            if (!empty($g[$k])) {
                $bits[] = $lab;
            }
        }
        $extra = ' Formats: ' . ($bits ? implode(', ', $bits) : 'unknown');
        if (empty($g['WebP Support'])) {
            $required[] = row(false, 'GD WebP support', 'GD loaded but WebP missing — logos/optimiser prefer WebP. Ask host for php-gd with WebP.', 'warn');
        }
    }
    $required[] = row($on, "Extension: {$ext}", $why . $extra);
}

// ── Required functions ───────────────────────────────────────
$reqFn = [
    'json_encode' => 'API responses',
    'json_decode' => 'AppDB / config',
    'mb_substr' => 'Unicode-safe strings',
    'curl_init' => 'HTTP clients',
    'finfo_open' => 'Upload MIME checks',
    'imagecreatetruecolor' => 'GD image pipeline',
    'hash_equals' => 'Safe token compare',
    'random_bytes' => 'CSRF / secrets',
    'password_verify' => 'Auth hashes',
];
foreach ($reqFn as $fn => $why) {
    $required[] = row(function_exists($fn), "Function: {$fn}", $why);
}

// ── Optional (nice to have) ──────────────────────────────────
$optExt = [
    'imagick'  => 'Better SVG→raster / advanced image ops (optional)',
    'intl'     => 'Locale-aware number/date formatting',
    'zip'      => 'ZipArchive for installers / backups',
    'exif'     => 'Photo orientation fix on upload',
    'opcache'  => 'Performance (almost always on for production)',
    'apcu'     => 'Optional local cache',
];
foreach ($optExt as $ext => $why) {
    $on = extension_loaded($ext);
    if ($ext === 'zip') {
        $on = $on && class_exists('ZipArchive', false);
    }
    if ($ext === 'imagick') {
        $on = $on && class_exists('Imagick', false);
    }
    $optional[] = row($on, "Optional: {$ext}", $why, $on ? 'ok' : 'warn');
}

$optFn = [
    'imagewebp' => 'Serve/save WebP via GD',
    'exif_read_data' => 'Auto-rotate phone photos',
    'opcache_reset' => 'Version bump can flush OPcache',
    'mime_content_type' => 'Fallback MIME probe',
];
foreach ($optFn as $fn => $why) {
    $on = function_exists($fn);
    $optional[] = row($on, "Optional function: {$fn}", $why, $on ? 'ok' : 'warn');
}

// Disabled functions (expected on many shared hosts)
$disabledRaw = (string) ini_get('disable_functions');
$disabled = array_filter(array_map('trim', explode(',', strtolower($disabledRaw))));
$risky = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen'];
$disabledHit = array_values(array_intersect($risky, $disabled));
$optional[] = row(
    true,
    'Shell exec functions',
    $disabledHit
        ? ('Disabled (OK for RC): ' . implode(', ', $disabledHit) . ' — app does not require them.')
        : 'Not disabled. RC still does not need shell_exec on shared hosting.',
    'ok'
);

// ── INI limits ───────────────────────────────────────────────
function ini_bytes(string $val): int
{
    $val = trim($val);
    if ($val === '') {
        return 0;
    }
    $u = strtolower(substr($val, -1));
    $n = (float) $val;
    return (int) match ($u) {
        'g' => $n * 1073741824,
        'm' => $n * 1048576,
        'k' => $n * 1024,
        default => (int) $n,
    };
}

$mem = (string) ini_get('memory_limit');
$memB = ini_bytes($mem);
$required[] = row(
    $memB === -1 || $memB >= 128 * 1048576,
    'memory_limit ≥ 128M',
    "Current: {$mem}" . ($memB > 0 && $memB < 256 * 1048576 ? ' — 256M recommended for mediakit.' : ''),
    ($memB === -1 || $memB >= 256 * 1048576) ? 'ok' : (($memB >= 128 * 1048576) ? 'warn' : 'fail')
);

$upload = (string) ini_get('upload_max_filesize');
$post = (string) ini_get('post_max_size');
$upB = ini_bytes($upload);
$postB = ini_bytes($post);
$required[] = row(
    $upB >= 2 * 1048576,
    'upload_max_filesize ≥ 2M',
    "Current: {$upload}. Logos/SVG often 0.5–3MB before server optimise.",
    $upB >= 8 * 1048576 ? 'ok' : ($upB >= 2 * 1048576 ? 'warn' : 'fail')
);
$required[] = row(
    $postB >= $upB && $postB >= 2 * 1048576,
    'post_max_size ≥ upload_max_filesize',
    "post_max_size={$post}, upload_max_filesize={$upload}",
    $postB >= $upB ? 'ok' : 'fail'
);

$maxExec = (int) ini_get('max_execution_time');
$optional[] = row(
    $maxExec === 0 || $maxExec >= 60,
    'max_execution_time ≥ 60 (or 0)',
    "Current: {$maxExec}",
    ($maxExec === 0 || $maxExec >= 60) ? 'ok' : 'warn'
);

$optional[] = row(
    (string) ini_get('allow_url_fopen') === '1',
    'allow_url_fopen',
    'Some fallbacks use it; curl covers most RC HTTP needs.',
    (string) ini_get('allow_url_fopen') === '1' ? 'ok' : 'warn'
);

// ── Paths / writability ──────────────────────────────────────
$base = __DIR__;
$dataCandidates = [];
if (is_file($base . '/app/tenant_bootstrap.php')) {
    // Don't fully boot tenant (may need host) — probe common paths
}
$dataCandidates[] = $base . '/data';
$dataCandidates[] = $base . '/tenants';
$writableChecks = [
    'data/' => $base . '/data',
    'data/media/images/ (or create)' => $base . '/data/media/images',
    'data/cache/ (or create)' => $base . '/data/cache',
];
foreach ($writableChecks as $label => $path) {
    $dir = $path;
    if (!is_dir($dir)) {
        $parent = dirname($dir);
        $canParent = is_dir($parent) && is_writable($parent);
        $required[] = row(
            $canParent,
            "Writable path: {$label}",
            is_dir($dir)
                ? $dir
                : ("Missing; parent writable=" . ($canParent ? 'yes' : 'no') . " — RC creates folders on first save when parent allows."),
            $canParent ? 'warn' : 'fail'
        );
        continue;
    }
    $w = is_writable($dir);
    $required[] = row($w, "Writable path: {$label}", $dir . ($w ? '' : ' — not writable; uploads/cache will fail.'));
}

$sessionPath = (string) ini_get('session.save_path');
$info[] = row(true, 'session.save_path', $sessionPath !== '' ? $sessionPath : '(empty — PHP default)', 'ok');
$info[] = row(true, 'SAPI', PHP_SAPI, 'ok');
$info[] = row(true, 'Server software', (string) ($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'), 'ok');
$info[] = row(true, 'Document root', (string) ($_SERVER['DOCUMENT_ROOT'] ?? $base), 'ok');
$info[] = row(true, 'RC version.php', $appVersion, 'ok');
$info[] = row(true, 'Timezone (php.ini)', date_default_timezone_get(), 'ok');
$info[] = row(true, 'Temp dir', sys_get_temp_dir() . (is_writable(sys_get_temp_dir()) ? ' (writable)' : ' (NOT writable)'), is_writable(sys_get_temp_dir()) ? 'ok' : 'fail');

// open_basedir
$ob = (string) ini_get('open_basedir');
$info[] = row(true, 'open_basedir', $ob !== '' ? $ob : '(not set)', 'ok');


// ── Extended host probes (shared-hosting safe) ───────────────

// max_input_vars / max_file_uploads
$maxInput = (int) ini_get('max_input_vars');
$maxFiles = (int) ini_get('max_file_uploads');
$optional[] = row(
    $maxInput >= 1000,
    'max_input_vars ≥ 1000',
    'Current: ' . $maxInput . ' — large company/mediakit forms need headroom.',
    $maxInput >= 3000 ? 'ok' : ($maxInput >= 1000 ? 'warn' : 'fail')
);
$optional[] = row(
    $maxFiles >= 20,
    'max_file_uploads ≥ 20',
    'Current: ' . $maxFiles . ' — bulk mediakit upload.',
    $maxFiles >= 50 ? 'ok' : ($maxFiles >= 20 ? 'warn' : 'fail')
);

// OPcache
$opcOn = function_exists('opcache_get_status');
$opcDetail = 'extension not loaded';
if ($opcOn) {
    $st = @opcache_get_status(false);
    if (is_array($st)) {
        $opcOn = !empty($st['opcache_enabled']);
        $opcDetail = $opcOn
            ? ('enabled; scripts=' . (string) ($st['opcache_statistics']['num_cached_scripts'] ?? '?'))
            : 'loaded but opcache_enabled=false';
    } else {
        $opcDetail = 'loaded; status unavailable (often restricted on shared hosting)';
        $opcOn = extension_loaded('Zend OPcache') || extension_loaded('opcache');
    }
}
$optional[] = row($opcOn, 'OPcache', $opcDetail, $opcOn ? 'ok' : 'warn');

// Session real write test
$sessionOk = false;
$sessionDetail = '';
try {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['__rc_diag'] = 'ok:' . (string) time();
        $sessionOk = ($_SESSION['__rc_diag'] ?? '') !== '';
        $sessionDetail = 'session_id=' . session_id() . '; save_path=' . (string) ini_get('session.save_path');
        unset($_SESSION['__rc_diag']);
    } else {
        $sessionDetail = 'session could not be started';
    }
} catch (Throwable $e) {
    $sessionDetail = 'error: ' . $e->getMessage();
}
$required[] = row($sessionOk, 'Session write test', $sessionDetail);

// Outbound HTTPS (curl) — needed for metals / AI / oEmbed
$httpsOutOk = false;
$httpsOutDetail = 'curl missing';
if (function_exists('curl_init')) {
    $ch = curl_init('https://www.example.com/');
    if ($ch !== false) {
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'RC-diag/5.1.3',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_NOBODY => true,
        ]);
        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        unset($ch);
        if ($errno === 0 && $code > 0) {
            $httpsOutOk = true;
            $httpsOutDetail = 'HTTP ' . $code . ' from https://www.example.com/ (TLS OK)';
        } else {
            $httpsOutDetail = 'failed errno=' . $errno . ' http=' . $code . ' ' . $err;
        }
    }
}
$required[] = row($httpsOutOk, 'Outbound HTTPS (curl)', $httpsOutDetail);

// Disk free space
$diskPath = (string) ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$diskFree = @disk_free_space($diskPath);
$diskTotal = @disk_total_space($diskPath);
if ($diskFree === false || $diskTotal === false) {
    $optional[] = row(false, 'Disk free space', 'disk_free_space() blocked or unavailable on this host', 'warn');
} else {
    $freeMb = round($diskFree / 1048576, 1);
    $totalMb = round($diskTotal / 1048576, 1);
    $optional[] = row(
        $diskFree >= 100 * 1048576,
        'Disk free space ≥ 100 MB',
        "Free {$freeMb} MB / total {$totalMb} MB under {$diskPath}",
        $diskFree >= 500 * 1048576 ? 'ok' : ($diskFree >= 100 * 1048576 ? 'warn' : 'fail')
    );
}

// putenv / getenv
$putOk = function_exists('putenv') && function_exists('getenv');
$putDetail = 'functions missing';
if ($putOk) {
    $token = 'RC_DIAG_' . bin2hex(random_bytes(4));
    $prev = getenv($token);
    $putOk = @putenv($token . '=1') && getenv($token) === '1';
    @putenv($token); // clear
    $putDetail = $putOk ? 'putenv/getenv work' : 'functions exist but putenv is disabled by host policy';
}
$optional[] = row($putOk, 'putenv / getenv', $putDetail, $putOk ? 'ok' : 'warn');

// mail() exists only — do not send
$optional[] = row(
    function_exists('mail'),
    'mail() function exists',
    function_exists('mail')
        ? 'Present (not tested with a real send — use SMTP in RC for production mail).'
        : 'Missing — configure SMTP in RC; do not rely on PHP mail().',
    function_exists('mail') ? 'ok' : 'warn'
);

// URL rewriting probe (best-effort)
$rewriteDetail = 'unknown';
$rewriteOk = false;
$httpsOnProbe = (
    (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
    || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https')
);
$sw = strtolower((string) ($_SERVER['SERVER_SOFTWARE'] ?? ''));
if (is_file(__DIR__ . '/web.config')) {
    $wc = (string) @file_get_contents(__DIR__ . '/web.config');
    $rewriteOk = stripos($wc, '<rewrite>') !== false || stripos($wc, 'URLRewrite') !== false;
    $rewriteDetail = $rewriteOk
        ? 'web.config present with rewrite rules (IIS URL Rewrite expected)'
        : 'web.config present but no <rewrite> section detected';
} elseif (is_file(__DIR__ . '/.htaccess')) {
    $ht = (string) @file_get_contents(__DIR__ . '/.htaccess');
    $rewriteOk = stripos($ht, 'RewriteEngine') !== false;
    $rewriteDetail = $rewriteOk ? '.htaccess RewriteEngine found (Apache)' : '.htaccess found, RewriteEngine not detected';
} else {
    $rewriteDetail = 'No web.config/.htaccess in site root — RC mostly uses query routes (?tab=); rewrite optional';
    $rewriteOk = true; // not required for RC core
}
$optional[] = row($rewriteOk, 'URL rewrite config', $rewriteDetail, $rewriteOk ? 'ok' : 'warn');

// RC tenant resolution (read-only)
$tenantDetail = 'tenants/map.json not found';
$tenantOk = false;
$mapFile = __DIR__ . '/tenants/map.json';
if (is_file($mapFile)) {
    $map = json_decode((string) @file_get_contents($mapFile), true);
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    if (is_array($map)) {
        $matched = '';
        // common shapes: exact host keys, or list with host/hosts
        if (isset($map['exact']) && is_array($map['exact'])) {
            foreach ($map['exact'] as $h => $tid) {
                if (strtolower((string) $h) === $host) {
                    $matched = (string) $tid;
                    break;
                }
            }
        }
        if ($matched === '') {
            foreach ($map as $k => $v) {
                if (is_string($k) && strtolower($k) === $host && is_string($v)) {
                    $matched = $v;
                    break;
                }
                if (is_array($v)) {
                    $hosts = $v['hosts'] ?? $v['host'] ?? null;
                    if (is_string($hosts) && strtolower($hosts) === $host) {
                        $matched = (string) ($v['id'] ?? $v['tenant'] ?? $k);
                        break;
                    }
                    if (is_array($hosts)) {
                        foreach ($hosts as $h) {
                            if (strtolower((string) $h) === $host) {
                                $matched = (string) ($v['id'] ?? $v['tenant'] ?? $k);
                                break 2;
                            }
                        }
                    }
                }
            }
        }
        $tenantOk = $matched !== '';
        $tenantDetail = $tenantOk
            ? ("Host {$host} → tenant \"{$matched}\"")
            : ("Host {$host} not matched in tenants/map.json (may use default tenant)");
    } else {
        $tenantDetail = 'tenants/map.json present but not valid JSON';
    }
}
$optional[] = row($tenantOk, 'RC tenant map match', $tenantDetail, $tenantOk ? 'ok' : 'warn');


// ── Tallies ──────────────────────────────────────────────────
$all = array_merge($required, $optional);
$failN = count(array_filter($required, static fn (array $r): bool => $r['level'] === 'fail'));
$warnN = count(array_filter($all, static fn (array $r): bool => $r['level'] === 'warn'));
$okN = count(array_filter($all, static fn (array $r): bool => $r['level'] === 'ok'));

$overall = $failN === 0 ? ($warnN === 0 ? 'PASS' : 'PASS WITH WARNINGS') : 'FAIL';

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function render_table(string $title, array $rows): void
{
    echo '<h2>' . h($title) . '</h2><table><thead><tr><th>Status</th><th>Check</th><th>Detail</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $badge = match ($r['level']) {
            'ok' => '<span class="b ok">OK</span>',
            'warn' => '<span class="b warn">WARN</span>',
            default => '<span class="b fail">MISSING</span>',
        };
        echo '<tr class="' . h($r['level']) . '"><td>' . $badge . '</td><td>' . h($r['label']) . '</td><td>' . h($r['detail']) . '</td></tr>';
    }
    echo '</tbody></table>';
}
?>
<!DOCTYPE html>
<html lang="en-IN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>RC environment diagnostic</title>
  <style>
    :root { color-scheme: light dark; --bg:#f5f5f4; --card:#fff; --ink:#1c1917; --muted:#57534e; --ok:#15803d; --warn:#b45309; --fail:#b91c1c; --bd:#e7e5e4; }
    @media (prefers-color-scheme: dark) {
      :root { --bg:#1c1917; --card:#292524; --ink:#fafaf9; --muted:#a8a29e; --bd:#44403c; --ok:#4ade80; --warn:#fbbf24; --fail:#f87171; }
    }
    * { box-sizing: border-box; }
    body { margin:0; font: 15px/1.45 system-ui, Segoe UI, sans-serif; background: var(--bg); color: var(--ink); }
    main { max-width: 920px; margin: 0 auto; padding: 24px 16px 48px; }
    h1 { font-size: 1.35rem; margin: 0 0 8px; }
    .sub { color: var(--muted); margin-bottom: 20px; }
    .banner { padding: 14px 16px; border-radius: 10px; font-weight: 600; margin-bottom: 20px; border: 1px solid var(--bd); background: var(--card); }
    .banner.PASS { border-color: var(--ok); color: var(--ok); }
    .banner.PASS\ WITH\ WARNINGS, .banner.warn { border-color: var(--warn); color: var(--warn); }
    .banner.FAIL { border-color: var(--fail); color: var(--fail); }
    table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 10px; overflow: hidden; border: 1px solid var(--bd); margin-bottom: 24px; }
    th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--bd); vertical-align: top; }
    th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); }
    tr:last-child td { border-bottom: 0; }
    .b { display:inline-block; min-width: 4.5rem; text-align:center; font-size: 12px; font-weight: 700; padding: 2px 8px; border-radius: 999px; }
    .b.ok { background: color-mix(in srgb, var(--ok) 18%, transparent); color: var(--ok); }
    .b.warn { background: color-mix(in srgb, var(--warn) 18%, transparent); color: var(--warn); }
    .b.fail { background: color-mix(in srgb, var(--fail) 18%, transparent); color: var(--fail); }
    h2 { font-size: 1.05rem; margin: 0 0 10px; }
    .stamp { background: var(--card); border: 1px solid var(--bd); border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; }
    .stamp-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin-bottom: 10px; }
    .stamp-grid { margin: 0; display: grid; gap: 6px 16px; }
    @media (min-width: 640px) {
      .stamp-grid { grid-template-columns: 1fr 1fr; }
    }
    .stamp-row { display: grid; grid-template-columns: minmax(7rem, 11rem) 1fr; gap: 8px; align-items: start; margin: 0; padding: 4px 0; border-bottom: 1px solid var(--bd); }
    .stamp-row:last-child { border-bottom: 0; }
    .stamp-row dt { margin: 0; color: var(--muted); font-size: 12px; font-weight: 600; }
    .stamp-row dd { margin: 0; word-break: break-all; }
    .stamp-row code { font-size: 12.5px; background: transparent; }
    .actions { font-size: 14px; color: var(--muted); }
    code { font-size: 0.92em; }
  </style>
</head>
<body>
<main>
  <h1>Resource Centre — environment check</h1>
  <p class="sub">Shared-hosting diagnostic · noindex · delete after use</p>

  <div class="stamp" role="region" aria-label="Diagnostic header stamp">
    <div class="stamp-title">Header stamp</div>
    <dl class="stamp-grid">
<?php foreach ($stamp as $k => $v): ?>
      <div class="stamp-row">
        <dt><?= h((string) $k) ?></dt>
        <dd><code><?= h((string) $v) ?></code></dd>
      </div>
<?php endforeach; ?>
    </dl>
  </div>

  <div class="banner <?= h($overall === 'PASS WITH WARNINGS' ? 'warn' : $overall) ?>">
    Overall: <?= h($overall) ?>
    — <?= (int) $failN ?> missing/required fail,
    <?= (int) $warnN ?> warnings,
    <?= (int) $okN ?> OK
  </div>

<?php if ($failN > 0): ?>
  <div class="banner FAIL">
    Action: open a ticket with your host and ask them to enable the items marked MISSING
    (especially <code>dom</code> / <code>xml</code>, <code>gd</code>, <code>curl</code>, PHP 8.5).
    On Plesk: domain → PHP Settings → enable extensions, or change PHP version to 8.5.x.
  </div>
<?php endif; ?>

<?php
render_table('Required for Resource Centre', $required);
render_table('Optional / performance', $optional);
render_table('Host info', $info);
?>

  <p class="actions">
    <strong>Security:</strong> Delete <code>diag.php</code> after you finish checking.
    To lock this page, set <code>DIAG_KEY</code> at the top of the file and open
    <code>diag.php?key=…</code> only.
  </p>
</main>
</body>
</html>
