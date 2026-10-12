<?php
/**
 * Tiny JSON file cache with TTL. Keys must come from the destination whitelist.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Build the cache file path for a key.
 *
 * @param string $key Whitelisted key (letters and digits only).
 * @return string Absolute file path.
 */
function cachePath(string $key): string
{
    $safe = preg_replace('/[^A-Za-z0-9_]/', '', $key);
    return __DIR__ . '/cache/' . $safe . '.json';
}

/**
 * Read a cache entry.
 *
 * @param string $key Cache key.
 * @return array|null ['time' => int, 'data' => array] or null if absent.
 */
function cacheRead(string $key): ?array
{
    $path = cachePath($key);
    if (!is_file($path)) {
        return null;
    }
    $entry = json_decode((string) file_get_contents($path), true);
    return is_array($entry) && isset($entry['time'], $entry['data']) ? $entry : null;
}

/**
 * Write a cache entry atomically (temp file then rename).
 *
 * @param string $key  Cache key.
 * @param array  $data Data to store.
 * @return void
 */
function cacheWrite(string $key, array $data): void
{
    $path = cachePath($key);
    $tmp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, json_encode(['time' => time(), 'data' => $data])) !== false) {
        @rename($tmp, $path);
    }
}

/**
 * Age of an entry in seconds.
 *
 * @param array|null $entry Entry from cacheRead().
 * @return int|null Age in seconds, or null when there is no entry.
 */
function cacheAge(?array $entry): ?int
{
    return $entry ? time() - (int) $entry['time'] : null;
}
