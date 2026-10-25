<?php
// Version: 5.2.13 — Markets: % under refs, 2dp prices, MCX/FX session row, merged clock
/**
 * Google Finance–style markets board + Scriptable/KWGT JSON API
 * Endpoint: /metals.php | /metals.php?format=json | /metals.php?embed=1
 * Markets: Delhi NCR / MCX commodities + INR FX (USD, EUR, GBP, AED, CNY)
 * Timezone: Asia/Kolkata (IST) | Locales: en-IN, hi-IN
 * PHP: 8.5.12+ required (RC 5.2)
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
                'http' => ['timeout' => $timeout, 'header' => "User-Agent: RC-Metals/5.2.7\r\n"],
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
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; RC-Metals/5.2.7; +https://arthsathi.com)',
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
    $result = $json['chart']['result'][0] ?? null;
    if (!is_array($result)) {
        return null;
    }
    $meta = $result['meta'] ?? null;
    if (!is_array($meta) || empty($meta['regularMarketPrice'])) {
        return null;
    }
    $price = (float) $meta['regularMarketPrice'];
    $prev  = (float) ($meta['chartPreviousClose'] ?? $meta['previousClose'] ?? $price);
    $chg   = $prev > 0 ? (($price - $prev) / $prev) * 100.0 : 0.0;
    $chgAbs = $price - $prev;

    // Build chronological close series
    $series = [];
    $ts = $result['timestamp'] ?? [];
    $closes = $result['indicators']['quote'][0]['close'] ?? [];
    if (is_array($ts) && is_array($closes)) {
        $n = min(count($ts), count($closes));
        for ($i = 0; $i < $n; $i++) {
            $c = $closes[$i] ?? null;
            if ($c === null || !is_numeric($c)) {
                continue;
            }
            $series[] = [
                'ts'    => (int) $ts[$i],
                'date'  => gmdate('Y-m-d', (int) $ts[$i]),
                'close' => (float) $c,
            ];
        }
    }

    $refs = metals_ref_closes($series, $price, $prev);

    return [
        'price'     => $price,
        'changePct' => round($chg, 2),
        'changeAbs' => round($chgAbs, 4),
        'prev'      => $prev,
        'lastDay'   => $refs['lastDay'],
        'lastMonth' => $refs['lastMonth'],
        'lastYear'  => $refs['lastYear'],
        'history'   => $refs, // back-compat alias
    ];
}

/**
 * Pick closes nearest to 1 day / ~1 month / ~1 year ago from a daily series.
 *
 * @param list<array{ts:int,date:string,close:float}> $series
 * @return array{lastDay:?float,lastMonth:?float,lastYear:?float}
 */
function metals_ref_closes(array $series, float $price, float $prevClose): array
{
    $lastDay = $prevClose > 0 ? $prevClose : null;
    $lastMonth = null;
    $lastYear = null;
    if ($series === []) {
        return ['lastDay' => $lastDay, 'lastMonth' => $lastMonth, 'lastYear' => $lastYear];
    }
    $lastTs = (int) $series[count($series) - 1]['ts'];
    // Prefer previous bar as last day if available
    if (count($series) >= 2) {
        $lastDay = (float) $series[count($series) - 2]['close'];
    }
    $targetM = $lastTs - 30 * 86400;
    $targetY = $lastTs - 365 * 86400;
    $bestM = null;
    $bestY = null;
    $bestMd = PHP_INT_MAX;
    $bestYd = PHP_INT_MAX;
    foreach ($series as $row) {
        $ts = (int) $row['ts'];
        $dM = abs($ts - $targetM);
        $dY = abs($ts - $targetY);
        if ($dM < $bestMd) {
            $bestMd = $dM;
            $bestM = (float) $row['close'];
        }
        if ($dY < $bestYd) {
            $bestYd = $dY;
            $bestY = (float) $row['close'];
        }
    }
    // Only accept if within ~5 days of target for month, ~14 for year
    if ($bestMd <= 5 * 86400) {
        $lastMonth = $bestM;
    }
    if ($bestYd <= 14 * 86400) {
        $lastYear = $bestY;
    }
    return [
        'lastDay'   => $lastDay,
        'lastMonth' => $lastMonth,
        'lastYear'  => $lastYear,
    ];
}

/**
 * MCX-oriented session clock (Asia/Kolkata).
 * Mon–Fri 09:00–23:30 IST; Sat 09:00–14:00; Sun closed.
 *
 * @return array{open:bool,label:string,labelHi:string,hours:string,hoursHi:string,nextOpen:string,nextOpenHi:string}
 */
function metals_session_info(): array
{
    $tz = new \DateTimeZone('Asia/Kolkata');
    $now = new \DateTimeImmutable('now', $tz);
    $dow = (int) $now->format('w');
    $mins = ((int) $now->format('G')) * 60 + (int) $now->format('i');

    // MCX (bullion + base metals): Mon–Fri 09:00–23:30; Sat 09:00–14:00; Sun closed
    $mcxOpen = false;
    if ($dow >= 1 && $dow <= 5) {
        $mcxOpen = $mins >= 9 * 60 && $mins < 23 * 60 + 30;
    } elseif ($dow === 6) {
        $mcxOpen = $mins >= 9 * 60 && $mins < 14 * 60;
    }

    // FX (INR crosses): nearly 24×5 — Sun 17:00 IST → Fri 17:00 IST (approx interbank)
    $fxOpen = false;
    if ($dow >= 1 && $dow <= 4) {
        $fxOpen = true; // Mon–Thu continuous after Sunday open
    } elseif ($dow === 5) {
        $fxOpen = $mins < 17 * 60; // Friday until ~17:00 IST
    } elseif ($dow === 0) {
        $fxOpen = $mins >= 17 * 60; // Sunday from ~17:00 IST
    }

    $anyOpen = $mcxOpen || $fxOpen;
    $nextMcx = metals_next_open($now); // existing MCX next-open helper
    $nextMcxStr = $nextMcx->format('D, d-m-Y') . ' @ ' . $nextMcx->format('H:i') . ' hours (IST)';

    // Next FX open
    $fxNext = $now;
    if ($fxOpen) {
        // next close then reopen: after Fri 17:00 → Sun 17:00
        if ($dow === 5 && $mins < 17 * 60) {
            $fxNextLabel = 'Open now · closes Fri 17:00 IST';
        } else {
            $fxNextLabel = 'Open now';
        }
    } else {
        if ($dow === 5 || $dow === 6) {
            // after Fri close or Sat → Sunday 17:00
            $days = $dow === 5 ? 2 : 1;
            $fxNext = $now->modify('+' . $days . ' day')->setTime(17, 0, 0);
        } elseif ($dow === 0 && $mins < 17 * 60) {
            $fxNext = $now->setTime(17, 0, 0);
        } else {
            $fxNext = $now->modify('next sunday')->setTime(17, 0, 0);
        }
        $fxNextLabel = 'FX will open on ' . $fxNext->format('D, d-m-Y') . ' @ ' . $fxNext->format('H:i') . ' hours (IST)';
    }

    if ($mcxOpen && $fxOpen) {
        $label = 'Markets open · MCX & FX';
        $labelHi = 'बाज़ार खुला · MCX व FX';
    } elseif ($mcxOpen) {
        $label = 'MCX open · ' . $fxNextLabel;
        $labelHi = 'MCX खुला · FX अलग सत्र';
    } elseif ($fxOpen) {
        $label = 'FX open · MCX will open on ' . $nextMcxStr;
        $labelHi = 'FX खुला · MCX ' . $nextMcx->format('d-m-Y H:i') . ' IST';
    } else {
        $label = 'MCX will open on ' . $nextMcxStr . ' · ' . $fxNextLabel;
        $labelHi = 'MCX खुलेगा ' . $nextMcx->format('d-m-Y H:i') . ' · FX अलग';
    }

    return [
        'open' => $anyOpen,
        'mcxOpen' => $mcxOpen,
        'fxOpen' => $fxOpen,
        'label' => $label,
        'labelHi' => $labelHi,
        'hours' => 'MCX Mon–Fri 09:00–23:30 · Sat 09:00–14:00 · Sun closed · FX ≈ Sun 17:00–Fri 17:00 IST',
        'hoursHi' => 'MCX सोम–शुक्र 09:00–23:30 · शनि 09:00–14:00 · रवि बंद · FX ≈ रवि 17:00–शुक्र 17:00 IST',
        'nextOpen' => $nextMcxStr,
        'nextOpenHi' => $nextMcx->format('d-m-Y') . ' @ ' . $nextMcx->format('H:i') . ' बजे (IST)',
        'nextOpenIso' => $nextMcx->format('c'),
        'fxNextLabel' => $fxNextLabel,
    ];
}

function metals_next_open(\DateTimeImmutable $now): \DateTimeImmutable
{
    $tz = $now->getTimezone();
    $candidate = $now;
    for ($i = 0; $i < 10; $i++) {
        $dow = (int) $candidate->format('w');
        $mins = ((int) $candidate->format('G')) * 60 + (int) $candidate->format('i');
        if ($dow >= 1 && $dow <= 5) {
            if ($mins < 9 * 60) {
                return $candidate->setTime(9, 0, 0);
            }
            if ($mins < 23 * 60 + 30) {
                // currently open — next open is tomorrow 09:00 (or Mon if Fri after close handled below)
                $candidate = $candidate->modify('+1 day')->setTime(9, 0, 0);
                continue;
            }
            // after close → next weekday 09:00
            $candidate = $candidate->modify('+1 day')->setTime(9, 0, 0);
            continue;
        }
        if ($dow === 6) {
            if ($mins < 9 * 60) {
                return $candidate->setTime(9, 0, 0);
            }
            if ($mins < 14 * 60) {
                $candidate = $candidate->modify('+1 day')->setTime(9, 0, 0);
                continue;
            }
            // Saturday after close → Monday 09:00
            $candidate = $candidate->modify('+2 day')->setTime(9, 0, 0);
            continue;
        }
        // Sunday → Monday 09:00
        $candidate = $candidate->modify('+1 day')->setTime(9, 0, 0);
    }
    return $now->modify('next monday')->setTime(9, 0, 0);
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
            'lastDay'    => isset($entry['lastDay']) ? $entry['lastDay'] : null,
            'lastMonth'  => isset($entry['lastMonth']) ? $entry['lastMonth'] : null,
            'lastYear'   => isset($entry['lastYear']) ? $entry['lastYear'] : null,
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
            'lastDay'   => isset($row['lastDay']) ? $row['lastDay'] : null,
            'lastMonth' => isset($row['lastMonth']) ? $row['lastMonth'] : null,
            'lastYear'  => isset($row['lastYear']) ? $row['lastYear'] : null,
        ];
    }

    $self = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
        . '://' . preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'))
        . '/metals.php?format=json';

    return [
        'version'       => '5.0.1',
        'status'        => $status,
        'session'       => metals_session_info(),
        'sections'      => [
            'fx' => array_values($fxOut),
            'bullion' => array_values(array_filter($items, static fn($k) => in_array($k, ['gold','silver','platinum'], true), ARRAY_FILTER_USE_KEY)),
            'commodities' => array_values(array_filter($items, static fn($k) => in_array($k, ['copper','brass','zinc','steel'], true), ARRAY_FILTER_USE_KEY)),
        ],
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
        'USDINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/USDINR=X?interval=1d&range=1y',
        'EURINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/EURINR=X?interval=1d&range=1y',
        'GBPINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/GBPINR=X?interval=1d&range=1y',
        'AEDINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/AEDINR=X?interval=1d&range=1y',
        'CNYINR' => 'https://query1.finance.yahoo.com/v8/finance/chart/CNYINR=X?interval=1d&range=1y',
        'gold_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/GC=F?interval=1d&range=1y',
        'plat_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/PL=F?interval=1d&range=1y',
        'silv_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/SI=F?interval=1d&range=1y',
        'copp_f' => 'https://query1.finance.yahoo.com/v8/finance/chart/HG=F?interval=1d&range=1y',
        'frank'  => 'https://api.frankfurter.app/latest?from=USD&to=INR,EUR,GBP,CNY',
    ];

    $responses = metals_fetch_parallel($endpoints, 5);

    foreach (FX_ORDER as $pair) {
        $q = metals_parse_yahoo($responses[$pair] ?? null);
        if ($q && $q['price'] > 0) {
            $fxWork[$pair]['price']  = $q['price'];
            $fxWork[$pair]['change'] = $q['changePct'];
            $fxWork[$pair]['lastDay'] = $q['lastDay'] ?? null;
            $fxWork[$pair]['lastMonth'] = $q['lastMonth'] ?? null;
            $fxWork[$pair]['lastYear'] = $q['lastYear'] ?? null;
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
            if ($goldQ !== null) {
                $usd = (float)($fxWork['USDINR']['price'] ?? 83);
                $conv = static function ($v) use ($usd) {
                    if ($v === null || !is_numeric($v)) { return null; }
                    return round(((float) $v) * $usd * (10.0 / 31.1034768), 0);
                };
                $working['gold']['lastDay'] = $conv($goldQ['lastDay'] ?? null);
                $working['gold']['lastMonth'] = $conv($goldQ['lastMonth'] ?? null);
                $working['gold']['lastYear'] = $conv($goldQ['lastYear'] ?? null);
            }
            $liveHits++;
        }
    }

    $platQ = metals_parse_yahoo($responses['plat_f'] ?? null);
    if ($platQ && $platQ['price'] > 400) {
        $calc = round($platQ['price'] * $usdInr * (10.0 / 31.1034768) * 1.05);
        if ($calc > 20000 && $calc < 120000) {
            $working['platinum']['basePrice'] = $calc;
            $working['platinum']['change']    = $platQ['changePct'];
            if ($platQ !== null) {
                $usd = (float)($fxWork['USDINR']['price'] ?? 83);
                $conv = static function ($v) use ($usd) {
                    if ($v === null || !is_numeric($v)) { return null; }
                    return round(((float) $v) * $usd * (10.0 / 31.1034768), 0);
                };
                $working['platinum']['lastDay'] = $conv($platQ['lastDay'] ?? null);
                $working['platinum']['lastMonth'] = $conv($platQ['lastMonth'] ?? null);
                $working['platinum']['lastYear'] = $conv($platQ['lastYear'] ?? null);
            }
            $liveHits++;
        }
    }

    $silvQ = metals_parse_yahoo($responses['silv_f'] ?? null);
    if ($silvQ && $silvQ['price'] > 10) {
        $calc = round($silvQ['price'] * $usdInr * (1000.0 / 31.1034768) * 1.08);
        if ($calc > 120000 && $calc < 450000) {
            $working['silver']['basePrice'] = $calc;
            $working['silver']['change']    = $silvQ['changePct'];
            if ($silvQ !== null) {
                $usd = (float)($fxWork['USDINR']['price'] ?? 83);
                $conv = static function ($v) use ($usd) {
                    if ($v === null || !is_numeric($v)) { return null; }
                    return round(((float) $v) * $usd * (1000.0 / 31.1034768), 0);
                };
                $working['silver']['lastDay'] = $conv($silvQ['lastDay'] ?? null);
                $working['silver']['lastMonth'] = $conv($silvQ['lastMonth'] ?? null);
                $working['silver']['lastYear'] = $conv($silvQ['lastYear'] ?? null);
            }
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
            if ($coppQ !== null) {
                $usd = (float)($fxWork['USDINR']['price'] ?? 83);
                $conv = static function ($v) use ($usd) {
                    if ($v === null || !is_numeric($v)) { return null; }
                    // USD/lb → INR/kg approx
                    return round(((float) $v) * $usd * 2.20462, 2);
                };
                $working['copper']['lastDay'] = $conv($coppQ['lastDay'] ?? null);
                $working['copper']['lastMonth'] = $conv($coppQ['lastMonth'] ?? null);
                $working['copper']['lastYear'] = $conv($coppQ['lastYear'] ?? null);
            }
            $working['brass']['change']     = $coppQ['changePct'];
            $liveHits++;
        }
    }

    $session = metals_session_info();
    if (empty($session['mcxOpen']) && empty($session['fxOpen'])) {
        $status = 'closed';
    } else {
        $status = $liveHits >= 4 ? 'live' : ($liveHits > 0 ? 'partial' : 'cached');
    }
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
        if ($data['status'] === 'CLOSED') {
            $data['status'] = 'CLOSED';
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
        'fx' => 'FX', 'fxSub' => 'INR crosses',
        'bullion' => 'Bullion', 'bullionSub' => 'Gold · Silver · Platinum (GST incl.)',
        'commodities' => 'Commodities', 'commoditiesSub' => 'Base metals · MCX / Delhi spot',
        'incl' => 'Incl. GST', 'ex' => 'Ex-GST', 'asof' => 'Market time',
        'refresh' => 'Refresh', 'json' => 'JSON', 'live' => 'Live',
        'partial' => 'Partial', 'cached' => 'Cached', 'closed' => 'Markets closed',
        'hoursTitle' => 'Trading hours', 'lastDay' => 'Last day', 'lastMonth' => 'Last month', 'lastYear' => 'Last year',
        'price' => 'Price', 'change' => 'Change', 'unit' => 'Unit',
    ],
    'hi' => [
        'title' => 'बाज़ार', 'sub' => 'दिल्ली NCR · MCX · INR मुद्रा',
        'fx' => 'मुद्रा', 'fxSub' => 'INR क्रॉस',
        'bullion' => 'बुल्लियन', 'bullionSub' => 'सोना · चाँदी · प्लैटिनम (GST सहित)',
        'commodities' => 'कमोडिटी', 'commoditiesSub' => 'बेस मेटल · MCX / दिल्ली स्पॉट',
        'incl' => 'GST सहित', 'ex' => 'बिना GST', 'asof' => 'बाज़ार समय',
        'refresh' => 'रिफ्रेश', 'json' => 'JSON', 'live' => 'लाइव',
        'partial' => 'आंशिक', 'cached' => 'कैश', 'closed' => 'बाज़ार बंद',
        'hoursTitle' => 'व्यापार समय', 'lastDay' => 'कल', 'lastMonth' => 'गत माह', 'lastYear' => 'गत वर्ष',
        'price' => 'मूल्य', 'change' => 'परिवर्तन', 'unit' => 'इकाई',
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

$statusKey = strtolower((string) ($data['status'] ?? 'cached'));
$session   = is_array($data['session'] ?? null) ? $data['session'] : metals_session_info();
if (!$session['open'] && $statusKey === 'live') {
    $statusKey = 'closed';
}
$statusLbl = $t[$statusKey] ?? $t['cached'];
if ($statusKey === 'closed' || empty($session['open'])) {
    $statusKey = 'closed';
    $statusLbl = $lang === 'hi'
        ? (string)($session['labelHi'] ?? ($t['closed'] ?? 'बाज़ार बंद'))
        : (string)($session['label'] ?? ($t['closed'] ?? 'Markets closed'));
}
$asOf      = ($data['formattedTime'] ?? '') . ' IST · ' . ($data['formattedDate'] ?? '');
$hoursLine = $lang === 'hi' ? (string)($session['hoursHi'] ?? $session['hours'] ?? '') : (string)($session['hours'] ?? '');
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
<meta name="description" content="Delhi NCR MCX commodity prices and INR FX rates.">
<link rel="stylesheet" href="/assets/fonts/rc-fonts.css">
<style>
:root{--bg:#f0f3f7;--card:#fff;--card-border:#e3e8ef;--ink:#0f172a;--muted:#64748b;--line:#e8edf3;--up:#16a34a;--up-bg:#dcfce7;--down:#dc2626;--down-bg:#fee2e2;--flat:#64748b;--accent:#2563eb;--header:#0f172a;--shadow:0 1px 2px rgba(15,23,42,.06),0 4px 16px rgba(15,23,42,.04);--radius:14px;--font:system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans Devanagari",sans-serif;--mono:ui-monospace,"Cascadia Code","SF Mono",Menlo,monospace}
html[data-theme="dark"]{--bg:#0b1220;--card:#111827;--card-border:#1f2a3a;--ink:#f1f5f9;--muted:#94a3b8;--line:#1e293b;--up:#4ade80;--up-bg:rgba(22,163,74,.18);--down:#f87171;--down-bg:rgba(220,38,38,.18);--flat:#94a3b8;--accent:#60a5fa;--header:#f8fafc;--shadow:0 1px 2px rgba(0,0,0,.35),0 8px 24px rgba(0,0,0,.25)}
*{box-sizing:border-box}html,body{margin:0}
body{font-family:var(--font);background:var(--bg);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased}
.wrap{max-width:1100px;margin:0 auto;padding:16px 14px 28px}
.top{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
.top h1{font-size:1.35rem;font-weight:700;letter-spacing:-.03em;margin:0;color:var(--header)}
.top .sub{margin:2px 0 0;font-size:12.5px;color:var(--muted);font-weight:500}
.tools{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.chip{display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;padding:6px 11px;border-radius:999px;background:var(--card);border:1px solid var(--card-border);color:var(--ink);text-decoration:none}
.chip.ghost{background:transparent}.chip.live{background:var(--up-bg);color:var(--up);border-color:transparent}
.chip.partial{background:#fef3c7;color:#b45309;border-color:transparent}
.chip.closed{background:#e2e8f0;color:#475569;border-color:transparent}
html[data-theme="dark"] .chip.closed{background:#334155;color:#cbd5e1}
.hours-bar{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;margin:0 0 14px;padding:10px 14px;border-radius:12px;background:var(--card);border:1px solid var(--card-border);font-size:12.5px;color:var(--muted)}
.hours-bar strong{color:var(--ink);font-weight:700}
.hours-bar .dot{width:8px;height:8px;border-radius:50%;background:#94a3b8}
.hours-bar.open .dot{background:var(--up);box-shadow:0 0 0 3px var(--up-bg)}
.hours-bar.closed .dot{background:#94a3b8}
.hist{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px}
.hist span{font-size:10px;font-weight:600;font-variant-numeric:tabular-nums;padding:2px 6px;border-radius:6px;background:color-mix(in srgb,var(--muted) 10%,transparent);color:var(--muted);white-space:nowrap}
.hist span b{color:var(--ink);font-weight:700;margin-left:3px}

html[data-theme="dark"] .chip.partial{background:rgba(180,83,9,.25);color:#fbbf24}
.asof-inline{font-variant-numeric:tabular-nums;opacity:.9}.hours-detail{opacity:.85}.hours-bar{flex-wrap:wrap}.px-stack{display:flex;flex-direction:column;align-items:flex-end;gap:2px}.px-stack .pct{font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}.px-stack .pct.up{color:var(--up)}.px-stack .pct.down{color:var(--down)}.px-stack .pct.flat{color:var(--flat)}.ref-stack{display:flex;flex-direction:column;align-items:flex-end;gap:2px}.ref-stack .ref-px{font-size:12.5px;font-weight:600;font-variant-numeric:tabular-nums;font-family:var(--mono);color:var(--muted)}.ref-stack .ref-pct{font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}.ref-stack .ref-pct.up{color:var(--up)}.ref-stack .ref-pct.down{color:var(--down)}.ref-stack .ref-pct.flat{color:var(--flat)}.ref-stack.empty .ref-px,.ref-stack.empty .ref-pct{opacity:.35}
.mkt-sec{background:var(--card);border:1px solid var(--card-border);border-radius:var(--radius);box-shadow:var(--shadow);margin-bottom:14px;overflow:hidden}
.mkt-sec-hd{display:flex;align-items:center;gap:12px;padding:12px 16px;border-bottom:1px solid var(--line)}
.mkt-sec-hd .ico{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;font-size:18px;flex:none}
.mkt-sec-hd h2{margin:0;font-size:15px;font-weight:700;letter-spacing:-.01em}
.mkt-sec-hd p{margin:1px 0 0;font-size:11.5px;color:var(--muted);font-weight:500}
.mkt-sec.fx .ico{background:linear-gradient(135deg,#dbeafe,#eff6ff)}
.mkt-sec.bullion .ico{background:linear-gradient(135deg,#fef3c7,#fffbeb)}
.mkt-sec.commodity .ico{background:linear-gradient(135deg,#ffedd5,#fff7ed)}
html[data-theme="dark"] .mkt-sec.fx .ico{background:linear-gradient(135deg,#1e3a5f,#172554)}
html[data-theme="dark"] .mkt-sec.bullion .ico{background:linear-gradient(135deg,#422006,#451a03)}
html[data-theme="dark"] .mkt-sec.commodity .ico{background:linear-gradient(135deg,#431407,#7c2d12)}
.mkt-cols{display:grid;grid-template-columns:minmax(140px,1.6fr) minmax(100px,.9fr) minmax(88px,.8fr) minmax(88px,.8fr) minmax(88px,.8fr);gap:6px;padding:8px 14px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border-bottom:1px solid var(--line);background:color-mix(in srgb,var(--bg) 65%,var(--card))}
.mkt-cols span:not(:first-child){text-align:right}
.mkt-row{display:grid;grid-template-columns:minmax(140px,1.6fr) minmax(100px,.9fr) minmax(88px,.8fr) minmax(88px,.8fr) minmax(88px,.8fr);gap:6px;align-items:center;padding:12px 14px;border-bottom:1px solid var(--line);position:relative;transition:background .12s ease}
.mkt-row .ref{text-align:right;font-size:12.5px;font-weight:600;font-variant-numeric:tabular-nums;font-family:var(--mono);color:var(--muted)}
.mkt-row .ref.empty{opacity:.35}
.mkt-row:last-child{border-bottom:none}
.mkt-row:hover{background:color-mix(in srgb,var(--accent) 5%,transparent)}
.mkt-row::before{content:"";position:absolute;left:0;top:18%;bottom:18%;width:3px;border-radius:0 3px 3px 0;background:var(--row-accent,transparent);opacity:.9}
.mkt-row .sym{display:flex;align-items:center;gap:12px;min-width:0}
.mkt-row .badge{width:38px;height:38px;border-radius:11px;flex:none;display:grid;place-items:center;font-size:13px;font-weight:800;color:#fff;letter-spacing:-.02em;box-shadow:inset 0 0 0 1px rgba(255,255,255,.12)}
.mkt-row .nm{font-size:14px;font-weight:700;letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mkt-row .meta{font-size:11.5px;color:var(--muted);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mkt-row .px{text-align:right;font-size:15px;font-weight:700;font-variant-numeric:tabular-nums;font-family:var(--mono);letter-spacing:-.02em}
.mkt-row .ch{text-align:right;font-size:12.5px;font-weight:700;font-variant-numeric:tabular-nums;justify-self:end;padding:4px 9px;border-radius:8px;white-space:nowrap}
.mkt-row .ch.up{color:var(--up);background:var(--up-bg)}
.mkt-row .ch.down{color:var(--down);background:var(--down-bg)}
.mkt-row .ch.flat{color:var(--flat);background:color-mix(in srgb,var(--muted) 12%,transparent)}
.foot{display:flex;flex-wrap:wrap;gap:8px 16px;align-items:center;justify-content:space-between;margin-top:8px;padding:12px 4px;font-size:11.5px;color:var(--muted)}
.foot a{color:var(--accent);text-decoration:none;font-weight:600}
.foot code{font-size:10.5px;opacity:.85;word-break:break-all}
@media (max-width:720px){
  .mkt-cols{display:none}
  .mkt-row{grid-template-columns:minmax(0,1fr) auto;grid-template-areas:"sym px" "sym ch" "ref ref";gap:4px 10px;padding:12px 14px 12px 16px}
  .mkt-row .sym{grid-area:sym}.mkt-row .px{grid-area:px;font-size:14px}.mkt-row .ch{grid-area:ch;font-size:11.5px;padding:3px 7px}
  .mkt-row .ref{display:inline;font-size:11px;margin-right:8px}
  .mkt-row .ref::before{content:attr(data-lbl) " ";font-size:9px;text-transform:uppercase;opacity:.7}
  .mkt-row .refs-mobile{grid-area:ref;display:flex;flex-wrap:wrap;gap:4px 10px}
}
@media (min-width:721px){ .mkt-row .refs-mobile{display:contents} }

<?= $embed ? 'body{min-height:auto;background:transparent}.wrap{padding:4px 2px 12px}.top h1,.top .sub{display:none!important}.top{margin-bottom:6px}' : '' ?>
</style>
</head>
<body>
<?php if ($embed): ?><meta http-equiv="refresh" content="300"><?php endif; ?>
<div class="wrap">
  <header class="top">
    <div>
      <h1><?= htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="sub"><?= htmlspecialchars($t['sub'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <div class="tools">
      <span class="chip <?= $statusKey === 'live' ? 'live' : ($statusKey === 'partial' ? 'partial' : ($statusKey === 'closed' ? 'closed' : '')) ?>"><?= htmlspecialchars($statusLbl, ENT_QUOTES, 'UTF-8') ?></span>
      <a class="chip ghost" href="<?= htmlspecialchars($selfPath . (str_contains($selfPath, '?') ? '&' : '?') . 'refresh=1', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['refresh'], ENT_QUOTES, 'UTF-8') ?></a>
      <a class="chip ghost" href="<?= htmlspecialchars($jsonUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($t['json'], ENT_QUOTES, 'UTF-8') ?></a>
      <a class="chip ghost" href="?<?= $embed ? 'embed=1&' : '' ?>theme=<?= htmlspecialchars($theme, ENT_QUOTES, 'UTF-8') ?>&lang=<?= $lang === 'hi' ? 'en' : 'hi' ?>"><?= $lang === 'hi' ? 'EN' : 'हिंदी' ?></a>
    </div>
  </header>
  <div class="hours-bar <?= !empty($session['open']) ? 'open' : 'closed' ?>">
    <span class="dot" aria-hidden="true"></span>
    <strong><?= htmlspecialchars($lang === 'hi' ? (string)($session['labelHi'] ?? $statusLbl) : (string)($session['label'] ?? $statusLbl), ENT_QUOTES, 'UTF-8') ?></strong>
    <span class="asof-inline"><?= htmlspecialchars($t['asof'] . ' · ' . $asOf, ENT_QUOTES, 'UTF-8') ?></span>
    <span class="hours-detail"><?= htmlspecialchars($hoursLine ?? '', ENT_QUOTES, 'UTF-8') ?></span>
  </div>
<?php
$assetUi = [
    'USDINR' => ['color' => '#2563eb', 'letter' => '$'],
    'EURINR' => ['color' => '#4f46e5', 'letter' => '€'],
    'GBPINR' => ['color' => '#7c3aed', 'letter' => '£'],
    'AEDINR' => ['color' => '#0d9488', 'letter' => 'د'],
    'CNYINR' => ['color' => '#dc2626', 'letter' => '¥'],
    'gold' => ['color' => '#d97706', 'letter' => 'Au'],
    'silver' => ['color' => '#64748b', 'letter' => 'Ag'],
    'platinum' => ['color' => '#78716c', 'letter' => 'Pt'],
    'copper' => ['color' => '#c2410c', 'letter' => 'Cu'],
    'brass' => ['color' => '#ca8a04', 'letter' => 'Br'],
    'zinc' => ['color' => '#57534e', 'letter' => 'Zn'],
    'steel' => ['color' => '#334155', 'letter' => 'Fe'],
];
$bullionKeys = ['gold', 'silver', 'platinum'];
$commodityKeys = ['copper', 'brass', 'zinc', 'steel'];
$pctOf = static function ($cur, $ref): array {
    if ($ref === null || $ref === '' || !is_numeric($ref) || (float) $ref == 0.0) {
        return ['—', 'flat', true];
    }
    if ($cur === null || !is_numeric($cur)) {
        return ['—', 'flat', true];
    }
    $p = (((float) $cur - (float) $ref) / (float) $ref) * 100.0;
    $cls = $p > 0.005 ? 'up' : ($p < -0.005 ? 'down' : 'flat');
    $sign = $p > 0.005 ? '+' : '';
    return [$sign . number_format($p, 2) . '%', $cls, false];
};
$money2 = static function ($v): array {
    if ($v === null || $v === '' || !is_numeric($v)) {
        return ['—', true];
    }
    return [gf_inr($v, 2), false];
};
$cols = static function (array $t, string $sec) {
    return '<div class="mkt-cols" aria-hidden="true"><span>'
        . htmlspecialchars($t[$sec] ?? $sec, ENT_QUOTES, 'UTF-8') . '</span><span>'
        . htmlspecialchars($t['price'], ENT_QUOTES, 'UTF-8') . '</span><span>'
        . htmlspecialchars($t['lastDay'] ?? 'Last day', ENT_QUOTES, 'UTF-8') . '</span><span>'
        . htmlspecialchars($t['lastMonth'] ?? 'Last month', ENT_QUOTES, 'UTF-8') . '</span><span>'
        . htmlspecialchars($t['lastYear'] ?? 'Last year', ENT_QUOTES, 'UTF-8') . '</span></div>';
};
?>

  <section class="mkt-sec fx" aria-labelledby="sec-fx">
    <div class="mkt-sec-hd">
      <div class="ico" aria-hidden="true">💱</div>
      <div><h2 id="sec-fx"><?= htmlspecialchars($t['fx'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($t['fxSub'], ENT_QUOTES, 'UTF-8') ?></p></div>
    </div>
    <?= $cols($t, 'fx') ?>
<?php foreach (FX_ORDER as $pair):
    $row = $data['fx'][$pair] ?? null;
    if (!$row) { continue; }
    $ui = $assetUi[$pair] ?? ['color' => '#64748b', 'letter' => '?'];
    $nm = $lang === 'hi' ? (string)($row['nameHi'] ?? $row['name'] ?? $pair) : (string)($row['name'] ?? $pair);
    $meta = $pair . ' · ' . (string)($row['symbol'] ?? 'INR');
    $curPx = $row['price'] ?? 0;
    [$pxLbl] = $money2($curPx);
    [$ldPx, $ldE] = $money2($row['lastDay'] ?? null);
    [$lmPx, $lmE] = $money2($row['lastMonth'] ?? null);
    [$lyPx, $lyE] = $money2($row['lastYear'] ?? null);
    [$ldPct, $ldCls] = $pctOf($curPx, $row['lastDay'] ?? null);
    [$lmPct, $lmCls] = $pctOf($curPx, $row['lastMonth'] ?? null);
    [$lyPct, $lyCls] = $pctOf($curPx, $row['lastYear'] ?? null);
?>
    <div class="mkt-row" role="row" style="--row-accent:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="sym">
        <span class="badge" style="background:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ui['letter'], ENT_QUOTES, 'UTF-8') ?></span>
        <div><div class="nm"><?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?></div><div class="meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="px-stack"><div class="px"><?= htmlspecialchars($pxLbl, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $ldE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($ldPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($ldCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ldPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lmE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lmPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lmCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lmPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lyE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lyPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lyCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lyPct, ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
<?php endforeach; ?>
  </section>

  <section class="mkt-sec bullion" aria-labelledby="sec-bullion">
    <div class="mkt-sec-hd">
      <div class="ico" aria-hidden="true">🏅</div>
      <div><h2 id="sec-bullion"><?= htmlspecialchars($t['bullion'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($t['bullionSub'], ENT_QUOTES, 'UTF-8') ?></p></div>
    </div>
    <?= $cols($t, 'bullion') ?>
<?php foreach ($bullionKeys as $key):
    $row = $data['items'][$key] ?? null;
    if (!$row) { continue; }
    $ui = $assetUi[$key] ?? ['color' => '#64748b', 'letter' => '?'];
    $nm = $lang === 'hi' ? (string)($row['nameHi'] ?? $row['name'] ?? $key) : (string)($row['name'] ?? $key);
    $unit = (string)($row['unit'] ?? '');
    $meta = $t['incl'] . ' · ' . $unit . ' · GST ' . (int)($row['gstPct'] ?? 0) . '%';
    $curPx = $row['totalPrice'] ?? 0;
    [$pxLbl] = $money2($curPx);
    [$ldPx, $ldE] = $money2($row['lastDay'] ?? null);
    [$lmPx, $lmE] = $money2($row['lastMonth'] ?? null);
    [$lyPx, $lyE] = $money2($row['lastYear'] ?? null);
    [$ldPct, $ldCls] = $pctOf($curPx, $row['lastDay'] ?? null);
    [$lmPct, $lmCls] = $pctOf($curPx, $row['lastMonth'] ?? null);
    [$lyPct, $lyCls] = $pctOf($curPx, $row['lastYear'] ?? null);
?>
    <div class="mkt-row" role="row" style="--row-accent:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="sym">
        <span class="badge" style="background:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ui['letter'], ENT_QUOTES, 'UTF-8') ?></span>
        <div><div class="nm"><?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?></div><div class="meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="px-stack"><div class="px"><?= htmlspecialchars($pxLbl, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $ldE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($ldPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($ldCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ldPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lmE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lmPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lmCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lmPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lyE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lyPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lyCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lyPct, ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
<?php endforeach; ?>
  </section>

  <section class="mkt-sec commodity" aria-labelledby="sec-cmdty">
    <div class="mkt-sec-hd">
      <div class="ico" aria-hidden="true">🏗️</div>
      <div><h2 id="sec-cmdty"><?= htmlspecialchars($t['commodities'], ENT_QUOTES, 'UTF-8') ?></h2><p><?= htmlspecialchars($t['commoditiesSub'], ENT_QUOTES, 'UTF-8') ?></p></div>
    </div>
    <?= $cols($t, 'commodities') ?>
<?php foreach ($commodityKeys as $key):
    $row = $data['items'][$key] ?? null;
    if (!$row) { continue; }
    $ui = $assetUi[$key] ?? ['color' => '#64748b', 'letter' => '?'];
    $nm = $lang === 'hi' ? (string)($row['nameHi'] ?? $row['name'] ?? $key) : (string)($row['name'] ?? $key);
    $unit = (string)($row['unit'] ?? '');
    $meta = $t['incl'] . ' · ' . $unit . ' · GST ' . (int)($row['gstPct'] ?? 0) . '%';
    $curPx = $row['totalPrice'] ?? 0;
    [$pxLbl] = $money2($curPx);
    [$ldPx, $ldE] = $money2($row['lastDay'] ?? null);
    [$lmPx, $lmE] = $money2($row['lastMonth'] ?? null);
    [$lyPx, $lyE] = $money2($row['lastYear'] ?? null);
    [$ldPct, $ldCls] = $pctOf($curPx, $row['lastDay'] ?? null);
    [$lmPct, $lmCls] = $pctOf($curPx, $row['lastMonth'] ?? null);
    [$lyPct, $lyCls] = $pctOf($curPx, $row['lastYear'] ?? null);
?>
    <div class="mkt-row" role="row" style="--row-accent:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>">
      <div class="sym">
        <span class="badge" style="background:<?= htmlspecialchars($ui['color'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ui['letter'], ENT_QUOTES, 'UTF-8') ?></span>
        <div><div class="nm"><?= htmlspecialchars($nm, ENT_QUOTES, 'UTF-8') ?></div><div class="meta"><?= htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') ?></div></div>
      </div>
      <div class="px-stack"><div class="px"><?= htmlspecialchars($pxLbl, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $ldE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($ldPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($ldCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($ldPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lmE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lmPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lmCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lmPct, ENT_QUOTES, 'UTF-8') ?></div></div>
      <div class="ref-stack<?= $lyE ? ' empty' : '' ?>"><div class="ref-px"><?= htmlspecialchars($lyPx, ENT_QUOTES, 'UTF-8') ?></div><div class="ref-pct <?= htmlspecialchars($lyCls, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($lyPct, ENT_QUOTES, 'UTF-8') ?></div></div>
    </div>
<?php endforeach; ?>
  </section>

  <footer class="foot">
    <a href="<?= htmlspecialchars((string)(CORPORATE_BRAND['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string)(CORPORATE_BRAND['text'] ?? ''), ENT_QUOTES, 'UTF-8') ?></a>
    <code class="api">API <?= htmlspecialchars(($data['corporate']['apiSelf'] ?? $jsonUrl), ENT_QUOTES, 'UTF-8') ?></code>
  </footer>
</div>
</body>
</html>
