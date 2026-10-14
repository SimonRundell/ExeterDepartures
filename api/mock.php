<?php
/**
 * Mock departure data so the UI can run without a Darwin API key.
 * Covers on time, late, very late, cancelled, delayed, no platform, non-stop
 * and a midnight rollover, for both directions (arrivals into the origin
 * for the morning section, departures from the origin for the afternoon
 * section). Builds the same shape Darwin's REST API returns: full ISO
 * datetimes for std/etd, not bare "HH:MM" strings.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * The on time / late / cancelled / delayed / no-platform / non-stop test
 * cases shared by both directions.
 *
 * @return array[] Case definitions (off, etd, plat, stops, dur).
 */
function mockCases(): array
{
    return [
        ['off' => 8,  'etd' => 'On time', 'plat' => '3', 'stops' => 2, 'dur' => 31],
        ['off' => 23, 'etd' => '+7',      'plat' => '5', 'stops' => 4, 'dur' => 44],
        ['off' => 38, 'etd' => '+14',     'plat' => '',  'stops' => 0, 'dur' => 26],
        ['off' => 53, 'etd' => 'Cancelled', 'plat' => '4', 'stops' => 3, 'dur' => 37],
        ['off' => 68, 'etd' => 'Delayed', 'plat' => '',  'stops' => 1, 'dur' => 33],
        ['off' => 83, 'etd' => '+1',      'plat' => '2', 'stops' => 2, 'dur' => 31],
        ['off' => 98, 'etd' => 'On time', 'plat' => '1', 'stops' => 0, 'dur' => 22],
    ];
}

/**
 * Build mock departures FROM the origin TO a destination (the afternoon,
 * going-home direction).
 *
 * @param DateTimeImmutable $today  Midnight today.
 * @param array[]           $starts Minute offsets from midnight to anchor each batch of cases at.
 * @param array              $dest   Destination entry (crs, name).
 * @return array[] Raw service entries, shaped like Darwin's REST response.
 */
function mockDepartureServices(DateTimeImmutable $today, array $starts, array $dest): array
{
    $finals = ['EXM' => 'Exmouth', 'PLY' => 'Penzance', 'OKE' => 'Okehampton', 'BNP' => 'Barnstaple', 'TVP' => 'Taunton'];
    $final = $finals[$dest['crs']] ?? $dest['name'];
    $stopNames = ['St Thomas', 'Newton Abbot', 'Totnes', 'Ivybridge'];
    $cases = mockCases();
    $raw = [];
    foreach ($starts as $s => $start) {
        foreach ($cases as $i => $c) {
            if ($s > 0 && $i > 2) {
                continue;
            }
            $dep = $today->modify('+' . ($start + $c['off']) . ' minutes');
            $late = str_starts_with($c['etd'], '+') ? (int) substr($c['etd'], 1) : 0;
            $svc = [
                'std' => $dep->format('Y-m-d\TH:i:s'),
                'platform' => $c['plat'],
                'operator' => 'Great Western Railway',
                'operatorCode' => 'GW',
                'rid' => 'MOCKDEP' . $s . $i . $dest['crs'],
                'destination' => [['locationName' => $final]],
            ];
            if ($c['etd'] === 'Cancelled') {
                $svc['isCancelled'] = true;
                $svc['cancelReason'] = 'This train has been cancelled because of a shortage of train crew';
            } elseif ($c['etd'] === 'Delayed') {
                $svc['delayReason'] = 'A fault with the signalling system';
                // No etd: an explicit "known delay, no estimate yet" case.
            } else {
                $svc['etd'] = $dep->modify("+{$late} minutes")->format('Y-m-d\TH:i:s');
                if ($late > 10) {
                    $svc['delayReason'] = 'A fault with the signalling system';
                }
            }
            $locations = [];
            for ($k = 0; $k < $c['stops']; $k++) {
                $stopTime = $dep->modify('+' . (6 + $k * 5) . ' minutes');
                $locations[] = [
                    'locationName' => $stopNames[$k % 4],
                    'crs' => 'ZZ' . $k,
                    'std' => $stopTime->format('Y-m-d\TH:i:s'),
                    'etd' => $stopTime->format('Y-m-d\TH:i:s'),
                ];
            }
            $destTime = $dep->modify('+' . $c['dur'] . ' minutes');
            $locations[] = [
                'locationName' => $dest['name'],
                'crs' => $dest['crs'],
                'std' => $destTime->format('Y-m-d\TH:i:s'),
                'etd' => $destTime->modify("+{$late} minutes")->format('Y-m-d\TH:i:s'),
            ];
            $svc['subsequentLocations'] = $locations;
            $raw[] = $svc;
        }
    }
    return $raw;
}

/**
 * Build mock departures FROM a destination TO the origin (the morning,
 * arriving-at-college direction), in the same shape Darwin returns when
 * queried from the destination's own board.
 *
 * @param DateTimeImmutable $today  Midnight today.
 * @param array              $dest   Destination entry (crs, name).
 * @param array              $config Validated config.
 * @return array[] Raw service entries, shaped like Darwin's REST response.
 */
function mockArrivalServices(DateTimeImmutable $today, array $dest, array $config): array
{
    $originCrs = $config['origin']['crs'] ?? 'EXD';
    $originName = $config['origin']['name'] ?? 'Exeter St David\'s';
    $stopNames = ['Digby & Sowton', 'Polsloe Bridge', 'Exeter Central'];
    $cases = mockCases();
    // Anchor to the morning window regardless of wall-clock time, so the
    // morning section always has something to show in mock mode.
    $starts = [6 * 60 + 30];
    $raw = [];
    foreach ($starts as $s => $start) {
        foreach ($cases as $i => $c) {
            $dep = $today->modify('+' . ($start + $c['off']) . ' minutes');
            $late = str_starts_with($c['etd'], '+') ? (int) substr($c['etd'], 1) : 0;
            $svc = [
                'std' => $dep->format('Y-m-d\TH:i:s'),
                'platform' => $c['plat'],
                'operator' => 'Great Western Railway',
                'operatorCode' => 'GW',
                'rid' => 'MOCKARR' . $s . $i . $dest['crs'],
                'origin' => [['locationName' => $dest['name']]],
            ];
            if ($c['etd'] === 'Cancelled') {
                $svc['isCancelled'] = true;
                $svc['cancelReason'] = 'This train has been cancelled because of a shortage of train crew';
            } elseif ($c['etd'] === 'Delayed') {
                $svc['delayReason'] = 'A fault with the signalling system';
            }
            $locations = [];
            for ($k = 0; $k < $c['stops']; $k++) {
                $stopTime = $dep->modify('+' . (6 + $k * 5) . ' minutes');
                $locations[] = [
                    'locationName' => $stopNames[$k % 3],
                    'crs' => 'ZZ' . $k,
                    'std' => $stopTime->format('Y-m-d\TH:i:s'),
                    'etd' => $stopTime->format('Y-m-d\TH:i:s'),
                ];
            }
            $arrTime = $dep->modify('+' . $c['dur'] . ' minutes');
            $location = [
                'locationName' => $originName,
                'crs' => $originCrs,
                'sta' => $arrTime->format('Y-m-d\TH:i:s'),
                'platform' => $c['plat'],
            ];
            if ($c['etd'] !== 'Delayed' && $c['etd'] !== 'Cancelled') {
                $location['eta'] = $arrTime->modify("+{$late} minutes")->format('Y-m-d\TH:i:s');
            }
            $locations[] = $location;
            $svc['subsequentLocations'] = $locations;
            $raw[] = $svc;
        }
    }
    return $raw;
}

/**
 * Build mock services for a destination, covering both directions.
 *
 * @param array $dest   Destination entry (crs, name).
 * @param array $config Validated config.
 * @return array Response body fields (services, messages).
 */
function mockBoard(array $dest, array $config): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));
    $today = $now->setTime(0, 0);
    $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
    // Spread departure cases over a day boundary-friendly range: also seed afternoon times.
    $departureStarts = [$nowMinutes, 16 * 60 - 10, 23 * 60 + 40];

    $rawDepartures = mockDepartureServices($today, $departureStarts, $dest);
    usort($rawDepartures, fn(array $a, array $b): int => strcmp((string) $a['std'], (string) $b['std']));
    $departureServices = [];
    foreach ($rawDepartures as $svc) {
        $departureServices[] = normaliseService($svc, $dest['crs'], $config);
    }

    $rawArrivals = mockArrivalServices($today, $dest, $config);
    usort($rawArrivals, fn(array $a, array $b): int => strcmp((string) $a['std'], (string) $b['std']));
    $originCrs = $config['origin']['crs'] ?? 'EXD';
    $arrivalServices = [];
    foreach ($rawArrivals as $svc) {
        $arrivalServices[] = normaliseArrivalService($svc, $originCrs, $config);
    }

    return [
        'services' => array_merge($arrivalServices, $departureServices),
        'messages' => ['Mock data in use. These are not real trains.'],
    ];
}
