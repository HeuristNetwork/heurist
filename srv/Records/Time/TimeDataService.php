<?php
/**
* TimeDataService.php - Temporal projection for record responses
*
* Adds timeline-compatible `when` arrays to records already retrieved by the
* ordinary records pipeline. Search, pagination and ordinary detail retrieval
* therefore remain owned by /records; this service only selects temporal
* fields, follows explicitly requested linked paths and converts values.
*
* @project     Heurist academic knowledge management system
* @package     Records\Time
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @since       7.0
*/

declare(strict_types=1);

namespace Heurist\Records\Time;

use Heurist\Database\DatabaseInterface;
use Heurist\Records\Data\RecordDataService;
use Heurist\Records\Expansion\ExpansionEngine;
use Heurist\Records\Expansion\ExpansionRequest;
use Heurist\Records\Query\QueryExecutor;
use Heurist\Records\Query\QueryValidationException;
use Heurist\Records\Query\RecordSearchService;
use Heurist\Runtime\RuntimeContext;
use Heurist\Runtime\SystemCode;
use Heurist\Utilities\Temporal;

/** Converts direct and linked date/year values into the legacy timeline span format. */
final class TimeDataService
{
    private RecordDataService $data;
    private ExpansionEngine $expansion;
    private TimeFieldSelector $selector;
    private int $startDateFieldId;
    private int $endDateFieldId;

    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        SystemCode $codes,
        ?RecordDataService $data = null,
        ?ExpansionEngine $expansion = null,
        ?TimeFieldSelector $selector = null
    ) {
        $executor = new QueryExecutor($database);
        $search = new RecordSearchService($database, $runtime, null, $executor);
        $this->data = $data ?? new RecordDataService($database, $runtime, $executor);
        $this->expansion = $expansion ?? new ExpansionEngine($executor, $search);
        $this->selector = $selector ?? new TimeFieldSelector();
        $this->startDateFieldId = $codes->id('DT_START_DATE');
        $this->endDateFieldId = $codes->id('DT_END_DATE');
    }

    /** Add `when` to each record in an ordinary /records response envelope. */
    public function attachWhen(array &$response, $timeFields = null): void
    {
        if(!isset($response['records']) || !is_array($response['records'])){ return; }

        $topIds = array();
        $recordIndex = array();
        foreach($response['records'] as $index=>$record){
            $id = intval($record['rec_ID'] ?? 0);
            if($id<1){ continue; }
            $topIds[] = $id;
            $recordIndex[$id] = $index;
            $response['records'][$index]['when'] = array();
        }
        if(empty($topIds)){ return; }

        $selection = $this->selector->parse($timeFields);
        $nativeFields = $selection['native'];
        if($selection['allNative']){
            $nativeFields = array_values(array_unique(array_merge(
                $nativeFields,
                $this->data->findFieldIdsByType($topIds, 'date'),
                $this->data->findFieldIdsByType($topIds, 'year')
            )));
        }
        $this->validateTemporalFields($this->fieldDescriptors($nativeFields));

        $nativeValues = $this->data->loadFieldValues($topIds, $nativeFields);
        foreach($topIds as $topId){
            $response['records'][$recordIndex[$topId]]['when'] = $this->timespansForOwner(
                $nativeValues[$topId] ?? array(), $nativeFields
            );
        }

        if(empty($selection['linked'])){ return; }
        $this->validateTemporalFields($selection['linked']);

        $linkedByTraversal = array();
        foreach($selection['linked'] as $field){
            $linkedByTraversal[$field['traversal']][] = $field;
        }

        foreach($linkedByTraversal as $traversal=>$fields){
            $graph = $this->expansion->expand(new ExpansionRequest($topIds, $traversal));
            $internalPathId = null;
            foreach($graph->getPaths() as $pathId=>$code){
                if($code === $traversal){ $internalPathId = (string)$pathId; }
            }
            if($internalPathId === null){ continue; }

            $occurrences = $graph->getOccurrences($internalPathId);
            $owners = array();
            foreach($occurrences as $occurrence){
                $chain = $occurrence['recordIds'] ?? array();
                if(!empty($chain)){ $owners[intval(end($chain))] = intval(end($chain)); }
            }
            if(empty($owners)){ continue; }

            $fieldIds = array_values(array_unique(array_map(static function($field){
                return intval($field['fieldId']);
            }, $fields)));
            $values = $this->data->loadFieldValues(array_values($owners), $fieldIds);

            foreach($occurrences as $occurrence){
                $topId = intval($occurrence['top'] ?? 0);
                $chain = $occurrence['recordIds'] ?? array();
                if(!isset($recordIndex[$topId]) || empty($chain)){ continue; }
                $ownerId = intval(end($chain));
                $spans = $this->timespansForOwner($values[$ownerId] ?? array(), $fieldIds);
                foreach($spans as $span){
                    $response['records'][$recordIndex[$topId]]['when'][] = $span;
                }
            }
        }
    }

    /** @return array<int,array{fieldId:int,pathCode:?string}> */
    private function fieldDescriptors(array $fieldIds): array
    {
        return array_map(static function($id){
            return array('fieldId'=>intval($id), 'pathCode'=>null);
        }, $fieldIds);
    }

    /** Reject non-date/year timefields early instead of silently ignoring them. */
    private function validateTemporalFields(array $fields): void
    {
        if(empty($fields)){ return; }
        $metadata = $this->data->loadFieldMetadata(array_map(static function($field){
            return array(
                'fieldId'=>intval($field['fieldId']),
                'pathCode'=>$field['code'] ?? $field['pathCode'] ?? null
            );
        }, $fields));
        foreach($metadata as $definition){
            $type = strtolower((string)($definition['dty_Type'] ?? ''));
            if(!in_array($type, array('date','year'), true)){
                $name = $definition['dty_PathCode'] ?? $definition['dty_ID'] ?? '?';
                throw new QueryValidationException('Timeline field is not a date/year field: '.$name);
            }
        }
    }

    /**
     * Convert one owner's selected values, merging the Heurist Start/End pair.
     * Every returned span has the source detail-type ID appended as element 9.
     */
    private function timespansForOwner(array $valuesByField, array $fieldIds): array
    {
        $spans = array();
        $start = null;
        $end = null;
        $hasStart = $this->startDateFieldId>0 && in_array($this->startDateFieldId, $fieldIds, true);
        $hasEnd = $this->endDateFieldId>0 && in_array($this->endDateFieldId, $fieldIds, true);

        foreach($fieldIds as $fieldId){
            $fieldId = intval($fieldId);
            $values = $valuesByField[$fieldId] ?? array();
            if($fieldId === $this->startDateFieldId){
                if(!empty($values)){ $start = end($values); }
                continue;
            }
            if($fieldId === $this->endDateFieldId){
                if(!empty($values)){ $end = end($values); }
                continue;
            }
            foreach($values as $value){
                $span = $this->timespan($value, $fieldId);
                if($span !== null){ $spans[] = $span; }
            }
        }

        if(($hasStart && $start !== null) || ($hasEnd && $end !== null)){
            $sourceFieldId = $this->startDateFieldId;
            if($start !== null && $end !== null){
                $temporal = Temporal::mergeTemporals($start, $end);
            }else{
                if($start === null){
                    $start = $end;
                    $sourceFieldId = $this->endDateFieldId;
                }
                $temporal = new Temporal($start);
            }
            if($temporal instanceof Temporal && $temporal->isValid()){
                $span = $temporal->getTimespan(true);
                if($span !== null){
                    $span[] = $sourceFieldId;
                    $spans[] = $span;
                }
            }
        }

        return $spans;
    }

    private function timespan($value, int $fieldId): ?array
    {
        if($value === null || $value === ''){ return null; }
        // RecordDataService normally returns scalar date/year values. Keep this
        // unwrapping defensive for callers using enriched values in future.
        if(is_array($value) && array_key_exists('value', $value)){ $value = $value['value']; }
        $temporal = new Temporal($value);
        if(!$temporal->isValid()){ return null; }
        $span = $temporal->getTimespan(true);
        if($span === null){ return null; }
        $span[] = $fieldId;
        return $span;
    }
}
