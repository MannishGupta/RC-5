<?php
// Version: 3.2.1
declare(strict_types=1);
/**
 * Local QR (SVG) — fully self-hosted, no GD, no external API.
 * GET /qr.php?data=...&size=200
 */
$data = (string) ($_GET['data'] ?? $_GET['t'] ?? '');
if ($data === '' || strlen($data) > 2048) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Missing data';
    exit;
}
$size = (int) ($_GET['size'] ?? 200);
$size = max(80, min(512, $size));
$lib = __DIR__ . '/app/lib/phpqrcode/qrlib.php';
if (!is_file($lib)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR library missing';
    exit;
}
require_once $lib;
$frame = QRcode::text($data, false, QR_ECLEVEL_M);
$n = is_array($frame) ? count($frame) : 0;
if ($n < 1) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'QR encode failed';
    exit;
}
$pixel = max(1, (int) floor($size / ($n + 8)));
if (class_exists('QRvect') && method_exists('QRvect', 'svg')) {
    if (!headers_sent()) {
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    }
    QRvect::svg($frame, false, $pixel, 2, false, 0xFFFFFF, 0x000000);
    exit;
}
// Pure SVG fallback without QRvect
$outer = 2;
$w = ($n + 2 * $outer) * $pixel;
$svg = '<?xml version="1.0" encoding="UTF-8"?><svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $w . '" viewBox="0 0 ' . $w . ' ' . $w . '">';
$svg .= '<rect width="100%" height="100%" fill="#fff"/>';
for ($y = 0; $y < $n; $y++) {
    $row = $frame[$y];
    for ($x = 0; $x < $n; $x++) {
        if (($row[$x] ?? '0') === '1') {
            $svg .= '<rect x="' . (($x + $outer) * $pixel) . '" y="' . (($y + $outer) * $pixel) . '" width="' . $pixel . '" height="' . $pixel . '" fill="#000"/>';
        }
    }
}
$svg .= '</svg>';
if (!headers_sent()) {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
}
echo $svg;
