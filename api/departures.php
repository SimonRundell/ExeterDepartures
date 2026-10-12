<?php
/**
 * GET /api/departures.php?dest=EXM
 *
 * Returns normalised departures from the origin station to a whitelisted
 * destination, cached on the server. Add &mock=1 for fixture data.
 *
 * @license CC BY-NC-SA 4.0
 */

require_once __DIR__ . '/cors.php';   // always first
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/ldbws.php';
require_once __DIR__ . '/normalise.php';
require_once __DIR__ . '/mock.php';

$config = getConfig();
$dest = findDestination((string) ($_GET['dest'] ?? ''));
if ($dest === null) {
    failJson(400, 'Unknown destination.');
}

/**
 * Wrap a body in the standard response envelope.
 *
 * @param array $dest      Destination entry.
 * @param array $body      Services and messages.
 * @param bool  $fromCache Whether the data came from cache.
 * @param bool  $stale     Whether the data is stale after an upstream failure.
 * @param int   $time      Unix time the data was generated.
 * @return array Envelope.
 */
function envelope(array $dest, array $body, bool $fromCache, bool $stale, int $time): array
{
    return [
        'destination' => ['crs' => $dest['crs'], 'name' => $dest['name']],
        'generatedAt' => date('c', $time),
        'fromCache' => $fromCache,
        'stale' => $stale,
        'services' => $body['services'],
        'messages' => $body['messages'],
    ];
}

if (!empty($_GET['mock'])) {
    echo json_encode(envelope($dest, mockBoard($dest, $config), false, false, time()));
    exit();
}

$key = $dest['crs'];
$entry = cacheRead($key);
$age = cacheAge($entry);
if ($entry !== null && $age < $config['cacheSeconds']) {
    echo json_encode(envelope($dest, $entry['data'], true, false, (int) $entry['time']));
    exit();
}

try {
    $board = fetchDepartureBoard($config, $config['origin']['crs'], $dest['crs'], 15);
    $body = normaliseBoard($board, $dest, $config);
    cacheWrite($key, $body);
    echo json_encode(envelope($dest, $body, false, false, time()));
} catch (Throwable $e) {
    error_log('departures.php: ' . $e->getMessage());
    if ($entry !== null) {
        echo json_encode(envelope($dest, $entry['data'], true, true, (int) $entry['time']));
    } else {
        failJson(502, 'Train data is temporarily unavailable.');
    }
}
