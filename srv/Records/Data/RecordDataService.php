<?php
/**
* RecordDataService.php - Batched retrieval and formatting of record details
*
* Retrieves headers and native or linked detail values for record IDs already
* selected by the search and expansion services. It does not execute searches
* or traverse record links.
*
* @project     Heurist academic knowledge management system
* @package     Records\Data
* @link        https://HeuristNetwork.org
* @copyright   (C) 2005-2023 University of Sydney, (C) 2024 onwards Heurist Network
* @license     https://www.gnu.org/licenses/gpl-3.0.txt GNU License 3.0
* @author      Artem Osmakov   <osmakov@gmail.com>
* @author      Ian Johnson     <ian.johnson.heurist@gmail.com>
* @since       7.0
*/

namespace Heurist\Records\Data;

use Heurist\Database\DatabaseInterface;
use Heurist\Runtime\RuntimeContext;
use Heurist\Records\Query\QueryExecutor;

/** Loads requested values without knowing how their owner records were found. */
final class RecordDataService
{
    private const BATCH_SIZE = 500;

    /** @var QueryExecutor */
    private $executor;
    private RuntimeContext $runtime;

    /** Initialise batched record retrieval with the shared database adapter. */
    public function __construct(
        DatabaseInterface $database,
        RuntimeContext $runtime,
        ?QueryExecutor $executor = null
    )
    {
        $this->runtime = $runtime;
        $this->executor = $executor ?? new QueryExecutor($database);
    }

    /** Build standard record objects for the ordered top-query page. */
    public function loadRecords(
        array $topIds,
        array $headers,
        array $nativeFields,
        array $options = array()
    ): array
    {
        $topIds = $this->ids($topIds);
        $records = array();
        foreach($topIds as $id){
            $records[$id] = array('rec_ID'=>(string)$id, 'details'=>array());
        }
        if(empty($records)){ return array(); }

        $columns = array_values(array_unique(array_merge(
            array('rec_ID','rec_RecTypeID','rec_Title'), $headers
        )));
        foreach(array_chunk($topIds, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT '.implode(',', $columns).' FROM Records WHERE rec_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')';
            foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                $id = intval($row[0]);
                if(!isset($records[$id])){ continue; }
                foreach($columns as $index=>$column){
                    if($column === 'rec_ID'){ continue; }
                    $records[$id][$column] = in_array($column, array(
                        'rec_RecTypeID','rec_OwnerUGrpID','rec_AddedByUGrpID'
                    ), true) && $row[$index] !== null
                        ? (string)$row[$index]
                        : $row[$index];
                }
            }
        }

        $this->attachVirtualHeaders($records, $topIds, $options['virtuals'] ?? array());

        $fieldIds = array_values(array_unique(array_map(static function($field){
            return intval($field['fieldId']);
        }, $nativeFields)));
        if(!empty($fieldIds)){
            $values = $this->loadFieldValues($topIds, $fieldIds, $options);
            foreach($nativeFields as $field){
                foreach($topIds as $id){
                    foreach($values[$id][$field['fieldId']] ?? array() as $value){
                        $records[$id]['details'][$field['key']][] = $value;
                    }
                }
            }
        }
        return array_values($records);
    }

    /** Resolve presentation-only fields in one query per page, never per record. */
    private function attachVirtualHeaders(array &$records, array $topIds, array $virtuals): void
    {
        if(empty($virtuals)){ return; }
        $wanted = array_fill_keys($virtuals, true);
        $index = array();
        foreach($records as $id=>$unused){ $index[intval($id)] = $id; }
        foreach(array_chunk($topIds, self::BATCH_SIZE) as $chunk){
            $select = array('r.rec_ID');
            if(isset($wanted['rec_OwnerName'])){ $select[] = 'g.ugr_Name'; }
            if(isset($wanted['rec_Bookmarked'])){
                $select[] = '(EXISTS(SELECT 1 FROM usrBookmarks b WHERE b.bkm_RecID=r.rec_ID LIMIT 1) '
                    .'OR EXISTS(SELECT 1 FROM usrRecTagLinks t WHERE t.rtl_RecID=r.rec_ID LIMIT 1))';
            }
            if(isset($wanted['rec_ThumbnailURL'])){
                $select[] = '(SELECT f.ulf_ObfuscatedFileID FROM recDetails d '
                    .'JOIN recUploadedFiles f ON f.ulf_ID=d.dtl_UploadedFileID '
                    .'LEFT JOIN defFileExtToMimetype m ON m.fxm_Extension=f.ulf_MimeExt '
                    .'WHERE d.dtl_RecID=r.rec_ID AND (m.fxm_MimeType LIKE "image%" '
                    .'OR m.fxm_MimeType IN ("video/youtube","video/vimeo","audio/soundcloud") '
                    .'OR f.ulf_PreferredSource LIKE "iiif%") LIMIT 1)';
            }
            $sql = 'SELECT '.implode(',', $select).' FROM Records r '
                .'LEFT JOIN sysUGrps g ON g.ugr_ID=r.rec_OwnerUGrpID WHERE r.rec_ID IN ('
                .implode(',', array_fill(0, count($chunk), '?')).')';
            foreach($this->executor->executeRows($sql, str_repeat('i', count($chunk)), $chunk) as $row){
                $position = 1; $id = intval($row[0]);
                if(!isset($index[$id])){ continue; }
                $key = $index[$id];
                if(isset($wanted['rec_OwnerName'])){ $records[$key]['rec_OwnerName'] = $row[$position++]; }
                if(isset($wanted['rec_Bookmarked'])){ $records[$key]['rec_Bookmarked'] = (bool)$row[$position++]; }
                if(isset($wanted['rec_ThumbnailURL'])){
                    $fileId = preg_replace('/[^a-z0-9]/i', '', (string)$row[$position++]);
                    $records[$key]['rec_ThumbnailURL'] = $fileId === '' ? null
                        : HEURIST_BASE_URL.'?db='.rawurlencode($this->runtime->databaseName).'&thumb='.$fileId;
                }
            }
        }
    }

    /** Attach values reached through a concrete expansion occurrence. */
    public function attachLinkedValues(
        array &$records,
        array $field,
        array $occurrences,
        string $pathId,
        array $options = array()
    ): void
    {
        $owners = array();
        foreach($occurrences as $occurrence){
            $chain = $occurrence['recordIds'] ?? array();
            if(!empty($chain)){ $owners[intval(end($chain))] = intval(end($chain)); }
        }
        $values = $this->loadFieldValues(
            array_values($owners), array(intval($field['fieldId'])), $options
        );
        $recordIndex = array();
        foreach($records as $index=>$record){ $recordIndex[intval($record['rec_ID'])] = $index; }

        foreach($occurrences as $occurrence){
            $topId = intval($occurrence['top'] ?? 0);
            $chain = array_values(array_map('intval', $occurrence['recordIds'] ?? array()));
            if(!isset($recordIndex[$topId]) || empty($chain)){ continue; }
            $ownerId = intval(end($chain));
            foreach($values[$ownerId][$field['fieldId']] ?? array() as $value){
                $entry = array('value'=>$value, 'path'=>array(
                    'id'=>$pathId,
                    'recordIDs'=>array_map('strval', $chain)
                ));
                if(is_array($value)){
                    $entry = $value;
                    if(!array_key_exists('value', $entry)){
                        $entry = array('value'=>$value);
                    }
                    $entry['path'] = array(
                        'id'=>$pathId,
                        'recordIDs'=>array_map('strval', $chain)
                    );
                }
                $records[$recordIndex[$topId]]['details'][$field['key']][] = $entry;
            }
        }
    }

    /** Return OpenAPI field definitions once per requested detail/path. */
    public function loadFieldMetadata(array $fields): array
    {
        if(empty($fields)){ return array(); }
        $ids = array_values(array_unique(array_map(static function($field){
            return intval($field['fieldId']);
        }, $fields)));
        $sql = 'SELECT dty_ID,dty_Name,dty_Type,dty_OriginatingDBID FROM defDetailTypes WHERE dty_ID IN ('
            .implode(',', array_fill(0, count($ids), '?')).')';
        $definitions = array();
        foreach($this->executor->executeRows($sql, str_repeat('i', count($ids)), $ids) as $row){
            $definitions[intval($row[0])] = array(
                'dty_ID'=>(string)$row[0],
                'dty_Name'=>$row[1],
                'dty_Type'=>$row[2],
                'dty_ConceptCode'=>(string)$row[3].'-'.(string)$row[0]
            );
        }
        $result = array();
        foreach($fields as $field){
            $definition = $definitions[intval($field['fieldId'])] ?? array(
                'dty_ID'=>(string)$field['fieldId'],
                'dty_Name'=>null,
                'dty_Type'=>null,
                'dty_ConceptCode'=>null
            );
            if($field['pathCode'] !== null){ $definition['dty_PathCode'] = $field['pathCode']; }
            $result[] = $definition;
        }
        return $result;
    }

    /** Load and enrich requested details, indexed by owner and field ID. */
    public function loadFieldValues(array $ownerIds, array $fieldIds, array $options = array()): array
    {
        $ownerIds = $this->ids($ownerIds);
        $fieldIds = $this->ids($fieldIds);
        if(empty($ownerIds) || empty($fieldIds)){ return array(); }
        $values = array();
        $extentCondition = $this->extentCondition($options['extent'] ?? null);
        foreach(array_chunk($ownerIds, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT d.dtl_ID,d.dtl_RecID,d.dtl_DetailTypeID,d.dtl_Value,'
                .'ST_AsText(d.dtl_Geo),d.dtl_UploadedFileID,t.dty_Type,'
                .'rr.rec_ID,rr.rec_RecTypeID,rr.rec_Title,rr.rec_Hash,'
                .'trm.trm_Label,trm.trm_Code,trm.trm_OriginatingDBID,'
                .'f.ulf_ID,CONCAT(f.ulf_FilePath,f.ulf_FileName),f.ulf_ExternalFileReference,'
                .'fxm.fxm_MimeType,f.ulf_PreferredSource,f.ulf_OrigFileName,f.ulf_FileSizeKB,'
                .'f.ulf_ObfuscatedFileID,f.ulf_Description,f.ulf_Added,f.ulf_MimeExt,'
                .'f.ulf_Caption,f.ulf_Copyright,f.ulf_Copyowner,f.ulf_Parameters,f.ulf_WhoCanView '
                .'FROM recDetails d JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID '
                .'LEFT JOIN Records rr ON t.dty_Type="resource" AND rr.rec_ID=d.dtl_Value '
                .'LEFT JOIN defTerms trm ON t.dty_Type IN ("enum","relationtype") AND trm.trm_ID=d.dtl_Value '
                .'LEFT JOIN recUploadedFiles f ON t.dty_Type="file" AND f.ulf_ID=d.dtl_UploadedFileID '
                .'LEFT JOIN defFileExtToMimetype fxm ON fxm.fxm_Extension=f.ulf_MimeExt '
                .'JOIN Records ro ON ro.rec_ID=d.dtl_RecID '
                .'LEFT JOIN defRecStructure rst ON rst.rst_RecTypeID=ro.rec_RecTypeID '
                .'AND rst.rst_DetailTypeID=d.dtl_DetailTypeID '
                .'WHERE d.dtl_RecID IN ('.implode(',', array_fill(0, count($chunk), '?')).') '
                .'AND d.dtl_DetailTypeID IN ('.implode(',', array_fill(0, count($fieldIds), '?')).') '
                .$extentCondition['sql']
                .$this->fieldVisibilitySql()
                .'ORDER BY d.dtl_RecID,d.dtl_DetailTypeID,d.dtl_ID';
            $parameters = array_merge($chunk, $fieldIds, $extentCondition['values']);
            foreach($this->executor->executeRows(
                $sql,
                str_repeat('i', count($chunk)+count($fieldIds)).$extentCondition['types'],
                $parameters
            ) as $row){
                $value = $this->formatValue(
                    $row,
                    filter_var($options['resolveDetails'] ?? false, FILTER_VALIDATE_BOOLEAN)
                );
                if($value !== null && $value !== ''){
                    $values[intval($row[1])][intval($row[2])][] = $value;
                }
            }
        }
        return $values;
    }

    /** Build an exact MBR filter for one normal or antimeridian-crossing extent. */
    private function extentCondition($extent): array
    {
        if(!is_array($extent) || count($extent)!==4){
            return array('sql'=>'', 'types'=>'', 'values'=>array());
        }
        list($west,$south,$east,$north) = array_values($extent);
        if(!($west<=$east)){
            $left = $this->extentPolygon($west, $south, 180.0, $north);
            $right = $this->extentPolygon(-180.0, $south, $east, $north);
            return array(
                'sql'=>' AND (MBRIntersects(d.dtl_Geo,ST_GeomFromText(?))'
                    .' OR MBRIntersects(d.dtl_Geo,ST_GeomFromText(?))) ',
                'types'=>'ss',
                'values'=>array($left,$right)
            );
        }
        return array(
            'sql'=>' AND MBRIntersects(d.dtl_Geo,ST_GeomFromText(?)) ',
            'types'=>'s',
            'values'=>array($this->extentPolygon($west,$south,$east,$north))
        );
    }

    private function extentPolygon(float $west, float $south, float $east, float $north): string
    {
        return 'POLYGON(('.$west.' '.$south.','.$east.' '.$south.','.$east.' '.$north.','
            .$west.' '.$north.','.$west.' '.$south.'))';
    }

    /** Discover visible detail fields of a given type used by supplied records. */
    public function findFieldIdsByType(array $ownerIds, string $fieldType): array
    {
        $ownerIds = $this->ids($ownerIds);
        $fieldType = strtolower(trim($fieldType));
        if(empty($ownerIds) || !preg_match('/^[a-z]+$/', $fieldType)){ return array(); }
        $found = array();
        foreach(array_chunk($ownerIds, self::BATCH_SIZE) as $chunk){
            $sql = 'SELECT DISTINCT d.dtl_DetailTypeID FROM recDetails d '
                .'JOIN defDetailTypes t ON t.dty_ID=d.dtl_DetailTypeID '
                .'JOIN Records ro ON ro.rec_ID=d.dtl_RecID '
                .'LEFT JOIN defRecStructure rst ON rst.rst_RecTypeID=ro.rec_RecTypeID '
                .'AND rst.rst_DetailTypeID=d.dtl_DetailTypeID '
                .'WHERE d.dtl_RecID IN ('.implode(',', array_fill(0, count($chunk), '?')).') '
                .'AND t.dty_Type=? '.$this->fieldVisibilitySql();
            $parameters = array_merge($chunk, array($fieldType));
            foreach($this->executor->executeRows(
                $sql, str_repeat('i', count($chunk)).'s', $parameters
            ) as $row){
                $id = intval($row[0]);
                if($id>0){ $found[$id] = $id; }
            }
        }
        return array_values($found);
    }

    private function formatValue(array $row, bool $resolveDetails = false)
    {
        $raw = $row[3];
        $type = (string)$row[6];
        if($type === 'resource'){
            if(!$resolveDetails){ return (string)$raw; }
            return array(
                'value'=>(string)$raw,
                'rec_ID'=>$row[7] === null ? null : (string)$row[7],
                'rec_RecTypeID'=>$row[8] === null ? null : (string)$row[8],
                'rec_Title'=>$row[9],
                'rec_Hash'=>$row[10]
            );
        }
        if($type === 'enum' || $type === 'relationtype'){
            if(!$resolveDetails){ return (string)$raw; }
            return array(
                'value'=>(string)$raw,
                'trm_ID'=>(string)$raw,
                'trm_Label'=>$row[11],
                'trm_Code'=>$row[12],
                'trm_ConceptCode'=>$row[13] === null ? null : (string)$row[13]
            );
        }
        if($type === 'file'){
            $fileId = $row[14] === null ? $row[5] : $row[14];
            if(!$resolveDetails){ return $fileId === null ? null : (string)$fileId; }
            return array(
                'value'=>$fileId === null ? $raw : (string)$fileId,
                'file'=>array_filter(array(
                    'ulf_ID'=>$row[14] === null ? null : (string)$row[14],
                    'fullPath'=>$row[15],
                    'ulf_ExternalFileReference'=>$row[16],
                    'fxm_MimeType'=>$row[17],
                    'ulf_PreferredSource'=>$row[18],
                    'ulf_OrigFileName'=>$row[19],
                    'ulf_FileSizeKB'=>$row[20],
                    'ulf_ObfuscatedFileID'=>$row[21],
                    'ulf_Description'=>$row[22],
                    'ulf_Added'=>$row[23],
                    'ulf_MimeExt'=>$row[24],
                    'ulf_Caption'=>$row[25],
                    'ulf_Copyright'=>$row[26],
                    'ulf_Copyowner'=>$row[27],
                    'ulf_Parameters'=>$row[28],
                    'ulf_WhoCanView'=>$row[29]
                ), static function($item){ return $item !== null; })
            );
        }
        if($type === 'geo'){
            if($raw === null || $row[4] === null){ return null; }
            return array('geo'=>array('type'=>$raw, 'wkt'=>$row[4]));
        }
        if($type === 'separator' || $type === 'relmarker'){ return null; }
        return $raw;
    }

    private function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static function($id){
            return $id>0;
        })));
    }

    /** Match the established per-field non-owner visibility rules. */
    private function fieldVisibilitySql(): string
    {
        if($this->runtime->isDbOwner){
            return ' AND IFNULL(rst.rst_RequirementType,"")<>"forbidden" ';
        }
        $hasAccess = $this->runtime->hasAccess;
        $visible = array(
            'rst.rst_NonOwnerVisibility IS NULL',
            '((rst.rst_NonOwnerVisibility="public" OR rst.rst_NonOwnerVisibility="pending")'
                .' AND IFNULL(d.dtl_HideFromPublic,0)<>1)'
        );
        if($hasAccess){ $visible[] = 'rst.rst_NonOwnerVisibility="viewable"'; }

        $owner = '';
        if($hasAccess){
            $groups = $this->ids($this->runtime->groupIds);
            $userId = $this->runtime->userId;
            if($userId>0){ $groups[] = $userId; }
            $groups = array_values(array_unique($groups));
            if(!empty($groups)){
                $owner = 'ro.rec_OwnerUGrpID IN ('.implode(',', $groups).') OR ';
            }
        }
        return ' AND IFNULL(rst.rst_RequirementType,"")<>"forbidden" AND ('
            .$owner.implode(' OR ', $visible).') ';
    }
}
