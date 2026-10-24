<?php
// Version: 5.0.1 (PHP 8.5.12+ critical path)
/**
 * Google Finance–style markets board + Scriptable/KWGT JSON API
 * Endpoint: /metals.php | /metals.php?format=json | /metals.php?embed=1
 * Markets: Delhi NCR / MCX commodities + INR FX (USD, EUR, GBP, AED, CNY)
 * Timezone: Asia/Kolkata (IST) | Locales: en-IN, hi-IN
 * PHP: 8.2+ (curl handles auto-closed; PHP 8.5.12+ only — pipe no 8.5-only syntax so 8.5.12+ only native array helpers)
 */
declare(strict_types=1);

if (is_file(__DIR__ . '/app/php85_runtime.php')) {
    require_once __DIR__ . '/app/php85_runtime.php';
}
date_default_timezone_set('Asia/Kolkata');

$__rcBase = __DIR__;
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $__rcBase);
}
if (!defined('DATA_PATH') && is_file($__rcBase . '/app/tenant_bootstrap.php')) {
    try {
        require_once $__rcBase . '/app/tenant_bootstrap.php';
    } catch (Throwable $e) {
        /* standalone OK */
    }
}

function metals_cache_path(): string
{
    if (defined('DATA_PATH') && is_dir((string) DATA_PATH)) {
        $d = rtrim((string) DATA_PATH, '/\\') . '/cache';
        if (!is_dir($d)) {
            @mkdir($d, 0775, true);
        }
        return $d . '/delhi_metals_cache_v41.json';
    }
    return __DIR__ . '/delhi_metals_cache_v41.json';
}

const CACHE_TTL_SECONDS = 300;

const CORPORATE_BRAND = [
    'text'    => 'Powered by Arthsathi Limited.',
    'url'     => 'https://arthsathi.com/',
    'logoUrl' => 'https://arthsathi.com/favicon.ico',
];

const COMMODITY_ORDER = ['gold', 'platinum', 'silver', 'copper', 'brass', 'zinc', 'steel'];

const FX_ORDER = ['USDINR', 'EURINR', 'GBPINR', 'AEDINR', 'CNYINR'];

const GST_RATES = [
    'gold' => 0.03, 'platinum' => 0.03, 'silver' => 0.03,
    'copper' => 0.18, 'brass' => 0.18, 'zinc' => 0.18, 'steel' => 0.18,
];

const DELHI_EX_GST_BASE = [
    'gold'     => ['basePrice' => 147580.00, 'change' => 0.42,  'unit' => '10g',  'mcxLot' => '1 kg'],
    'platinum' => ['basePrice' => 54065.00,  'change' => 1.80,  'unit' => '10g',  'mcxLot' => '100g'],
    'silver'   => ['basePrice' => 188390.00, 'change' => 0.52,  'unit' => '1kg',  'mcxLot' => '30 kg / 5 kg'],
    'copper'   => ['basePrice' => 1480.91,   'change' => 0.78,  'unit' => '1kg',  'mcxLot' => '2.5 MT'],
    'brass'    => ['basePrice' => 621.98,    'change' => 0.78,  'unit' => '1kg',  'mcxLot' => 'Delhi Spot'],
    'zinc'     => ['basePrice' => 447.90,    'change' => -0.86, 'unit' => '1kg',  'mcxLot' => 'ZINC 5MT'],
    'steel'    => ['basePrice' => 47000.00,  'change' => -2.09, 'unit' => '1 MT', 'mcxLot' => 'NCR Mandi'],
];

const FX_FALLBACK = [
    'USDINR' => ['price' => 83.50, 'change' => 0.05, 'name' => 'US Dollar / Indian Rupee'],
    'EURINR' => ['price' => 90.20, 'change' => 0.08, 'name' => 'Euro / Indian Rupee'],
    'GBPINR' => ['price' => 108.40, 'change' => 0.12, 'name' => 'British Pound / Indian Rupee'],
    'AEDINR' => ['price' => 22.73, 'change' => 0.02, 'name' => 'UAE Dirham / Indian Rupee'],
    'CNYINR' => ['price' => 11.55, 'change' => -0.03, 'name' => 'Chinese Yuan / Indian Rupee'],
];

const FX_META = [
    'USDINR' => ['symbol' => 'USDINR=X', 'base' => 'USD', 'name' => 'US Dollar', 'nameHi' => 'अमेरिकी डॉलर'],
    'EURINR' => ['symbol' => 'EURINR=X', 'base' => 'EUR', 'name' => 'Euro', 'nameHi' => 'यूरो'],
    'GBPINR' => ['symbol' => 'GBPINR=X', 'base' => 'GBP', 'name' => 'British Pound', 'nameHi' => 'ब्रिटिश पाउंड'],
    'AEDINR' => ['symbol' => 'AEDINR=X', 'base' => 'AED', 'name' => 'UAE Dirham', 'nameHi' => 'यूएई दिरहम'],
    'CNYINR' => ['symbol' => 'CNYINR=X', 'base' => 'CNY', 'name' => 'Chinese Yuan', 'nameHi' => 'चीनी युआन'],
];

const COMMODITY_META = [
    'gold'     => ['name' => 'Gold', 'nameHi' => 'सोना', 'tint' => '#E6B422'],
    'platinum' => ['name' => 'Platinum', 'nameHi' => 'प्लैटिनम', 'tint' => '#9CA3AF'],
    'silver'   => ['name' => 'Silver', 'nameHi' => 'चांदी', 'tint' => '#94A3B8'],
    'copper'   => ['name' => 'Copper', 'nameHi' => 'तांबा', 'tint' => '#C2410C'],
    'brass'    => ['name' => 'Brass', 'nameHi' => 'पीतल', 'tint' => '#CA8A04'],
    'zinc'     => ['name' => 'Zinc', 'nameHi' => 'जस्ता', 'tint' => '#64748B'],
    'steel'    => ['name' => 'MS Steel Ingot', 'nameHi' => 'एमएस स्टील', 'tint' => '#475569'],
];

function metals_fetch_parallel(array $urls, int $timeout = 4): array
{
    if (!function_exists('curl_multi_init')) {
        $out = [];
        foreach ($urls as $k => $u) {
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout, 'header' => "User-Agent: RC-Metals/4.1\r\n"],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $body = @file_get_contents($u, false, $ctx);
            $out[$k] = is_string($body) && $body !== '' ? $body : null;
        }
        return $out;
    }
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        if ($ch === false) {
            continue;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RC-Metals/4.1; +https://arthsathi.com)',
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json,text/html;q=0.9,*/*;q=0.8',
                'Accept-Language: en-IN,en;q=0.9',
            ],
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
    }
    $running = null;
    do {
        $status = curl_multi_exec($mh, $running);
        if ($running > 0) {
            curl_multi_select($mh, 0.2);
        }
    } while ($running > 0 && $status === CURLM_OK);
    $results = [];
    foreach ($handles as $key => $ch) {
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $body = curl_multi_getcontent($ch);
        $results[$key] = ($code >= 200 && $code < 300 && is_string($body) && $body !== '') ? $body : null;
        curl_multi_remove_handle($mh, $ch);
        unset($ch);
    }
    unset($mh);
    return $results;
}

function metals_parse_yahoo(?string $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $json = json_decode($raw, true);
    $meta = $json['chart']['result'][0]['meta'] ?? null;
    if (!is_array($meta) || empty($meta['regularMarketPrice'])) {
        return null;
    }
    $price = (float) $meta['regularMarketPrice'];
    $prev  = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? $price);
    $chg   = $prev > 0 ? (($price - $prev) / $prev) * 100.0 : 0.0;
    $chgAbs = $price - $prev;
    return [
        'price'     => $price,
        'changePct' => round($chg, 2),
        'changeAbs' => round($chgAbs, 4),
        'prev'      => $prev,
    ];
}

function metals_build_payload(array $commodities, array $fx, string $status): array
{
    $items = [];
    foreach (COMMODITY_ORDER as $key) {
        $entry = $commodities[$key] ?? DELHI_EX_GST_BASE[$key];
        $gst   = GST_RATES[$key] ?? 0.0;
        $base  = (float) $entry['basePrice'];
        $dec   = in_array($key, ['copper', 'brass', 'zinc'], true);
        $total = $base * (1.0 + $gst);
        $meta  = COMMODITY_META[$key];
        $items[$key] = [
            'id'         => $key,
            'name'       => $meta['name'],
            'nameHi'     => $meta['nameHi'],
            'basePrice'  => $dec ? round($base, 2) : (int) round($base),
            'gstPct'     => (int) round($gst * 100),
            'gstAmount'  => $dec ? round($total - $base, 2) : (int) round($total - $base),
            'totalPrice' => $dec ? round($total, 2) : (int) round($total),
            'change'     => round((float) ($entry['change'] ?? 0), 2),
            'unit'       => $entry['unit'] ?? DELHI_EX_GST_BASE[$key]['unit'],
            'mcxLot'     => $entry['mcxLot'] ?? DELHI_EX_GST_BASE[$key]['mcxLot'],
            'currency'   => 'INR',
            'tint'       => $meta['tint'],
        ];
    }

    $fxOut = [];
    foreach (FX_ORDER as $pair) {
        $row  = $fx[$pair] ?? FX_FALLBACK[$pair];
        $meta = FX_META[$pair];
        $price = (float) $row['price'];
        $chg   = (float) ($row['change'] ?? 0);
        $prev  = $chg !== 0.0 && abs($chg) < 50 ? $price / (1 + $chg / 100) : $price;
        $fxOut[$pair] = [
            'id'        => $pair,
            'symbol'    => $meta['symbol'],
            'name'      => $meta['name'] . ' / INR',
            'nameHi'    => $meta['nameHi'] . ' / रुपया',
            'base'      => $meta['base'],
            'quote'     => 'INR',
            'price'     => round($price, 4),
            'change'    => round($chg, 2),
            'changeAbs' => round($price - $prev, 4),
            'unit'      => '1 ' . $meta['base'],
            'currency'  => 'INR',
        ];
    }

    $self = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))
        . '/metals.php?format=json';

    return [
        'version'       => '5.0.1',
        'status'        => $status,
        'timestamp'     => date('c'),
        'formattedTime' => date('h:i A'),
        'formattedDate' => date('d M Y'),
        'region'        => 'Delhi NCR, India',
        'timezone'      => 'Asia/Kolkata',
        'order'         => COMMODITY_ORDER,
        'fxOrder'       => FX_ORDER,
        'corporate'     => array_merge(CORPORATE_BRAND, ['apiSelf' => $self]),
        'items'         => $items,
        'fx'            => $fxOut,
        'markets'       => [
            'commodities' => array_values($items),
            'fx'          => array_values($fxOut),
        ],
        'counts'        => [
            'commodities' => count($items),
            'fx'          => count($fxOut),
            'total'       => count($items) + count($fxOut),
        ],
    ];
}

function metals_get_market(bool $force = false): array
{
    $path = metals_cache_path();
    if (!$force && is_file($path)) {
        $age = time() - (int) filemtime($path);
        if ($age < CACHE_TTL_SECONDS) {
            $cached = json_decode((string) file_get_contents($path), true);
            if (is_array($cached) && isset($cached['items']['gold'], $cached['fx']['USDINR'])) {
                return $cached;
            }
        }
    }

    $working = DELHI_EX_GST_BASE;
    $fxWork  = [];
    foreach (FX_ORDER as $p) {
        $fxWork[$p] = FX_FALLBACK[$p];
    }
    $liveHits = 0;

    $endpoints = [
        'USDINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/USDINR=X?interval=1d&range=5d',
        'EURINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/EURINR=X?interval=1d&range=5d',
        'GBPINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/GBPINR=X?interval=1d&range=5d',
        'AEDINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/AEDINR=X?interval=1d&range=5d',
        'CNYINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/CNYINR=X?interval=1d&range=5d',
        'gold_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/GC=F?interval=1d&range=5d',
        'plat_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/PL=F?interval=1d&range=5d',
        'silv_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/SI=F?interval=1d&range=5d',
        'copp_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/HG=F?interval=1d&range=5d',
        'frank'  => 'https://api.frankfurter.app/latest?from=USD&to=INR,EUR,GBP,CNY',
    ];

    $responses = metals_fetch_parallel($endpoints, 5);

    foreach (FX_ORDER as $pair) {
        $q = metals_parse_yahoo($responses[$pair] ?? null);
        if ($q && $q['price'] > 0) {
            $fxWork[$pair]['price']  = $q['price'];
            $fxWork[$pair]['change'] = $q['changePct'];
            $liveHits++;
        }
    }

    // Frankfurter cross-check for USDINR and derive others if Yahoo failed
    if (!empty($responses['frank'])) {
        $fj = json_decode($responses['frank'], true);
        $usdInr = (float) ($fj['rates']['INR'] ?? 0);
        if ($usdInr > 50 && $usdInr < 120) {
            if (($fxWork['USDINR']['price'] ?? 0) < 50) {
                $fxWork['USDINR']['price'] = $usdInr;
                $fxWork['USDINR']['change'] = 0;
                $liveHits++;
            }
        }
    }

    $usdInr = (float) ($fxWork['USDINR']['price'] ?? 83.5);

    $goldQ = metals_parse_yahoo($responses['gold_f'] ?? null);
    if ($goldQ && $goldQ['price'] > 1000) {
        $calc = round($goldQ['price'] * $usdInr * (10.0 / 31.1034768) * 1.02);
        if ($calc > 80000 && $calc < 250000) {
            $working['gold']['basePrice'] = $calc;
            $working['gold']['change']    = $goldQ['changePct'];
            $liveHits++;
        }
    }

    $platQ = metals_parse_yahoo($responses['plat_f'] ?? null);
    if ($platQ && $platQ['price'] > 400) {
        $calc = round($platQ['price'] * $usdInr * (10.0 / 31.1034768) * 1.05);
        if ($calc > 20000 && $calc < 120000) {
            $working['platinum']['basePrice'] = $calc;
            $working['platinum']['change']    = $platQ['changePct'];
            $liveHits++;
        }
    }

    $silvQ = metals_parse_yahoo($responses['silv_f'] ?? null);
    if ($silvQ && $silvQ['price'] > 10) {
        $calc = round($silvQ['price'] * $usdInr * (1000.0 / 31.1034768) * 1.08);
        if ($calc > 120000 && $calc < 450000) {
            $working['silver']['basePrice'] = $calc;
            $working['silver']['change']    = $silvQ['changePct'];
            $liveHits++;
        }
    }

    $coppQ = metals_parse_yahoo($responses['copp_f'] ?? null);
    if ($coppQ && $coppQ['price'] > 2) {
        $calc = round($coppQ['price'] * 2.20462262 * $usdInr * 1.078, 2);
        if ($calc > 900 && $calc < 2500) {
            $working['copper']['basePrice'] = $calc;
            $working['brass']['basePrice']  = round($calc * 0.42, 2);
            $working['copper']['change']    = $coppQ['changePct'];
            $working['brass']['change']     = $coppQ['changePct'];
            $liveHits++;
        }
    }

    $status  = $liveHits >= 4 ? 'live' : ($liveHits > 0 ? 'partial' : 'cached');
    $payload = metals_build_payload($working, $fxWork, $status);
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    return $payload;
}

// ---------------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------------
$force   = isset($_GET['refresh']) || isset($_GET['force']);
$format  = strtolower((string) ($_GET['format'] ?? ''));
$embed   = isset($_GET['embed']) || isset($_GET['theme']);
$lang    = (strtolower((string) ($_GET['lang'] ?? 'en')) === 'hi') ? 'hi' : 'en';
$theme   = preg_replace('/[^a-z]/', '', strtolower((string) ($_GET['theme'] ?? 'light'))) ?: 'light';
if (!in_array($theme, ['light', 'dark', 'reserve'], true)) {
    $theme = 'light';
}

$data = metals_get_market($force);

if ($format === 'json' || (isset($_SERVER['HTTP_ACCEPT']) && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json') && $format !== 'html')) {
    // Scriptable / KWGT / PWA feed — compact JSON + ETag (304 when unchanged)
    if (isset($data['status']) && is_string($data['status'])) {
        $data['status'] = strtoupper((string) $data['status']);
        if ($data['status'] === 'LIVE' || $data['status'] === 'OK') {
            $data['status'] = 'LIVE';
        }
    }
    // Aliases for older Scriptable widgets (base === basePrice)
    if (!empty($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $k => &$row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['base']) && isset($row['basePrice'])) {
                $row['base'] = $row['basePrice'];
            }
            if (!isset($row['total']) && isset($row['totalPrice'])) {
                $row['total'] = $row['totalPrice'];
            }
        }
        unset($row);
    }
    $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        $body = '{"status":"ERROR","items":{},"fx":{}}';
    }
    $etag = '"' . md5($body) . '"';
    $inm = (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
    if ($inm !== '' && trim($inm) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=60');
        header('Access-Control-Allow-Origin: *');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: public, max-age=60');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Expose-Headers: ETag');
    header('ETag: ' . $etag);
    header('X-Robots-Tag: noindex');
    echo $body;
    exit;
}

$t = [
    'en' => [
        'title' => 'Markets', 'sub' => 'Delhi NCR · MCX · INR FX',
        'commodities' => 'Commodities', 'fx' => 'Currencies',
        'incl' => 'Incl. GST', 'ex' => 'Ex-GST', 'asof' => 'As of',
        'refresh' => 'Refresh', 'json' => 'JSON API', 'live' => 'Live',
        'partial' => 'Partial', 'cached' => 'Cached',
    ],
    'hi' => [
        'title' => 'बाज़ार', 'sub' => 'दिल्ली NCR · MCX · INR मुद्रा',
        'commodities' => 'कमोडिटी', 'fx' => 'मुद्राएँ',
        'incl' => 'GST सहित', 'ex' => 'बिना GST', 'asof' => 'समय',
        'refresh' => 'रिफ्रेश', 'json' => 'JSON API', 'live' => 'लाइव',
        'partial' => 'आंशिक', 'cached' => 'कैश',
    ],
][$lang];

function gf_inr($n, int $dec = 0): string
{
    if (!is_numeric($n)) {
        return '—';
    }
    $n = (float) $n;
    if (class_exists('NumberFormatter')) {
        $fmt = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
        $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, $dec);
        return '₹' . $fmt->format($n);
    }
    return '₹' . number_format($n, $dec, '.', ',');
}

function gf_chg_class(float $c): string
{
    if ($c > 0.001) {
        return 'up';
    }
    if ($c < -0.001) {
        return 'down';
    }
    return 'flat';
}

function gf_chg_label(float $c, float $abs = 0.0, int $absDec = 2): string
{
    $arrow = $c > 0.001 ? '▲' : ($c < -0.001 ? '▼' : '•');
    $sign  = $c > 0 ? '+' : '';
    $pct   = $sign . number_format($c, 2) . '%';
    if (abs($abs) > 0.00005) {
        $aSign = $abs > 0 ? '+' : '';
        return $arrow . ' ' . $aSign . number_format($abs, $absDec) . ' (' . $pct . ')';
    }
    return $arrow . ' ' . $pct;
}

$statusKey = (string) ($data['status'] ?? 'cached');
$statusLbl = $t[$statusKey] ?? $t['cached'];
$asOf      = ($data['formattedTime'] ?? '') . ' IST · ' . ($data['formattedDate'] ?? '');
$jsonUrl   = '/metals.php?format=json';
$selfPath  = '/metals.php' . ($embed ? '?embed=1&theme=' . rawurlencode($theme) : '');

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=60');
?>
<!DOCTYPE html>
<html lang="<?= $lang === 'hi' ? 'hi-IN' : 'en-IN' ?>" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="light dark">
<title><?= htmlspecialchars($t['title'] . ' · Resource Centre', ENT_QUOTES, 'UTF-8') ?></title>
<meta name="description" content="Delhi NCR MCX commodity prices and INR FX rates. Scriptable JSON: metals.php?format=json">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<style>
:root {
  --bg: #fff; --surface: #fff; --row-hover: #f8f9fa; --border: #e8eaed;
  --text: #202124; --muted: #5f6368; --up: #137333; --up-bg: #e6f4ea;
  --down: #a50e0e; --down-bg: #fce8e6; --chip: #e8f0fe; --chip-ink: #1967d2;
  --header: #202124; --shadow: 0 1px 2px rgba(60,64,67,.15), 0 1px 3px 1px rgba(60,64,67,.08);
  --radius: 8px; --font: "Google Sans", "Segoe UI", system-ui, -apple-system, Roboto, "Noto Sans Devanagari", sans-serif;
}
html[data-theme="dark"], html[data-theme="reserve"] {
  --bg: #1b1a19; --surface: #242322; --row-hover: #2d2c2b; --border: #3b3a39;
  --text: #f3f2f1; --muted: #c8c6c4; --up: #81c995; --up-bg: #0d3b1e;
  --down: #f28b82; --down-bg: #3c1214; --chip: #10344f; --chip-ink: #cde5fb;
  --header: #f3f2f1; --shadow: none;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  font-family: var(--font); background: var(--bg); color: var(--text);
  line-height: 1.45; min-height: 100vh; -webkit-font-smoothing: antialiased;
}
.wrap { max-width: 960px; margin: 0 auto; padding: 16px 16px 32px; }
.top {
  display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
  gap: 12px; padding-bottom: 16px; border-bottom: 1px solid var(--border); margin-bottom: 8px;
}
.top h1 { font-size: 1.5rem; font-weight: 500; color: var(--header); letter-spacing: -0.02em; }
.top .sub { font-size: 0.8125rem; color: var(--muted); margin-top: 4px; }
.tools { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.chip {
  display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600;
  padding: 6px 10px; border-radius: 999px; background: var(--chip); color: var(--chip-ink);
  text-decoration: none; border: 0; cursor: pointer;
}
.chip.ghost { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.chip:hover { filter: brightness(0.97); }
.asof { font-size: 0.75rem; color: var(--muted); margin: 12px 0 4px; }
.section-label {
  font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em;
  color: var(--muted); margin: 20px 0 8px; padding: 0 4px;
}
.board {
  background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius);
  box-shadow: var(--shadow); overflow: hidden;
}
.row {
  display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(96px, auto) minmax(120px, auto);
  gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--border);
  text-decoration: none; color: inherit;
}
.row:last-child { border-bottom: 0; }
.row:hover { background: var(--row-hover); }
.name { font-size: 0.9375rem; font-weight: 500; }
.meta { font-size: 0.75rem; color: var(--muted); margin-top: 2px; }
.price { font-size: 0.9375rem; font-weight: 500; text-align: right; font-variant-numeric: tabular-nums; }
.chg {
  font-size: 0.8125rem; font-weight: 500; text-align: right; font-variant-numeric: tabular-nums;
  padding: 4px 8px; border-radius: 4px; justify-self: end; white-space: nowrap;
}
.chg.up { color: var(--up); background: var(--up-bg); }
.chg.down { color: var(--down); background: var(--down-bg); }
.chg.flat { color: var(--muted); background: transparent; }
.foot {
  margin-top: 20px; font-size: 0.75rem; color: var(--muted); text-align: center;
}
.foot a { color: var(--chip-ink); text-decoration: none; }
.foot a:hover { text-decoration: underline; }
code.api {
  display: block; margin-top: 8px; font-size: 0.7rem; word-break: break-all;
  background: var(--row-hover); padding: 8px 10px; border-radius: 6px; color: var(--muted);
}
@media (max-width: 560px) {
  .row { grid-template-columns: 1fr auto; grid-template-areas: "name chg" "name price"; }
  .name-block { grid-area: name; }
  .price { grid-area: price; }
  .chg { grid-area: chg; }
  .top h1 { font-size: 1.25rem; }
}
<?= $embed ? 'body{min-height:auto;background:transparent}.wrap{padding:8px 4px 16px}' : '' ?>
</style>
</head>
<body>
<div class="wrap">
  <header class="top">
    <div>
      <h1><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="sub"><?= htmlspecialchars($t['sub'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="tools">
      <span class="chip" title="Feed status"><?= htmlspecialchars($statusLbl, ENT_QUOTES, 'UTF-8') ?></span>
      <a class="chip ghost" href="<?= htmlspecialchars($selfPath . (str_contains($selfPath, '?') ? '&' : '?') . 'refresh=1', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['refresh'], ENT_QUOTES, 'UTF-8') ?></a>
      <a class="chip ghost" href="<?= htmlspecialchars($jsonUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($t['json'], ENT_QUOTES, 'UTF-8') ?></a>
      <a class="chip ghost" href="?<?= $embed ? 'embed=1&' : '' ?>theme=<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>&lang=<?= $lang === 'hi' ? 'en' : 'hi' ?>"><?= $lang === 'hi' ? 'EN' : 'हिंदी' ?></a>
    </div>
  </header>
  <p class="asof"><?= htmlspecialchars($t['asof'] . ' ' . $asOf, ENT_QUOTES, 'UTF-8') ?></p>

  <div class="section-label"><?= htmlspecialchars($t['fx'], ENT_QUOTES, 'UTF-8') ?></div>
  <div class="board" role="table" aria-label="<?= htmlspecialchars($t['fx'], ENT_QUOTES, 'UTF-8') ?>">
<?php foreach (FX_ORDER as $pair):
    $row = $data['fx'][$pair] ?? null;
    if (!$row) {
        continue;
    }
    $chg = (float) ($row['change'] ?? 0);
    $abs = (float) ($row['changeAbs'] ?? 0);
    $nm  = $lang === 'hi' ? ($row['nameHi'] ?? $row['name']) : ($row['name'] ?? $pair);
    ?>
    <div class="row" role="row">
      <div class="name-block">
        <div class="name"><?= htmlspecialchars((string) $nm, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="meta"><?= htmlspecialchars($pair . ' · ' . ($row['symbol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="price"><?= htmlspecialchars(gf_inr($row['price'] ?? 0, 4), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="chg <?= gf_chg_class($chg) ?>"><?= htmlspecialchars(gf_chg_label($chg, $abs, 4), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endforeach; ?>
  </div>

  <div class="section-label"><?= htmlspecialchars($t['commodities'], ENT_QUOTES, 'UTF-8') ?></div>
  <div class="board" role="table" aria-label="<?= htmlspecialchars($t['commodities'], ENT_QUOTES, 'UTF-8') ?>">
<?php foreach (COMMODITY_ORDER as $key):
    $row = $data['items'][$key] ?? null;
    if (!$row) {
        continue;
    }
    $chg = (float) ($row['change'] ?? 0);
    $nm  = $lang === 'hi' ? ($row['nameHi'] ?? $row['name']) : ($row['name'] ?? $key);
    $unit = (string) ($row['unit'] ?? '');
    $dec  = in_array($key, ['copper', 'brass', 'zinc'], true) ? 2 : 0;
    ?>
    <div class="row" role="row">
      <div class="name-block">
        <div class="name"><?= htmlspecialchars((string) $nm, ENT_QUOTES, 'UTF-8') ?></div>
        <div class="meta"><?= htmlspecialchars($t['incl'] . ' · ' . $unit . ' · GST ' . (int) ($row['gstPct'] ?? 0) . '%', ENT_QUOTES, 'UTF-8') ?></div>
      </div>
      <div class="price"><?= htmlspecialchars(gf_inr($row['totalPrice'] ?? 0, $dec), ENT_QUOTES, 'UTF-8') ?></div>
      <div class="chg <?= gf_chg_class($chg) ?>"><?= htmlspecialchars(gf_chg_label($chg), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
<?php endforeach; ?>
  </div>

  <footer class="foot">
    <a href="<?= htmlspecialchars((string) (CORPORATE_BRAND['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) (CORPORATE_BRAND['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
    <code class="api">Scriptable / KWGT: <?= htmlspecialchars(($data['corporate']['apiSelf'] ?? $jsonUrl), ENT_QUOTES, 'UTF-8') ?></code>
  </footer>
</div>
</body>
</html>
