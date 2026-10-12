<?php
/**
 * Converts a Darwin board into the normalised service objects the UI uses.
 * Pure functions, no I/O, so they can be tested against saved XML.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Convert "HH:MM" to minutes since midnight.
 *
 * @param string $hhmm Time string.
 * @return int|null Minutes, or null if not a valid time.
 */
function toMinutes(string $hhmm): ?int
{
    if (!preg_match('/^(\d{1,2}):(\d{2})/', trim($hhmm), $m)) {
        return null;
    }
    return ((int) $m[1]) * 60 + (int) $m[2];
}

/**
 * Minutes from $from to $to, allowing for passing midnight.
 *
 * @param string $from Earlier time "HH:MM".
 * @param string $to   Later time "HH:MM".
 * @return int|null Minutes difference (0..1439), or null if either is invalid.
 */
function minutesBetween(string $from, string $to): ?int
{
    $a = toMinutes($from);
    $b = toMinutes($to);
    if ($a === null || $b === null) {
        return null;
    }
    return ($b - $a + 1440) % 1440;
}

/**
 * Work out status, expected time and lateness from Darwin's std/etd.
 *
 * @param string $std       Scheduled time.
 * @param string $etd       "On time", "Delayed", "Cancelled" or an HH:MM time.
 * @param bool   $cancelled True when the service is cancelled.
 * @param int    $threshold Minutes late at or below which counts as on time.
 * @return array{status:string, expected:string, minutesLate:int}
 */
function computeStatus(string $std, string $etd, bool $cancelled, int $threshold = 1): array
{
    if ($cancelled || strcasecmp($etd, 'Cancelled') === 0) {
        return ['status' => 'cancelled', 'expected' => $std, 'minutesLate' => 0];
    }
    if (strcasecmp($etd, 'On time') === 0) {
        return ['status' => 'ontime', 'expected' => $std, 'minutesLate' => 0];
    }
    $late = toMinutes($etd) !== null ? minutesBetween($std, $etd) : null;
    if ($late === null) {
        return ['status' => 'delayed', 'expected' => $std, 'minutesLate' => 0];
    }
    // A big "negative" result means the train is running early across midnight; treat as on time.
    if ($late > 720) {
        $late = 0;
    }
    return [
        'status' => $late <= $threshold ? 'ontime' : 'late',
        'expected' => $etd,
        'minutesLate' => $late,
    ];
}

/**
 * Decide which section a scheduled time belongs to.
 *
 * @param string $std     Scheduled departure "HH:MM".
 * @param array  $windows Config windows (morningEnd, afternoonStart).
 * @return string "morning", "afternoon" or "other".
 */
function sectionFor(string $std, array $windows): string
{
    $m = toMinutes($std);
    if ($m === null) {
        return 'other';
    }
    if ($m < toMinutes($windows['morningEnd'])) {
        return 'morning';
    }
    if ($m >= toMinutes($windows['afternoonStart'])) {
        return 'afternoon';
    }
    return 'other';
}

/**
 * Normalise one Darwin ServiceItemWithLocations entry.
 *
 * @param array  $svc     The decoded service object (trainServices[n]).
 * @param string $destCrs CRS of the tab destination.
 * @param array  $config  Validated config.
 * @return array The normalised service.
 */
function normaliseService(array $svc, string $destCrs, array $config): array
{
    $std = (string) ($svc['std'] ?? '');
    $etd = (string) ($svc['etd'] ?? '');
    $cancelled = !empty($svc['isCancelled']) || strcasecmp($etd, 'Cancelled') === 0;
    $status = computeStatus($std, $etd, $cancelled, 1);

    $points = [];
    foreach ($svc['subsequentLocations'] ?? [] as $loc) {
        $etdLoc = (string) ($loc['etd'] ?? '');
        $points[] = [
            'name' => (string) ($loc['locationName'] ?? ''),
            'crs' => (string) ($loc['crs'] ?? ''),
            'time' => $etdLoc !== '' && toMinutes($etdLoc) !== null ? $etdLoc : (string) ($loc['std'] ?? ''),
        ];
    }

    $arrival = null;
    $duration = null;
    $between = [];
    $reached = false;
    foreach ($points as $p) {
        if (strcasecmp($p['crs'], $destCrs) === 0) {
            $arrival = $p['time'];
            $duration = minutesBetween($std, $p['time']);
            $reached = true;
            break;
        }
        $between[] = $p['name'];
    }
    if (!$reached) {
        $between = [];
    }

    $destinations = $svc['destination'] ?? [];
    $final = $destinations ? (string) (end($destinations)['locationName'] ?? '') : ($points ? end($points)['name'] : '');
    $platform = trim((string) ($svc['platform'] ?? ''));

    return [
        'id' => (string) ($svc['rid'] ?? $svc['uid'] ?? $svc['trainid'] ?? ''),
        'scheduledDeparture' => $std,
        'expectedDeparture' => $status['expected'],
        'status' => $status['status'],
        'minutesLate' => $status['minutesLate'],
        'cancelled' => $status['status'] === 'cancelled',
        'cancelReason' => trim((string) ($svc['cancelReason'] ?? '')) ?: null,
        'delayReason' => trim((string) ($svc['delayReason'] ?? '')) ?: null,
        'platform' => $platform !== '' ? $platform : null,
        'platformConfirmed' => $platform !== '' && empty($svc['platformIsHidden']),
        'finalDestination' => $final,
        'operator' => (string) ($svc['operator'] ?? ''),
        'operatorCode' => (string) ($svc['operatorCode'] ?? ''),
        'arrivalAtTabDestination' => $arrival,
        'durationMinutes' => $duration,
        'stopsBetween' => $reached ? count($between) : null,
        'callingPoints' => $between,
        'section' => sectionFor($std, $config['windows']),
    ];
}

/**
 * Normalise a whole board.
 *
 * @param array $board  Decoded StationBoardWithDetails object.
 * @param array $dest   Destination entry (crs, name).
 * @param array $config Validated config.
 * @return array Response body (services sorted, plus NRCC messages).
 */
function normaliseBoard(array $board, array $dest, array $config): array
{
    $services = [];
    foreach ($board['trainServices'] ?? [] as $svc) {
        $services[] = normaliseService($svc, $dest['crs'], $config);
    }
    // Sort by scheduled time, treating early-hours services as after late-night ones.
    $base = toMinutes(date('H:i'));
    usort($services, function (array $a, array $b) use ($base): int {
        $ka = (toMinutes($a['scheduledDeparture']) - $base + 1440 + 120) % 1440;
        $kb = (toMinutes($b['scheduledDeparture']) - $base + 1440 + 120) % 1440;
        return $ka <=> $kb;
    });
    $messages = [];
    foreach ($board['nrccMessages'] ?? [] as $msg) {
        $messages[] = trim(strip_tags(html_entity_decode((string) ($msg['xhtmlMessage'] ?? ''))));
    }
    return ['services' => $services, 'messages' => $messages];
}
