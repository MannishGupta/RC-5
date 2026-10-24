<?php
// Version: 3.2.42
declare(strict_types=1);

/**
 * Minimal Google Sheets API v4 client using a service-account JWT.
 * No Composer. Requires openssl + allow_url_fopen or curl.
 */
final class GoogleSheetsClient
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets.readonly';

    /**
     * Load SA credentials from tenant or shared config.
     * @return array{client_email:string,private_key:string}|null
     */
    public static function credentials(): ?array
    {
        $candidates = [];
        if (defined('DATA_PATH')) {
            $candidates[] = rtrim((string)DATA_PATH, '/\\') . '/config/google_sheets_sa.json';
            $candidates[] = rtrim((string)DATA_PATH, '/\\') . '/google_sheets_sa.json';
            $candidates[] = rtrim((string)DATA_PATH, '/\\') . '/config/google_sheets_config.php';
        }
        if (defined('BASE_PATH')) {
            // Intentionally no global BASE_PATH fallback when multi-tenant DATA_PATH is set
        }
        foreach ($candidates as $path) {
            if (!is_file($path)) {
                continue;
            }
            if (str_ends_with(strtolower($path), '.php')) {
                $cfg = @include $path;
                if (is_array($cfg)) {
                    if (!empty($cfg['service_account_json']) && is_string($cfg['service_account_json'])) {
                        $j = json_decode($cfg['service_account_json'], true);
                        if (is_array($j)) {
                            return self::normalizeSa($j);
                        }
                    }
                    if (!empty($cfg['client_email']) && !empty($cfg['private_key'])) {
                        return self::normalizeSa($cfg);
                    }
                    if (!empty($cfg['json_path']) && is_file((string)$cfg['json_path'])) {
                        $j = json_decode((string)@file_get_contents((string)$cfg['json_path']), true);
                        if (is_array($j)) {
                            return self::normalizeSa($j);
                        }
                    }
                }
                continue;
            }
            $j = json_decode((string)@file_get_contents($path), true);
            if (is_array($j)) {
                return self::normalizeSa($j);
            }
        }
        return null;
    }

    /** @param array<string,mixed> $j */
    private static function normalizeSa(array $j): ?array
    {
        $email = trim((string)($j['client_email'] ?? ''));
        $key = (string)($j['private_key'] ?? '');
        $key = str_replace(['\\n', "\r\n"], "\n", $key);
        if ($email === '' || $key === '' || !str_contains($key, 'PRIVATE KEY')) {
            return null;
        }
        return ['client_email' => $email, 'private_key' => $key];
    }

    public static function accessToken(bool $force = false): ?string
    {
        $sa = self::credentials();
        if ($sa === null) {
            return null;
        }
        $cacheDir = defined('DATA_PATH') ? rtrim((string)DATA_PATH, '/\\') . '/cache' : sys_get_temp_dir();
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }
        $cacheFile = $cacheDir . '/google_sheets_token.json';
        if (!$force && is_file($cacheFile)) {
            $c = json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($c) && !empty($c['access_token']) && (int)($c['exp'] ?? 0) > time() + 60) {
                return (string)$c['access_token'];
            }
        }
        $now = time();
        $header = self::b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_UNESCAPED_SLASHES));
        $claim = self::b64url(json_encode([
            'iss' => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_UNESCAPED_SLASHES));
        $unsigned = $header . '.' . $claim;
        $sig = '';
        $ok = openssl_sign($unsigned, $sig, $sa['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            return null;
        }
        $jwt = $unsigned . '.' . self::b64url($sig);
        $body = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);
        $res = self::httpPost(self::TOKEN_URL, $body, 'application/x-www-form-urlencoded');
        if ($res === null) {
            return null;
        }
        $j = json_decode($res, true);
        if (!is_array($j) || empty($j['access_token'])) {
            return null;
        }
        $token = (string)$j['access_token'];
        $exp = $now + (int)($j['expires_in'] ?? 3600) - 30;
        @file_put_contents($cacheFile, json_encode(['access_token' => $token, 'exp' => $exp], JSON_UNESCAPED_SLASHES), LOCK_EX);
        return $token;
    }

    /**
     * @return list<list<string>>|null
     */
    public static function values(string $spreadsheetId, string $range = 'A:Z'): ?array
    {
        $spreadsheetId = preg_replace('/[^a-zA-Z0-9\-_]/', '', $spreadsheetId) ?? '';
        if ($spreadsheetId === '') {
            return null;
        }
        $token = self::accessToken();
        if ($token === null) {
            return null;
        }
        $range = $range !== '' ? $range : 'A:Z';
        $url = 'https://sheets.googleapis.com/v4/spreadsheets/'
            . rawurlencode($spreadsheetId)
            . '/values/'
            . rawurlencode($range)
            . '?valueRenderOption=UNFORMATTED_VALUE&dateTimeRenderOption=FORMATTED_STRING';
        $raw = self::httpGet($url, $token);
        if ($raw === null) {
            return null;
        }
        $j = json_decode($raw, true);
        if (!is_array($j) || !isset($j['values']) || !is_array($j['values'])) {
            return null;
        }
        $out = [];
        foreach ($j['values'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = array_map(static fn($c) => is_scalar($c) ? (string)$c : '', $row);
        }
        return $out;
    }

    /** Canonical path for this tenant's service-account JSON */
    public static function saJsonPath(): string
    {
        $base = defined('DATA_PATH') ? rtrim((string)DATA_PATH, '/\\') : (defined('BASE_PATH') ? BASE_PATH . '/data' : __DIR__ . '/../data');
        $dir = $base . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/google_sheets_sa.json';
    }

    /**
     * Validate + store SA JSON. Never returns private_key to caller.
     * @return array{ok:bool,message:string,client_email?:string}
     */
    public static function saveSaJson(string $raw): array
    {
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return ['ok' => false, 'message' => 'File is not valid JSON.'];
        }
        $type = (string)($j['type'] ?? '');
        $email = trim((string)($j['client_email'] ?? ''));
        $key = (string)($j['private_key'] ?? '');
        if ($type !== 'service_account') {
            return ['ok' => false, 'message' => 'JSON must be a Google service_account key (type field).'];
        }
        if ($email === '' || !str_contains($email, '@')) {
            return ['ok' => false, 'message' => 'client_email missing in JSON.'];
        }
        if ($key === '' || !str_contains($key, 'BEGIN')) {
            return ['ok' => false, 'message' => 'private_key missing in JSON.'];
        }
        $path = self::saJsonPath();
        $ok = false !== @file_put_contents($path, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if (!$ok) {
            return ['ok' => false, 'message' => 'Could not write ' . basename(dirname($path)) . '/google_sheets_sa.json (permissions).'];
        }
        @chmod($path, 0600);
        return ['ok' => true, 'message' => 'Service account saved.', 'client_email' => $email];
    }

    public static function status(): array
    {
        $sa = self::credentials();
        if ($sa === null) {
            return [
                'ok' => false,
                'configured' => false,
                'message' => 'Service account not configured. Upload google_sheets_sa.json under the tenant data/config folder.',
            ];
        }
        $token = self::accessToken();
        if ($token === null) {
            return [
                'ok' => false,
                'configured' => true,
                'client_email' => $sa['client_email'],
                'message' => 'Could not obtain access token. Check private key and that the Sheets API is enabled on the Google Cloud project.',
            ];
        }
        return [
            'ok' => true,
            'configured' => true,
            'client_email' => $sa['client_email'],
            'message' => 'Connected. Share each spreadsheet with this email as Viewer.',
        ];
    }

    private static function b64url(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function httpGet(string $url, string $bearer): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $bearer,
                    'Accept: application/json',
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch); /* curl handle auto-closed (PHP 8+) */
            return ($res !== false && $code >= 200 && $code < 300) ? (string)$res : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$bearer}\r\nAccept: application/json\r\n",
                'timeout' => 20,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return ($res !== false) ? (string)$res : null;
    }

    private static function httpPost(string $url, string $body, string $contentType): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Content-Type: ' . $contentType],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch); /* curl handle auto-closed (PHP 8+) */
            return ($res !== false && $code >= 200 && $code < 300) ? (string)$res : null;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: {$contentType}\r\n",
                'content' => $body,
                'timeout' => 20,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return ($res !== false) ? (string)$res : null;
    }
}
