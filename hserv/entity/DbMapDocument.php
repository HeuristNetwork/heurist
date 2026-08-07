<?php
/**
* DbMapDocument.php - Map document record adapter
*
* Provides read-only access to RT_MAP_DOCUMENT records and their ordered map
* layer references for conversion into the public MapDocument API format.
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
 * Read-only adapter for Map Document records.
 *
 * Resolves the required record/detail type constants, loads visible
 * RT_MAP_DOCUMENT records, and exposes convenience accessors for their values.
 */
class DbMapDocument extends DbRecordTypeEntity
{
    /**
     * Configure the record type and detail type constants required by this entity.
     *
     * @return void
     */
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_MAP_DOCUMENT';
        $this->requiredConstants = array(
            'RT_MAP_DOCUMENT', 'RT_MAP_LAYER', 'DT_NAME', 'DT_MAP_LAYER',
            'DT_MAP_BOOKMARK', 'DT_GEO_OBJECT', 'DT_SYMBOLOGY',
            'DT_MINIMUM_ZOOM', 'DT_MAXIMUM_ZOOM', 'DT_ZOOM_KM_POINT',
            'DT_MINIMUM_ZOOM_LEVEL', 'DT_MAXIMUM_ZOOM_LEVEL',
            'DT_WORLD_BASEMAP', 'DT_CRS'
        );
        $this->requiredTermConstants = array();
    }

    /**
     * Load a visible Map Document record.
     *
     * The record is returned only when it exists, is visible to the current
     * API user, and belongs to RT_MAP_DOCUMENT.
     *
     * @param int $recordId Map Document record ID.
     * @return array|null Raw Heurist record data, or null when unavailable.
     */
    public function getPublicRecord(int $recordId): ?array
    {
        $this->defineRequiredConstants();
        if($recordId < 1 || $this->recordTypeId() < 1){
            return null;
        }

        // recordSearchByID applies normal record visibility rules.
        $record = recordSearchByID($this->system, $recordId, false,
            'rec_ID,rec_RecTypeID,rec_Title');
        if(!is_array($record) || intval(@$record['rec_RecTypeID']) !== $this->recordTypeId()){
            return null;
        }
        
        $record['details'] = $this->loadRecordDetails($recordId);
        
        return $record;
    }

    /**
     * Return the first value of a Map Document detail field.
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
     * Return all values of a Map Document detail field in stored order.
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
