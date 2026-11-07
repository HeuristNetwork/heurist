<?php
/**
* ReportJobPlanner.php - Validation and data for the report background jobs
*
* Turns the request of a report job into normalized parameters with the access
* rules of the current user (prepare*), finds the record ids of a query with the
* modern search (searchIds), and lists the schedules that cron must regenerate
* (dueOutputs). The Smarty run itself is done by the hserv job handlers.
*
*   report-preview  {report?: record id, body?: string, ids: int[] (1..50), replevel?: 0-3, language?: code}
*   report-generate {output: id}
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
use Heurist\Definitions\DefinitionLookup;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Records\Query\SearchRequest;
use Heurist\Records\Store\RecordRepository;
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
    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private SystemCode $codes;
    private ReportOutputStore $outputs;

    /** Initialise the planner from explicit dependencies. */
    public function __construct(
        ReportService $reports,
        ReportRepository $repository,
        ReportPolicy $policy,
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        ReportOutputStore $outputs
    ) {
        $this->reports = $reports;
        $this->repository = $repository;
        $this->policy = $policy;
        $this->database = $database;
        $this->runtime = $runtime;
        $this->codes = $codes;
        $this->outputs = $outputs;
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
        $reportId = intval($params['report'] ?? 0);
        $title = 'Unsaved template';
        $file = '';
        if($reportId > 0){
            $report = $this->reports->report($reportId);
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
                'replevel' => max(0, min(3, intval($params['replevel'] ?? 0))),
                'language' => preg_match('/^[A-Za-z]{2,3}$/', (string)($params['language'] ?? '')) === 1 ? (string)$params['language'] : ''
            )
        );
    }

    /**
     * Validate a generation of an output. The file of an output is public to who sees the output, so
     * the user needs the right to change the output record.
     *
     * @return array{title:string,params:array}
     */
    public function prepareGenerate(array $params): array
    {
        $this->policy->requireMember();
        $outputId = intval($params['output'] ?? 0);
        $output = $this->repository->getOutput($outputId);
        if($output === null){
            throw new OutOfBoundsException('Output '.$outputId.' is not available');
        }
        if(!$output['canEdit']){
            throw new DomainException('You are not allowed to generate this output');
        }
        $report = $this->reports->report($output['reportId']);
        $query = self::storedQuery($output['query']);
        if($query === null){
            throw new InvalidArgumentException('The output has no query');
        }
        if($report['file'] === '' || !$this->reports->templateExists($report['file'])){
            throw new InvalidArgumentException('Template file of report '.$report['title'].' does not exist');
        }

        return array(
            'title' => $output['title'],
            'params' => array(
                // the client finds the report of a running job by these (after a page reload)
                'reportId' => $report['id'],
                'templateFile' => $report['file'],
                'query' => $query,
                'format' => $report['format'] !== '' ? $report['format'] : $output['format'],
                'language' => $output['language'],
                'outputId' => $outputId
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
     * Outputs whose file is missing or older than their interval (cron).
     * Outputs without an interval are generated only on request.
     *
     * @return array<int,array> Output plus `templateFile`, `query` and `format`.
     */
    public function dueOutputs(): array
    {
        $due = array();
        foreach($this->repository->allOutputs() as $output){
            if(!($output['intervalMinutes'] > 0)){ continue; }
            $report = $this->reportForCron($output['reportId']);
            if($report === null){ continue; }
            $file = $this->outputs->find($output['id']);
            if($file !== null && time() - $file['time'] < $output['intervalMinutes'] * 60){ continue; }
            $query = self::storedQuery($output['query']);
            if($query === null){ continue; }
            $output['templateFile'] = $report['file'];
            $output['format'] = $report['format'] !== '' ? $report['format'] : $output['format'];
            $output['query'] = $query;
            $due[] = $output;
        }
        return $due;
    }

    /** The files of the outputs. */
    public function outputs(): ReportOutputStore
    {
        return $this->outputs;
    }

    /** Query of an output: JSON decoded, else its text; null when it has none. */
    private static function storedQuery(string $value)
    {
        if(trim($value) === ''){ return null; }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : ltrim(trim($value), '?');
    }

    /** Template file and format of a report record regardless of visibility (cron); null without a file. */
    private function reportForCron(int $reportId): ?array
    {
        $field = $this->codes->id('DT_FILE_NAME');
        $mime = $this->codes->id('DT_FILE_EXT');
        $values = RecordRepository::shared($this->database)->values(array($reportId), array($field, $mime))[$reportId] ?? array();
        $file = trim((string)($values[$field][0] ?? ''));
        if($file === ''){ return null; }
        $term = DefinitionLookup::shared($this->database)->terms(array(intval($values[$mime][0] ?? 0)));
        $term = reset($term);
        return array(
            'file' => strtolower(substr($file, -4)) === '.tpl' ? $file : $file.'.tpl',
            'format' => is_array($term) ? ReportRepository::formatFromMime((string)($term['code'] !== '' ? $term['code'] : $term['term'])) : ''
        );
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
