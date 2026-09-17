<?php
/**
* QuerySourcePresentationService.php - Public QuerySource response builder
*
* Converts RT_QUERY_SOURCE into the
* stable, engine-neutral definition consumed by heurist-data.
*
* @project     Heurist academic knowledge management system
* @package     Records\Presentation
* @link        https://HeuristNetwork.org
* @copyright   (C) 2026 Heurist Network Association. All rights reserved.
* @license     This software may be installed and operated only on servers operated by Heurist Network, or with the prior written permission of Heurist Network. No right is granted to copy, distribute, modify, install or operate this software elsewhere.
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       8.0
*/
namespace Heurist\Records\Presentation;

use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Records\Query\QueryValidationException;

/** Builds the public representation of one QuerySource record. */
class QuerySourcePresentationService
{
    private PresentationRecordRepository $querySources;

    /** Initialise the service for the current database. */
    public function __construct(PresentationRecordRepository $records)
    {
        $this->querySources = $records;
    }

    /**
     * Build the public QuerySource definition.
     *
     * @param int $recordId RT_QUERY_SOURCE record ID.
     * @return array|null Public QuerySource response, or null when unavailable.
     */
    public function getQuerySource(int $recordId): ?array
    {
        $querySource = $this->querySources->getPublicRecord($recordId, 'RT_QUERY_SOURCE');
        if(!$querySource){ return null; }

        $source = $this->querySources->getQuerySource($querySource);
        if(!$source){ return null; }

        $queryValue = $this->querySources->value($source, 'DT_QUERY_STRING');
        if($queryValue === null || trim((string)$queryValue) === ''){
            throw new QueryValidationException('QuerySource query is not defined');
        }

        $fields = $this->parseFields(
            $this->querySources->value($querySource, 'DT_TABLE_FIELDS')
        );
        $geofields = $this->parseFields(
            $this->querySources->value($querySource, 'DT_GEO_FIELDS')
        );
        $timefields = $this->parseFields(
            $this->querySources->value($querySource, 'DT_TIMELINE_FIELDS')
        );

        return array(
            'format'=>'heurist-query-source',
            'version'=>1,
            'id'=>intval($querySource['rec_ID']),
            'title'=>(string)($querySource['rec_Title'] ?? ''),
            'description'=>(string)($this->querySources->value($querySource, 'DT_SHORT_SUMMARY') ?? ''),
            'source'=>array(
                'type'=>'heurist-query',
                'recordId'=>intval($source['rec_ID'] ?? 0),
                'title'=>(string)($source['rec_Title'] ?? ''),
                'query'=>$this->parseQuery($queryValue)
            ),
            'fields'=>$fields,
            'timefields'=>$timefields,
            'map'=>array(
                'geoFields'=>$geofields,
                'dynamicRequests'=>$this->termBoolean(
                    $this->querySources->value($querySource, 'DT_IS_LOADED_BY_EXTENT'), false
                ),
                'minZoom'=>$this->numberOrNull(
                    $this->querySources->value($querySource, 'DT_MINIMUM_ZOOM_LEVEL')
                ),
                'maxZoom'=>$this->numberOrNull(
                    $this->querySources->value($querySource, 'DT_MAXIMUM_ZOOM_LEVEL')
                )
            ),
            'rules'=>$this->parseRules($this->querySources->value($querySource, 'DT_EXPANSION_RULES'))
        );
    }

    private function termBoolean($value, bool $default): bool
    {
        if($value === null || $value === ''){ return $default; }
        if($value === false || $value === 0 || $value === '0'){ return false; }
        if($value === true || $value === 1 || $value === '1'){ return true; }
        $code = strtolower((string)$this->querySources->getTermCode(intval($value)));
        $label = strtolower((string)$this->querySources->getTermLabel(intval($value)));
        return !in_array($code, array('no','false','0'), true)
            && !in_array($label, array('no','false'), true);
    }

    private function numberOrNull($value)
    {
        return is_numeric($value) ? 0 + $value : null;
    }

    private function parseRules($value): array
    {
        if($value === null || trim((string)$value) === '') return array();
        $rules = json_decode((string)$value, true);
        if(!is_array($rules) || (!empty($rules) && array_keys($rules)!==range(0, count($rules)-1))){
            throw new QueryValidationException('QuerySource expansion rules must be a JSON array');
        }
        (new \Heurist\Records\Expansion\ExpansionRuleParser())->parse($rules);
        return $rules; // Preserve generated names and descriptions.
    }

    /** Parse JSON or comma-separated DT_TABLE_FIELDS, DT_GEO_FIELDS, DT_TIMELINE_FIELDS into ordered definitions. */
    private function parseFields($value): array
    {
        if($value === null || trim((string)$value) === ''){ return array(); }

        $text = trim((string)$value);
        $decoded = json_decode($text, true);
        $values = is_array($decoded) ? $decoded : explode(',', $text);
        // Accept both the array and the public {fields:[...]} wrapper.
        if(isset($values['fields']) && is_array($values['fields'])){
            $values = $values['fields'];
        }
        $result = array();
        $fieldCodes = array();

        foreach($values as $item){
            if(is_string($item) || is_numeric($item)){
                $definition = array('field'=>trim((string)$item));
            }elseif(is_array($item)){
                $definition = array('field'=>trim((string)($item['field'] ?? '')));
                foreach(array('title', 'aggregation', 'ext') as $option){
                    if(array_key_exists($option, $item)
                        && $item[$option] !== null && $item[$option] !== ''){
                        $definition[$option] = (string)$item[$option];
                    }
                }
                if(array_key_exists('visible', $item)){
                    $definition['visible'] = (bool)$item['visible'];
                }
                if(array_key_exists('width', $item)
                    && $item['width'] !== null && $item['width'] !== ''){
                    $definition['width'] = (string)$item['width'];
                }
            }else{
                throw new QueryValidationException('QuerySource fields must be strings or objects');
            }

            if($definition['field'] === ''){
                throw new QueryValidationException('QuerySource field is not defined');
            }
            $this->validateOptions($definition);
            $fieldCodes[] = $definition['field'];
            $result[] = $definition;
        }

        // Reuse the records API parser as the authoritative field/path validator.
        (new RecordFieldSelector())->parse($fieldCodes);
        return $result;
    }

    /** Validate optional QuerySource column instructions. */
    private function validateOptions(array $field): void
    {
        if(isset($field['aggregation']) && !in_array(
            $field['aggregation'], array('count', 'sum', 'avg', 'min', 'max'), true
        )){
            throw new QueryValidationException('Invalid QuerySource field aggregation');
        }
        if(isset($field['ext']) && !in_array(
            $field['ext'], array(
                'term', 'code', 'conceptid', 'id',
                'url', 'thumb',
                'wkt', 'geojson', 'pair',
                'iso', 'human', 'raw'
            ), true
        )){
            throw new QueryValidationException('Invalid QuerySource field extension');
        }
        if(isset($field['width']) && !preg_match('/^\d+(?:\.\d+)?(?:px|%|em|rem)?$/', $field['width'])){
            throw new QueryValidationException('Invalid QuerySource field width');
        }
    }

    /** Parse a stored query exactly as MapPresentationService parses query sources. */
    private function parseQuery($value)
    {
        if(is_array($value)){ return $value; }
        $text = trim((string)$value);
        $json = json_decode($text, true);
        if(is_array($json)){ return $json; }
        return ltrim($text, '?');
    }
}
