<?php
/**
* ReportJobPlanner.php - Validation and data for the report background jobs
*
* Turns the request of a report job into normalized parameters with the access
* rules of the current user (prepare*), finds the record ids of a query with the
* modern search (searchIds), and lists the schedules that cron must regenerate
* (dueSchedules). The Smarty run itself is done by the hserv job handlers.
*
*   report-preview  {report?: ref, body?: string, ids: int[] (1..50), replevel?: 0-3}
*   report-generate {schedule: id} or
*                   {report: ref, querySource?: id, query?: json|text, format?: html|..., output?: name}
*
* @project     Heurist academic knowledge management system
* @package     Reports
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Reports;

use DomainException;
use Heurist\Database\DatabaseInterface;
use Heurist\Database\MysqlDatabase;
use Heurist\Records\Presentation\QuerySourcePresentationService;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;
use InvalidArgumentException;
use OutOfBoundsException;

/** Plans report preview and generation jobs. */
final class ReportJobPlanner
{
    /** Ids read by one search request (the search API returns at most 100000 per page). */
    private const PAGE = 100000;

    private ReportService $reports;
    private ReportRepository $repository;
    private ReportPolicy $policy;
    private QuerySourcePresentationService $querySources;
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemCode $codes;
    private string $generatedDirectory;
    private string $generatedUrl;

    /** Initialise the planner from explicit dependencies. */
    public function __construct(
        ReportService $reports,
        ReportRepository $repository,
        ReportPolicy $policy,
        QuerySourcePresentationService $querySources,
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        string $generatedDirectory = '',
        string $generatedUrl = ''
    ) {
        $this->reports = $reports;
        $this->repository = $repository;
        $this->policy = $policy;
        $this->querySources = $querySources;
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
        $this->generatedDirectory = $generatedDirectory === '' ? '' : rtrim($generatedDirectory, '/\\').'/';
        $this->generatedUrl = $generatedUrl === '' ? '' : rtrim($generatedUrl, '/').'/';
    }

    /** Connection id of the search connection (KILL QUERY on Stop), 0 if unknown. */
    public function connectionId(): int
    {
        return $this->database instanceof MysqlDatabase ? $this->database->connectionId() : 0;
    }

    /** The access policy (time limits). */
    public function policy(): ReportPolicy
    {
        return $this->policy;
    }

    /**
     * Validate a test run of a saved template or an unsaved body on at most
     * ReportPolicy::TEST_RECORD_LIMIT records.
     *
     * @return array{title:string,params:array}
     */
    public function preparePreview(array $params): array
    {
        $this->policy->requireMember();
        $ids = $this->ids($params['ids'] ?? array());
        if(empty($ids)){
            throw new InvalidArgumentException('Select the records to test the report with');
        }
        $ids = array_slice($ids, 0, ReportPolicy::TEST_RECORD_LIMIT);

        $body = isset($params['body']) && is_string($params['body']) ? $params['body'] : '';
        $reference = trim((string)($params['report'] ?? ''));
        $title = 'Unsaved template';
        $file = '';
        if($reference !== ''){
            $report = $this->reports->resolveReport($reference);
            $title = $report['title'];
            $file = $report['file'];
        }
        if(trim($body) === ''){
            if($file === '' || !$this->reports->templateExists($file)){
                throw new InvalidArgumentException('Template text or a saved report is required');
            }
        }else{
            $file = ''; // test the unsaved text
        }

        return array(
            'title' => 'Test: '.$title,
            'params' => array(
                'templateFile' => $file,
                'body' => $body,
                'ids' => $ids,
                'replevel' => max(0, min(3, intval($params['replevel'] ?? 0)))
            )
        );
    }

    /**
     * Validate a generation of a schedule, or of a report for a Query Source or query.
     *
     * @return array{title:string,params:array}
     */
    public function prepareGenerate(array $params): array
    {
        $this->policy->requireMember();
        $scheduleId = intval($params['schedule'] ?? 0);

        if($scheduleId > 0){
            $schedule = $this->repository->getSchedule($scheduleId);
            if($schedule === null){
                throw new OutOfBoundsException('Schedule '.$scheduleId.' is not available');
            }
            $report = $this->reports->resolveReport((string)$schedule['reportId']);
            if($schedule['dataSourceId'] < 1){
                throw new InvalidArgumentException('The schedule has no data source');
            }
            $query = $this->querySourceQuery($schedule['dataSourceId']);
            $output = $schedule['file'] !== '' ? $schedule['file'] : substr($report['file'], 0, -4);
            $format = $schedule['format'];
            $title = $schedule['title'];
        }else{
            $report = $this->reports->resolveReport(trim((string)($params['report'] ?? '')));
            $querySourceId = intval($params['querySource'] ?? 0);
            if($querySourceId > 0){
                $query = $this->querySourceQuery($querySourceId);
            }elseif(isset($params['query']) && $params['query'] !== '' && $params['query'] !== array()){
                $query = $params['query'];
            }else{
                throw new InvalidArgumentException('A schedule, a Query Source or a query is required');
            }
            $format = ReportRepository::formatFromMime((string)($params['format'] ?? 'html'));
            // report file name + optional suffix; a report generated without a
            // schedule is the user's own file ("_u<user id>")
            $output = $this->reports->outputName($report, (string)($params['suffix'] ?? '')).'_u'.$this->runtime->userId;
            $title = $report['title'];
        }
        if($report['file'] === '' || !$this->reports->templateExists($report['file'])){
            throw new InvalidArgumentException('Template file of report '.$report['title'].' does not exist');
        }

        return array(
            'title' => $title,
            'params' => array(
                // the client finds the report of a running job by these (after a page reload)
                'reportId' => isset($report['id']) && intval($report['id']) > 0 ? intval($report['id']) : null,
                'templateFile' => $report['file'],
                'query' => $query,
                'output' => $this->outputName($output),
                'format' => $format,
                'scheduleId' => $scheduleId > 0 ? $scheduleId : null
            )
        );
    }

    /**
     * Record ids of a query, in its sort order, with the visibility rules of the
     * current user (cron: anonymous, so public records only).
     *
     * @param array|string $query JSON query, its text, or a query array.
     * @return array<int,int>
     */
    public function searchIds($query): array
    {
        $builder = new QueryBuilder($this->database);
        if(is_string($query)){
            $decoded = json_decode($query, true);
            $query = is_array($decoded) ? $decoded : $builder->normalize($query);
        }
        if(!is_array($query)){
            throw new InvalidArgumentException('Invalid query');
        }
        $service = new RecordSearchService($this->database, $this->runtime, $builder);
        $max = $this->policy->generateMaxRecords();
        $result = $service->search(new SearchRequest($query, array('limit' => min(self::PAGE, $max))));
        if($result->total > $max){
            throw new DomainException('The query finds '.$result->total.' records; a generated report can have at most '
                .$max.' (database setting Reports: generateMaxRecords)');
        }
        $ids = $result->ids;
        // larger sets are read in pages
        while(count($ids) < $result->total && count($result->ids) > 0){
            $result = $service->search(new SearchRequest($query, array('limit' => self::PAGE, 'offset' => count($ids))));
            $ids = array_merge($ids, $result->ids);
        }
        return $ids;
    }

    /**
     * Schedules whose output is missing or older than their interval (cron).
     * Schedules without an interval are generated only on request.
     *
     * @return array<int,array> Schedule plus `templateFile` and `query`.
     */
    public function dueSchedules(): array
    {
        $due = array();
        foreach($this->repository->allSchedules() as $schedule){
            if(!($schedule['intervalMinutes'] > 0) || $schedule['dataSourceId'] < 1){ continue; }
            $templateFile = $this->reportFileForCron($schedule['reportId']);
            if($templateFile === ''){ continue; }
            $output = $this->outputName($schedule['file'] !== '' ? $schedule['file'] : substr($templateFile, 0, -4));
            $path = $this->generatedDirectory.$output.'.'.$schedule['format'];
            if(is_file($path) && time() - filemtime($path) < $schedule['intervalMinutes'] * 60){ continue; }
            $query = $this->querySourceQueryForCron($schedule['dataSourceId']);
            if($query === null){ continue; }
            $schedule['templateFile'] = $templateFile;
            $schedule['query'] = $query;
            $schedule['output'] = $output;
            $due[] = $schedule;
        }
        return $due;
    }

    /** Generated output: path and URL of "<output>.<format>". */
    public function generatedFile(string $output, string $format): array
    {
        $file = $output.'.'.$format;
        return array(
            'file' => $file,
            'path' => $this->generatedDirectory.$file,
            'url' => $this->generatedUrl === '' ? '' : $this->generatedUrl.rawurlencode($file)
        );
    }

    /** Folder of generated reports. */
    public function generatedDirectory(): string
    {
        return $this->generatedDirectory;
    }

    /** Query of a Query Source visible to the current user. */
    private function querySourceQuery(int $recordId)
    {
        $source = $this->querySources->getQuerySource($recordId);
        if($source === null){
            throw new OutOfBoundsException('Query Source '.$recordId.' is not available');
        }
        return $source['source']['query'];
    }

    /** Query text of a Query Source regardless of visibility (cron). */
    private function querySourceQueryForCron(int $recordId)
    {
        $value = $this->database->fetchValue(
            'SELECT dtl_Value FROM recDetails JOIN Records ON rec_ID=dtl_RecID '
            .'WHERE dtl_RecID=? AND dtl_DetailTypeID=? AND rec_FlagTemporary=0 LIMIT 1',
            array($recordId, $this->codes->id('DT_QUERY_STRING'))
        );
        if($value === null || trim((string)$value) === ''){ return null; }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : ltrim(trim((string)$value), '?');
    }

    /** Template file of a report record regardless of visibility (cron). */
    private function reportFileForCron(int $reportId): string
    {
        $value = $this->database->fetchValue(
            'SELECT dtl_Value FROM recDetails WHERE dtl_RecID=? AND dtl_DetailTypeID=? LIMIT 1',
            array($reportId, $this->codes->id('DT_FILE_NAME'))
        );
        $file = trim((string)$value);
        if($file === ''){ return ''; }
        return strtolower(substr($file, -4)) === '.tpl' ? $file : $file.'.tpl';
    }

    /** Output file name without extension: no path, no reserved characters. */
    private function outputName(string $name): string
    {
        $name = pathinfo($name, PATHINFO_FILENAME);
        $name = trim(preg_replace('/[\/\\\\:*?"<>|\x00-\x1F]+/u', '_', $name), " ._");
        if($name === ''){
            throw new InvalidArgumentException('Invalid output file name');
        }
        return mb_substr($name, 0, 100);
    }

    /** @return array<int,int> Positive unique ids. */
    private function ids($value): array
    {
        if(is_string($value)){ $value = explode(',', $value); }
        if(!is_array($value)){ return array(); }
        return array_values(array_unique(array_filter(array_map('intval', $value), static function(int $id): bool {
            return $id > 0;
        })));
    }
}
