<?php
// Version: 5.1.5 (CSV lookup via AppLookup)

declare(strict_types=1);

$base = dirname(__DIR__);
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $base);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';
if (!defined('DATA_PATH')) {
    define('DATA_PATH', BASE_PATH . '/data');
}
require_once $base . '/app/bootstrap.php';
if (session_status() === PHP_SESSION_NONE && class_exists('AppAuth')) {
    AppAuth::initSession();
}

$__u = (string)($_SESSION['user'] ?? '');
if ($__u === '' || !in_array($__u, ['admin', 'super_admin', 'public', 'crm'], true)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Access denied. Sign in via the Resource Centre first.';
    exit;
}

$team = class_exists('AppDB') ? (AppDB::read('team') ?: []) : [];
if (!is_array($team)) {
    $team = [];
}

$desigs = class_exists('AppDB') ? (AppDB::read('designations') ?: []) : [];
$depts  = class_exists('AppDB') ? (AppDB::read('departments') ?: []) : [];
$locs   = class_exists('AppDB') ? (AppDB::read('locations') ?: []) : [];
$desigName = class_exists('AppLookup') ? AppLookup::map($desigs) : [];
$deptName  = class_exists('AppLookup') ? AppLookup::map($depts) : [];
$locName   = class_exists('AppLookup') ? AppLookup::map($locs) : [];

if (ob_get_length()) {
    ob_end_clean();
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . date('ymd') . '_team.csv"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['Name', 'Slug', 'Phone', 'Email', 'Designation', 'Department', 'Location', 'DOB', 'Blood Group', 'Gender']);

foreach ($team as $m) {
    if (!is_array($m)) {
        continue;
    }
    if (class_exists('AppLookup')) {
        $desig = AppLookup::resolve(
            $m,
            $desigName,
            ['designation_id', 'designation_code', 'designation'],
            ['designation_name', 'title', 'role']
        );
        $dept = AppLookup::resolve(
            $m,
            $deptName,
            ['department_id', 'department_code', 'department'],
            ['department_name']
        );
        $loc = AppLookup::resolve(
            $m,
            $locName,
            ['location_id', 'location_code', 'location'],
            ['location_name', 'office', 'branch']
        );
    } else {
        $desig = trim((string) ($m['designation_name'] ?? $m['designation'] ?? ''));
        $dept = trim((string) ($m['department_name'] ?? $m['department'] ?? ''));
        $loc = trim((string) ($m['location_name'] ?? $m['location'] ?? ''));
    }
    fputcsv($out, [
        (string)($m['name'] ?? ''),
        (string)($m['slug'] ?? ''),
        (string)($m['phone'] ?? $m['mobile'] ?? ''),
        (string)($m['email'] ?? ''),
        $desig,
        $dept,
        $loc,
        (string)($m['dob'] ?? ''),
        (string)($m['blood_group'] ?? ''),
        (string)($m['gender'] ?? ''),
    ]);
}
fclose($out);
exit;
