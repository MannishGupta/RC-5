<?php
// Version: 3.2.42
declare(strict_types=1);

/**
 * Monthly Sales Register — flat-file store + aggregates for four projects.
 */
final class SalesRegister
{
    public const PROJECTS = [
        'Brook Phase 1',
        'Brook Phase 2',
        'Rivulet Phase 1',
        'U:Fairia',
    ];

    public const STATUSES = ['Booked', 'Agreement Signed', 'Cancelled', 'Registered'];

    public static function ns(): string
    {
        return 'sales';
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        if (!class_exists('AppDB')) {
            return [];
        }
        $rows = AppDB::read(self::ns());
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            if (is_array($r)) {
                $out[] = self::normalize($r);
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $r */
    public static function normalize(array $r): array
    {
        $bsp = self::num($r['bsp'] ?? $r['base_sale_price'] ?? 0);
        $other = self::num($r['other_charges'] ?? $r['other'] ?? 0);
        $gross = self::num($r['gross'] ?? $r['total_gross'] ?? 0);
        if ($gross <= 0) {
            $gross = $bsp + $other;
        }
        $month = self::monthKey((string)($r['month'] ?? $r['month_year'] ?? ''));
        return [
            'id' => (string)($r['id'] ?? ''),
            'month' => $month,
            'project' => self::projectName((string)($r['project'] ?? $r['project_name'] ?? '')),
            'unit' => trim((string)($r['unit'] ?? $r['unit_number'] ?? '')),
            'customer' => trim((string)($r['customer'] ?? $r['customer_name'] ?? '')),
            'unit_type' => trim((string)($r['unit_type'] ?? $r['type'] ?? '')),
            'area' => self::num($r['area'] ?? $r['area_sqft'] ?? 0),
            'bsp' => $bsp,
            'other_charges' => $other,
            'gross' => $gross,
            'status' => self::statusName((string)($r['status'] ?? $r['booking_status'] ?? 'Booked')),
            'notes' => trim((string)($r['notes'] ?? '')),
            'created_at' => (string)($r['created_at'] ?? ''),
            'updated_at' => (string)($r['updated_at'] ?? ''),
        ];
    }

    public static function projectName(string $p): string
    {
        $p = trim($p);
        foreach (self::PROJECTS as $known) {
            if (strcasecmp($known, $p) === 0) {
                return $known;
            }
        }
        // fuzzy
        $low = strtolower($p);
        if (str_contains($low, 'brook') && str_contains($low, '2')) {
            return 'Brook Phase 2';
        }
        if (str_contains($low, 'brook')) {
            return 'Brook Phase 1';
        }
        if (str_contains($low, 'rivulet')) {
            return 'Rivulet Phase 1';
        }
        if (str_contains($low, 'fairia') || str_contains($low, 'u:')) {
            return 'U:Fairia';
        }
        return $p !== '' ? $p : 'Brook Phase 1';
    }

    public static function statusName(string $s): string
    {
        $s = trim($s);
        foreach (self::STATUSES as $known) {
            if (strcasecmp($known, $s) === 0) {
                return $known;
            }
        }
        return $s !== '' ? $s : 'Booked';
    }

    public static function monthKey(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})-(\d{2})/', $raw, $m)) {
            return $m[1] . '-' . $m[2];
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{4})$/', $raw, $m)) {
            return $m[2] . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^([A-Za-z]{3,9})\s*[\/\-]?\s*(\d{4})$/', $raw, $m)) {
            $ts = strtotime('1 ' . $m[1] . ' ' . $m[2]);
            if ($ts) {
                return date('Y-m', $ts);
            }
        }
        if ($raw === '') {
            return date('Y-m');
        }
        $ts = strtotime($raw);
        return $ts ? date('Y-m', $ts) : date('Y-m');
    }

    private static function num(mixed $v): float
    {
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $s = preg_replace('/[^\d.\-]/', '', str_replace(',', '', (string)$v));
        return is_numeric($s) ? (float)$s : 0.0;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{by_project: array<string,array>, by_month: array<string,array>, months: list<string>}
     */
    public static function aggregate(array $rows, ?string $year = null): array
    {
        $year = $year ?: date('Y');
        $byProject = [];
        foreach (self::PROJECTS as $p) {
            $byProject[$p] = [
                'units_month' => 0,
                'area_month' => 0.0,
                'gross_month' => 0.0,
                'units_ytd' => 0,
                'area_ytd' => 0.0,
                'gross_ytd' => 0.0,
            ];
        }
        $curMonth = date('Y-m');
        $byMonth = []; // month => project => [units, area, gross]

        foreach ($rows as $r) {
            $r = self::normalize($r);
            if (($r['status'] ?? '') === 'Cancelled') {
                continue;
            }
            $p = $r['project'];
            if (!isset($byProject[$p])) {
                $byProject[$p] = [
                    'units_month' => 0, 'area_month' => 0.0, 'gross_month' => 0.0,
                    'units_ytd' => 0, 'area_ytd' => 0.0, 'gross_ytd' => 0.0,
                ];
            }
            $m = $r['month'];
            $y = substr($m, 0, 4);
            if ($y === $year) {
                $byProject[$p]['units_ytd']++;
                $byProject[$p]['area_ytd'] += $r['area'];
                $byProject[$p]['gross_ytd'] += $r['gross'];
            }
            if ($m === $curMonth) {
                $byProject[$p]['units_month']++;
                $byProject[$p]['area_month'] += $r['area'];
                $byProject[$p]['gross_month'] += $r['gross'];
            }
            if (!isset($byMonth[$m])) {
                $byMonth[$m] = [];
            }
            if (!isset($byMonth[$m][$p])) {
                $byMonth[$m][$p] = ['units' => 0, 'area' => 0.0, 'gross' => 0.0];
            }
            $byMonth[$m][$p]['units']++;
            $byMonth[$m][$p]['area'] += $r['area'];
            $byMonth[$m][$p]['gross'] += $r['gross'];
        }
        ksort($byMonth);
        return [
            'by_project' => $byProject,
            'by_month' => $byMonth,
            'months' => array_keys($byMonth),
            'current_month' => $curMonth,
            'year' => $year,
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function parseCsv(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        if ($lines === []) {
            return [];
        }
        $header = str_getcsv(array_shift($lines));
        $map = [];
        foreach ($header as $i => $h) {
            $k = strtolower(trim((string)$h));
            $k = preg_replace('/[^a-z0-9]+/', '_', $k) ?? $k;
            $map[$i] = $k;
        }
        $alias = [
            'month_year' => 'month', 'month' => 'month', 'booking_month' => 'month',
            'project_name' => 'project', 'project' => 'project',
            'unit_number' => 'unit', 'unit' => 'unit', 'unit_no' => 'unit',
            'customer_name' => 'customer', 'customer' => 'customer', 'buyer' => 'customer',
            'unit_type' => 'unit_type', 'configuration' => 'unit_type', 'type' => 'unit_type',
            'area_sold_sq_ft' => 'area', 'area_sq_ft' => 'area', 'area_sold' => 'area', 'area' => 'area',
            'base_sale_price_bsp' => 'bsp', 'base_sale_price' => 'bsp', 'bsp' => 'bsp',
            'other_charges' => 'other_charges', 'other' => 'other_charges',
            'total_gross_revenue' => 'gross', 'gross_revenue' => 'gross', 'gross' => 'gross', 'total' => 'gross',
            'booking_status' => 'status', 'status' => 'status',
        ];
        $out = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $row = [];
            foreach ($cols as $i => $val) {
                $key = $map[$i] ?? ('c' . $i);
                $std = $alias[$key] ?? $key;
                $row[$std] = $val;
            }
            $n = self::normalize($row);
            if ($n['unit'] === '' && $n['customer'] === '' && $n['gross'] <= 0) {
                continue;
            }
            $n['id'] = 'sale_' . bin2hex(random_bytes(4));
            $n['created_at'] = date('c');
            $n['updated_at'] = $n['created_at'];
            $out[] = $n;
        }
        return $out;
    }

    /** Path to per-tenant sheet connection config */
    public static function sheetsConfigPath(): string
    {
        $base = defined('DATA_PATH') ? rtrim((string)DATA_PATH, '/\\') : (defined('BASE_PATH') ? BASE_PATH . '/data' : __DIR__ . '/../data');
        $dir = $base . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/sales_sheets.json';
    }

    /**
     * @return array{enabled:bool,ttl_seconds:int,spreadsheets:list<array{id:string,range:string,label:string}>,last_sync:?string,last_error:?string}
     */
    public static function sheetsConfig(): array
    {
        $def = [
            'enabled' => false,
            'ttl_seconds' => 300,
            'spreadsheets' => [],
            'last_sync' => null,
            'last_error' => null,
        ];
        $path = self::sheetsConfigPath();
        if (!is_file($path)) {
            return $def;
        }
        $j = json_decode((string)@file_get_contents($path), true);
        if (!is_array($j)) {
            return $def;
        }
        $sheets = [];
        foreach ((array)($j['spreadsheets'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', (string)($s['id'] ?? '')) ?? '';
            if ($id === '' && !empty($s['url'])) {
                if (preg_match('~/spreadsheets/d/([a-zA-Z0-9\-_]+)~', (string)$s['url'], $m)) {
                    $id = $m[1];
                }
            }
            if ($id === '') {
                continue;
            }
            $sheets[] = [
                'id' => $id,
                'range' => trim((string)($s['range'] ?? 'A:Z')) ?: 'A:Z',
                'label' => trim((string)($s['label'] ?? $id)),
            ];
        }
        return [
            'enabled' => !empty($j['enabled']),
            'ttl_seconds' => max(60, (int)($j['ttl_seconds'] ?? 300)),
            'spreadsheets' => $sheets,
            'last_sync' => isset($j['last_sync']) ? (string)$j['last_sync'] : null,
            'last_error' => isset($j['last_error']) ? (string)$j['last_error'] : null,
        ];
    }


    /**
     * Accept full share URL, /edit?…, open?id=, or bare ID — return spreadsheet id only.
     */
    public static function parseSpreadsheetRef(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        // Bare id
        if (preg_match('/^[a-zA-Z0-9\-_]{20,}$/', $raw)) {
            return $raw;
        }
        // /spreadsheets/d/{id}/…
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9\-_]+)~', $raw, $m)) {
            return $m[1];
        }
        // open?id= or ?id=
        if (preg_match('/[?&]id=([a-zA-Z0-9\-_]+)/', $raw, $m)) {
            return $m[1];
        }
        // docs.google.com/… key=
        if (preg_match('/[?&]key=([a-zA-Z0-9\-_]+)/', $raw, $m)) {
            return $m[1];
        }
        return '';
    }

    /** @param array<string,mixed> $cfg */
    public static function saveSheetsConfig(array $cfg): bool
    {
        $path = self::sheetsConfigPath();
        $out = [
            'enabled' => !empty($cfg['enabled']),
            'ttl_seconds' => max(60, (int)($cfg['ttl_seconds'] ?? 300)),
            'spreadsheets' => [],
            'last_sync' => $cfg['last_sync'] ?? null,
            'last_error' => $cfg['last_error'] ?? null,
            'updated_at' => date('c'),
        ];
        foreach ((array)($cfg['spreadsheets'] ?? []) as $s) {
            if (!is_array($s)) {
                continue;
            }
            $id = self::parseSpreadsheetRef((string)($s['id'] ?? ''));
            if ($id === '') {
                $id = self::parseSpreadsheetRef((string)($s['url'] ?? ''));
            }
            if ($id === '') {
                continue;
            }
            $range = trim((string)($s['range'] ?? 'A:Z')) ?: 'A:Z';
            // Drop accidental full URLs pasted into range
            if (str_contains($range, 'http') || str_contains($range, 'docs.google')) {
                $range = 'A:Z';
            }
            $out['spreadsheets'][] = [
                'id' => $id,
                'range' => $range,
                'label' => trim((string)($s['label'] ?? '')),
            ];
        }
        return false !== @file_put_contents($path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Pull live rows from configured Google Sheets and merge into AppDB sales store.
     * @return array{ok:bool,message:string,imported:int,sheets:int}
     */
    public static function syncFromGoogleSheets(bool $force = false): array
    {
        $cfg = self::sheetsConfig();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'message' => 'Google Sheets sync is disabled for this tenant.', 'imported' => 0, 'sheets' => 0];
        }
        if ($cfg['spreadsheets'] === []) {
            return ['ok' => false, 'message' => 'No spreadsheet IDs configured.', 'imported' => 0, 'sheets' => 0];
        }
        if (!class_exists('GoogleSheetsClient') && is_file(__DIR__ . '/GoogleSheetsClient.php')) {
            require_once __DIR__ . '/GoogleSheetsClient.php';
        }
        if (!class_exists('GoogleSheetsClient')) {
            return ['ok' => false, 'message' => 'GoogleSheetsClient missing.', 'imported' => 0, 'sheets' => 0];
        }
        if (!$force && $cfg['last_sync']) {
            $ts = strtotime((string)$cfg['last_sync']);
            if ($ts && (time() - $ts) < $cfg['ttl_seconds']) {
                return [
                    'ok' => true,
                    'message' => 'Using cached sync from ' . $cfg['last_sync'],
                    'imported' => 0,
                    'sheets' => count($cfg['spreadsheets']),
                    'cached' => true,
                ];
            }
        }
        $st = GoogleSheetsClient::status();
        if (empty($st['ok'])) {
            $cfg['last_error'] = (string)($st['message'] ?? 'Not connected');
            self::saveSheetsConfig($cfg);
            return ['ok' => false, 'message' => $cfg['last_error'], 'imported' => 0, 'sheets' => 0];
        }

        $allIncoming = [];
        $sheetOk = 0;
        $errors = [];
        foreach ($cfg['spreadsheets'] as $s) {
            $values = GoogleSheetsClient::values($s['id'], $s['range']);
            if ($values === null || $values === []) {
                $errors[] = ($s['label'] ?: $s['id']) . ': empty or inaccessible';
                continue;
            }
            $sheetOk++;
            // First row = header
            $header = array_shift($values);
            $headerLine = implode(',', array_map(static function ($h) {
                $h = str_replace('"', '""', (string)$h);
                return '"' . $h . '"';
            }, $header));
            $csv = $headerLine . "\n";
            foreach ($values as $row) {
                $cells = [];
                foreach ($header as $i => $_h) {
                    $cells[] = '"' . str_replace('"', '""', (string)($row[$i] ?? '')) . '"';
                }
                $csv .= implode(',', $cells) . "\n";
            }
            $parsed = self::parseCsv($csv);
            foreach ($parsed as $r) {
                $r['source_sheet'] = $s['id'];
                $allIncoming[] = $r;
            }
        }

        if ($sheetOk === 0) {
            $msg = 'Could not read any spreadsheet. ' . implode('; ', $errors);
            $cfg['last_error'] = $msg;
            self::saveSheetsConfig($cfg);
            return ['ok' => false, 'message' => $msg, 'imported' => 0, 'sheets' => 0];
        }

        // Merge by month|project|unit
        $existing = self::all();
        $byKey = [];
        foreach ($existing as $r) {
            $k = strtolower(($r['month'] ?? '') . '|' . ($r['project'] ?? '') . '|' . ($r['unit'] ?? ''));
            $byKey[$k] = $r;
        }
        $added = 0;
        $updated = 0;
        foreach ($allIncoming as $r) {
            $k = strtolower(($r['month'] ?? '') . '|' . ($r['project'] ?? '') . '|' . ($r['unit'] ?? ''));
            if (isset($byKey[$k])) {
                $r['id'] = $byKey[$k]['id'];
                $r['created_at'] = $byKey[$k]['created_at'] ?? date('c');
                $r['updated_at'] = date('c');
                $byKey[$k] = $r;
                $updated++;
            } else {
                if (($r['id'] ?? '') === '') {
                    $r['id'] = 'sale_' . bin2hex(random_bytes(4));
                }
                $r['created_at'] = date('c');
                $r['updated_at'] = $r['created_at'];
                $byKey[$k] = $r;
                $added++;
            }
        }
        if (!class_exists('AppDB') || !AppDB::save(self::ns(), array_values($byKey))) {
            return ['ok' => false, 'message' => 'Failed to save merged sales data.', 'imported' => 0, 'sheets' => $sheetOk];
        }
        $cfg['last_sync'] = date('c');
        $cfg['last_error'] = $errors === [] ? null : implode('; ', $errors);
        self::saveSheetsConfig($cfg);
        return [
            'ok' => true,
            'message' => "Synced {$sheetOk} sheet(s): +{$added} new, {$updated} updated.",
            'imported' => $added + $updated,
            'added' => $added,
            'updated' => $updated,
            'sheets' => $sheetOk,
        ];
    }
}
