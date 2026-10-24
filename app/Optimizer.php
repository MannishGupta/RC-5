<?php
// Version: 5.1.7 (optimiser: global heal, verify, status, FKs, summary)
declare(strict_types=1);


if (!defined('BASE_PATH')) {
    exit('No direct script access');
}

class SystemDataOptimizer
{
     
    public const PROTECTED_SLUGS = ['mg', 'ag', 'ap', 'rj', 'akg', 'mk'];

    private const THROTTLE_SECONDS = 21600;  
    private const THROTTLE_FILE = 'optimizer_last_run.json';

     
    private static array $logs = [];

    /** @var array<string,mixed> Structured summary for UI / API */
    private static array $runStats = [];


    private static function dataRoot(): string
    {
        if (!empty($GLOBALS['RC_OPT_DATA_PATH']) && is_string($GLOBALS['RC_OPT_DATA_PATH'])) {
            return rtrim($GLOBALS['RC_OPT_DATA_PATH'], '/\\');
        }
        if (defined('DATA_PATH')) {
            return rtrim((string)DATA_PATH, '/\\');
        }
        return rtrim(BASE_PATH . '/data', '/\\');
    }

    private static function mediaImagesDir(): string
    {
        return self::dataRoot() . '/media/images';
    }

    private static function mediaDocsDir(): string
    {
        return self::dataRoot() . '/media/documents';
    }

    

    public static function run(bool $force = false, bool $lightweight = false): array
    {
        self::$logs = [];
        self::$runStats = [
            'images' => ['scanned' => 0, 'changed' => 0, 'untouched' => 0, 'failed' => 0, 'deferred' => 0, 'json_refs_updated' => 0],
            'heal' => ['fixed' => 0, 'missing' => 0, 'namespaces' => []],
            'verify' => ['ok' => 0, 'missing' => 0, 'by_ns' => []],
            'master_fk' => ['team_updated' => 0],
            'status_media' => ['scanned' => 0, 'changed' => 0],
            'mediakit_keep_format' => true,
        ];

        if (!$force && self::isThrottled()) {
            self::log('Skipped — last run within 6 hours (pass force=true to override).');
            return [
                'status'  => 'success',
                'skipped' => true,
                'logs'    => self::$logs,
                'summary' => self::$runStats,
                'message' => 'Throttled',
            ];
        }

        try {
            self::log('Optimizer started' . ($force ? ' (forced)' : '') . ($lightweight ? ' [lightweight]' : '') . '.');

            self::phaseEnsureDirs();
            self::phaseGenerateTemplates();
             
            self::phaseNormalizeCompany();
             
            foreach (self::discoverNormalizeTargets() as $ns => $schema) {
                if ($ns === 'company') {
                    continue;
                }
                self::phaseNormalizeList($ns, $schema);
            }

            self::phaseTeamPhonesAndNames();
            self::phaseMigrateTeamSlugs();
            self::phaseResolveMasterForeignKeys();
            self::phaseRebuildTeamIndex();
            self::phaseTrimSessions();
            self::phaseTrimLogs();
            self::phaseOrphanMedia();
             
            // Global path heal (all media namespaces) before convert
            self::phaseHealAllMediaPaths();
            if (!$lightweight) {
                self::phaseConvertImagesWebp();
                self::phaseOptimiseStatusMedia();
                // Second pass: heal after renames from this run
                self::phaseHealAllMediaPaths();
            } else {
                self::log('WebP convert skipped (multi-tenant sweep — run per-tenant optimise to convert images for a specific tenant).');
            }
            self::phaseVerifyMediaLinks();
            self::phaseRebuildDataCache();
            self::phasePublicTeamAndHealth();
            self::phaseLogRotateAndBackupHint();

            self::markRan();

             
            if (class_exists('AppCacheFlush')) {
                $flush = AppCacheFlush::all('optimise');
                self::log(
                    'OPcache ' . (!empty($flush['opcache']) ? 'cleared' : 'not available')
                    . ' (v' . ($flush['version'] ?? '?') . ')'
                );
            } elseif (function_exists('opcache_reset')) {
                @opcache_reset();
                self::log('OPcache cleared (fallback).');
            }

            self::log('Optimizer finished.');
            self::log(sprintf(
                'SUMMARY · images changed=%d untouched=%d failed=%d · heal fixed=%d missing=%d · verify ok=%d missing=%d · team FK fixes=%d',
                (int) (self::$runStats['images']['changed'] ?? 0),
                (int) (self::$runStats['images']['untouched'] ?? 0),
                (int) (self::$runStats['images']['failed'] ?? 0),
                (int) (self::$runStats['heal']['fixed'] ?? 0),
                (int) (self::$runStats['heal']['missing'] ?? 0),
                (int) (self::$runStats['verify']['ok'] ?? 0),
                (int) (self::$runStats['verify']['missing'] ?? 0),
                (int) (self::$runStats['master_fk']['team_updated'] ?? 0)
            ));
            return ['status' => 'success', 'logs' => self::$logs, 'summary' => self::$runStats];
        } catch (\Throwable $e) {
            self::log('FATAL: ' . $e->getMessage());
            if (class_exists('AppLog')) {
                AppLog::error('Optimizer failed: ' . $e->getMessage(), [
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine(),
                ]);
            }
            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
                'logs'    => self::$logs,
                'summary' => self::$runStats,
            ];
        }
    }

    

    public static function scanCleanup(): array
    {
         
         
        $groups = [
            'images' => [
                'key' => 'images', 'label' => 'Unreferenced images',
                'safety' => 'review', 'warning' => 'Not linked from team/company/docs records. Confirm before delete.',
                'items' => [],
            ],
            'docs' => [
                'key' => 'docs', 'label' => 'Unreferenced documents',
                'safety' => 'review', 'warning' => 'Not linked from document records. Confirm before delete.',
                'items' => [],
            ],
            'sessions' => [
                'key' => 'sessions', 'label' => 'Expired session files',
                'safety' => 'safe', 'warning' => '',
                'items' => [],
            ],
            'logs' => [
                'key' => 'logs', 'label' => 'Oversized log artefacts',
                'safety' => 'safe', 'warning' => 'Only flags logs over 2 MB; deletion is optional.',
                'items' => [],
            ],
            'temp' => [
                'key' => 'temp', 'label' => 'Temp / cache artefacts',
                'safety' => 'safe', 'warning' => '',
                'items' => [],
            ],
            'rubbish' => [
                'key' => 'rubbish', 'label' => 'Accidental / unused files (.bak, copies, OS junk)',
                'safety' => 'safe', 'warning' => 'Editor backups, Thumbs.db, *-copy.* — not used by the app.',
                'items' => [],
            ],
        ];

        $referenced = self::collectReferencedFiles();
        $imgDir = self::mediaImagesDir();
        $docDir = self::mediaDocsDir();
        $dataDir = self::dataRoot();
         
         

         
         
         
         
         
         
         
         
         
         
         
         
         
         
        $add = static function (string $gkey, string $rel, string $name, int $bytes, string $note, string $fullPath) use (&$groups): void {
            if (!isset($groups[$gkey])) return;
            $groups[$gkey]['items'][] = [
                'path'  => $rel,
                'name'  => $name,
                'note'  => $note,
                'bytes' => $bytes,
                'full'  => $fullPath,
            ];
        };

        foreach ([$imgDir => 'images', $docDir => 'docs'] as $dir => $label) {
            if (!is_dir($dir)) continue;
            foreach (@scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $dir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                if (isset($referenced[strtolower($f)])) continue;
                if (preg_match('/^og-/i', $f) || in_array(strtolower($f), ['favicon.ico', 'favicon.png', 'cover.jpg', '.htaccess'], true)) {
                    continue;
                }
                $size = (int) @filesize($full);
                $add($label, $label . '/' . $f, $f, $size, 'Unreferenced ' . $label, $full);
            }
        }

        $sessDir = $dataDir . '/sessions';
        if (is_dir($sessDir)) {
            $now = time();
            foreach (@scandir($sessDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $sessDir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                $mtime = (int) @filemtime($full);
                if ($mtime > 0 && ($now - $mtime) > 86400 * 14) {
                    $size = (int) @filesize($full);
                    $add('sessions', 'data/sessions/' . $f, $f, $size, 'Idle > 14 days', $full);
                }
            }
        }

        $logFile = $dataDir . '/logs/app.log';
        if (is_file($logFile) && filesize($logFile) > 2_000_000) {
            $add('logs', 'data/logs/app.log', 'app.log', (int) filesize($logFile), 'Over 2 MB — consider trim via Optimise', $logFile);
        }

        $tmpDir = $dataDir . '/tmp';
        if (is_dir($tmpDir)) {
            foreach (@scandir($tmpDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') continue;
                $full = $tmpDir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($full)) continue;
                $add('temp', 'data/tmp/' . $f, $f, (int) @filesize($full), 'Temp file', $full);
            }
        }

         
        foreach (self::allTenantDataRoots() as $tid => $root) {
            self::scanRubbishUnder($root, $tid, $add);
        }
         
        $legacyStatus = BASE_PATH . '/status/uploads';
        if (is_dir($legacyStatus)) {
            self::scanRubbishUnder($legacyStatus, 'status-legacy', $add);
        }

         
        $outGroups = [];
        $totalFiles = 0;
        $totalBytes = 0;
        foreach ($groups as $g) {
            if (empty($g['items'])) continue;
            $outGroups[] = $g;
            $totalFiles += count($g['items']);
            foreach ($g['items'] as $it) {
                $totalBytes += (int) ($it['bytes'] ?? 0);
            }
        }

        $human = static function (int $b): string {
            $u = ['B', 'KB', 'MB', 'GB'];
            $i = 0;
            $x = (float) $b;
            while ($x >= 1024 && $i < count($u) - 1) { $x /= 1024; $i++; }
            return (string) (round($x * 10) / 10) . ' ' . $u[$i];
        };

         
         
         
         
         
         
         
         
        $candidates = [];
        foreach ($outGroups as $g) {
            foreach ($g['items'] as $it) {
                $candidates[] = [
                    'path' => $it['path'],
                    'size' => $it['bytes'],
                    'full' => $it['full'] ?? (BASE_PATH . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $it['path'])),
                ];
            }
        }

        return [
            'status'      => 'success',
            'groups'      => $outGroups,
            'total_files' => $totalFiles,
            'total_bytes' => $totalBytes,
            'total_human' => $human($totalBytes),
            'candidates'  => $candidates,
            'bytes'       => $totalBytes,
            'count'       => $totalFiles,
        ];
    }

    

    public static function applyCleanup(array $paths): array
    {
        $scan = self::scanCleanup();
        $allowed = [];
        foreach ($scan['candidates'] as $c) {
            $allowed[$c['path']] = $c;
        }

        $deleted = 0;
        $freed = 0;
        $refused = 0;
        $failed = 0;

        foreach ($paths as $rel) {
            $rel = str_replace('\\', '/', (string) $rel);
            $rel = ltrim($rel, '/');
            if (!isset($allowed[$rel])) {
                $refused++;
                continue;
            }
            $full = $allowed[$rel]['full'];
            $size = (int) ($allowed[$rel]['size'] ?? $allowed[$rel]['bytes'] ?? 0);
             
            $roots = [];
            foreach ([
                defined('IMG_PATH') ? IMG_PATH : (self::dataRoot() . '/media/images'),
                defined('DOC_PATH') ? DOC_PATH : (self::dataRoot() . '/media/documents'),
                self::dataRoot() . '/sessions',
                self::dataRoot() . '/logs',
                self::dataRoot() . '/tmp',
                 
                 
                 
                 
                 
                 
                 
            ] as $r) {
                $rp = realpath($r);
                if ($rp !== false) $roots[] = $rp;
            }
            $real = realpath($full);
            if ($real === false) {
                 
                $refused++;
                continue;
            }
             
             
             
             
             
             
            $okRoot = false;
            foreach ($roots as $root) {
                if ($real === $root || strpos($real, $root . DIRECTORY_SEPARATOR) === 0) {
                    $okRoot = true;
                    break;
                }
            }
            if (!$okRoot) {
                $refused++;
                continue;
            }
            if (@unlink($real)) {
                $deleted++;
                $freed += $size;
            } else {
                $failed++;
            }
        }

        if (class_exists('AppLog')) {
            AppLog::info('Cleanup applied', compact('deleted', 'freed', 'refused', 'failed'));
        }

        $logs = [
            "Deleted {$deleted} file(s).",
            'Freed ~' . $freed . ' bytes.',
            "Refused {$refused}, failed {$failed}.",
        ];

        return [
            'status'  => 'success',
            'deleted' => $deleted,
            'freed'   => $freed,
            'refused' => $refused,
            'failed'  => $failed,
            'logs'    => $logs,
        ];
    }

     

    private static function phaseEnsureDirs(): void
    {
        $data = self::dataRoot();
         
        $dirs = [
            $data,
            $data . '/media/images',
            $data . '/media/documents',
            $data . '/sessions',
            $data . '/logs',
            $data . '/backups',
            $data . '/dispatch',
            $data . '/janam',
            $data . '/value',
            $data . '/runners',
            $data . '/config',
            $data . '/media',
            $data . '/tmp',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                if (@mkdir($d, 0775, true)) {
                    self::log('Created directory ' . self::relPath($d));
                }
            }
        }
    }

    
    private static function phaseGenerateTemplates(): void
    {
        $data = self::dataRoot();
        $inv = self::inventoryWatched();
        $created = 0;
        foreach ($inv['namespaces'] as $ns) {
            $path = $data . '/' . $ns['file'];
            if (is_file($path)) continue;  
            $key = (string) ($ns['key'] ?? '');
             
             
             
             
             
             
             
             
             
            $isObject = ($key === 'company');
            $template = $isObject ? '{}' : '[]';
            if (@file_put_contents($path, $template, LOCK_EX) !== false) {
                $created++;
                self::log('Created template ' . self::relPath($path) . ' (' . $template . ')');
            }
        }
        if ($created > 0) {
            self::log("Template generation: {$created} missing JSON file(s) created.");
        }
    }

    

    public static function inventoryWatched(): array
    {
        $data = self::dataRoot();
        $namespaces = [
            ['key' => 'team', 'file' => 'team.json', 'optimized' => true, 'label' => 'Human Capital Index'],
            ['key' => 'company', 'file' => 'company.json', 'optimized' => true, 'label' => 'Organizational Config'],
            ['key' => 'bank', 'file' => 'banking.json', 'optimized' => true, 'label' => 'Treasury Ledger'],
            ['key' => 'docs', 'file' => 'documents.json', 'optimized' => true, 'label' => 'Document Vault'],
            ['key' => 'events', 'file' => 'events.json', 'optimized' => true, 'label' => 'Corporate Calendar'],
            ['key' => 'locations', 'file' => 'locations.json', 'optimized' => true, 'label' => 'Premises Registry'],
            ['key' => 'departments', 'file' => 'departments.json', 'optimized' => true, 'label' => 'Organizational Units'],
            ['key' => 'designations', 'file' => 'designations.json', 'optimized' => true, 'label' => 'Role Taxonomy'],
            ['key' => 'statutory', 'file' => 'statutory.json', 'optimized' => true, 'label' => 'Compliance Register'],
            ['key' => 'cartags', 'file' => 'cartags.json', 'optimized' => true, 'label' => 'Fleet Asset Registry'],
            ['key' => 'settings', 'file' => 'settings.json', 'optimized' => false, 'label' => 'Platform settings'],
        ];
        foreach ($namespaces as &$ns) {
            $path = $data . '/' . $ns['file'];
            $ns['exists'] = is_file($path);
            $ns['bytes'] = $ns['exists'] ? (int) @filesize($path) : 0;
            $ns['writable'] = $ns['exists'] ? is_writable($path) : is_writable($data);
            $ns['path'] = 'data/' . $ns['file'];
        }
        unset($ns);

        $folderDefs = [
            ['id' => 'data', 'path' => $data, 'label' => 'Data root', 'required' => true],
            ['id' => 'images', 'path' => $data . '/media/images', 'label' => 'Media · images', 'required' => true],
            ['id' => 'documents', 'path' => $data . '/media/documents', 'label' => 'Media · documents', 'required' => true],
            ['id' => 'sessions', 'path' => defined('SESSION_PATH') ? SESSION_PATH : ($data . '/sessions'), 'label' => 'Sessions', 'required' => true],
            ['id' => 'logs', 'path' => defined('LOG_PATH') ? LOG_PATH : ($data . '/logs'), 'label' => 'Logs', 'required' => true],
            ['id' => 'backups', 'path' => defined('BACKUP_PATH') ? BACKUP_PATH : ($data . '/backups'), 'label' => 'Backups', 'required' => false],
            ['id' => 'dispatch', 'path' => defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : ($data . '/dispatch'), 'label' => 'Dispatch store', 'required' => false],
            ['id' => 'janam', 'path' => defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : ($data . '/janam'), 'label' => 'Janam Patri store', 'required' => false],
            ['id' => 'value', 'path' => defined('VALUE_DATA_PATH') ? VALUE_DATA_PATH : ($data . '/value'), 'label' => 'Value Pack store', 'required' => false],
            ['id' => 'runners', 'path' => defined('RUNNERS_DATA_PATH') ? RUNNERS_DATA_PATH : ($data . '/runners'), 'label' => 'Live tracking', 'required' => false],
            ['id' => 'config', 'path' => defined('CONFIG_DATA_PATH') ? CONFIG_DATA_PATH : ($data . '/config'), 'label' => 'Config secrets', 'required' => false],
        ];
        $folders = [];
        foreach ($folderDefs as $fd) {
            $path = $fd['path'];
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $fileCount = 0;
            $bytes = 0;
            if ($exists) {
                foreach (scandir($path) ?: [] as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    $fp = $path . '/' . $f;
                    if (is_file($fp)) {
                        $fileCount++;
                        $bytes += (int) @filesize($fp);
                    }
                }
            }
            $folders[] = [
                'id' => $fd['id'],
                'label' => $fd['label'],
                'path' => self::relPath($path),
                'required' => $fd['required'],
                'exists' => $exists,
                'writable' => $writable,
                'files' => $fileCount,
                'bytes' => $bytes,
                'ok' => $exists && $writable,
            ];
        }

        $legacyDefs = [];  

        $legacy = [];
        foreach ($legacyDefs as $ld) {
            $exists = is_dir($ld['path']);
            $count = 0;
            if ($exists) {
                foreach (scandir($ld['path']) ?: [] as $f) {
                    if ($f === '.' || $f === '..') {
                        continue;
                    }
                    if (is_file($ld['path'] . '/' . $f)) {
                        $count++;
                    }
                }
            }
            $legacy[] = [
                'label' => $ld['label'],
                'path' => self::relPath($ld['path']),
                'target' => self::relPath($ld['target']),
                'exists' => $exists,
                'files' => $count,
                'needs_migration' => $exists && $count > 0,
            ];
        }

        return [
            'status' => 'success',
            'layout_version' => 'data-v1',
            'namespaces' => $namespaces,
            'folders' => $folders,
            'legacy' => $legacy,
            'backup_policy' => [
                'includes' => [
                    'data/** entity JSON and module stores',
                    'data/media (images + documents)',
                    'data/config and data/*_config.php',
                    '(legacy root paths retired — only tenants/*/data and unified data/media)',
                ],
                'excludes' => [
                    'data/sessions',
                    'data/logs',
                    'data/backups',
                    'data/tmp',
                    '*.tmp / cache stamps',
                    'application PHP (app/, cards/, index.php)',
                ],
            ],
            'optimize_namespaces' => array_values(array_unique(array_merge(
                ['company'],
                array_keys(self::discoverNormalizeTargets())
            ))),
        ];
    }

    

    public static function migrateDataLayout(bool $removeEmptyLegacy = false): array
    {
        return ['copied' => 0, 'removed' => [], 'skipped' => true, 'message' => 'Legacy migration retired'];
    }

    

    private static function phaseMigrateDataLayout(bool $removeEmptyLegacy = false, bool $asPublic = false): array
    {
        self::log('Legacy layout migration skipped (obsolete paths retired).');
        return ['copied' => 0, 'removed' => [], 'skipped' => true];

        $data = self::dataRoot();
        $img = $data . '/media/images';
        $doc = $data . '/media/documents';
        $dispatch = defined('DISPATCH_DATA_PATH') ? DISPATCH_DATA_PATH : ($data . '/dispatch');
        $janam = defined('JANAM_DATA_PATH') ? JANAM_DATA_PATH : ($data . '/janam');
        $config = defined('CONFIG_DATA_PATH') ? CONFIG_DATA_PATH : ($data . '/config');

        foreach ([$img, $doc, $dispatch, $janam, $config] as $d) {
            if (!is_dir($d)) {
                @mkdir($d, 0775, true);
            }
        }

        $copied = 0;
        $skipped = 0;
        $errors = [];
        $removed = [];

        $pairs = [
             
            [BASE_PATH . '/images', $img, null],
            [BASE_PATH . '/dispatch/data', $dispatch, null],
            [BASE_PATH . '/storage/janam', $janam, null],
        ];

         
        $docFilter = static function (string $name): bool {
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            return in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'zip', 'txt'], true);
        };
        $pairs[] = [BASE_PATH . '/docs', $doc, $docFilter];

         
        if (is_dir($data)) {
            foreach (scandir($data) ?: [] as $f) {
                if (!preg_match('/^(auth_config|smtp_config|registry_config|whatsapp_config|google_wallet_config|apple_wallet_config)\.php$/', $f)) {
                    continue;
                }
                $src = $data . '/' . $f;
                $dst = $config . '/' . $f;
                if (!is_file($src)) {
                    continue;
                }
                if (is_file($dst)) {
                    $skipped++;
                    continue;
                }
                if (@copy($src, $dst)) {
                    $copied++;
                    self::log('Migrated config ' . $f . ' → data/config/');
                } else {
                    $errors[] = 'Failed to copy ' . $f;
                }
            }
        }

        foreach ($pairs as [$srcDir, $dstDir, $filter]) {
            if (!is_dir($srcDir)) {
                continue;
            }
            if (!is_dir($dstDir)) {
                @mkdir($dstDir, 0775, true);
            }
            foreach (scandir($srcDir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $src = $srcDir . '/' . $f;
                if (!is_file($src)) {
                    continue;
                }
                if ($filter !== null && !$filter($f)) {
                    $skipped++;
                    continue;
                }
                $dst = $dstDir . '/' . $f;
                if (is_file($dst)) {
                     
                     
                    $skipped++;
                    continue;
                }
                if (@copy($src, $dst)) {
                    $copied++;
                    self::log('Migrated ' . self::relPath($src) . ' → ' . self::relPath($dst));
                } else {
                    $errors[] = 'Failed ' . self::relPath($src);
                    self::log('ERROR migrating ' . self::relPath($src));
                }
            }
        }

        if ($removeEmptyLegacy) {
            foreach ([BASE_PATH . '/images', BASE_PATH . '/dispatch/data', BASE_PATH . '/storage/janam', BASE_PATH . '/storage'] as $legacyDir) {
                if (!is_dir($legacyDir)) {
                    continue;
                }
                $left = array_values(array_filter(scandir($legacyDir) ?: [], static fn($x) => $x !== '.' && $x !== '..'));
                if ($left === []) {
                    if (@rmdir($legacyDir)) {
                        $removed[] = self::relPath($legacyDir);
                        self::log('Removed empty legacy folder ' . self::relPath($legacyDir));
                    }
                } else {
                    self::log('Legacy folder not empty, kept: ' . self::relPath($legacyDir) . ' (' . count($left) . ' entries)');
                }
            }
        }

        self::log("Layout migration: copied={$copied}, skipped={$skipped}, removed_dirs=" . count($removed));

        $result = [
            'copied' => $copied,
            'skipped' => $skipped,
            'removed_dirs' => $removed,
            'errors' => $errors,
        ];
        if ($asPublic) {
            $result['status'] = empty($errors) ? 'success' : 'partial';
            $result['logs'] = self::$logs;
        }
        return $result;
    }

    private static function relPath(string $abs): string
    {
        $base = str_replace('\\', '/', BASE_PATH);
        $abs = str_replace('\\', '/', $abs);
        if (str_starts_with($abs, $base . '/')) {
            return substr($abs, strlen($base) + 1);
        }
        return $abs;
    }

    private static function phaseNormalizeCompany(): void
    {
        $c = AppDB::read('company');
        if (!is_array($c)) {
            $c = [];
        }
        $schema = [
            'name' => '', 'website' => '', 'phone' => '', 'email' => '',
            'logo' => '', 'favicon' => '', 'cover' => '', 'brand_color' => '#1e3a5f',
            'social' => [],
        ];
        $out = array_merge($schema, $c);
        if (isset($out['social']) && is_string($out['social'])) {
            $decoded = json_decode($out['social'], true);
            $out['social'] = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($out['social'])) {
            $out['social'] = [];
        }
        AppDB::save('company', $out);
        self::log('Company singleton normalised.');
    }

    

    private static function phaseNormalizeList(string $ns, array $schema): void
    {
        $rows = AppDB::read($ns);
        if (!is_array($rows)) {
            $rows = [];
        }
         
        if ($ns !== 'company' && self::isAssoc($rows)) {
            $rows = array_values($rows);
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $merged = array_merge($schema, $row);
             
            if ($ns === 'team') {
                if (!empty($row['slug_locked'])) {
                    $merged['slug_locked'] = true;
                } else {
                    unset($merged['slug_locked']);
                }
            }
            if (empty($merged['id'])) {
                $merged['id'] = uniqid('', true);
            }
            $out[] = $merged;
        }
        AppDB::save($ns, $out);
        self::log(ucfirst($ns) . ': ' . count($out) . ' record(s) normalised.');
    }

    private static function phaseTeamPhonesAndNames(): void
    {
        $rows = AppDB::read('team');
        if (!is_array($rows)) {
            return;
        }
        $changed = 0;
        foreach ($rows as &$m) {
            if (!is_array($m)) {
                continue;
            }
            if (!empty($m['name']) && class_exists('mb_convert_case')) {
                $n = mb_convert_case(mb_strtolower(trim((string) $m['name']), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                if ($n !== $m['name']) {
                    $m['name'] = $n;
                    $changed++;
                }
            }
            if (!empty($m['email'])) {
                $m['email'] = strtolower(trim((string) $m['email']));
            }
            if (!empty($m['phone']) && class_exists('AppSlug')) {
                $p = AppSlug::normalizePhone((string) $m['phone']);
                if ($p !== $m['phone']) {
                    $m['phone'] = $p;
                    $changed++;
                }
            }
        }
        unset($m);
        AppDB::save('team', array_values($rows));
        self::log("Team phones/names adjusted ({$changed} field change(s)).");
    }

    private static function phaseMigrateTeamSlugs(): void
    {
        $rows = AppDB::read('team');
        if (!is_array($rows) || !class_exists('AppSlug')) {
            return;
        }
        $used = [];
        foreach ($rows as $m) {
            if (is_array($m) && !empty($m['slug'])) {
                $used[strtolower((string) $m['slug'])] = true;
            }
        }
        $migrated = 0;
        $skipped = 0;
        foreach ($rows as &$m) {
            if (!is_array($m)) {
                continue;
            }
            $slug = strtolower(trim((string) ($m['slug'] ?? '')));
            if (!empty($m['slug_locked'])) {
                $skipped++;
                continue;
            }
            if ($slug !== '' && AppSlug::isProtected($slug)) {
                $skipped++;
                continue;
            }
            if ($slug !== '' && in_array($slug, self::PROTECTED_SLUGS, true)) {
                $skipped++;
                continue;
            }
             
            if ($slug !== '') {
                continue;
            }
            $new = AppSlug::generate(
                (string) ($m['name'] ?? ''),
                (string) ($m['dob'] ?? ''),
                (string) ($m['phone'] ?? $m['mobile'] ?? '')
            );
            $base = $new;
            $i = 2;
            while (isset($used[$new])) {
                $new = $base . $i;
                $i++;
            }
            $m['slug'] = $new;
            $used[$new] = true;
            $migrated++;
        }
        unset($m);
        AppDB::save('team', array_values($rows));
        self::log("Slug migration: {$migrated} generated, {$skipped} skipped (locked/protected).");
    }

    private static function phaseRebuildTeamIndex(): void
    {
        self::phaseRebuildIndexes();
    }

    

    private static function phaseRebuildIndexes(): void
    {
        $root = self::dataRoot();
        $idxDir = $root . '/indexes';
        if (!is_dir($idxDir)) {
            @mkdir($idxDir, 0775, true);
        }

        $catalog = [
            'schema_version' => 1,
            'generated_at' => date('c'),
            'timezone' => 'Asia/Kolkata',
            'namespaces' => [],
        ];

        $namespaces = [
            'team' => 'rank_asc_name_asc',
            'events' => 'date_asc',
            'docs' => 'updated_desc',
            'bank' => 'name_asc',
            'locations' => 'name_asc',
            'departments' => 'name_asc',
            'designations' => 'rank_asc_name_asc',
            'cartags' => 'name_asc',
            'leads' => 'updated_desc',
            'cctv' => 'name_asc',
            'statutory' => 'name_asc',
        ];

        foreach ($namespaces as $ns => $order) {
            $rows = AppDB::read($ns);
            if (!is_array($rows)) {
                $rows = [];
            }
             
            if ($ns !== 'company') {
                AppDB::save($ns, $rows);
                $rows = AppDB::read($ns);
            }
            $count = is_array($rows) ? count(array_filter($rows, 'is_array')) : 0;
            $byKey = [];
            if (is_array($rows)) {
                foreach ($rows as $i => $m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    foreach (['slug', 'id', 'code', 'tag_id', 'plate', 'phone', 'mobile'] as $k) {
                        $v = strtolower(trim((string)($m[$k] ?? '')));
                        if ($v !== '') {
                            $byKey[$k . ':' . $v] = (int)$i;
                        }
                    }
                }
            }
            $idxFile = $idxDir . '/' . $ns . '.json';
            @file_put_contents(
                $idxFile,
                json_encode(['order' => $order, 'count' => $count, 'by' => $byKey], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX
            );
            $catalog['namespaces'][$ns] = [
                'count' => $count,
                'order' => $order,
                'index' => 'indexes/' . $ns . '.json',
            ];
            self::log("Index {$ns}: {$count} row(s), order={$order}");
        }

         
        $team = AppDB::read('team');
        $legacy = [];
        if (is_array($team)) {
            foreach ($team as $i => $m) {
                if (!is_array($m)) {
                    continue;
                }
                $slug = strtolower(trim((string)($m['slug'] ?? '')));
                if ($slug !== '') {
                    $legacy[$slug] = $i;
                }
                $id = strtolower(trim((string)($m['id'] ?? '')));
                if ($id !== '') {
                    $legacy[$id] = $i;
                }
            }
        }
        @file_put_contents($root . '/team_index.json', json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);

        @file_put_contents(
            $root . '/_catalog.json',
            json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
        self::log('_catalog.json written (' . count($catalog['namespaces']) . ' namespaces).');
    }

        private static function phaseTrimSessions(): void
    {
        // Idle session files > 14 days (matches cleanup scanner). Also drop
        // empty/corrupt stubs. Runs per tenant data root via runForDataPath.
        $dirs = [self::dataRoot() . '/sessions'];
        if (defined('SESSION_PATH')) {
            $sp = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string) SESSION_PATH), DIRECTORY_SEPARATOR);
            if ($sp !== '' && is_dir($sp) && !in_array($sp, $dirs, true)) {
                $dirs[] = $sp;
            }
        }
        $maxAge = 14 * 86400;
        $now = time();
        $n = 0;
        $freed = 0;
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (@scandir($dir) ?: [] as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $f;
                if (!is_file($path)) {
                    continue;
                }
                $mtime = (int) (@filemtime($path) ?: 0);
                $size = (int) (@filesize($path) ?: 0);
                $stale = ($mtime > 0 && ($now - $mtime) > $maxAge);
                $empty = ($size === 0);
                if ($stale || $empty) {
                    if (@unlink($path)) {
                        $n++;
                        $freed += $size;
                    }
                }
            }
        }
        self::log('Expired/empty sessions removed: ' . $n . ($freed > 0 ? (' (~' . round($freed / 1024) . ' KB)') : '') . '.');
    }

    private static function phaseTrimLogs(): void
    {
        $log = self::dataRoot() . '/logs/app.log';
        if (!is_file($log)) {
            return;
        }
        $size = (int) @filesize($log);
         
        if ($size > 2_000_000) {
            $data = @file($log);
            if (is_array($data) && count($data) > 5000) {
                $data = array_slice($data, -5000);
                @file_put_contents($log, implode('', $data), LOCK_EX);
                self::log('app.log trimmed to last 5000 lines.');
                return;
            }
        }
        self::log('app.log size OK (' . $size . ' bytes).');
    }

    


    /**
     * Basenames referenced by mediakit JSON (lowercase keys).
     * @return array<string,true>
     */
    private static function mediaKitBasenames(): array
    {
        $out = [];
        if (!class_exists('AppDB')) {
            return $out;
        }
        $rows = AppDB::read('mediakit');
        if (!is_array($rows)) {
            return $out;
        }
        if (isset($rows['id']) && !isset($rows[0])) {
            $rows = [$rows];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            foreach (['file', 'photo', 'doc_file', 'preview_file', 'filename', 'url'] as $k) {
                $v = trim((string)($row[$k] ?? ''));
                if ($v === '' || preg_match('~^https?://~i', $v) || str_starts_with($v, 'data:')) {
                    continue;
                }
                $bn = strtolower(basename(str_replace('\\', '/', $v)));
                if ($bn !== '') {
                    $out[$bn] = true;
                }
            }
        }
        return $out;
    }

    
    /** Media field map used by heal + verify. */
    private static function mediaFieldMap(): array
    {
        return [
            'team' => ['photo', 'image', 'avatar'],
            'company' => ['logo', 'favicon', 'cover'],
            'mediakit' => ['file', 'photo', 'doc_file', 'preview_file', 'filename', 'url', 'preview', 'image', 'asset'],
            'locations' => ['photo', 'image', 'cover', 'logo'],
            'docs' => ['file', 'doc_file', 'photo'],
            'cartags' => ['photo', 'image', 'logo'],
            'bank' => ['logo', 'image'],
            'events' => ['photo', 'image', 'cover'],
        ];
    }

    /** Directories to search for media files (RC + status). */
    private static function mediaSearchRoots(): array
    {
        $roots = [];
        $img = self::mediaImagesDir();
        $extra = [$img, $img . '/mediakit', self::dataRoot() . '/mediakit', self::dataRoot() . '/media/mediakit'];
        if (defined('IMG_PATH')) {
            $extra[] = (string) IMG_PATH;
            $extra[] = rtrim((string) IMG_PATH, '/\\') . '/mediakit';
        }
        if (defined('DOC_PATH')) {
            $extra[] = (string) DOC_PATH;
            $extra[] = rtrim((string) DOC_PATH, '/\\') . '/mediakit';
        }
        if (defined('DATA_PATH')) {
            $dp = rtrim((string) DATA_PATH, '/\\');
            $extra[] = $dp . '/images';
            $extra[] = $dp . '/images/mediakit';
            $extra[] = $dp . '/uploads';
            $extra[] = $dp . '/media';
            $extra[] = $dp . '/status';
            $extra[] = $dp . '/status/uploads';
            $extra[] = $dp . '/status/uploads/images';
            $extra[] = $dp . '/status/assets';
            $extra[] = $dp . '/status/images';
        }
        $dr = self::dataRoot();
        foreach (['/status', '/status/uploads', '/status/uploads/images', '/status/assets', '/status/images'] as $suf) {
            $extra[] = $dr . $suf;
        }
        foreach ($extra as $d) {
            $d = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, (string) $d), DIRECTORY_SEPARATOR);
            if ($d !== '' && is_dir($d)) {
                $roots[] = $d;
            }
        }
        return array_values(array_unique($roots));
    }

    /**
     * Index files under roots by lowercase basename and by stem→ext.
     * @return array{0: array<string,string>, 1: array<string,array<string,string>>}
     */
    private static function indexMediaFiles(array $roots): array
    {
        $byName = [];
        $byStem = [];
        foreach ($roots as $root) {
            try {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
                );
                $n = 0;
                foreach ($it as $fi) {
                    if (++$n > 12000) {
                        break;
                    }
                    if (!$fi->isFile()) {
                        continue;
                    }
                    $name = $fi->getFilename();
                    $byName[strtolower($name)] = $name;
                    $stem = strtolower(pathinfo($name, PATHINFO_FILENAME));
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    if ($stem !== '') {
                        $byStem[$stem][$ext] = $name;
                    }
                }
            } catch (\Throwable $e) {
                // skip unreadable roots
            }
        }
        return [$byName, $byStem];
    }

    /** Find alternate basename on disk for a missing reference. */
    private static function findAlternateBasename(string $bn, array $byName, array $byStem): string
    {
        $bn = basename(str_replace('\\', '/', $bn));
        if ($bn === '') {
            return '';
        }
        $low = strtolower($bn);
        if (isset($byName[$low])) {
            return (string) $byName[$low];
        }
        $stem = strtolower(pathinfo($bn, PATHINFO_FILENAME));
        if ($stem === '' || empty($byStem[$stem])) {
            // soft stem match: optimiser sometimes appends -hash
            foreach ($byStem as $st => $exts) {
                if (str_starts_with((string) $st, $stem) || str_starts_with($stem, (string) $st)) {
                    foreach (['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'svg'] as $pref) {
                        if (isset($exts[$pref])) {
                            return (string) $exts[$pref];
                        }
                    }
                    return (string) reset($exts);
                }
            }
            return '';
        }
        $alts = $byStem[$stem];
        foreach (['webp', 'jpg', 'jpeg', 'png', 'gif', 'avif', 'svg', 'svgz', 'bmp'] as $pref) {
            if (isset($alts[$pref])) {
                return (string) $alts[$pref];
            }
        }
        return (string) reset($alts);
    }

    /** Heal all known media JSON namespaces (not only mediakit). */
    private static function phaseHealAllMediaPaths(): void
    {
        if (!class_exists('AppDB')) {
            return;
        }
        $roots = self::mediaSearchRoots();
        [$byName, $byStem] = self::indexMediaFiles($roots);
        $totalFixed = 0;
        $totalMissing = 0;
        $nsReport = [];
        foreach (self::mediaFieldMap() as $ns => $fields) {
            $rows = AppDB::read($ns);
            if (!is_array($rows) || $rows === []) {
                continue;
            }
            $singleton = isset($rows['id']) && !isset($rows[0]);
            if ($singleton) {
                $rows = [$rows];
            }
            $fixed = 0;
            $missing = 0;
            $changed = false;
            foreach ($rows as &$row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($fields as $fk) {
                    $v = trim((string) ($row[$fk] ?? ''));
                    if ($v === '' || preg_match('~^https?://~i', $v) || str_starts_with($v, 'data:')) {
                        continue;
                    }
                    $bn = basename(str_replace('\\', '/', $v));
                    if ($bn === '') {
                        continue;
                    }
                    if (isset($byName[strtolower($bn)])) {
                        // exists — normalize case if needed
                        $actual = $byName[strtolower($bn)];
                        if ($actual !== $bn && ($v === $bn || str_ends_with($v, $bn))) {
                            $row[$fk] = (str_contains($v, '/') || str_contains($v, '\\'))
                                ? (dirname($v) === '.' ? $actual : rtrim(str_replace('\\', '/', dirname($v)), '/') . '/' . $actual)
                                : $actual;
                            $fixed++;
                            $changed = true;
                        }
                        continue;
                    }
                    $found = self::findAlternateBasename($bn, $byName, $byStem);
                    if ($found !== '' && strtolower($found) !== strtolower($bn)) {
                        $row[$fk] = (str_contains($v, '/') || str_contains($v, '\\'))
                            ? (dirname($v) === '.' ? $found : rtrim(str_replace('\\', '/', dirname($v)), '/') . '/' . $found)
                            : $found;
                        $fixed++;
                        $changed = true;
                        self::log('Path healed [' . $ns . '.' . $fk . ']: ' . $bn . ' → ' . $found);
                    } else {
                        $missing++;
                    }
                }
            }
            unset($row);
            if ($changed) {
                AppDB::save($ns, $singleton ? ($rows[0] ?? $rows) : array_values($rows));
            }
            $nsReport[$ns] = ['fixed' => $fixed, 'missing' => $missing];
            $totalFixed += $fixed;
            $totalMissing += $missing;
        }
        self::$runStats['heal'] = ['fixed' => $totalFixed, 'missing' => $totalMissing, 'namespaces' => $nsReport];
        self::log("Global media path heal: fixed={$totalFixed}, unresolved_missing={$totalMissing}.");
    }

    /** Backward-compatible alias. */
    private static function phaseHealMediaKitPaths(): void
    {
        self::phaseHealAllMediaPaths();
    }

    /**
     * Verify every media basename in JSON exists on disk; log missing links.
     */
    private static function phaseVerifyMediaLinks(): void
    {
        if (!class_exists('AppDB')) {
            return;
        }
        $roots = self::mediaSearchRoots();
        [$byName] = self::indexMediaFiles($roots);
        $ok = 0;
        $missing = 0;
        $byNs = [];
        foreach (self::mediaFieldMap() as $ns => $fields) {
            $rows = AppDB::read($ns);
            if (!is_array($rows) || $rows === []) {
                continue;
            }
            if (isset($rows['id']) && !isset($rows[0])) {
                $rows = [$rows];
            }
            $nsMiss = 0;
            $nsOk = 0;
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($fields as $fk) {
                    $v = trim((string) ($row[$fk] ?? ''));
                    if ($v === '' || preg_match('~^https?://~i', $v) || str_starts_with($v, 'data:')) {
                        continue;
                    }
                    $bn = basename(str_replace('\\', '/', $v));
                    if ($bn === '') {
                        continue;
                    }
                    if (isset($byName[strtolower($bn)])) {
                        $ok++;
                        $nsOk++;
                    } else {
                        $missing++;
                        $nsMiss++;
                        self::log('VERIFY missing [' . $ns . '.' . $fk . ']: ' . $bn);
                    }
                }
            }
            $byNs[$ns] = ['ok' => $nsOk, 'missing' => $nsMiss];
        }
        self::$runStats['verify'] = ['ok' => $ok, 'missing' => $missing, 'by_ns' => $byNs];
        self::log("Media link verify: ok={$ok}, missing={$missing}.");
    }

    /**
     * Point team designation_id / department_id / location_id at canonical master ids.
     */
    private static function phaseResolveMasterForeignKeys(): void
    {
        if (!class_exists('AppDB') || !class_exists('AppLookup')) {
            return;
        }
        $desigRec = AppLookup::recordsByKey(AppDB::read('designations') ?: []);
        $deptRec = AppLookup::recordsByKey(AppDB::read('departments') ?: []);
        $locRec = AppLookup::recordsByKey(AppDB::read('locations') ?: []);
        $team = AppDB::read('team');
        if (!is_array($team) || $team === []) {
            return;
        }
        $singleton = isset($team['id']) && !isset($team[0]);
        if ($singleton) {
            $team = [$team];
        }
        $updated = 0;
        foreach ($team as &$m) {
            if (!is_array($m)) {
                continue;
            }
            $changed = false;
            foreach (
                [
                    [['designation_id', 'designation_code', 'designation'], $desigRec, 'designation_id', 'designation_name'],
                    [['department_id', 'department_code', 'department'], $deptRec, 'department_id', 'department_name'],
                    [['location_id', 'location_code', 'location'], $locRec, 'location_id', 'location_name'],
                ] as $pack
            ) {
                [$keys, $recMap, $idField, $nameField] = $pack;
                $row = null;
                foreach ($keys as $k) {
                    $v = trim((string) ($m[$k] ?? ''));
                    if ($v === '') {
                        continue;
                    }
                    $row = $recMap[$v] ?? $recMap[strtolower($v)] ?? null;
                    if (is_array($row)) {
                        break;
                    }
                }
                if (!is_array($row)) {
                    continue;
                }
                $canon = trim((string) ($row['id'] ?? ''));
                $label = trim((string) ($row['name'] ?? $row['title'] ?? ''));
                if ($canon !== '' && (string) ($m[$idField] ?? '') !== $canon) {
                    $m[$idField] = $canon;
                    $changed = true;
                }
                if ($label !== '' && !AppLookup::isRawCode($label)) {
                    if (trim((string) ($m[$nameField] ?? '')) === '' || AppLookup::isRawCode((string) ($m[$nameField] ?? ''))) {
                        $m[$nameField] = $label;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                $updated++;
            }
        }
        unset($m);
        if ($updated > 0) {
            AppDB::save('team', $singleton ? ($team[0] ?? $team) : array_values($team));
            self::log('Master FK resolve: updated ' . $updated . ' team row(s) to canonical designation/department/location ids.');
        } else {
            self::log('Master FK resolve: no team rows needed id rewrites.');
        }
        self::$runStats['master_fk']['team_updated'] = $updated;
    }

    /**
     * Optimise Construction Status images under data/status (keep format; no forced WebP).
     */
    private static function phaseOptimiseStatusMedia(): void
    {
        $dirs = [];
        foreach (self::mediaSearchRoots() as $r) {
            if (stripos($r, 'status') !== false) {
                $dirs[] = $r;
            }
        }
        $dirs = array_values(array_unique($dirs));
        if ($dirs === []) {
            self::log('Status media: no status directories found under tenant data.');
            self::$runStats['status_media'] = ['scanned' => 0, 'changed' => 0];
            return;
        }
        $scanned = 0;
        $changed = 0;
        $maxPer = 15;
        foreach ($dirs as $dir) {
            try {
                $it = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
                );
                foreach ($it as $fi) {
                    if (!$fi->isFile()) {
                        continue;
                    }
                    $ext = strtolower($fi->getExtension());
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                        continue;
                    }
                    $full = $fi->getPathname();
                    $scanned++;
                    if ($changed >= $maxPer) {
                        continue;
                    }
                    $sz = (int) @filesize($full);
                    if ($sz > 0 && $sz <= 450000) {
                        continue;
                    }
                    $result = self::optimizeImageFile($full, [
                        'prefer_webp' => false,
                        'keep_format' => true,
                        'max_edge' => 1600,
                        'max_bytes' => 450000,
                        'quality' => 82,
                    ]);
                    if (!empty($result['ok']) && !empty($result['changed'])) {
                        $changed++;
                        self::log('Status image optimised: ' . $fi->getFilename() . ' — ' . ($result['message'] ?? 'ok'));
                    }
                }
            } catch (\Throwable $e) {
                self::log('Status media scan error: ' . $e->getMessage());
            }
        }
        self::$runStats['status_media'] = ['scanned' => $scanned, 'changed' => $changed];
        self::log("Status media: scanned={$scanned}, changed={$changed} (keep_format).");
    }




    private static function fmtBytes(int $b): string
    {
        if ($b >= 1048576) {
            return (round($b / 104857.6) / 10) . ' MB';
        }
        if ($b >= 1024) {
            return (string)(int)round($b / 1024) . ' KB';
        }
        return $b . ' B';
    }

    private static function phaseConvertImagesWebp(): void
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromjpeg')) {
            self::log('Image optimise skipped (GD imagewebp / JPEG unavailable).');
            return;
        }

        $prevMem = ini_get('memory_limit');
        @ini_set('memory_limit', '256M');

        $imgDir = self::mediaImagesDir();
        if (!is_dir($imgDir)) {
            self::log('Image optimise: no media/images directory.');
            if ($prevMem !== false && $prevMem !== '') {
                @ini_set('memory_limit', (string)$prevMem);
            }
            return;
        }

        $changed = 0;
        $untouched = 0;
        $failed = 0;
        $deferred = 0;
        $scanned = 0;
        $map = [];
        $changedLines = [];
        $untouchedReasons = [
            'already_ok' => 0,
            'unsupported' => 0,
            'protected' => 0,
            'non_image' => 0,
        ];
        $mkNames = self::mediaKitBasenames();
        self::log('Media Kit policy: keep_format=true (no forced WebP rename for mk assets).');


        $maxPerRun = 20;
        $targetBytes = 450000;
        $maxEdge = 1600;
        $changedThisRun = 0;

        $files = @scandir($imgDir) ?: [];
        self::log('Image optimise: scanning ' . $imgDir);

        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || str_contains($f, '.opt-tmp.')) {
                continue;
            }
            $full = $imgDir . DIRECTORY_SEPARATOR . $f;
            if (!is_file($full)) {
                continue;
            }
            $scanned++;

            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $untouched++;
                $untouchedReasons['unsupported']++;
                continue;
            }
            if (preg_match('/^(bank-qr-|og-)/i', $f)) {
                $untouched++;
                $untouchedReasons['protected']++;
                continue;
            }

            $sz = (int) @filesize($full);
            $info = @getimagesize($full);
            $edge = 0;
            $edgeCap = $maxEdge;
            if (is_array($info) && !empty($info[0]) && !empty($info[1])) {
                $edge = max((int) $info[0], (int) $info[1]);
                if (((int)$info[0] * (int)$info[1]) > 40000000) {
                    $edgeCap = 1200;
                }
            }

            // Already within size and edge limits
            if ($sz > 0 && $sz <= $targetBytes && $edge > 0 && $edge <= $maxEdge) {
                // Still allow non-webp non-mk to convert later only if prefer_webp wanted — treat as OK if webp OR keep_format candidates
                if ($ext === 'webp' || isset($mkNames[strtolower($f)]) || str_starts_with(strtolower($f), 'mk-')) {
                    $untouched++;
                    $untouchedReasons['already_ok']++;
                    continue;
                }
                // raster already small — leave untouched (no forced webp when already under budget)
                $untouched++;
                $untouchedReasons['already_ok']++;
                continue;
            }
            if ($sz > 0 && $sz <= $targetBytes && $edge === 0 && $ext === 'webp') {
                $untouched++;
                $untouchedReasons['already_ok']++;
                continue;
            }

            if ($changedThisRun >= $maxPerRun) {
                $deferred++;
                continue;
            }

            $memLimit = self::parseIniBytes((string)ini_get('memory_limit'));
            $memUsed = memory_get_usage(true);
            if ($memLimit > 0 && ($memLimit - $memUsed) < 16 * 1048576) {
                self::log('Image optimise paused — low memory (' . round($memUsed / 1048576, 1) . 'M used). Re-run to continue.');
                $deferred++;
                break;
            }

            $isMk = isset($mkNames[strtolower($f)]) || str_starts_with(strtolower($f), 'mk-');
            try {
                $result = self::optimizeImageFile($full, [
                    'max_edge' => $edgeCap,
                    'quality' => 80,
                    'prefer_webp' => !$isMk,
                    'keep_format' => $isMk,
                    'max_bytes' => $targetBytes,
                ]);
            } catch (\Throwable $ie) {
                $failed++;
                self::log('CHANGED failed: ' . $f . ' — exception: ' . $ie->getMessage());
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                continue;
            }

            if (!empty($result['ok'])) {
                $changed++;
                $changedThisRun++;
                $newName = (string)($result['name'] ?? $f);
                $b0 = (int)($result['bytes_before'] ?? $sz);
                $b1 = (int)($result['bytes_after'] ?? $b0);
                if ($newName !== '' && $newName !== $f) {
                    $map[$f] = $newName;
                }
                $line = sprintf(
                    'CHANGED: %s → %s (%s → %s, %s)',
                    $f,
                    $newName,
                    self::fmtBytes($b0),
                    self::fmtBytes($b1),
                    $b0 > 0 ? (round((1 - $b1 / $b0) * 100) . '% smaller') : 'n/a'
                );
                $changedLines[] = $line;
                self::log($line);
            } else {
                $msg = (string)($result['message'] ?? 'error');
                // Treat soft no-ops as untouched
                if (in_array($msg, ['already_ok', 'no_gain', 'skipped'], true)) {
                    $untouched++;
                    $untouchedReasons['already_ok']++;
                } else {
                    $failed++;
                    self::log('FAILED: ' . $f . ' — ' . $msg);
                }
            }
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $rewritten = 0;
        if ($map && class_exists('AppDB')) {
            $mapCi = [];
            foreach ($map as $from => $to) {
                $mapCi[strtolower((string)$from)] = (string)$to;
            }
            $applyMap = static function (string $v) use ($map, $mapCi): ?string {
                $v = trim($v);
                if ($v === '' || preg_match('~^https?://~i', $v) || str_starts_with($v, 'data:')) {
                    return null;
                }
                $bn = basename(str_replace('\\', '/', $v));
                if ($bn === '') {
                    return null;
                }
                if (isset($map[$bn])) {
                    return $map[$bn];
                }
                $low = strtolower($bn);
                if (isset($mapCi[$low])) {
                    return $mapCi[$low];
                }
                return null;
            };
            $patchList = static function (string $ns, array $fields) use ($applyMap, &$rewritten): void {
                $rows = AppDB::read($ns);
                if (!is_array($rows)) {
                    return;
                }
                $changedNs = false;
                $isSingleton = isset($rows['id']) && !isset($rows[0]);
                if ($isSingleton) {
                    $rows = [$rows];
                }
                foreach ($rows as &$m) {
                    if (!is_array($m)) {
                        continue;
                    }
                    foreach ($fields as $fk) {
                        $v = (string)($m[$fk] ?? '');
                        $neu = $applyMap($v);
                        if ($neu !== null) {
                            $m[$fk] = $neu;
                            if ($fk === 'file' || $fk === 'photo' || $fk === 'doc_file') {
                                $m['filename'] = $neu;
                                $m['file_type'] = strtolower(pathinfo($neu, PATHINFO_EXTENSION));
                                $m['preview_file'] = $neu;
                            }
                            $changedNs = true;
                            $rewritten++;
                        }
                    }
                }
                unset($m);
                if ($changedNs) {
                    AppDB::save($ns, $isSingleton ? ($rows[0] ?? $rows) : array_values($rows));
                }
            };
            $patchList('team', ['photo', 'image', 'avatar']);
            $patchList('company', ['logo', 'favicon', 'cover']);
            $patchList('mediakit', ['file', 'photo', 'doc_file', 'preview_file', 'filename', 'url']);
            $patchList('locations', ['photo', 'image', 'cover']);
            $patchList('docs', ['file', 'doc_file', 'photo']);
            $patchList('cartags', ['photo', 'image', 'logo']);
            $patchList('bank', ['logo', 'image']);
            $patchList('events', ['photo', 'image', 'cover']);
            if ($rewritten > 0) {
                self::log('JSON path updates: ' . $rewritten . ' field(s) rewired after rename.');
                foreach ($map as $from => $to) {
                    self::log('  ref map: ' . $from . ' → ' . $to);
                }
            }
        }

        self::$runStats['images'] = [
            'scanned' => $scanned,
            'changed' => $changed,
            'untouched' => $untouched,
            'failed' => $failed,
            'deferred' => $deferred,
            'json_refs_updated' => $rewritten,
            'already_ok' => $untouchedReasons['already_ok'],
            'protected' => $untouchedReasons['protected'],
        ];
        self::log(sprintf(
            'Image optimise summary: scanned=%d · changed=%d · untouched=%d (already OK=%d, protected=%d, other=%d) · failed=%d · deferred=%d · JSON refs updated=%d',
            $scanned,
            $changed,
            $untouched,
            $untouchedReasons['already_ok'],
            $untouchedReasons['protected'],
            $untouchedReasons['unsupported'] + $untouchedReasons['non_image'],
            $failed,
            $deferred,
            $rewritten
        ));
        if ($changedLines !== []) {
            self::log('Changed files (' . count($changedLines) . '):');
            foreach ($changedLines as $line) {
                self::log('  • ' . $line);
            }
        } else {
            self::log('Changed files: none (all eligible images already within limits or deferred).');
        }
        if ($deferred > 0) {
            self::log('Deferred ' . $deferred . ' file(s) for a later run (max ' . $maxPerRun . ' changes per run).');
        }

        if ($prevMem !== false && $prevMem !== '') {
            @ini_set('memory_limit', (string)$prevMem);
        }
    }

    private static function phaseRebuildDataCache(): void
    {
        if (!class_exists('AppDataCache')) {
            $f = BASE_PATH . '/app/DataCache.php';
            if (is_file($f)) {
                require_once $f;
            }
        }
        if (!class_exists('AppDataCache')) {
            self::log('Data cache rebuild skipped (AppDataCache missing).');
            return;
        }
        AppDataCache::flushAll();
        $done = AppDataCache::rebuildFromAppDb();
        $n = count(array_filter($done, static fn($v) => is_int($v)));
        self::log("SQLite/file data cache rebuilt ({$n} namespaces). sqlite=" . (AppDataCache::sqliteAvailable() ? 'yes' : 'no'));
    }

    private static function phasePublicTeamAndHealth(): void
    {
        if (!class_exists('PublicTeam') && is_file(BASE_PATH . '/app/PublicTeam.php')) {
            require_once BASE_PATH . '/app/PublicTeam.php';
        }
        if (class_exists('PublicTeam')) {
            $ok = PublicTeam::writeFromTeam();
            self::log($ok ? 'team_public.json refreshed.' : 'team_public.json write failed.');
        }
        $root = self::dataRoot();
        $checks = [
            'data_writable' => is_dir($root) && is_writable($root),
            'images_writable' => is_dir($root . '/media/images') && is_writable($root . '/media/images'),
            'team_exists' => is_file($root . '/team.json'),
            'map_readable' => is_readable(BASE_PATH . '/tenants/map.json'),
        ];
        $checks['ok'] = !in_array(false, $checks, true);
        $checks['checked_at'] = date('c');
        @file_put_contents($root . '/_health.json', json_encode($checks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        self::log('Health: ' . ($checks['ok'] ? 'OK' : 'ISSUES'));
    }

    private static function phaseLogRotateAndBackupHint(): void
    {
        if (!class_exists('TenantBackup') && is_file(BASE_PATH . '/app/TenantBackup.php')) {
            require_once BASE_PATH . '/app/TenantBackup.php';
        }
        if (class_exists('TenantBackup')) {
            if (TenantBackup::rotateLogs(self::dataRoot())) {
                self::log('Log rotated for tenant data root.');
            }
        }
    }

    private static function phaseOrphanMedia(): void
    {
         
        $scan = self::scanCleanup();
        $n = (int) ($scan['count'] ?? 0);
        $bytes = (int) ($scan['bytes'] ?? 0);
        self::log("Orphan media scan: {$n} file(s), ~{$bytes} bytes (delete via Cleanup UI).");
    }

     

     

    

    private static function parseIniBytes(string $val): int
    {
        $val = trim($val);
        if ($val === '' || $val === '-1') {
            return 0;  
        }
        if (!preg_match('/^(\d+)\s*([KMG])?$/i', $val, $m)) {
            return (int) $val;
        }
        $n = (int) $m[1];
        $u = strtoupper($m[2] ?? '');
        if ($u === 'G') {
            return $n * 1073741824;
        }
        if ($u === 'M') {
            return $n * 1048576;
        }
        if ($u === 'K') {
            return $n * 1024;
        }
        return $n;
    }

    private static function discoverNormalizeTargets(): array
    {
        $known = [
            'team' => self::teamSchema(),
            'bank' => self::bankSchema(),
            'docs' => self::docsSchema(),
            'events' => self::eventsSchema(),
            'locations' => self::locationsSchema(),
            'departments' => self::departmentsSchema(),
            'designations' => self::designationsSchema(),
            'statutory' => self::statutorySchema(),
            'cartags' => self::cartagsSchema(),
            'cctv' => method_exists(__CLASS__, 'cctvSchema')
                ? self::cctvSchema()
                : ['id' => '', 'name' => '', 'url' => '', 'location' => ''],
            'leads' => ['id' => '', 'name' => '', 'phone' => '', 'email' => '', 'company' => '', 'status' => ''],
        ];
         
        $skip = [
            'map', 'analytics', 'team_index', 'team_public', 'optimizer_last_run',
            'card_analytics', 'audit_log', 'attendance', 'assets', 'visits',
            'roles', 'geofences', 'runners_state', 'policy_settings', 'settings',
        ];
        $root = self::dataRoot();
        $found = $known;
        if (is_dir($root)) {
            foreach (@scandir($root) ?: [] as $f) {
                if ($f === '.' || $f === '..' || !str_ends_with(strtolower($f), '.json')) {
                    continue;
                }
                $ns = substr($f, 0, -5);
                if ($ns === '' || isset($found[$ns]) || in_array($ns, $skip, true)) {
                    continue;
                }
                if (str_starts_with($ns, '_') || str_starts_with($ns, '.')) {
                    continue;
                }
                $path = $root . '/' . $f;
                $raw = @file_get_contents($path);
                $data = is_string($raw) ? json_decode($raw, true) : null;
                if (!is_array($data)) {
                    continue;
                }
                 
                $sample = null;
                if (array_is_list($data) && isset($data[0]) && is_array($data[0])) {
                    $sample = $data[0];
                } elseif (!array_is_list($data) && isset($data['id'])) {
                    $sample = $data;
                }
                if ($sample === null) {
                    continue;
                }
                $schema = ['id' => ''];
                foreach (array_keys($sample) as $k) {
                    if (!is_string($k) || $k === '') {
                        continue;
                    }
                    $schema[$k] = is_bool($sample[$k] ?? null) ? false : (is_int($sample[$k] ?? null) ? 0 : '');
                }
                $found[$ns] = $schema;
            }
        }
        return $found;
    }

    

    public static function optimizeImageFile(string $path, array $opts = []): array
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $before = is_file($path) ? (int) @filesize($path) : 0;
        $name = basename($path);
        $empty = ['ok' => false, 'path' => $path, 'name' => $name, 'bytes_before' => $before, 'bytes_after' => $before];

        if (!is_file($path) || !is_readable($path)) {
            return $empty + ['message' => 'not_found'];
        }
        if (!function_exists('imagecreatetruecolor')) {
            return $empty + ['message' => 'gd_missing'];
        }

        $maxEdge = (int) ($opts['max_edge'] ?? 1600);
        $quality = (int) ($opts['quality'] ?? 80);
        $preferWebp = (bool) ($opts['prefer_webp'] ?? true);
        $keepFormat = (bool) ($opts['keep_format'] ?? false);
        if ($keepFormat) {
            $preferWebp = false;
        }
        $maxBytes = (int) ($opts['max_bytes'] ?? 450000);  
        if ($maxEdge < 320) {
            $maxEdge = 320;
        }
        if ($quality < 40) {
            $quality = 40;
        }
        if ($quality > 95) {
            $quality = 95;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $empty + ['message' => 'unsupported_ext'];
        }

        $info = @getimagesize($path);
        if (!is_array($info) || empty($info[0]) || empty($info[1])) {
            return $empty + ['message' => 'bad_image'];
        }

        $src = null;
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $src = @imagecreatefromjpeg($path);
            if ($src && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                if (!empty($exif['Orientation'])) {
                    $src = match ((int) $exif['Orientation']) {
                        3 => imagerotate($src, 180, 0),
                        6 => imagerotate($src, -90, 0),
                        8 => imagerotate($src, 90, 0),
                        default => $src,
                    };
                }
            }
        } elseif ($ext === 'png') {
            $src = @imagecreatefrompng($path);
            if ($src) {
                @imagepalettetotruecolor($src);
                @imagealphablending($src, true);
                @imagesavealpha($src, true);
            }
        } elseif ($ext === 'gif') {
            $src = @imagecreatefromgif($path);
        } elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) {
            $src = @imagecreatefromwebp($path);
        }
        if (!$src) {
            return $empty + ['message' => 'decode_failed'];
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w < 1 || $h < 1) {
            unset($src); /* GdImage auto-freed (PHP 8.5) */
            return $empty + ['message' => 'zero_dim'];
        }

         
        $needScale = max($w, $h) > $maxEdge || $before > $maxBytes;
        if ($needScale && max($w, $h) > $maxEdge) {
            $scale = $maxEdge / max($w, $h);
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
                imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
                imagealphablending($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                unset($src); /* GdImage auto-freed (PHP 8.5) */
                $src = $dst;
                $w = $nw;
                $h = $nh;
            }
        } elseif ($needScale && $before > $maxBytes && max($w, $h) > 800) {
             
            $scale = 0.85;
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            if ($dst) {
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
                unset($src); /* GdImage auto-freed (PHP 8.5) */
                $src = $dst;
            }
        }

        $dir = dirname($path);
        $base = pathinfo($path, PATHINFO_FILENAME);
        $useWebp = $preferWebp && function_exists('imagewebp') && !$keepFormat;
        if ($keepFormat) {
            // Preserve original extension for Media Kit (and any caller that sets keep_format)
            $outExt = ($ext === 'jpeg') ? 'jpg' : $ext;
        } else {
            $outExt = $useWebp ? 'webp' : (($ext === 'png') ? 'png' : 'jpg');
        }
        $tmpOut = $dir . DIRECTORY_SEPARATOR . $base . '.opt-tmp.' . $outExt;

        $ok = false;
        if ($outExt === 'webp') {
            $ok = @imagewebp($src, $tmpOut, $quality);
        } elseif ($outExt === 'png') {
            $ok = @imagepng($src, $tmpOut, 6);
        } else {
            $ok = @imagejpeg($src, $tmpOut, $quality);
        }
        unset($src); /* GdImage auto-freed (PHP 8.5) */

        if (!$ok || !is_file($tmpOut)) {
            @unlink($tmpOut);
            return $empty + ['message' => 'encode_failed'];
        }

        $after = (int) @filesize($tmpOut);
         
        if ($after > $maxBytes && $outExt === 'webp' && $quality > 55) {
            $src2 = @imagecreatefromwebp($tmpOut);
            if ($src2) {
                $q2 = max(50, $quality - 15);
                @imagewebp($src2, $tmpOut, $q2);
                unset($src2); /* GdImage auto-freed (PHP 8.5) */
                $after = (int) @filesize($tmpOut);
            }
        }

        $finalName = $base . '.' . $outExt;
        $finalPath = $dir . DIRECTORY_SEPARATOR . $finalName;

         
        if (!@rename($tmpOut, $finalPath)) {
            @copy($tmpOut, $finalPath);
            @unlink($tmpOut);
        }
        if (is_file($finalPath) && realpath($path) && realpath($path) !== realpath($finalPath)) {
            @unlink($path);
        }
        @chmod($finalPath, 0664);

        return [
            'ok' => true,
            'path' => $finalPath,
            'name' => $finalName,
            'bytes_before' => $before,
            'bytes_after' => is_file($finalPath) ? (int) filesize($finalPath) : $after,
            'message' => 'optimised',
        ];
    }

    private static function teamSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '', 'phone' => '', 'email' => '',
            'photo' => '', 'dob' => '', 'time_of_birth' => '', 'place_of_birth' => '',
            'gender' => '', 'blood_group' => '', 'gotra' => '',
            'designation_id' => '', 'department_id' => '', 'location_id' => '',
            'social' => [],
        ];
    }

     
    private static function bankSchema(): array
    {
         
         
         
         
         
         
         
         
         
        return [
            'id' => '', 'slug' => '', 'bank_name' => '', 'holder_name' => '',
            'acc_no' => '', 'ifsc' => '', 'branch' => '', 'upi_id' => '', 'qr_image' => '',
        ];
    }

     
    private static function docsSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'slug' => '', 'doc_file' => '',
            'file_type' => '', 'size' => '', 'version' => '', 'external_url' => '',
            'updated_at' => '',
        ];
    }

     
    private static function eventsSchema(): array
    {
        return [
            'id' => '', 'name' => '', 'date' => '', 'location' => '',
            'description' => '', 'url' => '', 'is_virtual' => false,
        ];
    }

     
    private static function locationsSchema(): array
    {
        return [
            'id' => '', 'slug' => '', 'name' => '', 'address' => '',
            'city' => '', 'state' => '', 'pincode' => '', 'map_url' => '',
            'logo' => '', 'photo' => '',
        ];
    }

     
    private static function departmentsSchema(): array
    {
        return ['id' => '', 'code' => '', 'name' => '', 'slug' => ''];
    }

     
    private static function designationsSchema(): array
    {
        return ['id' => '', 'code' => '', 'name' => '', 'rank' => 0, 'slug' => '', 'mandatory_live_tracking' => false];
    }

     
    private static function statutorySchema(): array
    {
        return [
            'id' => '', 'company_name' => '', 'cin' => '', 'pan' => '', 'tan' => '',
            'gst' => '', 'lei' => '', 'roc_code' => '', 'date_of_incorporation' => '',
            'msme' => '', 'dpiit_startup' => '', 'bank_account' => '',
            'esic' => '', 'pf_code' => '', 'isin' => '', 'demat_id' => '',
            'phone' => '', 'email' => '', 'address' => '',
            'development_office' => '', 'gst_sales_office' => '',
            'rera' => '', 'rera_project_name' => '',
            'rera_phase_1' => '', 'rera_phase_2' => '', 'rera_phase_3' => '',
        ];
    }

     
    private static function cartagsSchema(): array
    {
        return [
            'id' => '', 'tag_id' => '', 'registration_number' => '', 'plate' => '',
            'make_model' => '', 'colour' => '', 'vehicle_class' => '',
            'owner_name' => '', 'member_id' => '', 'photo' => '',
        ];
    }

     

     
    private static function collectReferencedFiles(): array
    {
        $ref = [];
        $add = static function ($v) use (&$ref): void {
            if (!$v || !is_string($v)) {
                return;
            }
             
             
             
             
            if (preg_match('~[?&]f=([^&#]+)~', $v, $mm)) {
                $v = rawurldecode($mm[1]);
            } elseif (str_starts_with($v, 'http://') || str_starts_with($v, 'https://')) {
                return;  
            }
             
            $v = (string) strtok($v, '?#');
            $base = strtolower(basename(str_replace('\\', '/', $v)));
            if ($base !== '') {
                $ref[$base] = true;
            }
        };

        $company = AppDB::read('company');
        if (is_array($company)) {
            foreach (['logo', 'favicon', 'cover'] as $k) {
                $add($company[$k] ?? '');
            }
        }
         
        $nsList = ['team', 'docs', 'cartags', 'bank', 'locations', 'events',
                   'leads', 'statutory', 'departments', 'designations', 'cctv', 'mediakit'];
        $keys = ['photo', 'image', 'doc_file', 'logo', 'cover', 'favicon',
                 'qr_image', 'banner', 'avatar', 'file', 'preview_file', 'filename'];
        foreach ($nsList as $ns) {
            $rows = AppDB::read($ns);
            if (!is_array($rows)) {
                continue;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $add($row[$k] ?? '');
                }
            }
        }
         
         
         
         
        foreach ([BASE_PATH . '/status/data/projects.json', BASE_PATH . '/status/projects.json'] as $pf) {
            if (!is_file($pf)) {
                continue;
            }
            $pj = json_decode((string) @file_get_contents($pf), true);
            if (!is_array($pj)) {
                continue;
            }
            foreach ($pj as $proj) {
                if (!is_array($proj)) {
                    continue;
                }
                foreach ($keys as $k) {
                    $add($proj[$k] ?? '');
                }
            }
        }
        return $ref;
    }

    private static function isThrottled(): bool
    {
        $file = self::dataRoot() . '/' . self::THROTTLE_FILE;
        if (!is_file($file)) {
            return false;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        $ts = (int) ($data['ts'] ?? 0);
        return $ts > 0 && (time() - $ts) < self::THROTTLE_SECONDS;
    }

    private static function markRan(): void
    {
        $file = self::dataRoot() . '/' . self::THROTTLE_FILE;
        @file_put_contents($file, json_encode(['ts' => time(), 'version' => defined('APP_VERSION') ? APP_VERSION : '']), LOCK_EX);
    }

    private static function log(string $msg): void
    {
        self::$logs[] = $msg;
        if (class_exists('AppLog')) {
            AppLog::info('Optimizer: ' . $msg);
        }
    }

    private static function isAssoc(array $arr): bool
    {
        if ($arr === []) {
            return false;
        }
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    

    public static function allTenantDataRoots(): array
    {
        $roots = [];
        $tenantsDir = BASE_PATH . '/tenants';
        if (is_dir($tenantsDir)) {
            if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
                require_once BASE_PATH . '/app/TenantPaths.php';
            }
            $ids = class_exists('TenantPaths') ? TenantPaths::listIds() : [];
            if ($ids === []) {
                foreach (scandir($tenantsDir) ?: [] as $d) {
                    if ($d === '.' || $d === '..' || str_starts_with((string) $d, '.') || $d === 'map.json') {
                        continue;
                    }
                    if (preg_match('/\.(md|txt|json|htaccess)$/i', (string) $d)) {
                        continue;
                    }
                    if (!is_dir($tenantsDir . '/' . $d . '/data')) {
                        continue;
                    }
                    $ids[] = $d;
                }
            }
            foreach ($ids as $id) {
                $p = $tenantsDir . '/' . $id . '/data';
                if (is_dir($p)) {
                    $roots[(string) $id] = $p;
                }
            }
        }
        $legacy = BASE_PATH . '/data';
        if (is_dir($legacy) && !isset($roots['default'])) {
            $roots['_legacy'] = $legacy;
        }
        return $roots;
    }

    

    private static function scanRubbishUnder(string $root, string $tid, callable $add): void
    {
        if (!is_dir($root)) {
            return;
        }
        $rootNorm = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        try {
            $iter = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootNorm, FilesystemIterator::SKIP_DOTS)
            );
        } catch (\Throwable $e) {
            return;
        }
        foreach ($iter as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $name = $file->getFilename();
            $full = $file->getPathname();
            if (!self::isRubbishFileName($name)) {
                continue;
            }
             
            if (preg_match('/\.(json|php)$/i', $name) && !preg_match('/(\.bak|~|\.orig)$/i', $name)) {
                continue;
            }
            $rel = 'tenants/' . $tid . '/' . ltrim(str_replace('\\', '/', substr($full, strlen($rootNorm))), '/');
            if ($tid === 'status-legacy') {
                $rel = 'status/uploads/' . ltrim(str_replace('\\', '/', substr($full, strlen($rootNorm))), '/');
            }
            $add('rubbish', $rel, $name, (int) $file->getSize(), 'Unused backup/junk — safe to remove', $full);
        }
    }

    private static function isRubbishFileName(string $name): bool
    {
        if ($name === 'Thumbs.db' || $name === '.DS_Store' || strcasecmp($name, 'desktop.ini') === 0) {
            return true;
        }
        if (str_ends_with($name, '~') || str_ends_with($name, '.bak') || str_ends_with($name, '.orig') || str_ends_with($name, '.tmp')) {
            return true;
        }
        if (preg_match('/(-copy|_copy|_old|\.bak-|~)$/i', $name)) {
            return true;
        }
        if (preg_match('/\.(bak|old|orig|tmp)(\.|$)/i', $name)) {
            return true;
        }
        return false;
    }

    

    private static function phaseRubbishSweep(): void
    {
        $root = self::dataRoot();
        $n = 0;
        $freed = 0;
        $targets = [$root];
        $legacyUp = BASE_PATH . '/status/uploads';
        if (is_dir($legacyUp)) {
            $targets[] = $legacyUp;
        }
        foreach ($targets as $base) {
            if (!is_dir($base)) {
                continue;
            }
            try {
                $iter = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
                );
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($iter as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $name = $file->getFilename();
                if (!self::isRubbishFileName($name)) {
                    continue;
                }
                if (preg_match('/\.(json|php)$/i', $name) && !preg_match('/(\.bak|~|\.orig)$/i', $name)) {
                    continue;
                }
                $full = $file->getPathname();
                $sz = (int) $file->getSize();
                if (@unlink($full)) {
                    $n++;
                    $freed += $sz;
                }
            }
        }
        if ($n > 0) {
            self::log('Rubbish sweep: removed ' . $n . ' unused file(s), freed ~' . round($freed / 1024) . ' KB');
        } else {
            self::log('Rubbish sweep: nothing to remove');
        }
    }

    


    private static function phaseDocsCleanup(): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
        $keep = ['DEPLOY.md'=>1,'DATA_AND_TENANTS.md'=>1,'THEME_COMPLIANCE.md'=>1,'WHATSNEW.md'=>1,'README.md'=>1];
        $n = 0;
        $docs = $base . '/docs';
        if (is_dir($docs)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docs, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) {
                $path = $f->getPathname();
                if ($f->isDir()) { @rmdir($path); continue; }
                $name = $f->getFilename();
                if (isset($keep[$name])) continue;
                if (preg_match('/\.(md|txt)$/i', $name)) {
                    if (@unlink($path)) $n++;
                }
            }
        }
        foreach ([$base.'/assets/TAILWIND.md',$base.'/status/INTEGRATION.md',$base.'/status/RELEASE-v14.1.md'] as $extra) {
            if (is_file($extra) && @unlink($extra)) $n++;
        }
        $docRoot = $base.'/documentation';
        if (is_dir($docRoot)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
            @rmdir($docRoot);
            $n++;
        }
        foreach ([
            $base.'/README-FA.txt', $base.'/msg.txt', $base.'/docs/_REMOVE_THESE_IF_PRESENT.txt',
            $base.'/deploy/SIMCOAUTO_LINUX.txt', $base.'/status/ROOT-robots-additions.txt',
            $base.'/status/legacy-redirects/README.txt', $base.'/assets/TAILWIND.md',
            $base.'/status/INTEGRATION.md', $base.'/status/RELEASE-v14.1.md',
        ] as $extra) {
            if (is_file($extra) && @unlink($extra)) { $n++; }
        }
        self::log($n > 0 ? ('Docs cleanup: removed '.$n.' obsolete file(s)') : 'Docs cleanup: nothing to remove');
    }

    public static function runAllTenants(bool $force = true): array
    {
        $allLogs = [];
        $results = [];
        self::phaseDocsCleanup();
        foreach (self::$logs as $_dl) { $allLogs[] = $_dl; }
        self::$logs = [];
        $allLogs[] = 'Global multi-tenant optimizer started (IST ' . date('d-m-Y H:i:s') . ').';

        $roots = [];
        $tenantsDir = BASE_PATH . '/tenants';
        if (is_dir($tenantsDir)) {
            if (!class_exists('TenantPaths') && is_file(BASE_PATH . '/app/TenantPaths.php')) {
                require_once BASE_PATH . '/app/TenantPaths.php';
            }
            $ids = class_exists('TenantPaths') ? TenantPaths::listIds() : [];
            if ($ids === []) {
                foreach (scandir($tenantsDir) ?: [] as $d) {
                    if ($d === '.' || $d === '..' || str_starts_with((string)$d, '.') || $d === 'map.json') continue;
                    if (preg_match('/\.(md|txt|json|htaccess)$/i', (string)$d)) continue;
                    $folder = $tenantsDir . '/' . $d;
                    if (!@is_dir($folder)) continue;
                    $ids[] = $d;
                }
            }
            foreach ($ids as $d) {
                $folder = $tenantsDir . '/' . $d;
                if (!@is_dir($folder)) continue;
                $data = $folder . '/data';
                if (!is_dir($data)) {
                    @mkdir($data, 0775, true);
                }
                $roots[$d] = $data;
            }
        }
        $legacy = BASE_PATH . '/data';
        if (is_dir($legacy) && !isset($roots['default'])) {
            $roots['_legacy'] = $legacy;
        }
        if ($roots === []) {
            $cur = defined('DATA_PATH') ? DATA_PATH : $legacy;
            $roots[defined('TENANT_ID') ? (string) TENANT_ID : 'current'] = $cur;
        }

        foreach ($roots as $tid => $path) {
            $allLogs[] = '--- Tenant: ' . $tid . ' ---';
            $allLogs[] = 'Data path: ' . $path;
            $teamFile = '';
            foreach (['team.json'] as $tf) {
                if (is_file($path . '/' . $tf)) {
                    $teamFile = $path . '/' . $tf;
                    break;
                }
            }
            if ($teamFile !== '') {
                $pre = json_decode((string)@file_get_contents($teamFile), true);
                $preN = is_array($pre) ? count(array_filter($pre, 'is_array')) : 0;
                $allLogs[] = 'Pre-scan team.json records: ' . $preN;
            } else {
                $allLogs[] = 'Pre-scan team.json records: 0 (file missing — will seed empty template if needed)';
            }
            if (function_exists('rc_storage_self_heal')) {
                rc_storage_self_heal($path);
            }
             
             
            $activeData = defined('DATA_PATH') ? rtrim(str_replace(["\\", "/"], DIRECTORY_SEPARATOR, (string)DATA_PATH), DIRECTORY_SEPARATOR) : '';
            $thisData = rtrim(str_replace(["\\", "/"], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            $doImages = ($activeData !== '' && strcasecmp($activeData, $thisData) === 0);
            $r = self::runForDataPath($path, $force, !$doImages);
            $results[] = [
                'tenant' => $tid,
                'path'   => $path,
                'status' => $r['status'] ?? 'unknown',
            ];
            foreach ($r['logs'] ?? [] as $line) {
                $allLogs[] = '[' . $tid . '] ' . $line;
            }
        }

        if (!class_exists('TenantBackup') && is_file(BASE_PATH . '/app/TenantBackup.php')) {
            require_once BASE_PATH . '/app/TenantBackup.php';
        }
        if (class_exists('TenantBackup')) {
            try {
                $bak = TenantBackup::backupAllTenants();
                $allLogs[] = !empty($bak['ok']) ? ('Backup: ' . ($bak['message'] ?? 'ok')) : ('Backup: ' . ($bak['message'] ?? 'failed'));
                $week = TenantBackup::weeklySundayBackup(false);
                $allLogs[] = 'Weekly Sunday backup: ' . ($week['message'] ?? '');
            } catch (\Throwable $be) {
                $allLogs[] = 'Backup skipped: ' . $be->getMessage();
            }
        }
        $allLogs[] = 'Global multi-tenant optimizer finished. Tenants processed: ' . count($results);
        self::$logs = $allLogs;
        return [
            'status'  => 'success',
            'logs'    => $allLogs,
            'tenants' => $results,
            'message' => 'Optimized ' . count($results) . ' tenant data root(s)',
        ];
    }

    

    private static function runForDataPath(string $dataPath, bool $force = true, bool $lightweight = false): array
    {
        self::$logs = [];
        $prev = $GLOBALS['RC_OPT_DATA_PATH'] ?? null;
        $GLOBALS['RC_OPT_DATA_PATH'] = $dataPath;
        try {
            if (class_exists('AppDB') && method_exists('AppDB', 'clearCache')) {
                AppDB::clearCache();
            }
            self::log('Optimizer started for ' . $dataPath);
            self::phaseEnsureDirs();
             

            self::phaseNormalizeCompany();
             
            foreach (self::discoverNormalizeTargets() as $ns => $schema) {
                if ($ns === 'company') {
                    continue;  
                }
                self::phaseNormalizeList($ns, $schema);
            }
            self::phaseTeamPhonesAndNames();
            self::phaseMigrateTeamSlugs();
            self::phaseRebuildTeamIndex();
            self::phaseTrimSessions();
            self::phaseTrimLogs();
            self::phaseOrphanMedia();
            self::phaseRubbishSweep();
            if (!$lightweight) {
                self::phaseConvertImagesWebp();
            } else {
                self::log('WebP convert skipped (multi-tenant lightweight pass).');
            }
            self::phaseRebuildDataCache();
            self::phasePublicTeamAndHealth();
            self::phaseLogRotateAndBackupHint();
            self::markRan();
            return ['status' => 'success', 'logs' => self::$logs];
        } catch (\Throwable $e) {
            self::log('ERROR: ' . $e->getMessage());
            return ['status' => 'error', 'logs' => self::$logs, 'message' => $e->getMessage()];
        } finally {
            if ($prev === null) {
                unset($GLOBALS['RC_OPT_DATA_PATH']);
            } else {
                $GLOBALS['RC_OPT_DATA_PATH'] = $prev;
            }
            if (class_exists('AppDB') && method_exists('AppDB', 'clearCache')) {
                AppDB::clearCache();
            }
        }
    }

}
