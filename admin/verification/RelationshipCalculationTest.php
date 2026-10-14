<?php
/** Run with PHP from the repository root. No production database is used. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__, 2).'/autoload.php';

use hserv\records\edit\RelationshipCalculation as Calculation;

$checks = 0;
function same($expected, $actual, $description) {
    global $checks;
    if ($expected !== $actual) {
        throw new RuntimeException($description.': expected '.var_export($expected, true).', got '.var_export($actual, true));
    }
    $checks++;
}
function candidate($id, $target, $date, $modified = '2026-01-01 00:00:00') {
    return compact('id', 'target', 'date', 'modified');
}
same('', Calculation::selectTarget(array()), 'No storage history clears the calculation');
same('200', Calculation::selectTarget(array(
    candidate(1, 100, '2025-01-01', '2026-10-14 00:00:00'),
    candidate(2, 200, '2026-01-01', '2026-01-01 00:00:00'),
    candidate(3, 300, '', '2026-10-14 23:59:59')
)), 'Newest explicit date wins, regardless of modification dates');
same('200', Calculation::selectTarget(array(
    candidate(1, 100, null, '2026-01-01 00:00:00'),
    candidate(2, 200, '', '2026-02-01 00:00:00')
)), 'Modification time is the fallback when no dates exist');
same('200', Calculation::selectTarget(array(
    candidate(1, 100, '2026-01-01 09:00:00'),
    candidate(2, 200, '2026-01-01 10:00:00')
)), 'Times within the same day are ordered');
same('200', Calculation::selectTarget(array(
    candidate(1, 100, '2026-01-01', '2026-01-01 00:00:00'),
    candidate(2, 200, '2026-01-01', '2026-02-01 00:00:00')
)), 'Equal dates use modification time');
same('100', Calculation::selectTarget(array(
    candidate(1, 100, '2026-01-01', '2026-02-01 00:00:00'),
    candidate(2, 200, '2026-01-01 00:00:00', '2026-01-01 00:00:00')
)), 'A date and the same date at midnight tie');
same('200', Calculation::selectTarget(array(
    candidate(1, 100, json_encode(array('start'=>array('earliest'=>'2025-01-01'),'end'=>array('latest'=>'2028-01-01')))),
    candidate(2, 200, '2026-01-01')
)), 'A date range uses its earliest bound');
$ties = array(candidate(1, 100, '2026-01-01'), candidate(2, 200, '2026-01-01'));
same('200', Calculation::selectTarget($ties), 'Equal timestamps use relationship ID');
same('200', Calculation::selectTarget(array_reverse($ties)), 'Tie handling is independent of query order');
try {
    Calculation::selectTarget(array(candidate(1, 100, 'not-a-date')));
    throw new RuntimeException('Invalid dates must fail the formula');
} catch (InvalidArgumentException $expected) { $checks++; }
same(true, Calculation::isTargeted(Calculation::REFRESH_MARKER.'{$r.f1}'), 'Refresh is explicitly enabled');
same(false, Calculation::isTargeted('{$r.f1}'), 'Existing formulas retain their dependencies');
echo "Passed $checks selection checks\n";
