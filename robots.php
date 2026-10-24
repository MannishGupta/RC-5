<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
}
require_once BASE_PATH . '/app/tenant_bootstrap.php';

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
      || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
$host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$base = ($https ? 'https://' : 'http://') . $host;

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$lines = [];
$lines[] = '# Resource Centre — organic search & AI discovery (India)';
$lines[] = '# Host: ' . $base;
$lines[] = '# Locale: en-IN / hi-IN · Timezone: Asia/Kolkata';
$lines[] = '';
$lines[] = 'User-agent: *';
$lines[] = 'Allow: /';
foreach (['team','bank','docs','locations','events','statutory','status','terms','mediakit'] as $tab) {
    $lines[] = 'Allow: /?tab=' . $tab;
}
$lines[] = 'Allow: /cards/';
$lines[] = 'Allow: /images/';
$lines[] = 'Allow: /assets/';
$lines[] = 'Allow: /status/';
$lines[] = 'Allow: /metals.php';
$lines[] = 'Allow: /choghadiya.php';
$lines[] = 'Allow: /llms.txt';
$lines[] = 'Allow: /sitemap.php';
$lines[] = 'Allow: /health.php';
$lines[] = '';
foreach (['monitor','opt','settings','company','tenants','cctv','leads','access'] as $tab) {
    $lines[] = 'Disallow: /?tab=' . $tab;
}
foreach (['/data/','/tenants/','/app/','/tools/','/storage/','/tests/','/sql/'] as $path) {
    $lines[] = 'Disallow: ' . $path;
}
$lines[] = '';
$aiAgents = ['GPTBot','ChatGPT-User','Google-Extended','PerplexityBot','ClaudeBot','anthropic-ai','Bytespider'];
foreach ($aiAgents as $ua) {
    $lines[] = 'User-agent: ' . $ua;
    $lines[] = 'Allow: /';
    $lines[] = 'Allow: /llms.txt';
    $lines[] = 'Allow: /sitemap.php';
    $lines[] = 'Disallow: /data/';
    $lines[] = 'Disallow: /tenants/';
    $lines[] = 'Disallow: /app/';
    $lines[] = 'Disallow: /tools/';
    $lines[] = '';
}
$lines[] = 'Sitemap: ' . $base . '/sitemap.php';
$lines[] = '';

echo implode("\n", $lines);
