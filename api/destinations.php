<?php
/**
 * GET /api/destinations.php
 *
 * Returns only the non-secret configuration the frontend needs.
 *
 * @license CC BY-NC-SA 4.0
 */

require_once __DIR__ . '/cors.php';   // always first
require_once __DIR__ . '/config.php';

$config = getConfig();
echo json_encode([
    'origin' => $config['origin'],
    'destinations' => $config['destinations'],
    'windows' => $config['windows'],
    'ui' => $config['ui'],
]);
