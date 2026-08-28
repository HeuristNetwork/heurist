<?php
/**
* DbDataset.php - Dataset record adapter
*
* Provides read-only access to RT_DATASET records and resolves an optional
* RT_QUERY_SOURCE record used to supply the dataset query.
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

/** Read-only adapter for Dataset records and their query source. */
class DbDataset extends DbRecordTypeEntity
{
    /** Configure the record and detail constants required by datasets. */
    protected function initRecordTypeEntity(): void
    {
        $this->recordTypeConst = 'RT_DATASET';
        $this->requiredConstants = array(
            'RT_DATASET', 'RT_QUERY_SOURCE',
            'DT_NAME', 'DT_SHORT_SUMMARY', 'DT_DATA_SOURCE',
            'DT_QUERY_STRING', 'DT_DATA_FIELDS'
        );
        $this->requiredTermConstants = array();
    }

    /**
     * Load one visible Dataset record with its presentation fields.
     *
     * @param int $recordId Dataset record ID.
     * @return array|null Raw Dataset record, or null when unavailable or of a
     *                    different record type.
     */
    public function getPublicRecord(int $recordId): ?array
    {
        $this->defineRequiredConstants();
        $record = recordSearchByID($this->system, $recordId, false,
            'rec_ID,rec_RecTypeID,rec_Title');
        if(!is_array($record) || intval($record['rec_RecTypeID'] ?? 0) !== $this->recordTypeId()){
            return null;
        }

        $record['details'] = $this->loadRecordDetails($recordId, array(
            'DT_NAME', 'DT_SHORT_SUMMARY', 'DT_DATA_SOURCE',
            'DT_QUERY_STRING', 'DT_DATA_FIELDS'
        ));
        return $record;
    }

    /**
     * Resolve the optional RT_QUERY_SOURCE referenced by a Dataset.
     *
     * A Dataset without DT_DATA_SOURCE is returned as its own query source.
     * Referenced records must be visible RT_QUERY_SOURCE records. Only their
     * DT_QUERY_STRING detail is loaded.
     *
     * @param array $dataset Raw Dataset record.
     * @return array|null Query Source record, the Dataset itself, or null.
     */
    public function getQuerySource(array $dataset): ?array
    {
        $sourceId = intval($this->value($dataset, 'DT_DATA_SOURCE'));
        if($sourceId < 1){
            return $dataset;
        }

        $source = recordSearchByID($this->system, $sourceId, false,
            'rec_ID,rec_RecTypeID,rec_Title');
        if(!is_array($source)
            || intval($source['rec_RecTypeID'] ?? 0) !== intval(RT_QUERY_SOURCE)){
            return null;
        }

        $source['details'] = $this->loadRecordDetails($sourceId, array('DT_QUERY_STRING'));
        return $source;
    }

    /** Return the first value of a Dataset or Query Source detail. */
    public function value(array $record, $field)
    {
        return $this->getFirstDetailValue($record['details'] ?? array(), $field);
    }
}
