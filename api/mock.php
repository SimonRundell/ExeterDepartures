<?php
/**
 * Mock departure data so the UI can run without a Darwin API key.
 * Covers on time, late, very late, cancelled, delayed, no platform, non-stop
 * and a midnight rollover.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Format minutes since midnight as HH:MM, wrapping past midnight.
 *
 * @param int $m Minutes.
 * @return string Time string.
 */
function fmtMinutes(int $m): string
{
    $m = (($m % 1440) + 1440) % 1440;
    return sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
}

/**
 * Build mock services for a destination, starting from the current time.
 *
 * @param array $dest   Destination entry (crs, name).
 * @param array $config Validated config.
 * @return array Response body fields (services, messages).
 */
function mockBoard(array $dest, array $config): array
{
    $now = toMinutes(date('H:i'));
    $finals = ['EXM' => 'Exmouth', 'PLY' => 'Penzance', 'OKE' => 'Okehampton', 'BNP' => 'Barnstaple', 'TVP' => 'Taunton'];
    $final = $finals[$dest['crs']] ?? $dest['name'];
    $stopNames = ['St Thomas', 'Newton Abbot', 'Totnes', 'Ivybridge'];
    $cases = [
        ['off' => 8,  'etd' => 'On time', 'plat' => '3', 'stops' => 2, 'dur' => 31],
        ['off' => 23, 'etd' => '+7',      'plat' => '5', 'stops' => 4, 'dur' => 44],
        ['off' => 38, 'etd' => '+14',     'plat' => '',  'stops' => 0, 'dur' => 26],
        ['off' => 53, 'etd' => 'Cancelled', 'plat' => '4', 'stops' => 3, 'dur' => 37],
        ['off' => 68, 'etd' => 'Delayed', 'plat' => '',  'stops' => 1, 'dur' => 33],
        ['off' => 83, 'etd' => '+1',      'plat' => '2', 'stops' => 2, 'dur' => 31],
        ['off' => 98, 'etd' => 'On time', 'plat' => '1', 'stops' => 0, 'dur' => 22],
    ];
    // Spread cases over a day boundary-friendly range: also seed afternoon times.
    $starts = [$now, 16 * 60 - 10, 23 * 60 + 40];
    $services = [];
    foreach ($starts as $s => $start) {
        foreach ($cases as $i => $c) {
            if ($s > 0 && $i > 2) {
                continue;
            }
            $dep = $start + $c['off'];
            $std = fmtMinutes($dep);
            $late = str_starts_with($c['etd'], '+') ? (int) substr($c['etd'], 1) : 0;
            $etd = $late ? fmtMinutes($dep + $late) : $c['etd'];
            $svc = [
                'std' => $std,
                'etd' => $etd,
                'platform' => $c['plat'],
                'operator' => 'Great Western Railway',
                'operatorCode' => 'GW',
                'rid' => 'MOCK' . $s . $i . $dest['crs'],
                'destination' => [['locationName' => $final]],
            ];
            if ($c['etd'] === 'Cancelled') {
                $svc['isCancelled'] = true;
                $svc['cancelReason'] = 'This train has been cancelled because of a shortage of train crew';
            }
            if ($c['etd'] === 'Delayed' || $late > 10) {
                $svc['delayReason'] = 'A fault with the signalling system';
            }
            $locations = [];
            for ($k = 0; $k < $c['stops']; $k++) {
                $locations[] = [
                    'locationName' => $stopNames[$k % 4],
                    'crs' => 'ZZ' . $k,
                    'std' => fmtMinutes($dep + 6 + $k * 5),
                    'etd' => 'On time',
                ];
            }
            $locations[] = [
                'locationName' => $dest['name'],
                'crs' => $dest['crs'],
                'std' => fmtMinutes($dep + $c['dur']),
                'etd' => fmtMinutes($dep + $c['dur'] + $late),
            ];
            $svc['subsequentLocations'] = $locations;
            $services[] = normaliseService($svc, $dest['crs'], $config);
        }
    }
    usort($services, fn($a, $b) => toMinutes($a['scheduledDeparture']) <=> toMinutes($b['scheduledDeparture']));
    return ['services' => $services, 'messages' => ['Mock data in use. These are not real trains.']];
}
