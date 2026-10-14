<?php
/**
 * GET /api/departures.php?dest=EXM
 *
 * Returns normalised services between the origin station and a whitelisted
 * destination, cached on the server. Two directions are merged: arrivals
 * into the origin from the destination (morning, the college commute in)
 * and departures from the origin to the destination (afternoon, going
 * home). Add &mock=1 for fixture data.
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

if (empty($config['darwin']['apiKey']) || $config['darwin']['apiKey'] === 'REPLACE_WITH_API_KEY') {
    failJson(500, 'Server configuration error.');
}

$originCrs = $config['origin']['crs'];

$arrivalBody = null;
try {
    $arrivalBoard = fetchDepartureBoard($config, $dest['crs'], $originCrs, 9);
    $arrivalBody = normaliseArrivalBoard($arrivalBoard, $originCrs, $config);
} catch (Throwable $e) {
    error_log('departures.php (arrivals from ' . $dest['crs'] . '): ' . $e->getMessage());
}

$departureBody = null;
try {
    $departureBoard = fetchDepartureBoard($config, $originCrs, $dest['crs'], 9);
    $departureBody = normaliseBoard($departureBoard, $dest, $config);
} catch (Throwable $e) {
    error_log('departures.php (departures to ' . $dest['crs'] . '): ' . $e->getMessage());
}

if ($arrivalBody === null && $departureBody === null) {
    if ($entry !== null) {
        echo json_encode(envelope($dest, $entry['data'], true, true, (int) $entry['time']));
    } else {
        failJson(502, 'Train data is temporarily unavailable.');
    }
    exit();
}

$body = [
    'services' => array_merge($arrivalBody['services'] ?? [], $departureBody['services'] ?? []),
    'messages' => array_values(array_unique(array_merge($arrivalBody['messages'] ?? [], $departureBody['messages'] ?? []))),
];
cacheWrite($key, $body);
echo json_encode(envelope($dest, $body, false, false, time()));
