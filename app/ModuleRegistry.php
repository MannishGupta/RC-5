<?php
declare(strict_types=1);

final class ModuleRegistry
{
     
    public static function catalogue(): array
    {
        return [
            'today' => [
                'label' => 'Today',
                'group' => 'Human Capital',
                'features' => [
                    'today_brief' => 'Daily operating brief',
                    'today_health' => 'Tenant setup health',
                ],
            ],
            'team' => [
                'label' => 'Human Capital Index',
                'group' => 'Human Capital',
                'features' => [
                    'team_print' => 'Directory print / VCF export',
                    'team_blood' => 'Blood group reports',
                    'team_janam' => 'Janam Patri link from profile',
                    'team_numero' => 'Numerology link from profile',
                ],
            ],
            'tracking' => [
                'label' => 'Live Location',
                'group' => 'Field Operations',
                'features' => [
                    'tracking_map' => 'Live map',
                    'tracking_history' => 'Travel history',
                    'tracking_violations' => 'Policy violations',
                ],
            ],
            'ops' => [
                'label' => 'Field Ops Hub',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'cartags' => [
                'label' => 'Vehicle Inventory',
                'group' => 'Field Operations',
                'features' => [
                    'cartags_oem' => 'OEM master logos',
                ],
            ],
            'dispatch' => [
                'label' => 'Dispatch & Routes',
                'group' => 'Field Operations',
                'features' => [
                    'dispatch_hindi' => 'Roman-Hindi mobile dispatch',
                ],
            ],
            'locations' => [
                'label' => 'Standard Locations',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'assets' => [
                'label' => 'Asset Checkout',
                'group' => 'Field Operations',
                'features' => [],
            ],
            'bank' => [
                'label' => 'Treasury & Banking',
                'group' => 'Institutional Resources',
                'features' => [
                    'bank_qr' => 'UPI / QR previews',
                    'bank_import' => 'Statement import',
                ],
            ],
            'docs' => [
                'label' => 'Document Vault',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'ai' => [
                'label' => 'AI Assistant',
                'group' => 'Insights',
                'features' => [
                    'ai_chat' => 'Chat assistant (OpenAI-compatible)',
                    'ai_sales_brief' => 'Sales MIS brief from live data',
                ],
            ],
            'sales' => [
                'label' => 'Monthly Sales Register',
                'group' => 'Institutional Resources',
                'features' => [
                    'sales_register' => 'Unit bookings & revenue log',
                    'sales_charts' => 'Monthly / YTD performance charts',
                    'sales_import' => 'CSV import from spreadsheets',
                    'sales_live_sheets' => 'Live Google Sheets sync (service account)',
                ],
            ],
            'metals' => [
                'label' => 'Commodity Prices (7)',
                'group' => 'Institutional Resources',
                'features' => [
                    'metals_json' => 'JSON feed for KWGT / Scriptable',
                    'metals_pwa' => 'Installable PWA widget',
                ],
            ],
            'mediakit' => [
                'label' => 'Media Kit',
                'group' => 'Institutional Resources',
                'features' => [
                    'mediakit_upload' => 'Upload & manage assets',
                    'mediakit_share' => 'Share via WhatsApp / email',
                    'mediakit_social' => 'Official API / oEmbed / RSS ingest',
                ],
            ],
            'events' => [
                'label' => 'Calendar',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'statutory' => [
                'label' => 'Compliance Register',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'expiry' => [
                'label' => 'Expiry Radar',
                'group' => 'Institutional Resources',
                'features' => [],
            ],
            'insights' => [
                'label' => 'Personal insights',
                'group' => 'Insights',
                'features' => [
                    'numero' => 'Numerology reports',
                    'janam' => 'Janam Patri & Milan',
                    'blood' => 'Blood clinical brief',
                ],
            ],
            'choghadiya' => [
                'label' => 'Choghadiya',
                'group' => 'Insights',
                'features' => [
                    'choghadiya_day' => 'Tithi, Rahu Kaal, auspicious windows',
                ],
            ],
            'janam' => [
                'label' => 'Janam Patri',
                'group' => 'Insights',
                'features' => [],
            ],
            'blood' => [
                'label' => 'Blood Report',
                'group' => 'Insights',
                'features' => [],
            ],
            'numero' => [
                'label' => 'Numerology',
                'group' => 'Insights',
                'features' => [],
            ],
            'org' => [
                'label' => 'Organisation Chart',
                'group' => 'Administration',
                'features' => [],
            ],
            'data_rights' => [
                'label' => 'Data principal rights',
                'group' => 'Administration',
                'features' => [],
            ],
            'company' => [
                'label' => 'Organisation Setup',
                'group' => 'Administration',
                'features' => [],
            ],
            'designations' => [
                'label' => 'Designations',
                'group' => 'Administration',
                'features' => [],
            ],
            'departments' => [
                'label' => 'Departments',
                'group' => 'Administration',
                'features' => [],
            ],
            'settings' => [
                'label' => 'Integrations',
                'group' => 'Administration',
                'features' => [],
            ],
            'cctv' => [
                'label' => 'Access log',
                'group' => 'Administration',
                'features' => [],
            ],
            'leads' => [
                'label' => 'Opportunity Pipeline',
                'group' => 'Administration',
                'features' => [],
            ],
            'status' => [
                'label' => 'Construction Status',
                'group' => 'Projects',
                'features' => [
                    'status_feed' => 'Tower-wise progress and photographs',
                ],
            ],
            'health' => [
                'label' => 'Health & Tools',
                'group' => 'Platform operations',
                'features' => [],
            ],
            'audit' => [
                'label' => 'Audit log',
                'group' => 'Administration',
                'features' => [],
            ],
            'terms' => [
                'label' => 'Governance & privacy',
                'group' => 'System',
                'features' => [],
            ],
            'access' => [
                'label' => 'Access mode',
                'group' => 'System',
                'features' => [],
            ],
            'statistics' => [
                'label' => 'Statistics',
                'group' => 'System',
                'features' => [],
            ],
             
            'tenants' => [
                'label' => 'Tenant Setup',
                'group' => 'Control Plane',
                'features' => [
                    'modules_config' => 'Per-tenant module matrix',
                ],
            ],
            'monitor' => [
                'label' => 'Live Monitor',
                'group' => 'Control Plane',
                'features' => [],
            ],
            'opt' => [
                'label' => 'Maintenance (via Health & Tools)',
                'group' => 'Platform operations',
                'features' => [],
            ],
            'subscription_ad' => [
                'label' => 'Subscription banner',
                'group' => 'Platform',
                'features' => [
                    'subscription_ad_banner' => 'Top marquee + Pay subscription CTA (disable to remove)',
                ],
            ],
        ];
    }

    public static function configPath(): string
    {
        $base = defined('DATA_PATH') ? DATA_PATH : (defined('BASE_PATH') ? BASE_PATH . '/data' : dirname(__DIR__) . '/data');
        $dir = rtrim($base, '/\\') . '/config';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/modules.json';
    }

     
    public static function defaults(): array
    {
        $mods = [];
        foreach (self::catalogue() as $id => $meta) {
            $feats = [];
            foreach (array_keys($meta['features'] ?? []) as $fid) {
                $feats[$fid] = true;
            }
            $mods[$id] = [
                'enabled' => true,
                'features' => $feats,
            ];
        }
        return [
            'version' => 1,
            'updated_at' => null,
            'modules' => $mods,
        ];
    }

    public static function load(): array
    {
        $path = self::configPath();
        $defaults = self::defaults();
        if (!is_file($path)) {
            return $defaults;
        }
        $j = json_decode((string)@file_get_contents($path), true);
        if (!is_array($j) || !isset($j['modules']) || !is_array($j['modules'])) {
            return $defaults;
        }
         
        foreach ($defaults['modules'] as $id => $def) {
            if (!isset($j['modules'][$id]) || !is_array($j['modules'][$id])) {
                $j['modules'][$id] = $def;
                continue;
            }
            if (!array_key_exists('enabled', $j['modules'][$id])) {
                $j['modules'][$id]['enabled'] = true;
            }
            $feats = is_array($j['modules'][$id]['features'] ?? null) ? $j['modules'][$id]['features'] : [];
            foreach ($def['features'] as $fid => $on) {
                if (!array_key_exists($fid, $feats)) {
                    $feats[$fid] = $on;
                }
            }
            $j['modules'][$id]['features'] = $feats;
        }
        return $j;
    }

    public static function save(array $cfg): bool
    {
        $path = self::configPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return false;
            }
        }
        @chmod($dir, 0775);
        $cfg['version'] = 1;
        $cfg['updated_at'] = date('c');
        $json = json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }
        $tmp = $path . '.tmp.' . getmypid();
        $written = @file_put_contents($tmp, $json . "
", LOCK_EX);
        if ($written === false) {
            $ok = @file_put_contents($path, $json . "
", LOCK_EX) !== false;
            if ($ok) {
                @chmod($path, 0664);
            }
            return $ok;
        }
        if (is_file($path)) {
            @unlink($path);
        }
        if (!@rename($tmp, $path)) {
            $ok = @file_put_contents($path, $json . "
", LOCK_EX) !== false;
            @unlink($tmp);
            if ($ok) {
                @chmod($path, 0664);
            }
            return $ok;
        }
        @chmod($path, 0664);
        return true;
    }

    public static function isEnabled(string $moduleId): bool
    {
         
        $cfg = self::load();
        $m = $cfg['modules'][$moduleId] ?? null;
        if ($m === null) {
            return true;  
        }
        return !empty($m['enabled']);
    }

    public static function featureEnabled(string $moduleId, string $featureId): bool
    {
        if (!self::isEnabled($moduleId)) {
            return false;
        }
        $cfg = self::load();
        $feats = $cfg['modules'][$moduleId]['features'] ?? [];
        if (!array_key_exists($featureId, $feats)) {
            return true;
        }
        return !empty($feats[$featureId]);
    }

     
    public static function filterTabs(array $tabs, bool $isSuperAdmin = false): array
    {
        // Always-on modules must be *merged in*, not only protected from removal.
        // Prior versions listed them in $always but never added them when missing
        // from $tabs — which made Today (and related) disappear from the shell.
        $always = $isSuperAdmin
            ? ['tenants', 'monitor', 'health', 'access', 'today', 'sales', 'metals', 'choghadiya', 'ai']
            : ['terms', 'access', 'today', 'sales', 'metals', 'choghadiya', 'ai'];
        $out = [];
        foreach ($tabs as $t) {
            $t = (string)$t;
            if ($t === '') {
                continue;
            }
            if (in_array($t, $always, true) || self::isEnabled($t)) {
                $out[] = $t;
            }
        }
        foreach ($always as $t) {
            if (!in_array($t, $out, true) && ($isSuperAdmin || self::isEnabled($t) || in_array($t, ['terms', 'access', 'today'], true))) {
                $out[] = $t;
            }
        }
        return array_values(array_unique($out));
    }

     
    public static function clientPayload(): array
    {
        $cfg = self::load();
        $enabled = [];
        $features = [];
        // Start from catalogue so new modules (e.g. sales) appear enabled unless explicitly off
        foreach (array_keys(self::catalogue()) as $id) {
            $enabled[$id] = true;
            $features[$id] = [];
        }
        foreach ($cfg['modules'] as $id => $m) {
            if (is_array($m) && array_key_exists('enabled', $m)) {
                $enabled[$id] = !empty($m['enabled']);
            }
            $features[$id] = is_array($m['features'] ?? null) ? $m['features'] : ($features[$id] ?? []);
        }
        return [
            'enabled' => $enabled,
            'features' => $features,
            'updated_at' => $cfg['updated_at'] ?? null,
        ];
    }

    

    public static function resolveTab(string $requested, array $validTabs): array
    {
        if (in_array($requested, $validTabs, true) && self::isEnabled($requested)) {
            return ['tab' => $requested, 'redirected' => false, 'message' => null];
        }
         
        $fallback = in_array('today', $validTabs, true) ? 'today' : (in_array('team', $validTabs, true) ? 'team' : (array_first($validTabs) ?? 'today'));
        if (!self::isEnabled($fallback) && !empty($validTabs)) {
            foreach ($validTabs as $t) {
                if (self::isEnabled($t) || in_array($t, ['terms', 'access'], true)) {
                    $fallback = $t;
                    break;
                }
            }
        }
        $msg = 'That module is switched off for this organisation. Contact your Super Admin if you need it restored.';
        return ['tab' => $fallback, 'redirected' => true, 'message' => $msg];
    }
}
