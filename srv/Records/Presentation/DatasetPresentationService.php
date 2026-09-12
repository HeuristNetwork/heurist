<?php
/**
* DatasetPresentationService.php - Public Dataset response builder
*
* Converts RT_QUERY_SOURCE into the
* stable, engine-neutral definition consumed by heurist-data.
*
* @project     Heurist academic knowledge management system
* @package     Records\Presentation
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/
namespace Heurist\Records\Presentation;

use Heurist\Records\Data\RecordFieldSelector;
use Heurist\Records\Query\QueryValidationException;

/** Builds the public representation of one Dataset record. */
class DatasetPresentationService
{
    private PresentationRecordRepository $datasets;

    /** Initialise the service for the current database. */
    public function __construct(PresentationRecordRepository $records)
    {
        $this->datasets = $records;
    }

    /**
     * Build the public Dataset definition.
     *
     * @param int $recordId RT_QUERY_SOURCE record ID.
     * @return array|null Public Dataset response, or null when unavailable.
     */
    public function getDataset(int $recordId): ?array
    {
        $dataset = $this->datasets->getPublicRecord($recordId, 'RT_QUERY_SOURCE');
        if(!$dataset){ return null; }

        $source = $this->datasets->getQuerySource($dataset);
        if(!$source){ return null; }

        $queryValue = $this->datasets->value($source, 'DT_QUERY_STRING');
        if($queryValue === null || trim((string)$queryValue) === ''){
            throw new QueryValidationException('Dataset query is not defined');
        }

        $fields = $this->parseFields(
            $this->datasets->value($dataset, 'DT_TABLE_FIELDS')
        );
        $geofields = $this->parseFields(
            $this->datasets->value($dataset, 'DT_GEO_FIELDS')
        );
        $timefields = $this->parseFields(
            $this->datasets->value($dataset, 'DT_TIMELINE_FIELDS')
        );

        return array(
            'format'=>'heurist-dataset',
            'version'=>1,
            'id'=>intval($dataset['rec_ID']),
            'title'=>(string)($dataset['rec_Title'] ?? ''),
            'description'=>(string)($this->datasets->value($dataset, 'DT_SHORT_SUMMARY') ?? ''),
            'source'=>array(
                'type'=>'heurist-query',
                'recordId'=>intval($source['rec_ID'] ?? 0),
                'title'=>(string)($source['rec_Title'] ?? ''),
                'query'=>$this->parseQuery($queryValue)
            ),
            'fields'=>$fields,
            'geofields'=>$geofields,
            'timefields'=>$timefields,
            'map'=>array(
                'geoFields'=>$geofields,
                'dynamicRequests'=>$this->termBoolean(
                    $this->datasets->value($dataset, 'DT_IS_LOADED_BY_EXTENT'), false
                ),
                'minZoom'=>$this->numberOrNull(
                    $this->datasets->value($dataset, 'DT_MINIMUM_ZOOM_LEVEL')
                ),
                'maxZoom'=>$this->numberOrNull(
                    $this->datasets->value($dataset, 'DT_MAXIMUM_ZOOM_LEVEL')
                )
            ),
            'rules'=>$this->parseRules($this->datasets->value($dataset, 'DT_EXPANSION_RULES'))
        );
    }

    private function termBoolean($value, bool $default): bool
    {
        if($value === null || $value === ''){ return $default; }
        if($value === false || $value === 0 || $value === '0'){ return false; }
        if($value === true || $value === 1 || $value === '1'){ return true; }
        $code = strtolower((string)$this->datasets->getTermCode(intval($value)));
        $label = strtolower((string)$this->datasets->getTermLabel(intval($value)));
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
            throw new QueryValidationException('Dataset expansion rules must be a JSON array');
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
                throw new QueryValidationException('Dataset fields must be strings or objects');
            }

            if($definition['field'] === ''){
                throw new QueryValidationException('Dataset field is not defined');
            }
            $this->validateOptions($definition);
            $fieldCodes[] = $definition['field'];
            $result[] = $definition;
        }

        // Reuse the records API parser as the authoritative field/path validator.
        (new RecordFieldSelector())->parse($fieldCodes);
        return $result;
    }

    /** Validate optional Dataset column instructions. */
    private function validateOptions(array $field): void
    {
        if(isset($field['aggregation']) && !in_array(
            $field['aggregation'], array('count', 'sum', 'avg', 'min', 'max'), true
        )){
            throw new QueryValidationException('Invalid Dataset field aggregation');
        }
        if(isset($field['ext']) && !in_array(
            $field['ext'], array(
                'term', 'code', 'conceptid', 'id',
                'url', 'thumb',
                'wkt', 'geojson', 'pair',
                'iso', 'human', 'raw'
            ), true
        )){
            throw new QueryValidationException('Invalid Dataset field extension');
        }
        if(isset($field['width']) && !preg_match('/^\d+(?:\.\d+)?(?:px|%|em|rem)?$/', $field['width'])){
            throw new QueryValidationException('Invalid Dataset field width');
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
