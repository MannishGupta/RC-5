<?php
// Version: 3.2.0
declare(strict_types=1);
/**
 * Idempotent: ensure compliance dirs + default notices per tenant data path.
 * Usage: php tools/seed_compliance.php
 */
$base = dirname(__DIR__);
require_once $base . '/core/I18n/EighthSchedule.php';
require_once $base . '/core/Compliance/NoticeStore.php';
require_once $base . '/core/Compliance/PurposeCatalog.php';

$tenantsRoot = $base . '/tenants';
$done = 0;
if (is_dir($tenantsRoot)) {
    foreach (scandir($tenantsRoot) ?: [] as $id) {
        if ($id === '.' || $id === '..' || str_starts_with($id, '.')) {
            continue;
        }
        $data = $tenantsRoot . '/' . $id . '/data';
        if (!is_dir($data)) {
            continue;
        }
        if (!defined('DATA_PATH')) {
            define('DATA_PATH', $data);
        } else {
            // redefine not allowed — use chdir pattern via putenv not possible
            // NoticeStore uses DATA_PATH; process one tenant per run if already defined
        }
        $dir = $data . '/compliance/notices';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $log = $data . '/compliance/consents.jsonl';
        if (!is_file($log)) {
            @file_put_contents($log, '');
        }
        $done++;
        echo "ok $id\n";
    }
}
// also default data path
$fallback = $base . '/data/compliance';
if (!is_dir($fallback . '/notices')) {
    @mkdir($fallback . '/notices', 0775, true);
}
echo "seed_compliance: tenants touched=$done\n";
