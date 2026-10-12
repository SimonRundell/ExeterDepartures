<?php
/**
 * GET /api/health.php
 *
 * Server time and cache age per destination. No secrets.
 *
 * @license CC BY-NC-SA 4.0
 */

require_once __DIR__ . '/cors.php';   // always first
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';

$config = getConfig();
$ages = [];
foreach ($config['destinations'] as $dest) {
    $ages[$dest['crs']] = cacheAge(cacheRead($dest['crs']));
}
echo json_encode([
    'ok' => true,
    'serverTime' => date('c'),
    'soapExtension' => class_exists('SoapClient'),
    'curl' => function_exists('curl_init'),
    'cacheWritable' => is_writable(__DIR__ . '/cache'),
    'cacheAgeSeconds' => $ages,
]);
