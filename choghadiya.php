<?php
// Version: 5.0.0 (PHP 8.5.12+) (PHP 8.2–8.5.12 compatible)
/**
 * Version: 4.1.1 — Scriptable 6.3 compatible JSON + date query
 * Target: Web, iOS Scriptable, Android KWGT / PWA
 * Route: /choghadiya.php | ?format=json&date=YYYY-MM-DD&lat=&lon=&lang=
 * Default location: Simco Residency, East Delhi (28.65944N, 77.31705E) · Asia/Kolkata
 *
 * Scriptable 6.3 requires:
 *   - Top-level "date" as YYYY-MM-DD string (not only an object)
 *   - Honoured &date= so day navigation is not stuck on "today"
 *   - tithi.number / tithi.n (1–30) when available
 */
declare(strict_types=1);

if (is_file(__DIR__ . '/app/php85_runtime.php')) {
    require_once __DIR__ . '/app/php85_runtime.php';
}
date_default_timezone_set('Asia/Kolkata');

$__base = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $__base);
}
if (!defined('DATA_PATH') && is_file($__base . '/app/tenant_bootstrap.php')) {
    try {
        require_once $__base . '/app/tenant_bootstrap.php';
    } catch (\Throwable $e) {
    }
}
if (!defined('APP_VERSION') && is_file($__base . '/version.php')) {
    require_once $__base . '/version.php';
}

const CHOG_LAT = 28.65944;
const CHOG_LON = 77.31705; // Simco Residency, East Delhi

function chog_tz(): \DateTimeZone
{
    static $tz = null;
    if ($tz === null) {
        $tz = new \DateTimeZone('Asia/Kolkata');
    }
    return $tz;
}

function chog_now(): \DateTimeImmutable
{
    return new \DateTimeImmutable('now', chog_tz());
}

function chog_hm(\DateTimeInterface $d): string
{
    return $d->format('g:i A');
}

function chog_corporate(): array
{
    $text = 'Powered by Arthsathi Limited.';
    $url = 'https://arthsathi.com/';
    $logo = 'https://arthsathi.com/favicon.ico';
    if (class_exists('AppDB')) {
        try {
            $co = AppDB::read('company');
            $row = is_array($co) ? (isset($co['name']) ? $co : ($co[0] ?? [])) : [];
            if (is_array($row) && !empty($row['name'])) {
                $text = 'Powered by ' . (string)$row['name'];
                $w = trim((string)($row['website'] ?? ''));
                if ($w !== '') {
                    if (!preg_match('~^https?://~i', $w)) {
                        $w = 'https://' . $w;
                    }
                    $url = $w;
                }
            }
        } catch (\Throwable $e) {
        }
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && is_file(__DIR__ . '/favicon.ico')) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $logo = $scheme . '://' . $host . '/favicon.ico';
    }
    return ['text' => $text, 'url' => $url, 'logoUrl' => $logo];
}

function chog_to_rad(float $d): float
{
    return $d * M_PI / 180.0;
}

/** Approximate sunrise/sunset for lat/lon on calendar day in IST. */
function chog_sun_times(\DateTimeImmutable $date, ?float $lat = null, ?float $lon = null): array
{
    $lat = $lat ?? CHOG_LAT;
    $lon = $lon ?? CHOG_LON;
    $y = (int)$date->format('Y');
    $m = (int)$date->format('n');
    $d = (int)$date->format('j');
    $day = new \DateTimeImmutable(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, $d), chog_tz());
    $startYear = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $y), chog_tz());
    $N = (int)floor(($day->getTimestamp() - $startYear->getTimestamp()) / 86400) + 1;
    $decl = 23.44 * sin(chog_to_rad((360 / 365) * ($N - 81)));
    $latR = chog_to_rad($lat);
    $cosH = (sin(chog_to_rad(-0.83)) - sin($latR) * sin(chog_to_rad($decl))) / (cos($latR) * cos(chog_to_rad($decl)));
    $cosH = max(-1.0, min(1.0, $cosH));
    $H = acos($cosH) * 180.0 / M_PI;
    $eot = 9.87 * sin(2 * chog_to_rad((360 / 365) * ($N - 81)))
        - 7.53 * cos(chog_to_rad((360 / 365) * ($N - 81)))
        - 1.5 * sin(chog_to_rad((360 / 365) * ($N - 81)));
    $solarNoonMin = 720 - 4 * $lon - $eot;
    $riseMin = $solarNoonMin - $H * 4;
    $setMin = $solarNoonMin + $H * 4;
    $utcMidnight = gmmktime(0, 0, 0, $m, $d, $y);
    $rise = (new \DateTimeImmutable('@' . (int)round($utcMidnight + $riseMin * 60)))->setTimezone(chog_tz());
    $set = (new \DateTimeImmutable('@' . (int)round($utcMidnight + $setMin * 60)))->setTimezone(chog_tz());
    return ['rise' => $rise, 'set' => $set];
}

function chog_jd(\DateTimeInterface $date): float
{
    $y = (int)$date->format('Y');
    $m = (int)$date->format('n');
    $D = (int)$date->format('j');
    $hr = (int)$date->format('G') + ((int)$date->format('i')) / 60.0 + ((int)$date->format('s')) / 3600.0;
    // Use UTC components like JS
    $utc = \DateTimeImmutable::createFromFormat('U', (string)$date->getTimestamp(), new \DateTimeZone('UTC'));
    $y = (int)$utc->format('Y');
    $m = (int)$utc->format('n');
    $D = (int)$utc->format('j');
    $hr = (int)$utc->format('G') + ((int)$utc->format('i')) / 60.0;
    if ($m <= 2) {
        $y--;
        $m += 12;
    }
    $A = (int)floor($y / 100);
    $B = 2 - $A + (int)floor($A / 4);
    return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $D + $B - 1524.5 + $hr / 24.0;
}

function chog_moon_lon(\DateTimeInterface $date): float
{
    $jd = chog_jd($date);
    $Tm = ($jd - 2451545.0) / 36525.0;
    $Lp = 218.316 + 481267.8813 * $Tm;
    $M = 134.963 + 477198.8676 * $Tm;
    $lon = $Lp + 6.289 * sin(chog_to_rad($M)) - 1.274 * sin(chog_to_rad(2 * $Lp - $M))
        + 0.658 * sin(chog_to_rad(2 * $Lp)) + 0.214 * sin(chog_to_rad(2 * $M));
    $lon = fmod($lon, 360.0);
    if ($lon < 0) {
        $lon += 360.0;
    }
    return $lon;
}

function chog_sun_lon(\DateTimeInterface $date): float
{
    $jd = chog_jd($date);
    $n = $jd - 2451545.0;
    $L = 280.46 + 0.9856474 * $n;
    $g = 357.528 + 0.9856003 * $n;
    $lon = $L + 1.915 * sin(chog_to_rad($g)) + 0.020 * sin(chog_to_rad(2 * $g));
    $lon = fmod($lon, 360.0);
    if ($lon < 0) {
        $lon += 360.0;
    }
    return $lon;
}

const CHOG_TITHI_EN = [
    1 => 'Pratipada', 2 => 'Dwitiya', 3 => 'Tritiya', 4 => 'Chaturthi', 5 => 'Panchami',
    6 => 'Shashthi', 7 => 'Saptami', 8 => 'Ashtami', 9 => 'Navami', 10 => 'Dashami',
    11 => 'Ekadashi', 12 => 'Dwadashi', 13 => 'Trayodashi', 14 => 'Chaturdashi', 15 => 'Purnima/Amavasya',
];
const CHOG_TITHI_HI = [
    1 => 'प्रतिपदा', 2 => 'द्वितीया', 3 => 'तृतीया', 4 => 'चतुर्थी', 5 => 'पंचमी',
    6 => 'षष्ठी', 7 => 'सप्तमी', 8 => 'अष्टमी', 9 => 'नवमी', 10 => 'दशमी',
    11 => 'एकादशी', 12 => 'द्वादशी', 13 => 'त्रयोदशी', 14 => 'चतुर्दशी', 15 => 'पूर्णिमा/अमावस्या',
];

function chog_tithi_at(\DateTimeInterface $date): array
{
    $diff = chog_moon_lon($date) - chog_sun_lon($date);
    $diff = fmod($diff + 360.0, 360.0);
    // Full lunar day 1–30 (Scriptable-compatible)
    $n = (int) floor($diff / 12.0) + 1;
    if ($n < 1) {
        $n = 1;
    }
    if ($n > 30) {
        $n = 30;
    }
    $shukla = $n <= 15;
    $idx = $shukla ? $n : ($n === 30 ? 15 : $n - 15);
    $names = [
        1 => 'Pratipada', 2 => 'Dwitiya', 3 => 'Tritiya', 4 => 'Chaturthi', 5 => 'Panchami',
        6 => 'Shashthi', 7 => 'Saptami', 8 => 'Ashtami', 9 => 'Navami', 10 => 'Dashami',
        11 => 'Ekadashi', 12 => 'Dwadashi', 13 => 'Trayodashi', 14 => 'Chaturdashi',
        15 => 'Purnima', 30 => 'Amavasya',
    ];
    $namesHi = [
        1 => 'प्रतिपदा', 2 => 'द्वितीया', 3 => 'तृतीया', 4 => 'चतुर्थी', 5 => 'पंचमी',
        6 => 'षष्ठी', 7 => 'सप्तमी', 8 => 'अष्टमी', 9 => 'नवमी', 10 => 'दशमी',
        11 => 'एकादशी', 12 => 'द्वादशी', 13 => 'त्रयोदशी', 14 => 'चतुर्दशी',
        15 => 'पूर्णिमा', 30 => 'अमावस्या',
    ];
    if ($n === 15) {
        $name = 'Purnima';
        $nameHi = 'पूर्णिमा';
        $nameFull = 'Purnima (Full Moon)';
        $nameFullHi = 'पूर्णिमा (पूरा चाँद)';
    } elseif ($n === 30) {
        $name = 'Amavasya';
        $nameHi = 'अमावस्या';
        $nameFull = 'Amavasya (New Moon)';
        $nameFullHi = 'अमावस्या';
    } else {
        $base = $names[$idx] ?? ('Tithi ' . $idx);
        $baseHi = $namesHi[$idx] ?? $base;
        // Short name = tithi only (Dashami). Full = "Shukla · Dashami" for widgets that want one line.
        $name = $base;
        $nameHi = $baseHi;
        $pak = $shukla ? 'Shukla' : 'Krishna';
        $pakHi = $shukla ? 'शुक्ल' : 'कृष्ण';
        $nameFull = $pak . ' · ' . $base;
        $nameFullHi = $pakHi . ' · ' . $baseHi;
    }
    // Approximate end: remaining degrees / ~12.19° per day
    $target = ($n * 12) % 360;
    $rem = fmod(($n * 12) - $diff + 360.0, 360.0);
    if ($rem <= 0) {
        $rem = 12.0;
    }
    $endTs = $date->getTimestamp() + (int) round(($rem / 12.19) * 86400);
    $end = (new \DateTimeImmutable('@' . $endTs))->setTimezone(chog_tz());
    if (!isset($nameFull)) {
        $nameFull = $name;
        $nameFullHi = $nameHi;
    }
    return [
        'index' => $idx,
        'number' => $n,
        'n' => $n,
        'name' => $name,
        'nameHi' => $nameHi,
        'nameFull' => $nameFull,
        'nameFullHi' => $nameFullHi,
        'paksha' => $shukla ? 'Shukla' : 'Krishna',
        'pakshaFull' => $shukla ? 'Shukla Paksha' : 'Krishna Paksha',
        'pakshaHi' => $shukla ? 'शुक्ल' : 'कृष्ण',
        'pakshaFullHi' => $shukla ? 'शुक्ल पक्ष' : 'कृष्ण पक्ष',
        'end' => $end->format('c'),
        'endTime' => chog_hm($end),
    ];
}

// Day sequences by weekday (0=Sun … 6=Sat) — classic Choghadiya
const CHOG_DAY_SEQ = [
    0 => ['Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg'],
    1 => ['Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Char', 'Labh', 'Amrit'],
    2 => ['Rog', 'Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog'],
    3 => ['Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Char', 'Labh'],
    4 => ['Shubh', 'Rog', 'Udveg', 'Char', 'Labh', 'Amrit', 'Kaal', 'Shubh'],
    5 => ['Char', 'Labh', 'Amrit', 'Kaal', 'Shubh', 'Rog', 'Udveg', 'Char'],
    6 => ['Kaal', 'Shubh', 'Rog', 'Udveg', 'Char', 'Labh', 'Amrit', 'Kaal'],
];
const CHOG_NIGHT_SEQ = [
    // Aligned with Scriptable panchangLib NIGHT_SEQ (v6.3)
    0 => ['Shubh', 'Amrit', 'Char', 'Rog', 'Kaal', 'Labh', 'Udveg', 'Shubh'],
    1 => ['Char', 'Rog', 'Kaal', 'Labh', 'Udveg', 'Shubh', 'Amrit', 'Char'],
    2 => ['Kaal', 'Labh', 'Udveg', 'Shubh', 'Amrit', 'Char', 'Rog', 'Kaal'],
    3 => ['Udveg', 'Shubh', 'Amrit', 'Char', 'Rog', 'Kaal', 'Labh', 'Udveg'],
    4 => ['Amrit', 'Char', 'Rog', 'Kaal', 'Labh', 'Udveg', 'Shubh', 'Amrit'],
    5 => ['Rog', 'Kaal', 'Labh', 'Udveg', 'Shubh', 'Amrit', 'Char', 'Rog'],
    6 => ['Labh', 'Udveg', 'Shubh', 'Amrit', 'Char', 'Rog', 'Kaal', 'Labh'],
];
const CHOG_NAME_HI = [
    'Udveg' => 'उद्वेग', 'Char' => 'चर', 'Labh' => 'लाभ', 'Amrit' => 'अमृत',
    'Kaal' => 'काल', 'Shubh' => 'शुभ', 'Rog' => 'रोग',
];

function chog_kind(string $name): string
{
    if (in_array($name, ['Amrit', 'Shubh', 'Labh'], true)) {
        return 'shubh';
    }
    if ($name === 'Char') {
        return 'chal';
    }
    return 'ashubh';
}

function chog_rahu_window(\DateTimeImmutable $rise, \DateTimeImmutable $set, int $weekday): array
{
    // 8 equal segments; Rahu occupies segment by weekday (classic)
    $segMap = [0 => 7, 1 => 1, 2 => 6, 3 => 4, 4 => 5, 5 => 3, 6 => 2]; // 1-based segment
    $seg = $segMap[$weekday] ?? 4;
    $total = max(1, $set->getTimestamp() - $rise->getTimestamp());
    $len = (int)floor($total / 8);
    $startTs = $rise->getTimestamp() + ($seg - 1) * $len;
    $endTs = $startTs + $len;
    $start = (new \DateTimeImmutable('@' . $startTs))->setTimezone(chog_tz());
    $end = (new \DateTimeImmutable('@' . $endTs))->setTimezone(chog_tz());
    return [
        'start' => chog_hm($start),
        'end' => chog_hm($end),
        'startIso' => $start->format('c'),
        'endIso' => $end->format('c'),
        'label' => chog_hm($start) . ' – ' . chog_hm($end),
    ];
}

/** @return list<array<string,mixed>> */
function chog_build_periods(\DateTimeImmutable $start, \DateTimeImmutable $end, array $seq, \DateTimeImmutable $now): array
{
    $total = max(1, $end->getTimestamp() - $start->getTimestamp());
    $n = count($seq);
    $len = (int)floor($total / max(1, $n));
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $sTs = $start->getTimestamp() + $i * $len;
        $eTs = ($i === $n - 1) ? $end->getTimestamp() : $sTs + $len;
        $s = (new \DateTimeImmutable('@' . $sTs))->setTimezone(chog_tz());
        $e = (new \DateTimeImmutable('@' . $eTs))->setTimezone(chog_tz());
        $name = $seq[$i];
        $isNow = $now >= $s && $now < $e;
        $rows[] = [
            'name' => $name,
            'nameHi' => CHOG_NAME_HI[$name] ?? $name,
            'kind' => chog_kind($name),
            'start' => chog_hm($s),
            'end' => chog_hm($e),
            'startIso' => $s->format('c'),
            'endIso' => $e->format('c'),
            'isNow' => $isNow,
        ];
    }
    return $rows;
}

function chog_parse_lat_lon(): array
{
    $lat = CHOG_LAT;
    $lon = CHOG_LON;
    if (isset($_GET['lat']) && is_numeric($_GET['lat'])) {
        $v = (float) $_GET['lat'];
        if ($v >= -90.0 && $v <= 90.0) {
            $lat = $v;
        }
    }
    if (isset($_GET['lon']) && is_numeric($_GET['lon'])) {
        $v = (float) $_GET['lon'];
        if ($v >= -180.0 && $v <= 180.0) {
            $lon = $v;
        }
    }
    return [$lat, $lon];
}

/** Honour ?date=YYYY-MM-DD (Scriptable 6.3). Invalid/missing → today IST. */
function chog_parse_target_day(\DateTimeImmutable $clockNow): \DateTimeImmutable
{
    $raw = trim((string) ($_GET['date'] ?? $_GET['d'] ?? ''));
    if ($raw !== '' && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)) {
        $y = (int) $m[1];
        $mo = (int) $m[2];
        $da = (int) $m[3];
        if (checkdate($mo, $da, $y)) {
            // Midday IST so sun/tithi land on the civil day
            $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', sprintf('%04d-%02d-%02d 12:00:00', $y, $mo, $da), chog_tz());
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }
    }
    return $clockNow;
}

/**
 * @param \DateTimeImmutable $forDay civil day to describe (may differ from wall clock)
 * @param \DateTimeImmutable $clockNow real "now" for isNow / current period
 */
function chog_payload(?\DateTimeImmutable $forDay = null, ?\DateTimeImmutable $clockNow = null, ?float $lat = null, ?float $lon = null): array
{
    $clockNow = $clockNow ?? chog_now();
    $forDay = $forDay ?? $clockNow;
    $lat = $lat ?? CHOG_LAT;
    $lon = $lon ?? CHOG_LON;
    $iso = $forDay->format('Y-m-d');
    $sameDay = $iso === $clockNow->format('Y-m-d');
    // Period "isNow" only when the requested day is today
    $refNow = $sameDay ? $clockNow : $forDay->setTime(12, 0, 0);

    $sun = chog_sun_times($forDay, $lat, $lon);
    $wd = (int) $forDay->format('w');
    $tithi = chog_tithi_at($sameDay ? $clockNow : $forDay);
    $rahu = chog_rahu_window($sun['rise'], $sun['set'], $wd);
    $dayRows = chog_build_periods($sun['rise'], $sun['set'], CHOG_DAY_SEQ[$wd], $refNow);
    if (!$sameDay) {
        foreach ($dayRows as &$r) {
            $r['isNow'] = false;
        }
        unset($r);
    }
    $tomorrow = $forDay->modify('+1 day');
    $nextSun = chog_sun_times($tomorrow, $lat, $lon);
    $nightRows = chog_build_periods($sun['set'], $nextSun['rise'], CHOG_NIGHT_SEQ[$wd], $refNow);
    if (!$sameDay) {
        foreach ($nightRows as &$r) {
            $r['isNow'] = false;
        }
        unset($r);
    }

    $current = null;
    if ($sameDay) {
        $isDay = $clockNow >= $sun['rise'] && $clockNow < $sun['set'];
        foreach (array_merge($dayRows, $nightRows) as $r) {
            if (!empty($r['isNow'])) {
                $current = [
                    'name' => $r['name'],
                    'nameHi' => $r['nameHi'],
                    'kind' => $r['kind'],
                    'start' => $r['start'],
                    'end' => $r['end'],
                    'startIso' => $r['startIso'] ?? null,
                    'endIso' => $r['endIso'] ?? null,
                    'isDay' => $isDay,
                ];
                break;
            }
        }
    }

    return [
        'status' => 'OK',
        'timestamp' => $clockNow->format('c'),
        'formattedTime' => chog_hm($clockNow),
        'timezone' => 'Asia/Kolkata',
        'region' => 'East Delhi / Delhi NCR, India',
        'lat' => round($lat, 5),
        'lon' => round($lon, 5),
        'version' => '4.4.2',
        // Scriptable 6.3: top-level date MUST be YYYY-MM-DD string
        'date' => $iso,
        'dateStr' => $iso,
        'iso_date' => $iso,
        'isoDate' => $iso,
        'dateInfo' => [
            'iso' => $iso,
            'weekday' => (int) $forDay->format('w'),
            'label' => $forDay->format('D, d M Y'),
        ],
        'query' => [
            'date' => $iso,
            'lat' => round($lat, 5),
            'lon' => round($lon, 5),
        ],
        'meta' => [
            'date' => $iso,
        ],
        'sun' => [
            'rise' => chog_hm($sun['rise']),
            'set' => chog_hm($sun['set']),
            'sunrise' => chog_hm($sun['rise']),
            'sunset' => chog_hm($sun['set']),
            'riseIso' => $sun['rise']->format('c'),
            'setIso' => $sun['set']->format('c'),
        ],
        'tithi' => $tithi,
        'rahu' => $rahu,
        'current' => $current,
        'day' => $dayRows,
        'night' => $nightRows,
        'corporate' => chog_corporate(),
    ];
}

// --------------- Router ---------------
[$chogLat, $chogLon] = chog_parse_lat_lon();
$chogClock = chog_now();
$chogDay = chog_parse_target_day($chogClock);
$data = chog_payload($chogDay, $chogClock, $chogLat, $chogLon);

if (isset($_GET['format']) && strtolower((string)$_GET['format']) === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Accept, Content-Type');
    header('Cache-Control: public, max-age=60');
    header('X-Robots-Tag: noindex');
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (isset($_GET['manifest']) && (string)$_GET['manifest'] === '1') {
    $corp = is_array($data['corporate'] ?? null) ? $data['corporate'] : ['text' => 'Arthsathi Limited', 'url' => 'https://arthsathi.com/', 'logoUrl' => '/favicon.ico'];
    header('Content-Type: application/manifest+json; charset=utf-8');
    echo json_encode([
        'name' => 'Choghadiya · Auspicious hours',
        'short_name' => 'Choghadiya',
        'description' => 'Delhi NCR — Tithi, Rahu Kaal, day & night Choghadiya (IST)',
        'start_url' => './choghadiya.php',
        'display' => 'standalone',
        'background_color' => '#FAF9F8',
        'theme_color' => '#0078D4',
        'lang' => 'en-IN',
        'icons' => [
            ['src' => (string)($corp['logoUrl'] ?? '/favicon.ico'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

$lang = (isset($_GET['lang']) && preg_match('/^hi/i', (string)$_GET['lang'])) ? 'hi' : 'en';
$embed = isset($_GET['embed']) && (string)$_GET['embed'] === '1';
if ($embed) {
    header("Content-Security-Policy: frame-ancestors 'self' https://*.arthsathi.com https://*.fusionlimited.in https://*.digisofts.com https://*.simcoauto.com https://rc.arthsathi.com https://rc.fusionlimited.in");
} else {
    header('X-Frame-Options: SAMEORIGIN');
}

$corp = is_array($data['corporate'] ?? null) ? $data['corporate'] : ['text' => 'Arthsathi Limited', 'url' => 'https://arthsathi.com/', 'logoUrl' => '/favicon.ico'];
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $lang === 'hi' ? 'चौघड़िया · शुभ समय' : 'Choghadiya · Auspicious hours';
$desc = $lang === 'hi'
    ? 'दिल्ली NCR — तिथि, राहु काल, दिन/रात चौघड़िया (IST)'
    : 'Delhi NCR — Tithi, Rahu Kaal, day & night Choghadiya (IST)';
$cur = is_array($data['current'] ?? null) ? $data['current'] : [];
$curName = (string)($cur['name'] ?? '—');
$curKind = (string)($cur['kind'] ?? 'chal');
$curNameHi = (string)($cur['nameHi'] ?? $curName);
$iso = (string)($data['date'] ?? $data['dateStr'] ?? $data['dateInfo']['iso'] ?? date('Y-m-d'));
$dateLabel = (string)($data['dateInfo']['label'] ?? $iso);
$tithi = is_array($data['tithi'] ?? null) ? $data['tithi'] : [];
$rahu = is_array($data['rahu'] ?? null) ? $data['rahu'] : [];
$dayRows = is_array($data['day'] ?? null) ? $data['day'] : [];
$nightRows = is_array($data['night'] ?? null) ? $data['night'] : [];
$sun = is_array($data['sun'] ?? null) ? $data['sun'] : [];

// Week strip: selected day + 6 future dates (7 chips total)
$stripStart = $chogDay->setTime(0, 0, 0);
$weekChips = [];
$todayIso = $chogClock->format('Y-m-d');
for ($i = 0; $i < 7; $i++) {
    $d = $stripStart->modify('+' . $i . ' day');
    $weekChips[] = [
        'iso' => $d->format('Y-m-d'),
        'dow' => $lang === 'hi'
            ? (['रवि','सोम','मंगल','बुध','गुरु','शुक्र','शनि'][(int)$d->format('w')])
            : $d->format('D'),
        'dom' => $d->format('j'),
        'isToday' => $d->format('Y-m-d') === $todayIso,
        'isSel' => $d->format('Y-m-d') === $iso,
    ];
}

// Auspicious windows (Amrit / Shubh / Labh) for the selected day
$wins = [];
foreach (array_merge($dayRows, $nightRows) as $r) {
    if (!is_array($r)) {
        continue;
    }
    if (($r['kind'] ?? '') === 'shubh') {
        $wins[] = $r;
    }
}

$kindClass = static function (string $k): string {
    return match ($k) {
        'shubh' => 'best',
        'chal' => 'neutral',
        default => 'bad',
    };
};
$hi = $lang === 'hi';
$L = [
    'now' => $hi ? 'अभी' : 'Live Now',
    'today' => $hi ? 'आज' : 'Today',
    'tithi' => $hi ? 'तिथि' : 'Tithi',
    'paksha' => $hi ? 'पक्ष' : 'Paksha',
    'rahu' => $hi ? 'राहु काल' : 'Rahu Kaal',
    'rahuAvoid' => $hi ? 'इस अवधि में शुभ कार्य टालें' : 'Avoid auspicious work',
    'shubhWins' => $hi ? 'सर्वोत्तम शुभ मुहूर्त' : 'Most Auspicious Windows',
    'day' => $hi ? 'दिन चौघड़िया' : 'Day Choghadiya',
    'night' => $hi ? 'रात चौघड़िया' : 'Night Choghadiya',
    'sunrise' => $hi ? 'सूर्योदय' : 'Sunrise',
    'sunset' => $hi ? 'सूर्यास्त' : 'Sunset',
    'ends' => $hi ? 'समाप्ति' : 'Ends',
    'pick' => $hi ? 'तारीख चुनें' : 'Select date',
    'prev' => $hi ? 'पिछला दिन' : 'Previous day',
    'next' => $hi ? 'अगला दिन' : 'Next day',
    'langBtn' => $hi ? '🌐 EN' : '🌐 हिंदी',
    'note1' => $hi
        ? '★ अमृत, ✓ शुभ और ✓ लाभ महत्वपूर्ण कार्यों के लिए सर्वोत्तम हैं। – चर सामान्य (यात्रा हेतु उपयुक्त) है।'
        : '★ Amrit, ✓ Shubh and ✓ Labh are best for important work. – Char is neutral (travel).',
    'note2' => $hi
        ? '✕ राहु काल में शुभ कार्य टालें। नीचे दी गई सूची गणना सर्वर (IST) से है।'
        : '✕ Avoid Rahu Kaal for auspicious work. Times are server-calculated in IST.',
];

$statusLabel = static function (string $name, bool $hi): string {
    $map = [
        'Amrit' => $hi ? 'सर्वोत्तम शुभ' : 'Most Auspicious',
        'Shubh' => $hi ? 'शुभ समय' : 'Auspicious',
        'Labh' => $hi ? 'लाभकारी' : 'Beneficial',
        'Char' => $hi ? 'सामान्य' : 'Neutral',
        'Rog' => $hi ? 'अशुभ' : 'Inauspicious',
        'Kaal' => $hi ? 'अशुभ' : 'Inauspicious',
        'Udveg' => $hi ? 'अशुभ' : 'Inauspicious',
    ];
    return $map[$name] ?? ($hi ? 'समय' : 'Period');
};

$tithiName = $hi ? (string)($tithi['nameHi'] ?? $tithi['name'] ?? '—') : (string)($tithi['name'] ?? '—');
$pakshaName = $hi
    ? (string)($tithi['pakshaFullHi'] ?? $tithi['pakshaHi'] ?? '')
    : (string)($tithi['pakshaFull'] ?? $tithi['paksha'] ?? '');
$displayCur = $hi ? $curNameHi : $curName;
$moonEmoji = ((int)($tithi['number'] ?? 0) === 15) ? '🌕' : (((int)($tithi['number'] ?? 0) === 30) ? '🌑' : '🌓');
?>
<!DOCTYPE html>
<html lang="<?= $hi ? 'hi-IN' : 'en-IN' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $h($title) ?></title>
<meta name="description" content="<?= $h($desc) ?>">
<meta property="og:title" content="<?= $h($title) ?>">
<meta property="og:description" content="<?= $h($desc) ?>">
<meta name="theme-color" content="#0078D4">
<link rel="manifest" href="?manifest=1">
<link rel="icon" href="<?= $h($corp['logoUrl'] ?? '/favicon.ico') ?>">
<style>
:root{
  color-scheme:light dark;
  --bg:#F8F4EC;--bg2:#EFE6D5;--card:#fff;--line:#E2D5BE;--text:#1E293B;--muted:#64748B;
  --saffron:#B45309;--accent:#0078D4;
  --bad-bg:#FEF2F2;--bad-txt:#B91C1C;--bad-border:#F87171;
  --good-bg:#F0F9FF;--good-txt:#0369A1;--good-border:#38BDF8;
  --best-bg:#FEFCE8;--best-txt:#854D0E;--best-border:#FACC15;
  --neutral-bg:#F1F5F9;--neutral-txt:#475569;--neutral-border:#94A3B8;
}
@media(prefers-color-scheme:dark){
  :root{
    --bg:#0F1016;--bg2:#181A24;--card:#14161F;--line:#262938;--text:#F1F5F9;--muted:#94A3B8;
    --saffron:#FDE047;--accent:#4CA3EE;
    --bad-bg:#3B1216;--bad-txt:#FCA5A5;--bad-border:#DC2626;
    --good-bg:#0C2D48;--good-txt:#7DD3FC;--good-border:#0284C7;
    --best-bg:#3B2F0B;--best-txt:#FDE047;--best-border:#EAB308;
    --neutral-bg:#222530;--neutral-txt:#CBD5E1;--neutral-border:#64748B;
  }
}
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{margin:0}
body{
  font:15px/1.45 system-ui,-apple-system,"Segoe UI","Kohinoor Devanagari",sans-serif;
  background:var(--bg);color:var(--text);
  min-height:100vh;padding:<?= $embed ? '10px 12px 20px' : 'calc(12px + env(safe-area-inset-top)) 16px calc(24px + env(safe-area-inset-bottom))' ?>;
}
button{font:inherit;color:inherit;cursor:pointer}
.shell{max-width:880px;margin:0 auto}
header.top{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.nav{
  width:44px;height:44px;flex:none;border-radius:50%;border:1px solid var(--line);
  background:var(--card);color:var(--saffron);font-size:28px;line-height:1;
}
.ttl{flex:1;text-align:center;min-width:0}
#date{font-size:18px;font-weight:700;color:var(--saffron)}
#rel{font-size:12px;color:var(--muted)}
.tools{display:flex;justify-content:center;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.pill{
  font-size:13px;font-weight:600;background:var(--card);border:1px solid var(--line);
  border-radius:999px;padding:6px 14px;position:relative;
}
.pill.lang{border-color:var(--saffron);color:var(--saffron)}
.pill.pick input{position:absolute;inset:0;opacity:0;width:100%;height:100%;cursor:pointer}
.week{display:flex;gap:4px;margin-bottom:12px}
.wd{
  flex:1;min-width:0;padding:6px 0;border-radius:10px;border:1px solid var(--line);
  background:var(--card);display:flex;flex-direction:column;align-items:center;line-height:1.2;
  text-decoration:none;color:inherit;
}
.wd span{font-size:11px;color:var(--muted)}
.wd b{font-size:16px}
.wd.today b{color:var(--saffron)}
.wd.sel{background:var(--saffron);border-color:var(--saffron)}
.wd.sel span,.wd.sel b{color:#000}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px 14px;margin-bottom:12px}
.best{--t-bg:var(--best-bg);--t-txt:var(--best-txt);--t-bd:var(--best-border)}
.good{--t-bg:var(--good-bg);--t-txt:var(--good-txt);--t-bd:var(--good-border)}
.neutral{--t-bg:var(--neutral-bg);--t-txt:var(--neutral-txt);--t-bd:var(--neutral-border)}
.bad{--t-bg:var(--bad-bg);--t-txt:var(--bad-txt);--t-bd:var(--bad-border)}
.live{
  display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:12px;
  border-radius:14px;background:var(--t-bg);border:2px solid var(--t-bd);
}
.live .lv{flex:1;min-width:0}
.live small{font-size:11px;color:var(--muted)}
.live b.name{font-size:17px;color:var(--t-txt)}
.live .cd{display:flex;flex-direction:column;align-items:flex-end}
.live .cd b{font-size:18px;font-variant-numeric:tabular-nums}
.tithi{display:flex;gap:12px;align-items:center}
.moon{font-size:36px;line-height:1}
.tname{font-size:18px;font-weight:700}
.sun{display:flex;justify-content:space-around;font-size:13px;color:var(--muted);margin:-2px 0 12px}
.sun b{color:var(--text)}
.rahu{background:var(--bad-bg);border:2px solid var(--bad-border)}
.rahu .hd{color:var(--bad-txt);font-weight:700}
.big{font-size:20px;font-weight:700;font-variant-numeric:tabular-nums;margin-top:2px}
.shubh-card{background:var(--good-bg);border:2px solid var(--good-border)}
.shubh-card .hd{color:var(--good-txt);font-weight:700;margin-bottom:4px}
.win{display:grid;grid-template-columns:18px 1fr auto;gap:6px;align-items:center;padding:6px 0;border-top:1px solid color-mix(in srgb,var(--good-txt) 22%,transparent)}
.win .sg{color:var(--good-txt);font-weight:800}
.win .wn{font-size:13px;color:var(--good-txt);font-weight:600}
.win .wt{font-size:14px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
.ch-card{padding:0;overflow:hidden}
.ch-header{display:flex;justify-content:space-between;align-items:center;padding:10px 14px;background:var(--bg2);font-weight:700;font-size:14px;color:var(--saffron);border-bottom:1px solid var(--line)}
.sun-time{font-size:12px;font-weight:400;color:var(--muted)}
.row{
  display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:10px;
  padding:9px 12px;border-bottom:1px solid var(--line);background:var(--t-bg);border-left:6px solid var(--t-bd);
}
.row:last-child{border-bottom:none}
.row.on{box-shadow:inset 0 0 0 2px var(--saffron)}
.ch-name{font-weight:700;font-size:15px;color:var(--t-txt)}
.ch-en{font-size:11px;color:var(--muted)}
.tm{font-size:13px;font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}
.badge{font-size:10.5px;font-weight:700;white-space:nowrap;color:var(--t-txt);border:1px solid color-mix(in srgb,var(--t-txt) 40%,transparent);border-radius:999px;padding:3px 8px}
.nowchip{display:inline-block;font-size:10px;font-weight:700;color:#fff;background:#D97706;border-radius:6px;padding:1px 6px;margin-left:4px}
.note{background:var(--bg2);font-size:12.5px;color:var(--muted)}
.note p{margin:4px 0}
footer.ft{display:flex;justify-content:center;align-items:center;gap:8px;flex-wrap:wrap;margin-top:16px;padding:12px;background:var(--card);border:1px solid var(--line);border-radius:12px;color:var(--muted);font-size:12px}
footer.ft a{color:var(--saffron);font-weight:600}
.clock{font:700 12px/1 system-ui;color:var(--accent);margin-left:auto}
</style>
</head>
<body>
<div class="shell">
  <header class="top">
    <button type="button" class="nav" id="prev" aria-label="<?= $h($L['prev']) ?>">‹</button>
    <div class="ttl">
      <div id="date"><?= $h($dateLabel) ?></div>
      <div id="rel"><?= $iso === $todayIso ? $h($L['today']) : $h($iso) ?> · IST</div>
    </div>
    <button type="button" class="nav" id="next" aria-label="<?= $h($L['next']) ?>">›</button>
  </header>

  <div class="tools">
    <button type="button" class="pill" id="goToday"><?= $h($L['today']) ?></button>
    <label class="pill pick"><span><?= $h($L['pick']) ?></span><input type="date" id="picker" value="<?= $h($iso) ?>" aria-label="<?= $h($L['pick']) ?>"></label>
    <button type="button" class="pill lang" id="langBtn"><?= $h($L['langBtn']) ?></button>
    <span class="clock" id="clock" aria-live="polite"></span>
  </div>

  <nav class="week" aria-label="Next 7 days">
    <?php foreach ($weekChips as $c): ?>
      <a class="wd<?= !empty($c['isToday']) ? ' today' : '' ?><?= !empty($c['isSel']) ? ' sel' : '' ?>"
         href="?date=<?= $h($c['iso']) ?>&amp;lang=<?= $h($lang) ?><?= $embed ? '&amp;embed=1' : '' ?>">
        <span><?= $h($c['dow']) ?></span>
        <b><?= $h($c['dom']) ?></b>
      </a>
    <?php endforeach; ?>
  </nav>

  <?php if ($curName !== '—' && $iso === $todayIso): ?>
  <div class="live <?= $h($kindClass($curKind)) ?>" id="liveBanner">
    <div class="lv">
      <small><?= $h($L['now']) ?></small>
      <b class="name"><?= $h($displayCur) ?> <span class="nowchip">NOW</span></b>
      <small><?= $h($statusLabel($curName, $hi)) ?> · <?= $h(($cur['start'] ?? '') . ' – ' . ($cur['end'] ?? '')) ?></small>
    </div>
    <div class="cd">
      <small><?= $h($L['ends']) ?></small>
      <b id="cd"><?= $h($cur['end'] ?? '') ?></b>
    </div>
  </div>
  <?php endif; ?>

  <section class="card">
    <div class="tithi">
      <div class="moon" aria-hidden="true"><?= $moonEmoji ?></div>
      <div>
        <div class="tname"><?= $h($tithiName) ?></div>
        <div class="muted" style="color:var(--muted);font-size:13px"><?= $h($pakshaName) ?><?php if (!empty($tithi['endTime'])): ?> · <?= $h($L['ends']) ?> <?= $h((string)$tithi['endTime']) ?><?php endif; ?></div>
      </div>
    </div>
  </section>

  <div class="sun">
    <span><?= $h($L['sunrise']) ?> <b><?= $h((string)($sun['rise'] ?? $sun['sunrise'] ?? '—')) ?></b></span>
    <span><?= $h($L['sunset']) ?> <b><?= $h((string)($sun['set'] ?? $sun['sunset'] ?? '—')) ?></b></span>
  </div>

  <section class="card rahu">
    <div class="hd"><?= $h($L['rahu']) ?></div>
    <div class="big"><?= $h((string)($rahu['label'] ?? '—')) ?></div>
    <div style="font-size:12px;color:var(--muted);margin-top:4px"><?= $h($L['rahuAvoid']) ?></div>
  </section>

  <section class="card shubh-card">
    <div class="hd"><?= $h($L['shubhWins']) ?></div>
    <?php if (!$wins): ?>
      <p style="margin:6px 0;font-size:13px;color:var(--muted)"><?= $hi ? 'इस दिन कोई शुभ चौघड़िया नहीं।' : 'No auspicious Choghadiya window on this day.' ?></p>
    <?php else: ?>
      <?php foreach ($wins as $w): ?>
        <div class="win">
          <span class="sg">★</span>
          <span class="wn"><?= $h($hi ? (string)($w['nameHi'] ?? $w['name']) : (string)($w['name'] ?? '')) ?></span>
          <span class="wt"><?= $h((string)($w['start'] ?? '') . ' – ' . (string)($w['end'] ?? '')) ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

  <section class="card ch-card">
    <div class="ch-header"><span><?= $h($L['day']) ?></span><span class="sun-time"><?= $h($L['sunrise']) ?> · <?= $h((string)($sun['rise'] ?? '')) ?></span></div>
    <?php foreach ($dayRows as $r):
      if (!is_array($r)) continue;
      $kc = $kindClass((string)($r['kind'] ?? 'chal'));
      $nm = $hi ? (string)($r['nameHi'] ?? $r['name'] ?? '') : (string)($r['name'] ?? '');
      ?>
      <div class="row <?= $h($kc) ?><?= !empty($r['isNow']) ? ' on' : '' ?>">
        <div>
          <div class="ch-name"><?= $h($nm) ?><?php if (!empty($r['isNow'])): ?><span class="nowchip">NOW</span><?php endif; ?></div>
          <div class="ch-en"><?= $h($statusLabel((string)($r['name'] ?? ''), $hi)) ?></div>
        </div>
        <span class="tm"><?= $h((string)($r['start'] ?? '') . '–' . (string)($r['end'] ?? '')) ?></span>
        <span class="badge"><?= $h($statusLabel((string)($r['name'] ?? ''), $hi)) ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card ch-card">
    <div class="ch-header"><span><?= $h($L['night']) ?></span><span class="sun-time"><?= $h($L['sunset']) ?> · <?= $h((string)($sun['set'] ?? '')) ?></span></div>
    <?php foreach ($nightRows as $r):
      if (!is_array($r)) continue;
      $kc = $kindClass((string)($r['kind'] ?? 'chal'));
      $nm = $hi ? (string)($r['nameHi'] ?? $r['name'] ?? '') : (string)($r['name'] ?? '');
      ?>
      <div class="row <?= $h($kc) ?><?= !empty($r['isNow']) ? ' on' : '' ?>">
        <div>
          <div class="ch-name"><?= $h($nm) ?><?php if (!empty($r['isNow'])): ?><span class="nowchip">NOW</span><?php endif; ?></div>
          <div class="ch-en"><?= $h($statusLabel((string)($r['name'] ?? ''), $hi)) ?></div>
        </div>
        <span class="tm"><?= $h((string)($r['start'] ?? '') . '–' . (string)($r['end'] ?? '')) ?></span>
        <span class="badge"><?= $h($statusLabel((string)($r['name'] ?? ''), $hi)) ?></span>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card note">
    <p><?= $L['note1'] ?></p>
    <p><?= $L['note2'] ?></p>
    <p>📍 <?= $h((string)($data['region'] ?? 'Delhi NCR')) ?> · <?= $h((string)($data['lat'] ?? '')) ?>°N, <?= $h((string)($data['lon'] ?? '')) ?>°E · API <code>?format=json</code></p>
  </section>

  <footer class="ft">
    <span><?= $h((string)($corp['text'] ?? 'Arthsathi Limited')) ?></span>
    <a href="<?= $h((string)($corp['url'] ?? 'https://arthsathi.com/')) ?>" target="_blank" rel="noopener">www.arthsathi.com</a>
  </footer>
</div>
<script>
(function () {
  const iso = <?= json_encode($iso) ?>;
  const lang = <?= json_encode($lang) ?>;
  const embed = <?= $embed ? 'true' : 'false' ?>;
  const endIso = <?= json_encode($cur['endIso'] ?? null) ?>;

  function goDate(nextIso) {
    const u = new URL(location.href);
    u.searchParams.set('date', nextIso);
    u.searchParams.set('lang', lang);
    if (embed) u.searchParams.set('embed', '1');
    else u.searchParams.delete('embed');
    location.href = u.toString();
  }
  function addDays(isoStr, n) {
    const p = isoStr.split('-').map(Number);
    const d = new Date(Date.UTC(p[0], p[1] - 1, p[2] + n));
    const y = d.getUTCFullYear(), m = String(d.getUTCMonth() + 1).padStart(2, '0'), day = String(d.getUTCDate()).padStart(2, '0');
    return y + '-' + m + '-' + day;
  }
  document.getElementById('prev').onclick = () => goDate(addDays(iso, -1));
  document.getElementById('next').onclick = () => goDate(addDays(iso, 1));
  document.getElementById('goToday').onclick = () => {
    const u = new URL(location.href);
    u.searchParams.delete('date');
    u.searchParams.set('lang', lang);
    if (embed) u.searchParams.set('embed', '1');
    location.href = u.toString();
  };
  document.getElementById('picker').onchange = (e) => {
    if (e.target.value) goDate(e.target.value);
  };
  document.getElementById('langBtn').onclick = () => {
    const u = new URL(location.href);
    u.searchParams.set('lang', lang === 'hi' ? 'en' : 'hi');
    location.href = u.toString();
  };
  function tickClock() {
    try {
      const s = new Intl.DateTimeFormat('en-IN', { timeZone: 'Asia/Kolkata', hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true }).format(new Date());
      const el = document.getElementById('clock');
      if (el) el.textContent = s + ' IST';
    } catch (e) {}
  }
  function tickCd() {
    if (!endIso) return;
    const el = document.getElementById('cd');
    if (!el) return;
    const end = new Date(endIso).getTime();
    const left = end - Date.now();
    if (left <= 0) { el.textContent = '0:00'; return; }
    const m = Math.floor(left / 60000), s = Math.floor((left % 60000) / 1000);
    el.textContent = m + ':' + String(s).padStart(2, '0');
  }
  tickClock(); tickCd();
  setInterval(tickClock, 1000);
  setInterval(tickCd, 1000);
})();
</script>
</body>
</html>
