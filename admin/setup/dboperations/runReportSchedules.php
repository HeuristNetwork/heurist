<?php
/**
* runReportSchedules.php - Regenerate due Report Schedule records of one database
*
* Called by dailyCronJobs.php (report action) once per database, in its own PHP
* process: record type and field constants are defined per process.
* A schedule is due when it has an interval and its output file is missing or
* older than the interval. Reports are generated as an anonymous user, so they
* contain public records only (as the old usrReportSchedule generation).
*
* Usage: php admin/setup/dboperations/runReportSchedules.php --db=NAME
*
* @project     Heurist academic knowledge management system
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

if (PHP_SAPI !== 'cli') {
    exit('This function must be run from the shell');
}

require_once dirname(__FILE__).'/../../../autoload.php';

use Heurist\Runtime\ServiceFactory;

$options = getopt('', array('db:'));
$dbName = trim((string)($options['db'] ?? ''));
if ($dbName === '') {
    fwrite(STDERR, "Usage: php runReportSchedules.php --db=NAME\n");
    exit(2);
}

$system = new hserv\System();
if (!$system->init($dbName, true, false)) {
    fwrite(STDERR, 'Cannot open database '.$dbName.': '.$system->getErrorMsg()."\n");
    exit(2);
}
$system->initPathConstants($dbName);

$factory = ServiceFactory::fromLegacySystem($system);
// srv Smarty engine; the legacy renderer only converts templates on import/export
$renderer = $factory->reportRenderer(new hserv\report\SmartyReportRenderer($system));
$writer = new hserv\report\ReportRecordWriter($system);
$planner = $factory->reportJobPlanner($renderer, $writer);
$runner = $factory->jobRunner($factory->reportJobHandlers($renderer, $writer));

$counts = array('done' => 0, 'failed' => 0);
foreach ($planner->dueSchedules() as $schedule) {
    $start = time();
    $job = $runner->runSystemJob('report-generate', $schedule['title'], array(
        'templateFile' => $schedule['templateFile'],
        'query' => $schedule['query'],
        'output' => $schedule['output'],
        'format' => $schedule['format'],
        'scheduleId' => $schedule['id']
    ));
    $seconds = time() - $start;
    if ($job['status'] === 'done') {
        $counts['done']++;
        echo 'schedule '.$schedule['id'].' "'.$schedule['title'].'": '.$job['result']['file']
            .' ('.$job['result']['records'].' records, '.$seconds." s)\n";
    } else {
        $counts['failed']++;
        echo 'schedule '.$schedule['id'].' "'.$schedule['title'].'" '.$job['status'].': '.$job['error']."\n";
    }
}
echo 'report schedules: generated '.$counts['done'].', failed '.$counts['failed']."\n";
exit($counts['failed'] > 0 ? 1 : 0);
