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
check('status late', $a['status'], 'late');
check('expected', $a['expectedDeparture'], '00:03');
check('arrival uses et', $a['arrivalAtTabDestination'], '00:45');
check('duration rolls midnight', $a['durationMinutes'], 47);
check('stops between', $a['stopsBetween'], 1);
check('final beyond tab', $a['finalDestination'], 'Paignton');
check('platform', $a['platform'], '3');
check('section', $a['section'], 'afternoon');
$b = $byId['BBB2'];
check('cancelled', $b['status'], 'cancelled');
check('no platform null', $b['platform'], null);
check('non-stop zero', $b['stopsBetween'], 0);
check('cancel reason', $b['cancelReason'], 'Cancelled due to crew shortage');
check('message stripped', $out['messages'][0], 'Disruption at Plymouth.');
check('delayed', computeStatus('10:00', 'Delayed', false)['status'], 'delayed');
check('1 min is on time', computeStatus('10:00', '10:01', false)['status'], 'ontime');
exit($fails ? 1 : 0);
