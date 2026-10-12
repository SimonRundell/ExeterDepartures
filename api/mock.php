<?php
/**
 * Mock departure data so the UI can run without a Darwin API key.
 * Covers on time, late, very late, cancelled, delayed, no platform, non-stop
 * and a midnight rollover. Builds the same shape Darwin's REST API returns:
 * full ISO datetimes for std/etd, not bare "HH:MM" strings.
 *
 * @license CC BY-NC-SA 4.0
 */

/**
 * Build mock services for a destination, starting from the current time.
 *
 * @param array $dest   Destination entry (crs, name).
 * @param array $config Validated config.
 * @return array Response body fields (services, messages).
 */
function mockBoard(array $dest, array $config): array
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));
    $today = $now->setTime(0, 0);
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
    $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
    // Spread cases over a day boundary-friendly range: also seed afternoon times.
    $starts = [$nowMinutes, 16 * 60 - 10, 23 * 60 + 40];
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
                'rid' => 'MOCK' . $s . $i . $dest['crs'],
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
    usort($raw, fn(array $a, array $b): int => strcmp((string) $a['std'], (string) $b['std']));
    $services = [];
    foreach ($raw as $svc) {
        $services[] = normaliseService($svc, $dest['crs'], $config);
    }
    return ['services' => $services, 'messages' => ['Mock data in use. These are not real trains.']];
}
