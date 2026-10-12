<?php
/**
 * Configuration loader for the Exeter departures API.
 *
 * Reads .config.json from one directory above /api when present (so it can sit
 * outside the web root), otherwise from the project root. Never echoes secrets.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Locate and decode .config.json without validating it.
 *
 * @return array|null Decoded config, or null when missing or malformed.
 */
function readConfigFile(): ?array
{
    $candidates = [
        dirname(__DIR__, 2) . '/.config.json',
        dirname(__DIR__) . '/.config.json',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            $data = json_decode((string) file_get_contents($path), true);
            return is_array($data) ? $data : null;
        }
    }
    return null;
}

/**
 * Send a JSON error and stop.
 *
 * @param int    $status  HTTP status code.
 * @param string $message Safe, generic message.
 * @return never
 */
function failJson(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit();
}

/**
 * Load, default and validate the configuration. Cached per request.
 *
 * @return array The validated configuration.
 */
function getConfig(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $raw = readConfigFile();
    if ($raw === null) {
        failJson(500, 'Server configuration error.');
    }
    $defaults = [
        'windows' => ['morningEnd' => '09:00', 'afternoonStart' => '16:00', 'afternoonLiveFrom' => '14:00'],
        'cacheSeconds' => 30,
        'allowedOrigins' => [],
        'ui' => ['pollSeconds' => 30, 'rotateSeconds' => 20, 'pauseOnTouchSeconds' => 60, 'lateThresholdMinutes' => 2],
    ];
    $config = array_replace_recursive($defaults, $raw);
    foreach (['origin', 'destinations', 'darwin'] as $key) {
        if (empty($config[$key])) {
            failJson(500, 'Server configuration error.');
        }
    }
    if (empty($config['darwin']['endpoint']) || empty($config['darwin']['apiKey'])) {
        failJson(500, 'Server configuration error.');
    }
    $config['cacheSeconds'] = max(30, (int) $config['cacheSeconds']);
    return $config;
}

/**
 * Find a configured destination by CRS code (whitelist check).
 *
 * @param string $crs Requested CRS code.
 * @return array|null The destination entry, or null if not configured.
 */
function findDestination(string $crs): ?array
{
    foreach (getConfig()['destinations'] as $dest) {
        if (strcasecmp($dest['crs'], $crs) === 0) {
            return $dest;
        }
    }
    return null;
}
