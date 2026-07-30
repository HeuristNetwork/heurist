<?php
/**
* DbMapLayer.php - Map layer record adapter
*
* Provides read-only access to RT_MAP_LAYER records and resolves the linked data
* source record used to construct the public MapLayer API response.
*
* @project     Heurist academic knowledge management system
* @package     Entity
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson@heuristnetwork.org>
* @since       7.0
*/
namespace hserv\entity;

require_once dirname(__FILE__).'/DbRecordTypeEntity.php';
require_once dirname(__FILE__).'/../records/search/recordSearch.php';

/**
 * Read-only adapter for Map Layer records and their linked data sources.
 *
 * Loads visible RT_MAP_LAYER records and provides ordered detail values needed
 * by the engine-neutral MapLayer presentation service.
 */
class DbMapLayer extends DbRecordTypeEntity
{
    /**
     * Configure the record type and detail type constants required by this entity.
     *
     * @return void
     */
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_MAP_LAYER';
        $this->requiredConstants = array(
            'RT_MAP_LAYER', 'DT_NAME', 'DT_DATA_SOURCE', 'DT_IS_VISIBLE',
            'DT_SYMBOLOGY', 'DT_MAP_THEMATIC', 'DT_SMARTY_TEMPLATE',
            'DT_MINIMUM_ZOOM_LEVEL', 'DT_MAXIMUM_ZOOM_LEVEL',
            'DT_TIMELINE_FIELDS', 'DT_QUERY_STRING', 'DT_SERVICE_URL',
            'DT_FILE_RESOURCE', 'DT_MAP_IMAGE_LAYER_SCHEMA',
            'DT_MAP_IMAGE_WORLDFILE', 'DT_CRS'
        );
        $this->requiredTermConstants = array();
    }

    /**
     * Load a visible Map Layer record.
     *
     * @param int $recordId Map Layer record ID.
     * @return array|null Raw Heurist record data, or null when unavailable or of
     *                    another record type.
     */
    public function getPublicRecord(int $recordId): ?array
    {
        $this->defineRequiredConstants();
        $record = recordSearchByID($this->system, $recordId, false,
            'rec_ID,rec_RecTypeID,rec_Title');
        if(!is_array($record) || intval(@$record['rec_RecTypeID']) !== $this->recordTypeId()){
            return null;
        }
        
        $record['details'] = $this->loadRecordDetails($recordId);
        
        return $record;
    }

    /**
     * Resolve the data-source record associated with a Map Layer.
     *
     * Layers without DT_DATA_SOURCE are returned as their own source, which
     * supports temporary or query-based layers that store source fields directly.
     *
     * @param array $layer Raw Map Layer record data.
     * @return array|null Visible source record, the layer itself, or null when the
     *                    referenced record cannot be loaded.
     */
    public function getDataSource(array $layer): ?array
    {
        $sourceId = intval($this->value($layer, 'DT_DATA_SOURCE'));
        if($sourceId < 1){
            // Temporary/current-search layers may keep the query on the layer itself.
            return $layer;
        }
        $source = recordSearchByID($this->system, $sourceId, false,
            'rec_ID,rec_RecTypeID,rec_Title');
            
        if(!is_array($source)){
            return null;
        }
        
        $source['details'] = $this->loadRecordDetails($sourceId);    
        
        return $source;
    }

    /**
     * Return the first value of a Map Layer or source-record detail field.
     *
     * @param array $record Raw Heurist record data.
     * @param int|string $field Detail type ID or DT_* constant name.
     * @return mixed First field value, or null when the field is absent.
     */
    public function value(array $record, $field)
    {
        return $this->getFirstDetailValue($record['details'] ?? array(), $field);
    }

    /**
     * Return all values of a Map Layer or source-record detail field.
     *
     * @param array $record Raw Heurist record data.
     * @param int|string $field Detail type ID or DT_* constant name.
     * @return array Ordered field values, or an empty array.
     */
    public function values(array $record, $field): array
    {
        return $this->getDetailValues($record['details'] ?? array(), $field);
    }
}
