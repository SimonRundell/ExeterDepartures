<?php
/**
 * Converts a Darwin StationBoardWithDetails object into the normalised
 * service objects the UI uses. Pure functions, no I/O, so they can be
 * tested against a saved JSON fixture.
 *
 * Darwin's REST API returns std/etd/sta/eta/atd/ata as full ISO 8601
 * datetimes in local (Europe/London) time, not the bare "HH:MM"/"On time"
 * strings the legacy SOAP service used, so differences are computed from
 * real DateTime objects rather than a minutes-since-midnight heuristic.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Parse a Darwin ISO datetime string.
 *
 * @param mixed $value Raw value from the API, or null/absent.
 * @return DateTimeImmutable|null Parsed datetime, or null if absent/invalid.
 */
function parseDarwinTime($value): ?DateTimeImmutable
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    try {
        return new DateTimeImmutable($value, new DateTimeZone('Europe/London'));
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Format a parsed Darwin time as "HH:MM".
 *
 * @param DateTimeImmutable|null $dt Parsed time.
 * @return string|null "HH:MM", or null if absent.
 */
function formatHm(?DateTimeImmutable $dt): ?string
{
    return $dt !== null ? $dt->format('H:i') : null;
}

/**
 * Whole minutes from $from to $to (can be negative).
 *
 * @param DateTimeImmutable $from Earlier time.
 * @param DateTimeImmutable $to   Later time.
 * @return int Signed minutes.
 */
function minutesDiff(DateTimeImmutable $from, DateTimeImmutable $to): int
{
    return (int) round(($to->getTimestamp() - $from->getTimestamp()) / 60);
}

/**
 * Work out status, expected time and lateness from a service's std/etd/atd.
 *
 * @param DateTimeImmutable|null $std       Scheduled time.
 * @param DateTimeImmutable|null $etd       Forecast time, if given.
 * @param DateTimeImmutable|null $atd       Actual time, if the train has already left.
 * @param bool                   $cancelled True when the service is cancelled.
 * @return array{status:string, expected:DateTimeImmutable|null, minutesLate:int}
 */
function computeStatus(?DateTimeImmutable $std, ?DateTimeImmutable $etd, ?DateTimeImmutable $atd, bool $cancelled): array
{
    if ($cancelled) {
        return ['status' => 'cancelled', 'expected' => $std, 'minutesLate' => 0];
    }
    $actual = $atd ?? $etd;
    if ($std === null || $actual === null) {
        // No live forecast yet (common near the edge of the time window).
        // Show the scheduled time rather than guessing at a delay.
        return ['status' => 'ontime', 'expected' => $std, 'minutesLate' => 0];
    }
    return ['status' => 'ontime', 'expected' => $actual, 'minutesLate' => max(0, minutesDiff($std, $actual))];
}

/**
 * Decide which section a scheduled time belongs to.
 *
 * @param DateTimeImmutable|null $std     Scheduled departure.
 * @param array                  $windows Config windows (morningEnd, afternoonStart).
 * @return string "morning", "afternoon" or "other".
 */
function sectionFor(?DateTimeImmutable $std, array $windows): string
{
    if ($std === null) {
        return 'other';
    }
    $hm = $std->format('H:i');
    if ($hm < $windows['morningEnd']) {
        return 'morning';
    }
    if ($hm >= $windows['afternoonStart']) {
        return 'afternoon';
    }
    return 'other';
}

/**
 * Whether a ServiceLocation entry is a genuine public calling point (not a
 * passing location or operational-only junction).
 *
 * @param array $loc A subsequentLocations entry.
 * @return bool True when it is a real, public stop.
 */
function isPublicStop(array $loc): bool
{
    return !empty($loc['crs']) && empty($loc['isPass']) && empty($loc['isOperational']);
}

/**
 * Extract human-readable reason text, if any. Darwin's REST API returns
 * cancelReason/delayReason as a reason-code object ({near, Value}), not
 * text — resolving the code requires a separate GetReasonCode lookup we
 * don't make, so there is no text to show for that case.
 *
 * @param mixed $value Raw cancelReason/delayReason value.
 * @return string|null Reason text, or null when none is available.
 */
function reasonText($value): ?string
{
    return is_string($value) && trim($value) !== '' ? trim($value) : null;
}

/**
 * Normalise one Darwin ServiceItemWithLocations entry.
 *
 * Adds callingAt: every public stop still to come, in order, with the tab
 * destination flagged.
 *
 * @param array  $svc     The decoded service object (trainServices[n]).
 * @param string $destCrs CRS of the tab destination.
 * @param array  $config  Validated config.
 * @return array The normalised service.
 */
function normaliseService(array $svc, string $destCrs, array $config): array
{
    $std = parseDarwinTime($svc['std'] ?? null);
    $etd = parseDarwinTime($svc['etd'] ?? null);
    $atd = parseDarwinTime($svc['atd'] ?? null);
    $cancelled = !empty($svc['isCancelled']);
    $status = computeStatus($std, $etd, $atd, $cancelled);

    $locations = $svc['subsequentLocations'] ?? [];
    $arrival = null;
    $duration = null;
    $between = [];
    $reached = false;
    $callingAt = [];
    foreach ($locations as $loc) {
        if (isPublicStop($loc)) {
            $callingAt[] = [
                'name' => (string) $loc['locationName'],
                'isTabDestination' => strcasecmp((string) $loc['crs'], $destCrs) === 0,
            ];
        }
        if (!$reached && strcasecmp((string) ($loc['crs'] ?? ''), $destCrs) === 0) {
            $locTime = parseDarwinTime($loc['etd'] ?? null) ?? parseDarwinTime($loc['eta'] ?? null)
                ?? parseDarwinTime($loc['std'] ?? null) ?? parseDarwinTime($loc['sta'] ?? null);
            $arrival = formatHm($locTime);
            $duration = ($std !== null && $locTime !== null) ? minutesDiff($std, $locTime) : null;
            $reached = true;
            continue;
        }
        if (!$reached && isPublicStop($loc)) {
            $between[] = (string) $loc['locationName'];
        }
    }
    if (!$reached) {
        $between = [];
    }

    $destinations = $svc['destination'] ?? [];
    $final = $destinations ? (string) (end($destinations)['locationName'] ?? '') : '';
    $platform = trim((string) ($svc['platform'] ?? ''));

    return [
        'id' => (string) ($svc['rid'] ?? $svc['uid'] ?? $svc['trainid'] ?? ''),
        'scheduledDeparture' => formatHm($std),
        'expectedDeparture' => formatHm($status['expected']),
        'status' => $status['status'],
        'minutesLate' => $status['minutesLate'],
        'cancelled' => $status['status'] === 'cancelled',
        'cancelReason' => reasonText($svc['cancelReason'] ?? null),
        'delayReason' => reasonText($svc['delayReason'] ?? null),
        'platform' => $platform !== '' ? $platform : null,
        'platformConfirmed' => $platform !== '' && empty($svc['platformIsHidden']),
        'finalDestination' => $final,
        'operator' => (string) ($svc['operator'] ?? ''),
        'operatorCode' => (string) ($svc['operatorCode'] ?? ''),
        'arrivalAtTabDestination' => $arrival,
        'durationMinutes' => $duration,
        'stopsBetween' => $reached ? count($between) : null,
        'callingPoints' => $between,
        'callingAt' => $callingAt,
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
    $raw = $board['trainServices'] ?? [];
    // Sort by the raw ISO std first: it carries a full date, so this sorts
    // correctly across midnight with no rollover arithmetic needed.
    usort($raw, fn(array $a, array $b): int => strcmp((string) ($a['std'] ?? ''), (string) ($b['std'] ?? '')));
    $services = [];
    foreach ($raw as $svc) {
        $services[] = normaliseService($svc, $dest['crs'], $config);
    }
    $messages = [];
    foreach ($board['nrccMessages'] ?? [] as $msg) {
        $messages[] = trim(strip_tags(html_entity_decode((string) ($msg['xhtmlMessage'] ?? ''))));
    }
    return ['services' => $services, 'messages' => $messages];
}
