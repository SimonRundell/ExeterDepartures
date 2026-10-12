<?php
/**
 * Live Departure Boards Staff Web Service (LDBSVWS) REST client, accessed via
 * the Rail Data Marketplace. Darwin's modern API product is plain JSON over
 * HTTPS, authenticated with an x-apikey header, so no SOAP extension or
 * hand-built XML envelope is needed.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Fetch a departure board with calling point details.
 *
 * @param array  $config Validated config.
 * @param string $from   Origin CRS.
 * @param string $to     Filter CRS (services calling at this station).
 * @param int    $rows   Maximum rows. The API accepts 1-9 for this operation.
 * @return array The decoded StationBoardWithDetails object.
 * @throws RuntimeException On transport, HTTP or API error.
 */
function fetchDepartureBoard(array $config, string $from, string $to, int $rows = 9): array
{
    $time = (new DateTime('now', new DateTimeZone('Europe/London')))->format('Ymd\THis');
    $query = http_build_query([
        'numRows' => max(1, min(9, $rows)),
        'filterCrs' => $to,
        'filterType' => 'to',
        'timeWindow' => 120,
    ]);
    $url = rtrim($config['darwin']['endpoint'], '/')
        . '/GetDepBoardWithDetails/' . rawurlencode($from) . '/' . $time
        . '?' . $query;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPGET => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            'x-apikey: ' . $config['darwin']['apiKey'],
            'Accept: application/json',
        ],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('Darwin unreachable: ' . $err);
    }
    $data = json_decode($body, true);
    if (!is_array($data)) {
        throw new RuntimeException('Darwin returned unreadable data (HTTP ' . $status . ')');
    }
    if ($status !== 200) {
        $message = (string) ($data['message'] ?? $data['error'] ?? ('HTTP ' . $status));
        throw new RuntimeException('Darwin error: ' . $message);
    }
    return $data;
}
