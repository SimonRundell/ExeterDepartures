<?php
/**
 * Shared CORS handling. Include first in every endpoint.
 *
 * Reflects localhost origins on any port (Vite dev server) and any origin
 * listed in allowedOrigins in .config.json. Answers OPTIONS preflight.
 *
 * @license CC BY-NC-SA 4.0
 */

require_once __DIR__ . '/config.php';

date_default_timezone_set('Europe/London');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$settings = readConfigFile() ?? [];
$allowed = $settings['allowedOrigins'] ?? [];

if ($origin !== '' && (preg_match('#^https?://localhost(:\d+)?$#', $origin) || in_array($origin, $allowed, true))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit();
}
