<?php
/**
* MapFeatureService.php - Modern record-query map feature pipeline
*
* @project     Heurist academic knowledge management system
* @package     Records\Map
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/

namespace hserv\records\map;

require_once dirname(__FILE__).'/MapFieldSelector.php';
require_once dirname(__FILE__).'/GeoJsonGeometryConverter.php';
require_once dirname(__FILE__).'/../data/RecordDataService.php';
require_once dirname(__FILE__).'/../search/ExpansionEngine.php';
require_once dirname(__FILE__).'/../search/query/QueryBuilder.php';

use hserv\records\data\RecordDataService;
use hserv\records\search\ExpansionEngine;
use hserv\records\search\ExpansionRequest;
use hserv\records\search\QueryValidationException;
use hserv\records\search\RecordSearchService;
use hserv\records\search\SearchRequest;
use hserv\records\search\query\QueryBuilder;

/** Iterable feature stream plus metadata finalized after iteration. */
final class MapFeatureStream
{
    /** @var \Traversable */
    private $features;
    /** @var callable */
    private $metaProvider;

    public function __construct(\Traversable $features, callable $metaProvider)
    {
        $this->features = $features;
        $this->metaProvider = $metaProvider;
    }

    public function features(): \Traversable { return $this->features; }
    public function meta(): array { return call_user_func($this->metaProvider); }
}

/** Coordinates search, requested expansion, geometry retrieval and presentation. */
final class MapFeatureService
{
    private const BATCH_SIZE = 500;

    /** @var \hserv\System */
    private $system;
    /** @var QueryBuilder */
    private $builder;
    /** @var RecordSearchService */
    private $search;
    /** @var RecordDataService */
    private $data;
    /** @var ExpansionEngine */
    private $expansion;
    /** @var MapFieldSelector */
    private $selector;
    /** @var GeoJsonGeometryConverter */
    private $converter;

    public function __construct(
        $system,
        ?RecordSearchService $search = null,
        ?RecordDataService $data = null,
        ?ExpansionEngine $expansion = null,
        ?MapFieldSelector $selector = null,
        ?GeoJsonGeometryConverter $converter = null
    ) {
        $this->system = $system;
        $this->builder = new QueryBuilder($system->getMysqli());
        $this->search = $search ?? new RecordSearchService($system, $this->builder);
        $this->data = $data ?? new RecordDataService($system);
        $this->expansion = $expansion ?? new ExpansionEngine($system, $this->search);
        $this->selector = $selector ?? new MapFieldSelector();
        $this->converter = $converter ?? new GeoJsonGeometryConverter();
    }

    /** Prepare an iterable GeoJSON feature result for a query page. */
    public function createStream(array $params): MapFeatureStream
    {
        $query = $params['query'] ?? $params['q'] ?? null;
        if(($query === null || $query === '') && isset($params['ids'])){
            $query = array(array('ids'=>$params['ids']));
        }
        if($query === null || $query === ''){ $query = array(array('_all'=>true)); }
        $request = new SearchRequest($this->builder->normalize($query), array(
            'limit'=>$params['limit'] ?? 1000,
            'offset'=>$params['offset'] ?? 0,
            'detail'=>'ids'
        ));
        $searchResult = $this->search->search($request);
        $selection = $this->selector->parse($params['geofields'] ?? $params['geoFields'] ?? null);
        $this->validateGeoFields(array_merge(
            array_map(static function($id){
                return array('fieldId'=>$id, 'pathCode'=>null);
            }, $selection['native']),
            array_map(static function($field){
                return array('fieldId'=>$field['fieldId'], 'pathCode'=>$field['code']);
            }, $selection['linked'])
        ));

        $mode = $this->outputMode($params);
        $simplify = $this->boolean($params['simplify'] ?? false);
        $state = array(
            'returnedRecords'=>count($searchResult->ids),
            'returnedFeatures'=>0,
            'records'=>array(),
            'paths'=>array()
        );
        $pathIds = array();
        foreach($selection['linked'] as $field){
            if(!isset($pathIds[$field['traversal']])){
                $id = (string)(count($pathIds)+1);
                $pathIds[$field['traversal']] = $id;
                $state['paths'][$id] = $field['traversal'];
            }
        }

        $features = (function() use (
            $searchResult, $selection, $mode, $simplify, $pathIds, &$state
        ) {
            foreach(array_chunk($searchResult->ids, self::BATCH_SIZE) as $topIds){
                $topRecords = $this->data->loadRecords($topIds, array('rec_Title'), array());
                $topById = array();
                foreach($topRecords as $record){ $topById[intval($record['rec_ID'])] = $record; }
                $geometries = array_fill_keys($topIds, array());

                $nativeFields = $selection['native'];
                if($selection['allNative']){
                    $nativeFields = array_values(array_unique(array_merge(
                        $nativeFields,
                        $this->data->findFieldIdsByType($topIds, 'geo')
                    )));
                }
                $nativeValues = $this->data->loadFieldValues($topIds, $nativeFields);
                foreach($topIds as $topId){
                    foreach($nativeFields as $fieldId){
                        $index = 0;
                        foreach($nativeValues[$topId][$fieldId] ?? array() as $value){
                            $geometry = $this->geometry($value, $simplify);
                            if($geometry === null){ continue; }
                            $index++;
                            if($mode === 'features'){
                                $state['returnedFeatures']++;
                                yield $this->feature(
                                    (string)$topId.':'.$fieldId.':'.$index,
                                    $topById[$topId] ?? array('rec_ID'=>(string)$topId),
                                    $geometry
                                );
                            }else{
                                $geometries[$topId][] = $geometry;
                            }
                        }
                    }
                }

                $linkedByTraversal = array();
                foreach($selection['linked'] as $field){
                    $linkedByTraversal[$field['traversal']][] = $field;
                }
                foreach($linkedByTraversal as $traversal=>$fields){
                    $graph = $this->expansion->expand(new ExpansionRequest(
                        $topIds, $traversal, array('includeHeaders'=>true)
                    ));
                    $graphArray = $graph->toArray();
                    $recordTypes = array();
                    foreach($graphArray['records'] as $record){
                        $recordId = intval($record['rec_ID']);
                        $recordTypes[$recordId] = intval($record['rec_RecTypeID'] ?? 0);
                        $state['records'][$recordId] = array(
                            'rec_ID'=>(string)$recordId,
                            'rec_RecTypeID'=>(string)($record['rec_RecTypeID'] ?? ''),
                            'rec_Title'=>$record['rec_Title'] ?? null
                        );
                    }
                    $internalPathId = null;
                    foreach($graph->getPaths() as $id=>$code){
                        if($code === $traversal){ $internalPathId = (string)$id; }
                    }
                    if($internalPathId === null){ continue; }
                    $occurrences = $graph->getOccurrences($internalPathId);
                    $owners = array();
                    foreach($occurrences as $occurrence){
                        $chain = $occurrence['recordIds'] ?? array();
                        if(!empty($chain)){ $owners[intval(end($chain))] = intval(end($chain)); }
                    }
                    foreach($fields as $field){
                        $values = $this->data->loadFieldValues(
                            array_values($owners), array($field['fieldId'])
                        );
                        $occurrenceIndex = 0;
                        foreach($occurrences as $occurrence){
                            $topId = intval($occurrence['top'] ?? 0);
                            $chain = array_values(array_map('intval', $occurrence['recordIds'] ?? array()));
                            if(empty($chain) || !isset($topById[$topId])){ continue; }
                            $ownerId = intval(end($chain));
                            foreach($values[$ownerId][$field['fieldId']] ?? array() as $value){
                                $geometry = $this->geometry($value, $simplify);
                                if($geometry === null){ continue; }
                                $occurrenceIndex++;
                                if($mode === 'features'){
                                    $properties = $topById[$topId];
                                    unset($properties['details']);
                                    $properties['_geoRecordID'] = (string)$ownerId;
                                    $properties['_geoRecordTypeID'] = (string)($recordTypes[$ownerId] ?? '');
                                    $properties['_geoFieldID'] = (string)$field['fieldId'];
                                    $properties['_path'] = array(
                                        'id'=>$pathIds[$traversal],
                                        'recordIDs'=>array_map('strval', $chain)
                                    );
                                    $state['returnedFeatures']++;
                                    yield $this->feature(
                                        (string)$topId.':'.$pathIds[$traversal].':'.$ownerId
                                            .':'.$field['fieldId'].':'.$occurrenceIndex,
                                        $properties,
                                        $geometry
                                    );
                                }else{
                                    $geometries[$topId][] = $geometry;
                                }
                            }
                        }
                    }
                }

                if($mode === 'records'){
                    foreach($topIds as $topId){
                        if(empty($geometries[$topId])){ continue; }
                        $state['returnedFeatures']++;
                        yield $this->feature(
                            (string)$topId,
                            $topById[$topId] ?? array('rec_ID'=>(string)$topId),
                            $this->combine($geometries[$topId])
                        );
                    }
                }
            }
        })();

        $meta = function() use ($searchResult, $mode, &$state): array {
            $result = array(
                'database'=>$this->databaseName(),
                'totalRecords'=>$searchResult->total,
                'returnedRecords'=>$state['returnedRecords'],
                'returnedFeatures'=>$state['returnedFeatures'],
                'offset'=>$searchResult->offset,
                'limit'=>$searchResult->limit,
                'isPartial'=>$searchResult->offset>0
                    || $searchResult->offset+$state['returnedRecords']<$searchResult->total,
                'geoOutputMode'=>$mode
            );
            if($mode === 'features' && !empty($state['paths'])){
                $result['records'] = array_values($state['records']);
                $result['paths'] = $state['paths'];
            }
            return $result;
        };
        return new MapFeatureStream($features, $meta);
    }

    private function validateGeoFields(array $fields): void
    {
        if(empty($fields)){ return; }
        foreach($this->data->loadFieldMetadata($fields) as $definition){
            if(($definition['dty_Type'] ?? null) !== 'geo'){
                $code = $definition['dty_PathCode'] ?? $definition['dty_ID'] ?? '?';
                throw new QueryValidationException('Requested geographic field is not a geo field: '.$code);
            }
        }
    }

    private function geometry($value, bool $simplify): ?array
    {
        if(!is_array($value) || !isset($value['geo']['wkt'])){ return null; }
        return $this->converter->convert((string)$value['geo']['wkt'], $simplify);
    }

    private function feature(string $id, array $properties, array $geometry): array
    {
        unset($properties['details']);
        return array(
            'type'=>'Feature', 'id'=>$id,
            'properties'=>$properties,
            'geometry'=>$geometry
        );
    }

    private function combine(array $geometries): array
    {
        return count($geometries)===1
            ? $geometries[0]
            : array('type'=>'GeometryCollection', 'geometries'=>array_values($geometries));
    }

    private function outputMode(array $params): string
    {
        $value = strtolower(trim((string)($params['geoOutputMode'] ?? $params['mode'] ?? '')));
        if($value === 'features' || $this->boolean($params['separate'] ?? false)){ return 'features'; }
        return 'records';
    }

    private function boolean($value): bool
    {
        return in_array(strtolower(trim((string)$value)), array('1','true','yes','on'), true);
    }

    private function databaseName(): string
    {
        if(method_exists($this->system, 'dbname')){ return (string)$this->system->dbname(); }
        if(method_exists($this->system, 'getDbName')){ return (string)$this->system->getDbName(); }
        return '';
    }
}
