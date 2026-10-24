<?php
// Version: 3.2.44
declare(strict_types=1);

/**
 * OpenAI-compatible chat client (OpenAI, xAI Grok, Azure OpenAI, Ollama, Groq, etc.).
 * Keys stay in tenant data/config — never committed to the repo.
 */
final class AiClient
{
    public static function configPath(): string
    {
        $base = defined('DATA_PATH') ? rtrim((string)DATA_PATH, '/\\') : (defined('BASE_PATH') ? BASE_PATH . '/data' : dirname(__DIR__) . '/data');
        $dir = $base . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/ai.json';
    }

    /** @return array<string,mixed> */
    public static function config(): array
    {
        $def = [
            'enabled' => false,
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'model' => 'gpt-4o-mini',
            'api_key' => '',
            'max_tokens' => 1200,
            'temperature' => 0.4,
            'system_prompt' => 'You are the Resource Centre (RC) workplace AI for an Indian multi-tenant organisation portal. Scope: sales MIS, media kit copy, HR directory summaries (non-sensitive), construction status language, commodity market commentary, Choghadiya/panchang plain-language help, email/WhatsApp drafts, compliance-aware wording (DPDP — do not invent personal data). Rules: (1) Use only facts from Tenant context or the user message — never invent names, phones, blood groups, bank accounts, or revenue figures. (2) Prefer INR, IST, metric units, clear bullets. (3) For medical/legal/financial advice, state educational limits. (4) Write in the user language (en-IN or hi-IN) when asked. (5) Be concise and actionable.',
        ];
        $path = self::configPath();
        if (!is_file($path)) {
            return $def;
        }
        $j = json_decode((string)@file_get_contents($path), true);
        if (!is_array($j)) {
            return $def;
        }
        return array_merge($def, $j);
    }

    /** @param array<string,mixed> $cfg */
    public static function saveConfig(array $cfg): bool
    {
        $cur = self::config();
        $out = [
            'enabled' => !empty($cfg['enabled']),
            'provider' => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($cfg['provider'] ?? 'openai'))) ?: 'openai',
            'base_url' => rtrim(trim((string)($cfg['base_url'] ?? $cur['base_url'])), '/'),
            'model' => trim((string)($cfg['model'] ?? $cur['model'])),
            'api_key' => array_key_exists('api_key', $cfg) ? trim((string)$cfg['api_key']) : (string)($cur['api_key'] ?? ''),
            'max_tokens' => max(256, min(4096, (int)($cfg['max_tokens'] ?? 1200))),
            'temperature' => max(0.0, min(1.5, (float)($cfg['temperature'] ?? 0.4))),
            'system_prompt' => trim((string)($cfg['system_prompt'] ?? $cur['system_prompt'])),
            'updated_at' => date('c'),
        ];
        if ($out['api_key'] === '' && !empty($cur['api_key'])) {
            $out['api_key'] = (string)$cur['api_key'];
        }
        // Provider presets (OpenAI-compatible chat/completions)
        $prov = (string)($out['provider'] ?? '');
        if ($prov === 'openai' && ($out['base_url'] === '' || str_contains($out['base_url'], 'example'))) {
            $out['base_url'] = 'https://api.openai.com/v1';
        }
        if ($prov === 'gemini') {
            // Google AI Studio — OpenAI-compatible endpoint
            if ($out['base_url'] === '' || str_contains($out['base_url'], 'openai.com') || str_contains($out['base_url'], 'x.ai') || str_contains($out['base_url'], 'groq')) {
                $out['base_url'] = 'https://generativelanguage.googleapis.com/v1beta/openai';
            }
            if ($out['model'] === '' || $out['model'] === 'gpt-4o-mini') {
                $out['model'] = 'gemini-2.0-flash';
            }
        }
        if ($prov === 'xai') {
            if ($out['base_url'] === '' || str_contains($out['base_url'], 'openai.com')) {
                $out['base_url'] = 'https://api.x.ai/v1';
            }
            if ($out['model'] === '' || $out['model'] === 'gpt-4o-mini') {
                $out['model'] = 'grok-2-latest';
            }
        }
        if ($prov === 'groq') {
            if ($out['base_url'] === '' || str_contains($out['base_url'], 'openai.com')) {
                $out['base_url'] = 'https://api.groq.com/openai/v1';
            }
            if ($out['model'] === '' || $out['model'] === 'gpt-4o-mini') {
                $out['model'] = 'llama-3.3-70b-versatile';
            }
        }
        if ($prov === 'ollama') {
            if ($out['base_url'] === '' || str_contains($out['base_url'], 'openai.com')) {
                $out['base_url'] = 'http://127.0.0.1:11434/v1';
            }
            if ($out['model'] === '' || $out['model'] === 'gpt-4o-mini') {
                $out['model'] = 'llama3.2';
            }
            if ($out['api_key'] === '') {
                $out['api_key'] = 'ollama'; // many OpenAI clients expect a non-empty Bearer token
            }
        }
        return false !== @file_put_contents(self::configPath(), json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function isReady(): bool
    {
        $c = self::config();
        if (empty($c['enabled']) || trim((string)($c['base_url'] ?? '')) === '') {
            return false;
        }
        $provider = strtolower((string)($c['provider'] ?? ''));
        // Local Ollama often needs no key
        if ($provider === 'ollama') {
            return true;
        }
        return trim((string)($c['api_key'] ?? '')) !== '';
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return array{ok:bool,text?:string,error?:string,model?:string,usage?:array}
     */

    /** Infer context pack from user text or explicit kind */
    public static function detectKind(string $text, string $explicit = ''): string
    {
        $e = (($explicit) |> trim(...) |> strtolower(...));
        $allowed = ['general','sales','team','mediakit','company','status','metals','calendar','comms','full'];
        if (in_array($e, $allowed, true)) {
            return $e;
        }
        $t = strtolower($text);
        if (preg_match('/\b(sales|mis|revenue|booking|bsp|ytd|pipeline)\b/', $t)) return 'sales';
        if (preg_match('/\b(media kit|caption|instagram|whatsapp|linkedin|hashtag|creative)\b/', $t)) return 'mediakit';
        if (preg_match('/\b(team|hr|directory|headcount|designation|department)\b/', $t)) return 'team';
        if (preg_match('/\b(construction|tower|slab|rera|progress|status update)\b/', $t)) return 'status';
        if (preg_match('/\b(gold|silver|copper|metal|mcx|commodity|platinum|zinc|brass)\b/', $t)) return 'metals';
        if (preg_match('/\b(choghadiya|panchang|muhurat|rahu|tithi)\b/', $t)) return 'calendar';
        if (preg_match('/\b(email|subject line|whatsapp message|sms draft)\b/', $t)) return 'comms';
        return 'general';
    }

    public static function chat(array $messages, ?string $systemOverride = null): array
    {
        $c = self::config();
        if (empty($c['enabled'])) {
            return ['ok' => false, 'error' => 'AI is disabled for this tenant. Enable it in the AI tab settings.'];
        }
        $key = trim((string)($c['api_key'] ?? ''));
        $provider = strtolower((string)($c['provider'] ?? ''));
        if ($key === '' && $provider === 'ollama') {
            $key = 'ollama';
        }
        if ($key === '') {
            return ['ok' => false, 'error' => 'API key not configured. Open Settings and paste your key, then Save.'];
        }
        $base = rtrim((string)$c['base_url'], '/');
        $url = $base . '/chat/completions';
        $sys = $systemOverride !== null && $systemOverride !== '' ? $systemOverride : (string)$c['system_prompt'];
        $payloadMessages = [['role' => 'system', 'content' => $sys]];
        foreach ($messages as $m) {
            $role = (string)($m['role'] ?? 'user');
            if (!in_array($role, ['user', 'assistant', 'system'], true)) {
                $role = 'user';
            }
            $content = trim((string)($m['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            // Cap single message size
            if (strlen($content) > 24000) {
                $content = substr($content, 0, 24000) . "\n…[truncated]";
            }
            $payloadMessages[] = ['role' => $role, 'content' => $content];
        }
        if (count($payloadMessages) < 2) {
            return ['ok' => false, 'error' => 'Empty message.'];
        }
        $body = json_encode([
            'model' => (string)$c['model'],
            'messages' => $payloadMessages,
            'max_tokens' => (int)$c['max_tokens'],
            'temperature' => (float)$c['temperature'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            return ['ok' => false, 'error' => 'Could not encode request.'];
        }
        $raw = self::httpJson($url, $body, $key);
        if ($raw === null) {
            return ['ok' => false, 'error' => 'Network error or empty response from the AI provider.'];
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return ['ok' => false, 'error' => 'Invalid JSON from provider.'];
        }
        if (!empty($j['error'])) {
            $msg = is_array($j['error']) ? (string)($j['error']['message'] ?? 'API error') : (string)$j['error'];
            return ['ok' => false, 'error' => $msg];
        }
        $text = (string)($j['choices'][0]['message']['content'] ?? '');
        if ($text === '') {
            return ['ok' => false, 'error' => 'Empty model response.'];
        }
        return [
            'ok' => true,
            'text' => $text,
            'model' => (string)($j['model'] ?? $c['model']),
            'usage' => is_array($j['usage'] ?? null) ? $j['usage'] : [],
        ];
    }

    /**
     * Build a safe context snippet from tenant data for MIS / assistant prompts.
     */
    /**
     * Build safe, non-secret context for the model.
     * @param string $kind general|sales|team|mediakit|company|status|metals|calendar|comms|full
     */
    public static function tenantContext(string $kind = 'general'): string
    {
        $bits = [];
        $kind = (($kind) |> trim(...) |> strtolower(...));
        if ($kind === '') {
            $kind = 'general';
        }
        $want = static function (string $k) use ($kind): bool {
            return $kind === 'full' || $kind === 'general' || $kind === $k
                || ($kind === 'comms' && in_array($k, ['mediakit', 'company'], true));
        };

        if (defined('TENANT_ID')) {
            $bits[] = 'Tenant id: ' . TENANT_ID;
        }
        $bits[] = 'Portal: Resource Centre (RC)';
        $bits[] = 'Timezone: Asia/Kolkata (IST)';
        $bits[] = 'Generated at: ' . date('c');

        if (!class_exists('AppDB')) {
            return implode("\n", $bits);
        }

        // Company (public branding only)
        if ($want('company') || $want('sales') || $want('comms')) {
            $co = AppDB::read('company') ?: [];
            if (is_array($co)) {
                $firstCo = array_first($co);
                if (is_array($firstCo)) {
                    $co = $firstCo;
                }
                $name = trim((string)($co['name'] ?? ''));
                $web = trim((string)($co['website'] ?? ''));
                $city = trim((string)($co['city'] ?? $co['address'] ?? ''));
                if ($name !== '') {
                    $bits[] = 'Organisation: ' . $name;
                }
                if ($web !== '') {
                    $bits[] = 'Website: ' . $web;
                }
                if ($city !== '') {
                    $bits[] = 'Location hint: ' . mb_substr($city, 0, 120);
                }
            }
        }

        // Sales MIS
        if ($want('sales')) {
            if (!class_exists('SalesRegister') && is_file(__DIR__ . '/SalesRegister.php')) {
                require_once __DIR__ . '/SalesRegister.php';
            }
            if (class_exists('SalesRegister')) {
                $rows = SalesRegister::all();
                $agg = SalesRegister::aggregate($rows);
                $bits[] = 'Sales register rows: ' . count($rows);
                $bits[] = 'Sales year focus: ' . ($agg['year'] ?? date('Y'));
                foreach (($agg['by_project'] ?? []) as $p => $c) {
                    if (!is_array($c)) {
                        continue;
                    }
                    $bits[] = sprintf(
                        'Project %s | units_month %d | gross_month %.0f | units_ytd %d | gross_ytd %.0f',
                        $p,
                        (int)($c['units_month'] ?? 0),
                        (float)($c['gross_month'] ?? 0),
                        (int)($c['units_ytd'] ?? 0),
                        (float)($c['gross_ytd'] ?? 0)
                    );
                }
                $n = 0;
                foreach (array_reverse($rows) as $r) {
                    if (!is_array($r) || ($r['status'] ?? '') === 'Cancelled') {
                        continue;
                    }
                    $bits[] = sprintf(
                        'Booking %s | %s | unit %s | type %s | area %.0f | gross %.0f | %s',
                        $r['month'] ?? '',
                        $r['project'] ?? '',
                        $r['unit'] ?? '',
                        $r['unit_type'] ?? '',
                        (float)($r['area'] ?? 0),
                        (float)($r['gross'] ?? 0),
                        $r['status'] ?? ''
                    );
                    // Omit customer names for privacy in default context
                    if (++$n >= 50) {
                        break;
                    }
                }
            }
        }

        // Team headcount only (no PII lists)
        if ($want('team')) {
            $team = AppDB::read('team') ?: [];
            if (is_array($team)) {
                $bits[] = 'Team headcount: ' . count($team);
                $withBlood = 0;
                $withPhoto = 0;
                foreach ($team as $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    if (trim((string)($m['blood_group'] ?? $m['blood'] ?? '')) !== '') {
                        $withBlood++;
                    }
                    if (trim((string)($m['photo'] ?? '')) !== '') {
                        $withPhoto++;
                    }
                }
                $bits[] = 'Team with blood group on file: ' . $withBlood;
                $bits[] = 'Team with photo on file: ' . $withPhoto;
            }
            $depts = AppDB::read('departments') ?: [];
            $desigs = AppDB::read('designations') ?: [];
            if (is_array($depts)) {
                $bits[] = 'Departments defined: ' . count($depts);
            }
            if (is_array($desigs)) {
                $bits[] = 'Designations defined: ' . count($desigs);
            }
        }

        // Media kit inventory (filenames/categories only)
        if ($want('mediakit')) {
            $kit = AppDB::read('mediakit') ?: [];
            if (is_array($kit)) {
                $bits[] = 'Media kit assets: ' . count($kit);
                $byCat = [];
                $n = 0;
                foreach ($kit as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $cat = (string)($row['category'] ?? $row['type'] ?? 'other');
                    $byCat[$cat] = ($byCat[$cat] ?? 0) + 1;
                    if ($n < 25) {
                        $bits[] = sprintf(
                            'Asset: %s | cat %s | file %s',
                            mb_substr((string)($row['name'] ?? $row['title'] ?? ''), 0, 80),
                            $cat,
                            mb_substr((string)($row['filename'] ?? $row['file'] ?? ''), 0, 60)
                        );
                        $n++;
                    }
                }
                foreach ($byCat as $c => $cnt) {
                    $bits[] = 'Media category ' . $c . ': ' . $cnt;
                }
            }
        }

        // Locations count
        if ($want('company') || $want('team')) {
            $locs = AppDB::read('locations') ?: [];
            if (is_array($locs)) {
                $bits[] = 'Shared locations: ' . count($locs);
            }
        }

        // Events / calendar (titles + dates only)
        if ($want('calendar')) {
            $ev = AppDB::read('events') ?: [];
            if (is_array($ev)) {
                $bits[] = 'Calendar events on file: ' . count($ev);
                $n = 0;
                $now = time();
                foreach ($ev as $e) {
                    if (!is_array($e)) {
                        continue;
                    }
                    $title = trim((string)($e['title'] ?? $e['name'] ?? ''));
                    $dt = (string)($e['date'] ?? $e['start'] ?? $e['when'] ?? '');
                    if ($title === '') {
                        continue;
                    }
                    $bits[] = 'Event: ' . mb_substr($title, 0, 80) . ($dt !== '' ? ' @ ' . $dt : '');
                    if (++$n >= 20) {
                        break;
                    }
                }
            }
        }

        // Construction status summary if tenant status data exists
        if ($want('status')) {
            $statusDir = '';
            if (defined('DATA_PATH')) {
                $statusDir = rtrim((string)DATA_PATH, '/\\') . '/status';
            }
            if ($statusDir !== '' && is_dir($statusDir)) {
                $projFile = $statusDir . '/projects.json';
                $updFile = $statusDir . '/updates.json';
                if (is_file($projFile)) {
                    $pj = json_decode((string)@file_get_contents($projFile), true);
                    if (is_array($pj)) {
                        $bits[] = 'Construction projects: ' . count($pj);
                        foreach (array_slice($pj, 0, 8) as $p) {
                            if (is_array($p)) {
                                $bits[] = 'Project: ' . ($p['name'] ?? $p['id'] ?? '');
                            }
                        }
                    }
                }
                if (is_file($updFile)) {
                    $up = json_decode((string)@file_get_contents($updFile), true);
                    if (is_array($up)) {
                        $bits[] = 'Construction updates logged: ' . count($up);
                    }
                }
            }
        }

        // Metals / commodities — static product knowledge (rates are live on metals.php)
        if ($want('metals')) {
            $bits[] = 'Commodity module tracks: Gold, Platinum, Silver, Copper, Brass, Zinc, MS Steel Ingot (Delhi NCR / MCX oriented, GST-inclusive display).';
            $bits[] = 'Live rates endpoint: /metals.php?format=json (refresh ~5–10 min server cache).';
        }

        if ($want('calendar') || $kind === 'general' || $kind === 'full') {
            $bits[] = 'Choghadiya / panchang module available at /choghadiya.php (IST).';
        }

        $bits[] = 'Privacy: do not request or invent phone numbers, emails, blood groups, or home addresses unless the user pasted them in this chat.';
        // Cap total context size
        $out = implode("\n", $bits);
        if (strlen($out) > 28000) {
            $out = substr($out, 0, 28000) . "\n…(context truncated)";
        }
        return $out;
    }

    private static function httpJson(string $url, string $body, string $apiKey): ?string
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
                CURLOPT_TIMEOUT => 90,
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $res = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            unset($ch); /* curl handle auto-closed (PHP 8+) */
            if ($res === false) {
                return null;
            }
            // Return body even on 4xx so caller can parse error message
            return (string)$res;
        }
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
                'content' => $body,
                'timeout' => 90,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        return ($res !== false) ? (string)$res : null;
    }
}
