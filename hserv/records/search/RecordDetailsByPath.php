<?php
/**
* RecordDetailsByPath.php - Retrieve direct and linked record details by Heurist field path.
*
* Provides the implementation used by the public Records API endpoint
* POST /api/{database}/records/details. Field paths use the existing Heurist
* short-code notation, for example 10:237 or 10:lt240:48:237.
*
* This class is intentionally independent of the legacy record_search.php
* a=links_details workflow, which remains unchanged for existing clients.
*
* @project     Heurist academic knowledge management system
* @package     Records\Search
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson
* @since       7.0
*/

namespace hserv\records\search;

require_once dirname(__FILE__).'/recordSearch.php';

/**
 * Retrieves selected details for source records, following optional link paths.
 *
 * The public result is keyed by the original field path so that two paths ending
 * at the same detail type remain distinguishable. Detail values retain the
 * normal Heurist structure: dty_ID -> dtl_ID -> value internally, exposed as
 * fieldPath -> dtl_ID -> value by {@see fetch()}.
 */
class RecordDetailsByPath
{
    /** @var \hserv\System */
    private $system;

    /** @var \mysqli */
    private $mysqli;

    /** Maximum number of source records accepted by one API request. */
    private const MAX_RECORD_IDS = 5000;

    /** Maximum number of field paths accepted by one API request. */
    private const MAX_FIELD_PATHS = 100;

    /** Number of record headers loaded in one recordSearch() request. */
    private const RECORD_CHUNK_SIZE = 1000;

    /** Minimal record headers required by recordSearchDetails() visibility checks. */
    private const DETAIL_HEADER_FIELDS = 'rec_ID,rec_RecTypeID,rec_OwnerUGrpID';

    /**
     * @param \hserv\System $system Initialised Heurist system.
     */
    public function __construct($system)
    {
        $this->system = $system;
        $this->mysqli = $system->getMysqli();
    }

    /**
     * Retrieve details for visible source records.
     *
     * @param array|string $recordIds Source record IDs.
     * @param array|string $fieldCodes Heurist field-path codes.
     * @return array Array with `records` and `fields` metadata.
     * @throws \InvalidArgumentException For invalid IDs or field paths.
     * @throws \RuntimeException For search/database failures.
     */
    public function fetch($recordIds, $fieldCodes): array
    {
        $ids = $this->normalizeRecordIds($recordIds);
        $codes = $this->normalizeFieldCodes($fieldCodes);
        $plan = $this->buildRequestPlan($codes);

        $sourceRecords = $this->loadAccessibleRecords($ids);
        $outputById = array();

        foreach($ids as $id){
            if(!isset($sourceRecords[$id])){
                continue; // inaccessible or missing records are omitted
            }
            $record = $sourceRecords[$id];
            $outputById[$id] = array(
                'rec_ID' => (string)$id,
                'rec_RecTypeID' => isset($record['rec_RecTypeID']) ? (string)$record['rec_RecTypeID'] : null,
                'details' => array()
            );
        }

        if(empty($outputById)){
            return array('records' => array(), 'fields' => $plan['fieldDefinitions']);
        }

        foreach($plan['groups'] as $group){
            if($group['direct']){
                $this->fetchDirectGroup($sourceRecords, $outputById, $group);
            }else{
                $this->fetchLinkedGroup($sourceRecords, $outputById, $group);
            }
        }

        $records = array();
        foreach($ids as $id){
            if(isset($outputById[$id])){
                $records[] = $outputById[$id];
            }
        }

        return array(
            'records' => $records,
            'fields' => $plan['fieldDefinitions']
        );
    }

    /**
     * Parse and group field paths so fields sharing the same link path are fetched together.
     *
     * @param array $fieldCodes
     * @return array
     */
    private function buildRequestPlan(array $fieldCodes): array
    {
        $parsed = array();
        $allDetailIds = array();
        $allLinkFieldIds = array();
        $allRecordTypeIds = array();

        foreach($fieldCodes as $code){
            $field = $this->parseFieldCode($code);
            $parsed[] = $field;
            $allDetailIds[] = $field['detailId'];
            foreach($field['linkFieldIds'] as $id){
                $allLinkFieldIds[] = $id;
            }
            foreach($field['recordTypeIds'] as $id){
                $allRecordTypeIds[] = $id;
            }
        }

        $detailDefinitions = $this->validateDefinitions(
            array_values(array_unique($allDetailIds)),
            array_values(array_unique($allLinkFieldIds)),
            array_values(array_unique($allRecordTypeIds))
        );

        $groups = array();
        $fieldDefinitions = array();

        foreach($parsed as $field){
            $groupKey = $field['direct'] ? '$IDS' : $field['path'];
            if(!isset($groups[$groupKey])){
                $groups[$groupKey] = array(
                    'direct' => $field['direct'],
                    'path' => $field['path'],
                    'query' => $field['query'],
                    'detailIds' => array(),
                    'fields' => array()
                );
            }
            if(!in_array($field['detailId'], $groups[$groupKey]['detailIds'], true)){
                $groups[$groupKey]['detailIds'][] = $field['detailId'];
            }
            $groups[$groupKey]['fields'][] = $field;

            $definition = $detailDefinitions[$field['detailId']];
            $fieldDefinitions[] = array(
                'code' => $field['code'],
                'dty_ID' => (string)$field['detailId'],
                'dty_Name' => $definition['dty_Name'],
                'dty_Type' => $definition['dty_Type']
            );
        }

        return array(
            'groups' => array_values($groups),
            'fieldDefinitions' => $fieldDefinitions
        );
    }

    /**
     * Parse a Heurist field path using the same semantics as client createFacetQuery().
     *
     * Supported examples:
     *   237
     *   10:237
     *   10:lt240:48:237
     *   10:lt240:48:lf300:55:237
     *
     * @param string $code
     * @return array
     */
    private function parseFieldCode(string $code): array
    {
        $code = trim($code);
        if($code === ''){
            throw new \InvalidArgumentException('Field path cannot be empty');
        }

        $parts = explode(':', $code);
        if(count($parts) === 1){
            if(!ctype_digit($parts[0]) || intval($parts[0]) < 1){
                throw new \InvalidArgumentException('Invalid field path: '.$code);
            }
            return array(
                'code' => $code,
                'path' => '',
                'detailId' => intval($parts[0]),
                'direct' => true,
                'query' => '$IDS',
                'linkFieldIds' => array(),
                'recordTypeIds' => array()
            );
        }

        if(count($parts) < 2 || (count($parts) % 2) !== 0){
            throw new \InvalidArgumentException('Invalid field path: '.$code);
        }

        $last = $parts[count($parts)-1];
        if(!ctype_digit($last) || intval($last) < 1){
            throw new \InvalidArgumentException('Field path must end with a numeric detail type ID: '.$code);
        }

        $recordTypeIds = array();
        $linkFieldIds = array();

        for($i=0; $i<count($parts)-1; $i+=2){
            if(!ctype_digit($parts[$i]) || intval($parts[$i]) < 1){
                throw new \InvalidArgumentException('Invalid record type in field path: '.$code);
            }
            $recordTypeIds[] = intval($parts[$i]);

            if($i+1 < count($parts)-1){
                if(!preg_match('/^(lt|lf|rt|rf)([1-9][0-9]*)$/', $parts[$i+1], $matches)){
                    throw new \InvalidArgumentException('Invalid link segment in field path: '.$code);
                }
                $linkFieldIds[] = intval($matches[2]);
            }
        }

        $direct = count($parts) === 2;
        $query = $direct ? '$IDS' : $this->buildLinkedQuery($parts);

        return array(
            'code' => $code,
            'path' => implode(':', array_slice($parts, 0, -1)),
            'detailId' => intval($last),
            'direct' => $direct,
            'query' => $query,
            'linkFieldIds' => $linkFieldIds,
            'recordTypeIds' => $recordTypeIds
        );
    }

    /**
     * Build the nested Heurist search query represented by a linked field path.
     * Direction mapping mirrors createFacetQuery(..., true, false).
     *
     * @param array $parts Parsed colon-separated path parts including final detail ID.
     * @return array
     */
    private function buildLinkedQuery(array $parts): array
    {
        $query = '$IDS';

        for($i=1; $i<count($parts)-1; $i+=2){
            $linkToken = $parts[$i];
            $targetRecordType = intval($parts[$i+1]);
            preg_match('/^(lt|lf|rt|rf)([1-9][0-9]*)$/', $linkToken, $matches);

            $prefix = $matches[1];
            $fieldId = $matches[2];
            switch($prefix){
                case 'lf':
                    $predicate = 'linked_to:'.$fieldId;
                    break;
                case 'lt':
                    $predicate = 'linkedfrom:'.$fieldId;
                    break;
                case 'rf':
                case 'rt':
                    $predicate = 'related:'.$fieldId;
                    break;
                default:
                    throw new \InvalidArgumentException('Unsupported link direction: '.$linkToken);
            }

            $query = array(
                array('t' => $targetRecordType),
                array($predicate => $query)
            );
        }

        return $query;
    }

    /**
     * Validate field and record-type IDs and return metadata for terminal detail fields.
     *
     * @return array Map keyed by terminal dty_ID.
     */
    private function validateDefinitions(array $detailIds, array $linkFieldIds, array $recordTypeIds): array
    {
        $allFieldIds = array_values(array_unique(array_merge($detailIds, $linkFieldIds)));
        $fieldDefinitions = array();

        if(!empty($allFieldIds)){
            $res = $this->mysqli->query(
                'SELECT dty_ID,dty_Name,dty_Type FROM defDetailTypes WHERE dty_ID IN ('.implode(',', $allFieldIds).')'
            );
            if(!$res){
                throw new \RuntimeException('Unable to validate detail fields: '.$this->mysqli->error);
            }
            $found = array();
            while($row = $res->fetch_assoc()){
                $id = intval($row['dty_ID']);
                $found[] = $id;
                $fieldDefinitions[$id] = $row;
            }
            $res->close();

            $missing = array_values(array_diff($allFieldIds, $found));
            if(!empty($missing)){
                throw new \InvalidArgumentException('Unknown detail field ID: '.implode(',', $missing));
            }
        }

        if(!empty($recordTypeIds)){
            $res = $this->mysqli->query(
                'SELECT rty_ID FROM defRecTypes WHERE rty_ID IN ('.implode(',', $recordTypeIds).')'
            );
            if(!$res){
                throw new \RuntimeException('Unable to validate record types: '.$this->mysqli->error);
            }
            $found = array();
            while($row = $res->fetch_row()){
                $found[] = intval($row[0]);
            }
            $res->close();

            $missing = array_values(array_diff($recordTypeIds, $found));
            if(!empty($missing)){
                throw new \InvalidArgumentException('Unknown record type ID in field path: '.implode(',', $missing));
            }
        }

        $terminal = array();
        foreach($detailIds as $id){
            $terminal[$id] = $fieldDefinitions[$id];
        }
        return $terminal;
    }

    /**
     * Load only source records visible to the current API user and return associative headers.
     *
     * @param array $recordIds
     * @return array Map keyed by rec_ID.
     */
    private function loadAccessibleRecords(array $recordIds): array
    {
        if(empty($recordIds)){
            return array();
        }

        $records = array();
        foreach(array_chunk($recordIds, self::RECORD_CHUNK_SIZE) as $chunk){
            $params = array(
                'detail' => self::DETAIL_HEADER_FIELDS,
                'q' => array('ids' => implode(',', $chunk)),
                'w' => 'all',
                'limit' => count($chunk)
            );
            $response = \recordSearch($this->system, $params);
            $records += $this->recordsFromSearchResponse($response);
        }

        return $records;
    }

    /**
     * Fetch direct details for all visible source records.
     */
    private function fetchDirectGroup(array $sourceRecords, array &$outputById, array $group): void
    {
        foreach($outputById as $recId => &$output){
            $record = $sourceRecords[$recId];
            \recordSearchDetails($this->system, $record, $group['detailIds']);
            $details = is_array(@$record['details']) ? $record['details'] : array();

            foreach($group['fields'] as $field){
                $dtyId = $field['detailId'];
                if(isset($details[$dtyId]) && is_array($details[$dtyId])){
                    $output['details'][$field['code']] = $details[$dtyId];
                }
            }
        }
        unset($output);
    }

    /**
     * Fetch details from linked records for every visible source record.
     */
    private function fetchLinkedGroup(array $sourceRecords, array &$outputById, array $group): void
    {
        foreach($outputById as $sourceId => &$output){
            $query = $group['query'];
            $this->replaceIdsPlaceholder($query, intval($sourceId));

            // First resolve all visible linked record IDs. For ids-only searches,
            // needall=1 explicitly removes the normal search limit in recordSearch().
            $searchParams = array(
                'detail' => 'ids',
                'q' => $query,
                'w' => 'all',
                'needall' => 1
            );
            $linkedResponse = \recordSearch($this->system, $searchParams);
            $linkedIds = $this->idsFromSearchResponse($linkedResponse);
            if(empty($linkedIds)){
                continue;
            }

            // Load only the headers required for field-level visibility checks,
            // in bounded chunks rather than one potentially large IN() query.
            $linkedRecords = $this->loadAccessibleRecords($linkedIds);
            if(empty($linkedRecords)){
                continue;
            }

            $accumulated = array();
            foreach($linkedRecords as $linkedRecord){
                \recordSearchDetails($this->system, $linkedRecord, $group['detailIds']);
                $details = is_array(@$linkedRecord['details']) ? $linkedRecord['details'] : array();
                foreach($details as $dtyId => $values){
                    if(!isset($accumulated[$dtyId])){
                        $accumulated[$dtyId] = array();
                    }
                    if(is_array($values)){
                        foreach($values as $dtlId => $value){
                            $accumulated[$dtyId][$dtlId] = $value;
                        }
                    }
                }
            }

            foreach($group['fields'] as $field){
                $dtyId = $field['detailId'];
                if(isset($accumulated[$dtyId]) && !empty($accumulated[$dtyId])){
                    $output['details'][$field['code']] = $accumulated[$dtyId];
                }
            }
        }
        unset($output);
    }

    /**
     * Extract record IDs from an ids-only recordSearch response.
     *
     * @param array $response
     * @return array
     */
    private function idsFromSearchResponse($response): array
    {
        if(!is_array($response) || @$response['status'] !== HEURIST_OK){
            $message = 'Unable to retrieve linked record IDs';
            $error = $this->system->getError();
            if(is_array($error) && !empty($error['message'])){
                $message = $error['message'];
            }
            throw new \RuntimeException($message);
        }

        $rows = is_array(@$response['data']['records']) ? $response['data']['records'] : array();
        $ids = array();
        foreach($rows as $id){
            $id = intval($id);
            if($id > 0){
                $ids[] = $id;
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * Convert normal recordSearch header output into records keyed by rec_ID.
     * recordSearch itself enforces record-level visibility.
     */
    private function recordsFromSearchResponse($response): array
    {
        if(!is_array($response) || @$response['status'] !== HEURIST_OK){
            $message = 'Unable to retrieve records';
            $error = $this->system->getError();
            if(is_array($error) && !empty($error['message'])){
                $message = $error['message'];
            }
            throw new \RuntimeException($message);
        }

        $data = is_array(@$response['data']) ? $response['data'] : array();
        $fields = is_array(@$data['fields']) ? $data['fields'] : array();
        $rows = is_array(@$data['records']) ? $data['records'] : array();
        if(empty($fields) || empty($rows)){
            return array();
        }

        $records = array();
        foreach($rows as $row){
            if(!is_array($row)){
                continue;
            }
            $record = array();
            foreach($fields as $idx => $name){
                if(array_key_exists($idx, $row)){
                    $record[$name] = $row[$idx];
                }
            }
            $id = intval(@$record['rec_ID']);
            if($id > 0){
                $records[$id] = $record;
            }
        }
        return $records;
    }

    /** Replace every literal $IDS placeholder in a nested query with one source record ID. */
    private function replaceIdsPlaceholder(&$value, int $recordId): void
    {
        if(is_array($value)){
            foreach($value as &$item){
                $this->replaceIdsPlaceholder($item, $recordId);
            }
            unset($item);
        }elseif(is_string($value) && $value === '$IDS'){
            $value = $recordId;
        }
    }

    /** @return array */
    private function normalizeRecordIds($recordIds): array
    {
        if(is_string($recordIds)){
            $recordIds = explode(',', $recordIds);
        }
        if(!is_array($recordIds) || empty($recordIds)){
            throw new \InvalidArgumentException('ids must be a non-empty array of record IDs');
        }

        $result = array();
        foreach($recordIds as $id){
            if(!(is_int($id) || (is_string($id) && ctype_digit($id))) || intval($id) < 1){
                throw new \InvalidArgumentException('Invalid record ID in ids');
            }
            $id = intval($id);
            if(!in_array($id, $result, true)){
                $result[] = $id;
            }
        }
        if(count($result) > self::MAX_RECORD_IDS){
            throw new \InvalidArgumentException('Too many record IDs; maximum is '.self::MAX_RECORD_IDS);
        }
        return $result;
    }

    /** @return array */
    private function normalizeFieldCodes($fieldCodes): array
    {
        if(is_string($fieldCodes)){
            $fieldCodes = explode(',', $fieldCodes);
        }
        if(!is_array($fieldCodes) || empty($fieldCodes)){
            throw new \InvalidArgumentException('fields must be a non-empty array of field paths');
        }

        $result = array();
        foreach($fieldCodes as $code){
            if(!is_string($code) && !is_int($code)){
                throw new \InvalidArgumentException('Invalid field path in fields');
            }
            $code = trim((string)$code);
            if($code === ''){
                throw new \InvalidArgumentException('Field path cannot be empty');
            }
            if(!in_array($code, $result, true)){
                $result[] = $code;
            }
        }
        if(count($result) > self::MAX_FIELD_PATHS){
            throw new \InvalidArgumentException('Too many field paths; maximum is '.self::MAX_FIELD_PATHS);
        }
        return $result;
    }
}
