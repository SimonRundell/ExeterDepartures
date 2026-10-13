<?php
/**
 * Plain PHP tests for the Darwin normaliser. Run: php tests/normalise_test.php
 *
 * @license CC BY-NC-SA 4.0
 */
require_once __DIR__ . '/../api/normalise.php';
date_default_timezone_set('Europe/London');

$fails = 0;
/**
 * Assert equality and report.
 *
 * @param string $name     Test name.
 * @param mixed  $actual   Actual value.
 * @param mixed  $expected Expected value.
 * @return void
 */
function check(string $name, $actual, $expected): void
{
    global $fails;
    if ($actual !== $expected) {
        $fails++;
        echo "FAIL $name: got " . json_encode($actual) . ' expected ' . json_encode($expected) . "\n";
    } else {
        echo "ok   $name\n";
    }
}

$config = ['windows' => ['morningEnd' => '09:00', 'afternoonStart' => '16:00']];
$board = json_decode((string) file_get_contents(__DIR__ . '/../api/fixtures/board.json'), true);
$out = normaliseBoard($board, ['crs' => 'PLY', 'name' => 'Plymouth'], $config);
$byId = [];
foreach ($out['services'] as $s) {
    $byId[$s['id']] = $s;
}
$a = $byId['AAA1'];
check('midnight late minutes', $a['minutesLate'], 5);
check('status', $a['status'], 'ontime');
check('scheduled formatted HH:MM', $a['scheduledDeparture'], '23:58');
check('expected formatted HH:MM', $a['expectedDeparture'], '00:03');
check('arrival uses etd', $a['arrivalAtTabDestination'], '00:45');
check('duration rolls midnight', $a['durationMinutes'], 47);
check('junction and passing point excluded from stops', $a['stopsBetween'], 1);
check('calling points list excludes junction/passing', $a['callingPoints'], ['St Thomas']);
check('callingAt lists all public stops', array_column($a['callingAt'], 'name'), ['St Thomas', 'Plymouth', 'Paignton']);
check('callingAt flags tab destination', array_column($a['callingAt'], 'isTabDestination'), [false, true, false]);
check('final beyond tab', $a['finalDestination'], 'Paignton');
check('platform', $a['platform'], '3');
check('section', $a['section'], 'afternoon');

$b = $byId['BBB2'];
check('cancelled', $b['status'], 'cancelled');
check('no platform null', $b['platform'], null);
check('non-stop zero', $b['stopsBetween'], 0);
check('cancel reason', $b['cancelReason'], 'Cancelled due to crew shortage');
check('message stripped', $out['messages'][0], 'Disruption at Plymouth.');

$tz = new DateTimeZone('Europe/London');
$std = new DateTimeImmutable('2026-01-01T10:00:00', $tz);
$noForecast = computeStatus($std, null, null, false);
check('no live forecast treated as on time', $noForecast['status'], 'ontime');
check('no live forecast minutesLate zero', $noForecast['minutesLate'], 0);

$late = computeStatus($std, new DateTimeImmutable('2026-01-01T10:07:00', $tz), null, false);
check('late minutesLate', $late['minutesLate'], 7);

$earlyClampedToZero = computeStatus($std, new DateTimeImmutable('2026-01-01T09:58:00', $tz), null, false);
check('running early clamps minutesLate to zero', $earlyClampedToZero['minutesLate'], 0);

$cancelled = computeStatus($std, null, null, true);
check('cancelled status', $cancelled['status'], 'cancelled');

exit($fails ? 1 : 0);
