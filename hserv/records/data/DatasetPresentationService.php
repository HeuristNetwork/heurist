<?php
/**
* DatasetPresentationService.php - Public Dataset response builder
*
* Converts RT_DATASET records and their optional RT_QUERY_SOURCE into the
* stable, engine-neutral definition consumed by heurist-data.
*
* @project     Heurist academic knowledge management system
* @package     Records\Data
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/
namespace hserv\records\data;

use hserv\entity\DbDataset;
use hserv\records\search\QueryValidationException;

/** Builds the public representation of one Dataset record. */
class DatasetPresentationService
{
    private $datasets;

    /** Initialise the service for the current database. */
    public function __construct($system)
    {
        $this->datasets = new DbDataset($system);
    }

    /**
     * Build the public Dataset definition.
     *
     * @param int $recordId RT_DATASET record ID.
     * @return array|null Public Dataset response, or null when unavailable.
     */
    public function getDataset(int $recordId): ?array
    {
        $dataset = $this->datasets->getPublicRecord($recordId);
        if(!$dataset){ return null; }

        $source = $this->datasets->getQuerySource($dataset);
        if(!$source){ return null; }

        $queryValue = $this->datasets->value($source, 'DT_QUERY_STRING');
        if($queryValue === null || trim((string)$queryValue) === ''){
            throw new QueryValidationException('Dataset query is not defined');
        }

        $fields = $this->parseFields(
            $this->datasets->value($dataset, 'DT_DATA_FIELDS')
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
            'fields'=>$fields
        );
    }

    /** Parse JSON or comma-separated DT_DATA_FIELDS into ordered definitions. */
    private function parseFields($value): array
    {
        if($value === null || trim((string)$value) === ''){ return array(); }

        $text = trim((string)$value);
        $decoded = json_decode($text, true);
        $values = is_array($decoded) ? $decoded : explode(',', $text);
        // Accept both the DT_DATA_FIELDS array and the public {fields:[...]} wrapper.
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
