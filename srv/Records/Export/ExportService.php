<?php
/**
* ExportService.php - Runs one record export into a file
*
* 1. ExportPlanner resolves the ordered ids (result, then expanded records).
* 2. The records are loaded in batches of BATCH ids with RecordPageAssembler (the
*    record objects of /records) and passed to the writer of the format.
*    GeoJSON uses the map feature generator (MapFeatureService::featuresForIds)
*    and GeoJsonStreamWriter, as the /map endpoint does.
* 3. The written files are renamed (one file) or zipped (CSV of several record
*    types) to the download name. Temporary files are removed.
* Between batches $check() runs (Stop, time limit) and $progress() reports.
*
* @project     Heurist academic knowledge management system
* @package     Records\Export
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       8.0
*/

declare(strict_types=1);

namespace Heurist\Records\Export;

use Heurist\Definitions\DefinitionLookup;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Records\Data\RecordPageAssembler;
use Heurist\Records\Expansion\ExpansionEngine;
use Heurist\Records\Export\Writer\CsvExportWriter;
use Heurist\Records\Export\Writer\ExportWriterInterface;
use Heurist\Records\Export\Writer\GexfExportWriter;
use Heurist\Records\Export\Writer\HmlExportWriter;
use Heurist\Records\Export\Writer\JsonExportWriter;
use Heurist\Records\Export\Writer\KmlExportWriter;
use Heurist\Records\Map\GeoJsonStreamWriter;
use Heurist\Records\Map\MapFeatureService;
use Heurist\Records\Map\MapFeatureStream;
use Heurist\Records\Map\MapFieldSelector;
use Heurist\Records\Query\Compiler\QueryBuilder;
use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Runtime\RuntimeContext;
use RuntimeException;
use ZipArchive;

/** Export of records into a file in a work folder. */
final class ExportService
{
    /** Records loaded and written per batch. */
    public const BATCH = 100;

    private const EXTENSIONS = array('csv' => 'csv', 'tsv' => 'tsv', 'json' => 'json', 'geojson' => 'geojson',
        'kml' => 'kml', 'xml' => 'xml', 'gephi' => 'gexf');

    private DatabaseInterface $database;
    private RuntimeContext $runtime;
    private ExportPlanner $planner;
    /** @var array{date:int,start:int,end:int,relationType:int} */
    private array $codes;

    /**
     * @param DatabaseInterface $database Database of the export.
     * @param RuntimeContext $runtime Current user, database name, base URL.
     * @param ExportPlanner $planner Ids and links.
     * @param array $codes Field ids: date (DT_DATE), start (DT_START_DATE), end (DT_END_DATE),
     *        relationType (DT_RELATION_TYPE).
     */
    public function __construct(DatabaseInterface $database, RuntimeContext $runtime, ExportPlanner $planner, array $codes)
    {
        $this->database = $database;
        $this->runtime = $runtime;
        $this->planner = $planner;
        $this->codes = array(
            'date' => intval($codes['date'] ?? 0),
            'start' => intval($codes['start'] ?? 0),
            'end' => intval($codes['end'] ?? 0),
            'relationType' => intval($codes['relationType'] ?? 0)
        );
    }

    /** Ids, links and limits. */
    public function planner(): ExportPlanner
    {
        return $this->planner;
    }

    /**
     * Run the export.
     *
     * @param ExportRequest $request Export parameters.
     * @param string $workDir Empty folder for temporary and final files.
     * @param callable|null $check Throws to stop (Stop, time limit).
     * @param callable|null $progress fn(int $done, int $total, string $message).
     * @return array{path:string,file:string,size:int,records:int,format:string,rectypes:array<int,int>}
     */
    public function run(ExportRequest $request, string $workDir, ?callable $check = null, ?callable $progress = null): array
    {
        $workDir = rtrim($workDir, '/\\').'/';
        if(!is_dir($workDir) && !@mkdir($workDir, 0775, true) && !is_dir($workDir)){
            throw new RuntimeException('Cannot create the export folder');
        }
        $check = $check ?? static function(): void {};
        $progress = $progress ?? static function(int $done, int $total, string $message): void {};

        $progress(0, 0, 'searching records');
        $plan = $this->planner->resolve($request, $check);
        $ids = $plan['ids'];
        if(empty($ids)){
            throw new RuntimeException('The export has no records'
                .($this->runtime->userId > 0 ? '' : ' (only public records are used)'));
        }

        $definitions = new DefinitionLookup($this->database);
        $formatter = new ValueFormatter($definitions, $request->values, $this->runtime->baseUrl, $this->runtime->databaseName);
        $columns = new ExportColumns($definitions, $formatter);
        $meta = array(
            'database' => $this->runtime->databaseName,
            'title' => $request->title,
            'query' => $request->query,
            'total' => count($ids)
        );

        $rectypes = array();
        if($request->format === 'geojson'){
            $files = $this->geoJson($request, $ids, $columns, $workDir.'export.geojson', $meta, $check, $progress);
            $written = count($ids);
        }else{
            $writer = $this->writer($request, $definitions, $formatter, $columns, $workDir);
            if($writer instanceof HmlExportWriter || $writer instanceof GexfExportWriter){
                $progress(0, count($ids), 'reading links');
                $links = $this->planner->linksAmong($ids);
                $check();
                if($writer instanceof HmlExportWriter){
                    // relationship records between exported records are records of the HML too
                    $inSet = array_fill_keys($ids, true);
                    foreach($links as $link){
                        if($link['relation'] > 0 && !isset($inSet[$link['relation']])){
                            $inSet[$link['relation']] = true;
                            $ids[] = $link['relation'];
                        }
                    }
                    $writer->setContext(array_slice($ids, 0, $plan['seedCount']), $request->ids, $links);
                    $meta['total'] = count($ids);
                }else{
                    $writer->setLinks($links, function(array $relationIds): array {
                        return $this->relationDates($relationIds);
                    });
                }
            }
            $written = $this->writeRecords($writer, $ids, $meta, $check, $progress, $rectypes);
            $files = $writer->files();
        }

        $check();
        $progress($written, $written, 'packing the file');
        $result = $this->package($request, $files, $workDir);
        $progress($written, $written, 'done');
        return array(
            'path' => $result['path'],
            'file' => $result['file'],
            'size' => intval(filesize($result['path'])),
            'records' => $written,
            'format' => $request->format,
            'rectypes' => $rectypes
        );
    }

    /** Writer of a format (GeoJSON is written by geoJson()). */
    private function writer(ExportRequest $request, DefinitionLookup $definitions, ValueFormatter $formatter,
        ExportColumns $columns, string $workDir): ExportWriterInterface
    {
        switch($request->format){
            case 'csv':
            case 'tsv':
                return new CsvExportWriter($request, $columns, $definitions, $workDir);
            case 'kml':
                return new KmlExportWriter($request, $columns, $definitions, $workDir, $this->codes);
            case 'xml':
                return new HmlExportWriter($definitions, $formatter, $workDir, $this->codes['relationType'], $request->names);
            case 'gephi':
                return new GexfExportWriter($request, $columns, $definitions, $formatter, $workDir);
            default:
                return new JsonExportWriter($definitions, $formatter, $workDir, $request->names);
        }
    }

    /**
     * Load the records in batches and pass them to the writer.
     *
     * @param array<int,int> $rectypes Filled: records per record type.
     * @return int Records written.
     */
    private function writeRecords(ExportWriterInterface $writer, array $ids, array $meta,
        callable $check, callable $progress, array &$rectypes): int
    {
        $assembler = $this->assembler();
        $selection = (new RecordFieldSelector())->parse($writer->fieldCodes());
        $total = count($ids);
        $done = 0;
        $writer->begin($meta);
        try{
            foreach(array_chunk($ids, self::BATCH) as $chunk){
                $check();
                $page = $assembler->assemble($chunk, $selection, array('resolveDetails' => true));
                $records = array_values($page['records']);
                foreach($records as $record){
                    $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
                    $rectypes[$rectypeId] = ($rectypes[$rectypeId] ?? 0) + 1;
                }
                $writer->write($records);
                $done += count($chunk);
                $progress($done, $total, 'writing records');
            }
        }finally{
            $writer->end();
        }
        return $done;
    }

    /**
     * GeoJSON through the map feature generator: one feature per record (combined
     * geometry of its geo fields); the requested "*" columns become properties.
     *
     * @return array<int,array{path:string,name:string}>
     */
    private function geoJson(ExportRequest $request, array $ids, ExportColumns $columns, string $path,
        array $meta, callable $check, callable $progress): array
    {
        $assembler = $this->assembler();
        $selector = new RecordFieldSelector();
        $expanded = array();
        $properties = function(array $topIds) use ($request, $assembler, $selector, $columns, &$expanded): array {
            $codes = array();
            foreach($request->columns as $list){
                foreach(ExportColumns::codes($list) as $code){ $codes[$code] = $code; }
            }
            if(count($codes) <= 3){ return array(); } // only id, type and title: already properties
            $page = $assembler->assemble($topIds, $selector->parse(array_values($codes)), array('resolveDetails' => true));
            $result = array();
            foreach($page['records'] as $record){
                $rectypeId = intval($record['rec_RecTypeID'] ?? 0);
                if(!isset($expanded[$rectypeId])){
                    $expanded[$rectypeId] = $columns->expand($rectypeId, $request->columnsFor($rectypeId), false);
                }
                $cells = $columns->cells($record, $expanded[$rectypeId], $request->csv['mvsep']);
                $values = array();
                foreach($expanded[$rectypeId] as $index => $column){
                    if($column['type'] === 'header' && in_array($column['key'], array('rec_ID', 'rec_RecTypeID', 'rec_Title'), true)){
                        continue;
                    }
                    $values[$column['header']] = $cells[$index];
                }
                $result[intval($record['rec_ID'])] = $values;
            }
            return $result;
        };
        $total = count($ids);
        $state = array();
        $service = new MapFeatureService($this->database, $this->runtime);
        // geo fields of the data source (direct or linked), else every direct geo field
        $selection = (new MapFieldSelector())->parse(empty($request->geoFields) ? null : $request->geoFields);
        $features = $service->featuresForIds($ids, $selection, array(
            'mode' => 'records',
            'properties' => $properties,
            'onBatch' => static function(int $done) use ($check, $progress, $total): void {
                $check();
                $progress($done, $total, 'writing records');
            }
        ), $state);
        $stream = new MapFeatureStream($features, static function() use ($meta, &$state): array {
            return array(
                'database' => $meta['database'],
                'title' => $meta['title'],
                'exported' => date('c'),
                'totalRecords' => $meta['total'],
                'returnedFeatures' => $state['returnedFeatures'] ?? 0
            );
        });
        $handle = fopen($path, 'wb');
        if($handle === false){
            throw new RuntimeException('Cannot create the export file');
        }
        try{
            (new GeoJsonStreamWriter($handle))->write($stream);
        }finally{
            fclose($handle);
        }
        return array(array('path' => $path, 'name' => 'export.geojson'));
    }

    /**
     * Rename the single file, or zip several files, to the download name.
     *
     * @param array<int,array{path:string,name:string}> $files Written files.
     * @return array{path:string,file:string}
     */
    private function package(ExportRequest $request, array $files, string $workDir): array
    {
        $base = $request->fileName !== '' ? $request->fileName
            : 'Export_'.preg_replace('/[^A-Za-z0-9_\-]+/', '_', $this->runtime->databaseName).'_'.date('YmdHis');
        if(empty($files)){
            throw new RuntimeException('Nothing was written');
        }
        if(count($files) === 1){
            $name = $base.'.'.self::EXTENSIONS[$request->format];
            $final = $workDir.$name;
            if(!@rename($files[0]['path'], $final)){
                throw new RuntimeException('Cannot write '.$name);
            }
            return array('path' => $final, 'file' => $name);
        }
        $name = $base.'.zip';
        $final = $workDir.$name;
        $zip = new ZipArchive();
        if($zip->open($final, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true){
            throw new RuntimeException('Cannot create '.$name);
        }
        $used = array();
        foreach($files as $file){
            $entry = $file['name'];
            $suffix = 2;
            while(isset($used[strtolower($entry)])){
                $entry = pathinfo($file['name'], PATHINFO_FILENAME).' '.$suffix++.'.'.pathinfo($file['name'], PATHINFO_EXTENSION);
            }
            $used[strtolower($entry)] = true;
            $zip->addFile($file['path'], $entry);
        }
        if(!$zip->close()){
            throw new RuntimeException('Cannot write '.$name);
        }
        foreach($files as $file){ @unlink($file['path']); }
        return array('path' => $final, 'file' => $name);
    }

    /**
     * Start and end dates of relationship records (GEXF edges).
     *
     * @return array<int,array{0:string,1:string}>
     */
    private function relationDates(array $relationIds): array
    {
        $fields = array_values(array_filter(array($this->codes['start'], $this->codes['end'])));
        if(empty($fields)){ return array(); }
        $values = (new RecordDataService($this->database, $this->runtime))->loadFieldValues($relationIds, $fields);
        $result = array();
        foreach($values as $recordId => $byField){
            $start = $byField[$this->codes['start']][0] ?? '';
            $end = $byField[$this->codes['end']][0] ?? '';
            $result[intval($recordId)] = array(is_array($start) ? '' : (string)$start, is_array($end) ? '' : (string)$end);
        }
        return $result;
    }

    private function assembler(): RecordPageAssembler
    {
        $executor = new QueryExecutor($this->database);
        $search = new RecordSearchService($this->database, $this->runtime, new QueryBuilder($this->database), $executor);
        $engine = new ExpansionEngine($executor, $search);
        return new RecordPageAssembler(
            new RecordDataService($this->database, $this->runtime, $executor),
            static function() use ($engine): ExpansionEngine { return $engine; }
        );
    }
}
